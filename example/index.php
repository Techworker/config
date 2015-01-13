<?php

date_default_timezone_set('Europe/Berlin');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../vendor/autoload.php';

use \Techworker\Config\Loader as Loader;

$yamlParser = function($file) {
    return \Symfony\Component\Yaml\Yaml::parse($file) ?: [];
};

$loader = new Loader(__DIR__ . '/config/development.yml', $yamlParser, ['CONFIG_DIR' => __DIR__ . '/config']);

echo "<pre>" . print_r($loader->load(), true) . "</pre>";
