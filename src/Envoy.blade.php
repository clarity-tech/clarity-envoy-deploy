@servers(['web' => getenv('TARGET_HOST')])
<?php
$projectDir = getenv('DEPLOY_PROJECT_DIR');
$fullProjectDir = $fullProjectDir ?? '/home/deployer/' . $projectDir;
$release_dir = $fullProjectDir . '/releases';
$current_release = $fullProjectDir . '/current';
$persist_dir = $fullProjectDir . '/persist';

$last_release_file       = $fullProjectDir . '/LAST_RELEASE';
$current_release_file    = $fullProjectDir . '/CURRENT_RELEASE';

$webhookUrl = getenv('SLACK_WEBHOOK_DEPLOY');

$ciProjectDir = getenv('CI_BUILDS_DIR');
$commitSha = getenv('CI_COMMIT_SHORT_SHA');
if (getenv('CI_COMMIT_TAG')) {
    $commitSha = getenv('CI_COMMIT_TAG');
}
$ciProjectName = getenv('CI_PROJECT_NAME');
$ciEnvSlug = getenv('CI_ENVIRONMENT_SLUG');
$ciProjectUrl = getenv('CI_PROJECT_URL');
$ciJobUrl = getenv('CI_JOB_URL');

$local_ci_env_file = "$ciProjectDir/.env";

?>

@setup
$sha = $sha;
$archive = $sha;
$horizon = $horizon?? false;

$migrate = $migrate?? false;
$migrateBack = $migrate_back?? false;

function logMessage($message) {
    return "echo '\033[32m" .$message. "\033[0m';\n";
}

@endsetup

@success

@slack($webhookUrl, '#deploys', ':white_check_mark: Successfully Ran Task `' . $__task . '` on '. "*$ciProjectName* $ciProjectUrl" .' environment `'. $ciEnvSlug . '` commit `'. $commitSha .'` target dir `'. $fullProjectDir . '` *View Job* '. $ciJobUrl)

@endsuccess

@error

@slack($webhookUrl, '#deploys', ':exclamation: Error running Task `' . $__task . '` on '. "*$ciProjectName* $ciProjectUrl" . ' environment `'. $ciEnvSlug . '` commit `'. $commitSha .'` target dir `'. $fullProjectDir . '` *View Job* '. $ciJobUrl)

@enderror


@story('deploy')
init_dirs
extract_zip
upload_env
update_symlinks
write_last_release
write_current_release
@if ($migrate)
@php
$migrateFrom = $release_dir.'/'. $sha;
@endphp
migrate_forward
@endif
post_deploy_tasks
restart-queues
@endstory

@story('deploy_env')
init_dirs
upload_env
post_deploy_tasks
restart-queues
@endstory


@story('rollback')
init_dirs
@if ($migrateBack)
@php
$migrateFrom = $current_release;
@endphp
migrate_backward
@endif
rollback_release
post_deploy_tasks
restart-queues
@endstory

@task('init_dirs')

{{ logMessage("init directory set up") }}

[[ -f {{ $last_release_file }} ]] || touch {{ $last_release_file }}
[[ -f {{ $current_release_file }} ]] || touch {{ $current_release_file }}

CURRENT_RELEASE=$(cat {{ $current_release_file }})
echo 'PRESENT/CURRENT RELEASE='$CURRENT_RELEASE

[ -d {{ $release_dir }} ] || mkdir {{ $release_dir }}
[ -d {{ $persist_dir.'/storage/framework' }} ] || mkdir {{ $persist_dir.'/storage/framework' }}
mkdir -p {{ $persist_dir.'/storage/app/public' }}
mkdir -p {{ $persist_dir.'/storage/framework/cache/data' }}
mkdir -p {{ $persist_dir.'/storage/framework/sessions' }}
mkdir -p {{ $persist_dir.'/storage/framework/testing' }}
mkdir -p {{ $persist_dir.'/storage/framework/views' }}
mkdir -p {{ $persist_dir.'/storage/logs' }}

@endtask

@task('write_last_release')
{{ logMessage("write_last_release is executing") }}
CURRENT_RELEASE=$(cat {{ $current_release_file }})
echo 'PRESENT/CURRENT RELEASE='$CURRENT_RELEASE
echo -n "$CURRENT_RELEASE" > {{$last_release_file}}
@endtask

@task('write_current_release')
{{ logMessage("write_current_release is executing") }}
echo -n {{ $sha }} > {{$current_release_file}}
@endtask

@task('upload_env')
{{ logMessage("env set up for " . $sha) }}
cd {{ $release_dir }}
mv -f {{ $sha }}.env {{$persist_dir}}/.env
@endtask

@task('extract_zip')
{{ logMessage("extract_zip is executing") }}
@php
$deploymentRDir = $release_dir.'/'. $archive;
@endphp
[ -d {{ $deploymentRDir }} ] || mkdir {{ $deploymentRDir }}

mv -f {{$release_dir .'/'.$archive}}.zip {{ $deploymentRDir }}/{{$archive}}.zip
cd {{ $deploymentRDir }}
unzip -oq {{$archive}}.zip
rm -r {{$archive}}.zip

CURRENT_RELEASE=$(cat {{ $current_release_file }})

if [[ $CURRENT_RELEASE == {{ $sha }} ]]
then
echo "$CURRENT_RELEASE is the SAME {{ $sha }}"
else
echo "$CURRENT_RELEASE is the NOT SAME {{ $sha }} So deleting storage dir from the deployment zip"
rm -rf storage
fi

{{ logMessage("extract_zip is FINISHED") }}
@endtask

@task('post_deploy_tasks')
{{ logMessage("post_deploy_tasks is executing") }}

cd {{ $current_release }}
{{ logMessage("inside current release $current_release") }}
ls -lha
ls -lhad public/*

php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

php artisan optimize
php artisan event:cache
php artisan view:cache

php artisan config:cache

# Reload PHP-FPM gracefully
sudo service php8.1-fpm reload

{{ logMessage("DEPLOYED CURRENT RELEASE=") }}
cat {{ $current_release_file }}
{{ logMessage("POSSIBLE ROLLBACK RELEASE=") }}
cat {{ $last_release_file }}

@endtask

@task('restart-queues')
{{ logMessage("restart-queues is executing") }}

cd {{ $current_release }}

php artisan queue:restart
@if($horizon)
{{ logMessage("horizon is set to true, So termination is STARTED") }}
php artisan horizon:terminate
php artisan horizon:purge
@else
{{ logMessage("horizon is set to false") }}
@endif
php artisan queue:restart
@endtask

{{ logMessage("restart-queues is finished") }}

@task('update_symlinks')
{{ logMessage("update_symlinks is STARTED") }}

if [[ $CURRENT_RELEASE == {{ $sha }} ]]
then
{{ logMessage("NO SWAPING OF RELEASE POSSIBLE AS THE DEPLOYMENT ID IS ALREADY IN CURRENT RELEASE $sha") }}
return 1
else
{{ logMessage("ROLLBACK POSSIBLE BY SWAPING RELEASE AS THE DEPLOYMENT ID IS NOT SAME IN CURRENT RELEASE $sha") }}
fi

@php
$deploymentRDir = $release_dir.'/'. $archive;
@endphp

{{ logMessage("Symlink .env and existing storage") }}
ln -nfs {{ $persist_dir }}/.env {{ $deploymentRDir }}/.env

ln -nfs {{ $persist_dir }}/storage {{ $deploymentRDir }}/storage
ln -nfs {{ $persist_dir }}/storage/app/public {{ $deploymentRDir }}/public/storage

{{ logMessage("Put site live") }}
ln -nfs {{ $deploymentRDir }} {{$current_release}}

{{ logMessage("update_symlinks is FINISHED") }}
@endtask

@task('rollback_release')

LAST_RELEASE=$(cat {{ $last_release_file }})
CURRENT_RELEASE=$(cat {{ $current_release_file }})

echo "CURRENT RELEASE=$CURRENT_RELEASE"
echo "ROLLBACK TO RELEASE=$LAST_RELEASE"

echo "Rolling back to RELEASE=$LAST_RELEASE"
ln -nfs {{ $release_dir }}/$LAST_RELEASE {{$current_release}}

echo -n $LAST_RELEASE > {{$current_release_file}}
echo -n "$CURRENT_RELEASE" > {{$last_release_file}}

{{ logMessage("ROLLBACK SUCCESSFULL TO RELEASE") }}
cat {{ $current_release_file }}

@endtask


@task('migrate_forward')

{{ logMessage("DB MIGRATION IS RUNNING") }}

@php
$onReleaseDir = $migrateFrom?? $current_release;
@endphp

cd {{ $onReleaseDir }}

php artisan migrate --force

@endtask


@task('migrate_backward')

{{ logMessage("DB MIGRATION ROLLBACK OF LAST MIGRATION BATCH IS RUNNING") }}

@php
$onReleaseDir = $migrateFrom?? $current_release;
@endphp

cd {{ $onReleaseDir }}
php artisan migrate:rollback --force

@endtask
