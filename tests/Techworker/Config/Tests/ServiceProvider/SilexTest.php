<?php

namespace Techworker\Config\Tests\ServiceProvider;

use Silex\Application;
use Symfony\Component\Yaml\Yaml;
use Techworker\Config\Loader;
use Techworker\Config\Integration\Silex as ConfigServiceProvider;
use Techworker\Config\Tests\BaseTestCase;

class SilexTest extends BaseTestCase
{
    /**
     * @var \Silex\Application
     */
    protected $app;

    /**
     * @var callable
     */
    protected $parser;

    public function setUp()
    {
        $this->app = new \Silex\Application();
        $this->parser = function($file) {
            return Yaml::parse($file) ?: [];
        };
        $this->app['parser'] = $this->app->protect($this->parser);
    }

    public function testResolveParserFromString()
    {
        $this->app->register(new ConfigServiceProvider($this->getFixture('foobar.yml'), 'parser'));
        $this->assertTrue(true);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testResolveNonCallableFromString()
    {
        $this->app['parser'] = 'nothing';
        $this->app->register(new ConfigServiceProvider($this->getFixture('foobar.yml'), 'parser'));
        $this->assertTrue(true);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testErrorResolveParserFromString()
    {
        $this->app->register(new ConfigServiceProvider($this->getFixture('foobar.yml'), 'parser-not-existant'));
        $this->assertTrue(true);
    }

    public function testMergeIntoApp()
    {
        $this->app->register(new ConfigServiceProvider($this->getFixture('foobar.yml'), 'parser'));
        $this->assertEquals("bar", $this->app['foobar']['foo']);
        $this->assertEquals("foo", $this->app['foobar']['bar']);
    }

    public function testMergeIntoAppAndOverwrite()
    {
        $this->app['foobar'] = ['foo' => 'barbar'];
        $this->app->register(new ConfigServiceProvider($this->getFixture('foobar.yml'), 'parser'));
        $this->assertEquals("bar", $this->app['foobar']['foo']);

        //$this->app['ABC']['foo'] = 'barbar2';
        $this->app->register(new ConfigServiceProvider($this->getFixture('foobar.yml'), 'parser'));
        $this->assertEquals("bar", $this->app['foobar']['foo']);

        //$this->assertEquals("foo", $this->app['bar']);
    }

    public function testDebugIsDelegated()
    {
        $this->app['debug'] = true;
        $this->app->register(new ConfigServiceProvider($this->getFixture('foobar.yml'), 'parser'));

        // we cannot access the loader instance  but check if the debug keys are existant
        $this->assertArrayHasKey(Loader::ACCESS_DEBUG_RAW, $this->app);
        $this->assertArrayHasKey(Loader::ACCESS_DEBUG_FILENAME, $this->app);
    }

    public function testReplacementsDelegated()
    {
        $this->app->register(new ConfigServiceProvider($this->getFixture('foobar.yml'), 'parser', ['GLOBAL_VAR' => 'foobar']));
        $this->assertEquals('foobar', $this->app['foobar']['variable']);
    }

    public function testMergeIntoPrefixAndOverwrite()
    {
        $this->app['ABC'] = ['foobar'=> ['foo' => 'barbar']];
        $this->app->register(new ConfigServiceProvider($this->getFixture('foobar.yml'), 'parser', [], 'ABC'));
        $this->assertEquals("bar", $this->app['ABC']['foobar']['foo']);
        //$this->assertEquals("foo", $this->app['bar']);
    }

    public function testMergeIntoPrefix()
    {
        $this->app->register(new ConfigServiceProvider($this->getFixture('foobar.yml'), 'parser', [], 'ABC'));
        $this->assertEquals("bar", $this->app['ABC']['foobar']['foo']);
        $this->assertEquals("foo", $this->app['ABC']['foobar']['bar']);
    }

}
