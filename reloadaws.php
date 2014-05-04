#!/usr/bin/env php
<?php
require 'vendor/autoload.php';

use Reload\Aws\DeployToAWSCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new DeployToAWSCommand());
$application->run();
