<?php
/**
 * This file is part of the Techworker\Uuid package.
 *
 * (c) Benjamin Ansbach <benjaminansbach@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Techworker\Config\Integration;

use Silex\Application;
use Silex\ServiceProviderInterface;

use Techworker\Config\Loader;

/**
 * The Silex service provider.
 *
 * @package    Techworker\config
 * @author     Benjamin Ansbach <benjaminansbach@googlemail.com>
 * @copyright  2015 Benjamin Ansbach <benjaminansbach@googlemail.com>
 * @license    MIT
 * @link       http://www.techworker.de/
 */
class Silex implements ServiceProviderInterface
{
    /**
     * The path of the config file.
     *
     * @var string
     */
    protected $filename;

    /**
     * The list of replacement values where the key is the value to search for and the value is
     * the value to replace the key with.
     *
     * @var array
     */
    protected $replacements = [];

    /**
     * The parser which can read the configuration file. If its a string, the provider
     * will try to lookup the closure in the \Silex\Application instance.
     *
     * @var string|callable
     */
    protected $parser = null;

    /**
     * The prefix in which the config will be put inside of the application instance.
     *
     * @var string|null
     */
    protected $prefix;

    /**
     * Creates a new instance of the service provider to read configuration files.
     *
     * @param string $filename The name of the file.
     * @param string|callable parser Either a method which can read a config by the given filename
     * or the key which can be used to lookup the closure in the \Silex\Application instance. The callable
     * gets the name of the file to parse as the parameter and should return an array.
     * @param array $replacements The list of replacement values.
     * @param string $prefix The prefix.
     *
     * Examples:
     *
     * <?php
     *
     * use \Techworker\Silex\ConfigServiceProvider;
     * use \Symfony\Component\Yaml\Yaml;
     *
     * $yamlParser = function($file) {
     *     // see symfony/yaml component
     *     return Yaml::parse($file) ?: [];
     * };
     *
     *
     * // debug should be set to true if you want to use the explain feature
     * // $app['debug'] = true;
     * $app->register(new ConfigServiceProvider("config_dev.yml", $yamlParser, ['KEY' => 'VALUE'], 'config'));
     *
     * ConfigServiceProvider::explain('my_config_value', $app['config']);
     */
    public function __construct($filename, $parser, array $replacements = [], $prefix = null)
    {
        $this->filename = $filename;
        $this->parser = $parser;
        $this->prefix = $prefix;
        $this->replacements = $replacements;
    }

    /**
     * @inheritdoc ServiceProviderInterface::boot()
     */
    public function boot(Application $app) { }

    /**
     * Gets the file parser.
     *
     * @return callable
     */
    protected function getParser(\Silex\Application $app)
    {
        // check if we have to resolve the parser
        if (is_string($this->parser))
        {
            // no found.
            if(!isset($app[$this->parser]))
            {
                throw new \RuntimeException(
                    "Unable to resolve parser from application instance with key" . $this->parser
                );
            }

            $this->parser = $app[$this->parser];
        }

        // not callable? urgh
        if(!is_callable($this->parser)) {
            throw new \RuntimeException("The current parser should be a valid callable.");
        }

        return $this->parser;
    }

    /**
     * Starts to load the configuration.
     *
     * @inheritdoc ServiceProviderInterface::register
     * @param Application $app The application instance.
     * @return Silex
     */
    public function register(Application $app)
    {
        $loader = new Loader($this->filename, $this->getParser($app), $this->replacements, $app['debug']);
        $config = $loader->load();

        // fighting with "Notice: Indirect modification of overloaded element" - wtf, this should be
        // much cooler
        if(!is_null($this->prefix))
        {
            if(!isset($app[$this->prefix])) {
                $app[$this->prefix] = [];
            }
            $app[$this->prefix] = $this->merge($app[$this->prefix], $config);
        } else {
            $this->merge($app, $config);
        }
    }

    /**
     * Merges the config into the given array or object.
     *
     * @param \Silex\Application|array $app The app or a value of the app.
     * @param array $config The config to merge
     * @return \Silex\Application|array
     */
    protected function merge($destination, $config)
    {
        foreach($config as $key => $value)
        {
            if(isset($destination[$key]) && is_array($destination[$key])) {
                $destination[$key] = array_replace_recursive($destination[$key], $config[$key]);
            } else {
                $destination[$key] = $value;
            }
        }

        return $destination;
    }



}