<?php

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\ErrorHandler;

// needed to avoid encoding issues when running tests on different platforms
setlocale(LC_ALL, 'en_US.UTF-8');

// needed to avoid failed tests when other timezones than UTC are configured for PHP
date_default_timezone_set('UTC');

require dirname(__DIR__).'/vendor/autoload.php';

ErrorHandler::register(null, false);

(new Dotenv())->bootEnv(dirname(__DIR__).'/tests/App/AssetMapper/.env');
//$kernel = new Kernel('test', 'AssetMapper/config');
//(new Symfony\Component\Filesystem\Filesystem())->remove($kernel->getCacheDir());
//
//$application = new Application($kernel);
//$application->setAutoExit(false);
//$application->setCatchExceptions(false);
//
//$runCommand = function (string $name, array $options = []) use ($application) {
//    $input = new ArrayInput(array_merge(['command' => $name], $options));
//    $input->setInteractive(false);
//    $application->run($input);
//};
//
//
//$kernel->shutdown();
