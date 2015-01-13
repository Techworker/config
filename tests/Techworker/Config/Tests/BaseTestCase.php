<?php

namespace Techworker\Config\Tests;

use Symfony\Component\Yaml\Yaml;

class BaseTestCase extends \PHPUnit_Framework_TestCase
{
    protected function getYamlParser()
    {
        return function($file) {
            return Yaml::parse($file) ?: [];
        };
    }

    protected function getFixture($file)
    {
        return __DIR__ . '/../../../fixtures/' . $file;
    }
}