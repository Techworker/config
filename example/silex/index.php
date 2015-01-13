<?php

date_default_timezone_set('Europe/Berlin');
error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once '../../vendor/autoload.php';

use \Techworker\Config\Integration\Silex as ConfigServiceProvider;

$app = new \Silex\Application();

$app['parser.yaml'] = $app->protect(function($file) {
    return \Symfony\Component\Yaml\Yaml::parse($file) ?: [];
});

$app->register(new ConfigServiceProvider('../config/development.yml', 'parser.yaml', ['CONFIG_DIR' => __DIR__ . '/../config'], 'cfg'));


$app->get("/", function(Silex\Application $app) {
    return "<pre>" . print_r($app['cfg'], true) . "</pre>";
});

$app->run();