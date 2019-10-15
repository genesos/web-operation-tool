<?php
require_once __DIR__ . './vendor/autoload.php';

use Genesos\Operation\Command\WebTool;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new WebTool());
$application->run();
