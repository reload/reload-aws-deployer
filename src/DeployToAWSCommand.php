<?php
namespace Reload\Aws;

use Aws\Ec2\Ec2Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class DeployToAWSCommand extends Command {

  /**
   * Configure.
   */
  protected function configure() {
    $this
      ->setName('deploy')
      ->setDescription('Deploy')
      ->addArgument(
        'repository',
        InputArgument::REQUIRED,
        'Repository'
      )
      ->addArgument(
        'revision',
        InputArgument::REQUIRED,
        'Revision'
      );
  }

  /**
   * Execute.
   *
   * @param InputInterface $input
   * @param OutputInterface $output
   * @return int|null|void
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $style = new OutputFormatterStyle('green', 'black', array('bold', 'blink'));
    $output->getFormatter()->setStyle('greenblink', $style);

    // Read config.
    $config = Yaml::parse('config.yml');
    $repo = $input->getArgument('repository');
    $revision = $input->getArgument('revision');

    // Credentials via ~/.aws/credentials
    $client = Ec2Client::factory(array(
      'region'  => 'eu-west-1',
    ));

    $startup_time = time();
    // See http://docs.aws.amazon.com/aws-sdk-php/latest/class-Aws.Ec2.Ec2Client.html#_runInstances
    $result = $client->runInstances(array(
      'ImageId'          => $config['ami_id'],
      'MinCount'         => 1,
      'MaxCount'         => 1,
      'KeyName'          => $config['keypair_name'],
      'SecurityGroups'   => $config['security_groups'],
      'InstanceType'     => 'm1.small',
    ));

    $instance_ids = array();

    foreach ($result->get('Instances') as $instance) {
      $instance_ids[] = $instance['InstanceId'];
    }

    $output->writeln("Booting machine with id " . $instance_ids[0] . '...');

    // Wait for the instances to come online.
    $done = FALSE;
    while (!$done) {
      sleep(1);

      $result = $client->describeInstances(array('InstanceIds' => $instance_ids));

      $reservations = $result->get('Reservations');

      $done = TRUE;
      // Pick up the status-code for each instance;
      foreach ($reservations as $reservation) {
        foreach ($reservation['Instances'] as $instance) {
          $instance_id = $instance['InstanceId'];

          if (in_array($instance_id, $instance_ids)) {
            if ($instance['State']['Code'] == 0) {
              $done = FALSE;
            }
          }
        }
      }
    }

    $ip = $instance['PublicIpAddress'];
    $hostname = $instance['PublicDnsName'];

    $elapsed = time() - $startup_time;
    $output->writeln("Machine prepared in $elapsed seconds, info:");
    $output->writeln("Hostname: " . $hostname);
    $output->writeln("IP: " . $ip);
    $output->writeln("AvailabilityZone: " . $instance['Placement']['AvailabilityZone']);
    $delay = 60;
    $output->writeln("<greenblink>Waiting $delay seconds for the server to get ready.</greenblink>");
    // TODO:
    sleep($delay);

    $ssh_username = $config['ssh_username'];
    $keypair_path = $config['keypair_path'];
    // SSH to the box and start provisioning.
    $ssh_command = "ssh -q -t -o StrictHostKeyChecking=no -i $keypair_path $ssh_username@$hostname";

    // Clone the site.
    $vhost_dir = "/vagrant_sites/$hostname";
    $this->exec($output, "$ssh_command git clone $repo $vhost_dir");
    $this->exec($output, "$ssh_command git --git-dir=$vhost_dir/.git checkout $revision");


    // Create the destination-directory for the baseline.
    $this->exec($output, "$ssh_command sudo mkdir /mnt/baseline");
    $this->exec($output, "$ssh_command sudo chown " . $ssh_username . " /mnt/baseline");

    // Prepare a couple of baseline specific vars.
    $site_name = $config['baseline_site_name'];
    $baseline_database_file = $site_name . '.sql';
    $baseline_site_path = $config['baseline_site_name'] . '/sites/default';
    $baseline_site_destination_path = $vhost_dir . '/sites/default';

    // Unpack the baseline.
    $this->exec($output, "$ssh_command wget " . $config['baseline_url'] . " -O /mnt/baseline/baseline.tar.gz");
    $this->exec($output, "$ssh_command tar -zxf /mnt/baseline/baseline.tar.gz -C /mnt/baseline");

    // Get the database in place.
    $this->exec($output, "$ssh_command mv /mnt/baseline/" . $baseline_database_file . " /vagrant_databases/drupal.sql");

    // Get the site in place.
    $this->exec($output, "$ssh_command rm -fr $baseline_site_destination_path");
    $this->exec($output, "$ssh_command mv -f /mnt/baseline/$baseline_site_path $baseline_site_destination_path");
    // Put prepared settings.php in place.
    $this->exec($output, "$ssh_command cp /vagrant/reload/settings.php $baseline_site_destination_path/");

    // Provision (this will create the vhost and restart apache).
    $this->exec($output, "$ssh_command 'sudo bash -c \"cd /vagrant && FACTER_vagrant_guest_ip=127.0.0.1 FACTER_parrot_php_version=5.3 puppet apply --modulepath=/vagrant/modules -v manifests/parrot.pp\"'");

    // Final drupal setup stuff.
    $this->exec($output, "$ssh_command sudo chmod -R 777 $baseline_site_destination_path/files");
    $this->exec($output, "$ssh_command drush -r $baseline_site_destination_path cc all");

    $output->writeln('----------');

    $elapsed = time() - $startup_time;
    $output->writeln("<greenblink>Done in $elapsed seconds: http://$hostname</greenblink>");
  }

  function exec($output, $stuff) {
    $output->writeln('<fg=green>Doing</fg=green> <fg=cyan>' . $stuff . '</fg=cyan>');
    `$stuff`;
  }
}
