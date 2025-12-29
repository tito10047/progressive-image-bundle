<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SERVER['APP_RUNTIME_OPTIONS'] = [
    'project_dir' => dirname(__DIR__, 1),
];

require_once dirname(__DIR__).'/../../../vendor/autoload_runtime.php';

return function (array $context) {
    return new Tito10047\ProgressiveImageBundle\Tests\App\Kernel('dev', 'AssetMapper/config');
};
