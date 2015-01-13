<?php

namespace Techworker\Config\Tests\ServiceProvider;

use Silex\Application;
use Techworker\Config\Loader;
use Techworker\Config\Tests\BaseTestCase;

class LoaderTest extends BaseTestCase
{
    public function testHierarchy()
    {
        $config = (new Loader($this->getFixture('hierarchy/development.yml'), $this->getYamlParser(), [
            'CONFIG_DIR' => $this->getFixture('hierarchy/')
        ]))->load();

        // derived from global
        $this->assertEquals('app', $config['database']['dbname']);

        // overwrite production -> development
        $this->assertTrue($config['debug']);

        // only in development
        $this->assertTrue($config['database']['devmode']);

        // overwrite production -> development
        $this->assertEquals('dev', $config['database']['user']);
        $this->assertEquals('dev', $config['database']['pass']);

        // global only
        $this->assertTrue($config['only_in_global']);
    }

    public function testUnset()
    {
        $config = (new Loader($this->getFixture('hierarchy/unset.yml'), $this->getYamlParser(), [
            'CONFIG_DIR' => $this->getFixture('hierarchy/')
        ]))->load();

        // derived from global
        $this->assertArrayNotHasKey('debug', $config);
        $this->assertArrayNotHasKey('cache', $config['database']);
    }

    public function testLocalVariables()
    {
        $config = (new Loader($this->getFixture('local_replacements.yml'), $this->getYamlParser(), []))->load();

        $this->assertEquals('VALUE_1', $config['VAR1']);
        $this->assertEquals('VALUE_2', $config['VAR2']);
    }

    public function testLocalVariablesOverwriteGlobals()
    {
        $config = (new Loader($this->getFixture('local_replacements.yml'), $this->getYamlParser(), ['VAR1' => 'foobar']))->load();

        $this->assertEquals('VALUE_1', $config['VAR1']);
        $this->assertEquals('VALUE_2', $config['VAR2']);
    }

    /**
     * @expectedException \LogicException
     */
    public function testRecursion()
    {
        (new Loader($this->getFixture('recursion.yml'), $this->getYamlParser(), [
            'CONFIG_DIR' => $this->getFixture('')
        ]))->load();
    }

    /**
     * @expectedException \Exception
     */
    public function testParserThrowsExceptionAndNoOneHandlesIt()
    {
        $exceptionParser = function($file) {
            throw new \Exception();
        };

        (new Loader($this->getFixture('foobar.yml'), $exceptionParser))->load();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testParserReturnsNonArray()
    {
        $nonArrayReturnParser = function($file) {
            return "";
        };

        (new Loader($this->getFixture('foobar.yml'), $nonArrayReturnParser))->load();
    }
}
