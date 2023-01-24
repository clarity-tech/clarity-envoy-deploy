
A standard failsafe zero downtime deployment scripts used in our projects at Clarity


# step 1)
Go here https://gitlab.com/clarity-tech/dev-ops/pipelines

and read the ## Usage section 
<!-- * copy the gitlab yaml files to your project folder
* copy `.gitlab-ci.yml` to your project repo root
* copy folder `.gitlab/ci` to your project repo -->


# step 2)
create `Envoy.blade.php` on root of your project add below inside that file
```
@import('vendor/clarity-tech/clarity-envoy-deploy/src/Envoy.blade.php')
```

# step 3)
update/replace the variables inside these files `.gitlab/ci/.prepare-ssh-prod.yml` and `.gitlab/ci/.prepare-ssh-staging.yml`

```
PROJECT_DIR
your-server-alias
```

# step 4)
update `ENV_PROD` and `ENV_STAGING` as File variable as per your need in gitlab project CI/CD variables https://gitlab.com/clarity-tech/your-project-name/-/settings/ci_cd

and `SSH_CONFIG` as File variable with the config like below replace `ip-of-the-server` with your ip
```
Host clarity-server
   HostName ip-of-the-server
   User deployer
   IdentitiesOnly yes
   IdentityFile ~/.ssh/id_deployment
   StrictHostKeyChecking no
```

lastly `DEPLOYER_SSH_KEY_ID` as File variable with the private key content file of the user that has access to the server

```
-----BEGIN OPENSSH PRIVATE KEY-----
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
QyNTUxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxvPiD
egxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxw
AAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxLp
key-content-stripped-due-to-secuirty-xxxxxxxxxxxxxxx==
-----END OPENSSH PRIVATE KEY-----
```
