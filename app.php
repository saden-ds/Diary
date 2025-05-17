<?php

define('__ROOT__', __DIR__);

switch (getenv('APP_ENV')) {
    case 'staging':
        define('ENV', 'staging');
        break;
    case 'production':
        define('ENV', 'production');
        break;
    case 'testing':
        define('ENV', 'testing');
        break;
    case 'development':
    default:
        define('ENV', 'development');
        error_reporting(E_ALL);
}

mb_internal_encoding('UTF-8');

spl_autoload_register(function($class) {
    if (strpos($class, 'App') === 0) {
        $class = substr_replace($class, 'app', 0, 3);
    }

    $path = __ROOT__ . '/' . str_replace('\\', '/', $class) . '.php';

    if (file_exists($path)) {
        include_once($path);
    }
});

$config = \App\Base\Config::init([
    'version'           => '1.0.0',
    'version_timestamp' => '2025051701',
    'default_title'     => 'Life',
    'default_locale'    => 'lv',
    'locales'           => ['lv'],
    'timezone'          => 'Europe/Riga',
    'safety_mask'       => [
        'password', 'password_confirm'
    ]
]);

if ($config->get('timezone')) {
    date_default_timezone_set($config->get('timezone'));
} else {
    date_default_timezone_set('Europe/Riga');
}

ini_set('error_log', $config->get('tmp_dir') . '/php-errors.log');
ini_set('log_errors', 1);
ini_set('display_errors', 0);
ini_set('pcre.backtrack_limit', 10000000);
