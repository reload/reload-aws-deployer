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

*Stuff that needs doing*
 * Figure out a more clean interface to the baseline store, at the very least
   add commands for listing, generating and uploading baselines. Consider how
   this could be done with Drush
 * Commands for listing and controlling the running instances (apart from the
   aws webinterface).
 * Integration with Github pull-requets
 * A dedicated webinterface for controlling the instances.

