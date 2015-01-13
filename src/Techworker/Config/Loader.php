<?php
/**
 * This file is part of the Techworker\config package.
 *
 * (c) Benjamin Ansbach <benjaminansbach@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Techworker\Config;

/**
 * The loader class loads all configurations.
 *
 * @package    Techworker\config
 * @author     Benjamin Ansbach <benjaminansbach@googlemail.com>
 * @copyright  2015 Benjamin Ansbach <benjaminansbach@googlemail.com>
 * @license    MIT
 * @link       http://www.techworker.de/
 */
class Loader
{
    const ACCESS_DEBUG_FILENAME = 'csp.file';
    const ACCESS_DEBUG_IMPORTS = 'csp.imports';
    const ACCESS_DEBUG_RAW = 'csp.raw';

    const EXTENDS_KEYWORD = '@extends';
    const UNSET_KEYWORD = '@unset';

    protected $filename;
    protected $debug = false;
    protected $replacements = [];

    /**
     * A simple array to check for recursion while importing files.
     *
     * @var array
     */
    protected $recursionCheck = [];

    /**
     * The parser which can read the configuration file. If its a string, the provider
     * will try to lookup the closure in the \Silex\Application instance.
     *
     * @var callable
     */
    protected $parser = null;

    public function __construct($filename, callable $parser, array $replacements = [], $debug = false)
    {
        $this->filename = $filename;
        $this->parser = $parser;
        $this->debug = $debug;
        $this->initGlobalReplacements($replacements);
    }

    /**
     * @return array
     */
    public function load()
    {
        return $this->loadFile($this->filename);
    }

    /**
     * Loads a configuration file and returns the config values.
     *
     * @param string $file The name of the file to load.
     * @param int $level The current level, used internally on recursion.
     * @param string|null $importFrom The filename which triggered the import.
     *
     * @return array
     */
    protected function loadFile($file, $level = 0, $importFrom = null)
    {
        $this->checkRecursion($file, $importFrom);

        // read the config by the given config parser
        $config = call_user_func($this->parser, $file);

        if(!is_array($config)) {
            throw new \RuntimeException("The parser for the file " . $file . " did not return an array.");
        }

        // merge replacements
        $replacements = array_replace($this->initLocalReplacements($config), $this->replacements);

        // replace replacements in the config (string values only)
        $config = $this->replace($config, $replacements);

        // save the raw data without any imports or merged values
        !$this->debug ?: $rawRoot = array_slice($config, 0);

        // no files to import? we can get out of here
        if (!isset($config[self::EXTENDS_KEYWORD])) {
            !$this->debug ?: ($config[self::ACCESS_DEBUG_FILENAME] = $file);
            !$this->debug ?: $config[self::ACCESS_DEBUG_RAW] = $rawRoot;
            return $config;
        }

        // merged config from all imports
        $importConfig = $this->import($config[self::EXTENDS_KEYWORD], $level);

        // now check the current config for keys to unset and remove the from the config
        // which was imported
        if(isset($config[self::UNSET_KEYWORD]))
        {
            foreach($config[self::UNSET_KEYWORD] as $k)
            {
                $keys = explode("::", $k);
                $last = array_pop($keys);
                $tmp = &$importConfig;
                foreach($keys as $k) {
                    $tmp = &$tmp[$k];
                }
                unset($tmp[$last]);
            }
        }

        // merge import config with root config
        $config = array_replace_recursive($importConfig, $config);

        // kill imports info, this is useless now
        unset($config[self::EXTENDS_KEYWORD]);
        unset($config[self::UNSET_KEYWORD]);

        // sort imports by level.
        !$this->debug ?: ($config[self::ACCESS_DEBUG_FILENAME] = $file);
        !$this->debug ?: ksort($config[self::ACCESS_DEBUG_IMPORTS]) ;
        !$this->debug ?: $config[self::ACCESS_DEBUG_RAW] = $rawRoot;

        return $config;
    }

    /**
     * Imports and merges the given config files.
     *
     * @param string[] $files The list of config files.
     * @param int $level The level ident.
     *
     * @return array
     */
    protected function import($files, $level)
    {
        $importConfig = [];
        foreach ($files as $file)
        {
            // load import file
            $parent = $this->loadFile($file, $level + 1, $file);
            !$this->debug ?: $parent[self::ACCESS_DEBUG_FILENAME] = $file;
            !$this->debug ?: $rawImport = array_slice($parent, 0);

            // merge with data from other configs on the same level with the same root
            $importConfig = array_replace_recursive($importConfig, $parent);

            !$this->debug ?: $importConfig[self::ACCESS_DEBUG_IMPORTS][$level][] = $parent;
            !$this->debug ?: $parent[self::ACCESS_DEBUG_RAW] = $rawImport;
        }

        return $importConfig;
    }

    /**
     * Checks whether the file was already imported. Overwrite this method if you want to allow a file to be imported
     * more than once.
     *
     * @param string $file The file to import.
     * @param string $from The root file which had the import directive.
     * @return void
     * @throws \LogicException
     */
    protected function checkRecursion($file, $from)
    {
        if (isset($this->recursionCheck[$file]))
        {
            throw new \LogicException(vsprintf(
                "Recursion detected, the file %s was already imported" .
                "from %s. Now you wanted to import it again from %s.",
                [$file, $this->recursionCheck[$file], $from]
            ));
        }

        $this->recursionCheck[$file] = $from;
    }


    /**
     * Initializes the replacement keys from the given config.
     *
     * @param array $config The config array
     * @return array
     */
    protected function initLocalReplacements(array &$config)
    {
        $replacements = [];
        foreach ($config as $name => $value) {
            if ('%' === substr($name, 0, 1)) {
                $replacements[$name] = (string)$value;
                unset($config[$name]);
            }
        }

        return $replacements;
    }

    /**
     * Replaces the the replacement keys in the given config and returns the altered array.
     *
     * @param array $config The config array.
     * @param array $replacements The replacements.
     * @return array
     */
    protected function replace($config, $replacements)
    {
        foreach ($config as $key => $value)
        {
            if (is_array($value)) {
                $config[$key] = $this->replace($value, $replacements);
            } else {
                if (is_string($value)) {
                    $config[$key] = strtr($value, $replacements);
                }
            }
        }

        return $config;
    }

    /**
     * Initializes the global replacement keys.
     */
    protected function initGlobalReplacements($replacements)
    {
        foreach ($replacements as $key => $value) {
            $this->replacements["%" . $key . "%"] = $value;
        }
    }
}