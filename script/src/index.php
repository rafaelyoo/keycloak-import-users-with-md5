<?php
require __DIR__.'/../vendor/autoload.php';

use Gg\KeycloakCarga\Command\CargaCommand;
use Symfony\Component\Console\Application;

$application = new Application('echo', '1.0.0');
$command = new CargaCommand();

$application->add($command);

$application->setDefaultCommand($command->getName(), true);
$application->run();