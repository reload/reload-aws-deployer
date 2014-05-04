reload-aws-deployer
===================

Tool for deploying sites via Amazon Web Services (very much work in progress)

Website: http://reload.github.io/reload-aws-deployer/

*Prerequisites*
 * Credentials has been setup in ~/.aws/credentials
 * The user executing the deployment-script must have ssh-keys setup to grant
   access to github.

*Setup*
 * composer install
 * copy default.config.yml to config.yml and edit it

Sample command:
```
./reloadaws.php deploy git@github.com:reload/spejderdk 9c7c51082011d96094132168d7e351ec37267705
```
