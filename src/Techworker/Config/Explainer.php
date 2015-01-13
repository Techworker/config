<?php
/**
 * This file is part of the Techworker\Uuid package.
 *
 * (c) Benjamin Ansbach <benjaminansbach@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Techworker\Config;

/**
 * The explainer can explain where a value in the config comes from.
 *
 * @package    Techworker\config
 * @author     Benjamin Ansbach <benjaminansbach@googlemail.com>
 * @copyright  2015 Benjamin Ansbach <benjaminansbach@googlemail.com>
 * @license    MIT
 * @link       http://www.techworker.de/
 */
final class Explainer
{
    const VALUE_UNDEFINED = 'undefined';

    /**
     * This method tries to explain how a value is constructed while the config was read and merged.
     * This can be useful if you have a complex import hierarchy and want to understand where a value
     * comes from.
     *
     * This method is long and a bit cryptic but i dont think ill refactor that little monster. Use the
     * Explainer::toString method to get a description of how the value was constructed.
     *
     * @param string $keyAccess The key to explain.
     * @param array $config The config to check the explanation.
     * @param int $l Internal value to create indention.
     *
     * @return string
     */
    public static function explain($keyAccess, $config, $level = 0)
    {
        $explanation = [
            'key' => $keyAccess
        ];

        // check if the debug data is available
        if(!isset($config[Loader::ACCESS_DEBUG_RAW])) {
            return "";
        }

        // create root message string
        $explanation['level'] = $level;
        $explanation['file'] = basename($config[Loader::ACCESS_DEBUG_FILENAME]);

        // explode the keys, divided by "::"
        $keys = explode("::", $keyAccess);

        // this is the isolated raw configuration without any merge
        $rawConfig = $config[Loader::ACCESS_DEBUG_RAW];

        $found = true;
        // now loop through the keys and treverse the raw config array
        foreach($keys as $key)
        {
            // get in
            if(isset($rawConfig[$key])) {
                $rawConfig = $rawConfig[$key];
            } else {
                // the key does not exist
                $found = false;
                $explanation['value'] = self::VALUE_UNDEFINED;
                $realConfig = $config;

                // array_slice to create a new foreach pointer
                // traverse into the merged config array
                foreach(array_slice($keys, 0) as $k)
                {
                    if(!isset($realConfig[$k])) {
                        $realConfig = false;
                        break;
                    }

                    $realConfig = $realConfig[$k];
                }

                // we have some inherited data
                if($realConfig) {
                    $explanation['inherited_value'] = $realConfig;
                }

                break;
            }

        }

        // a raw config value was found
        if($found) {
            $explanation['value'] = $rawConfig;
        }

        if(isset($config[Loader::ACCESS_DEBUG_RAW][Loader::UNSET_KEYWORD]))
        {
            if(in_array($keyAccess, $config[Loader::ACCESS_DEBUG_RAW][Loader::UNSET_KEYWORD])) {
                $explanation['unset'] = true;
            }
        }

        // check importsa dn explain them
        if (isset($config[Loader::ACCESS_DEBUG_IMPORTS]))
        {
            $explanation['extended'] = [];
            foreach($config[Loader::ACCESS_DEBUG_IMPORTS] as $imports)
            {
                foreach ($imports as $parent) {
                    $explanation['extended'][] = Explainer::explain($keyAccess, $parent, $level + 1);
                }
                // just the first one..
                break;
            }
        }

        // append the resulting value
        if ($level === 0)
        {
            foreach($keys as $key) {
                if(!isset($config[$key])) {
                    $config = self::VALUE_UNDEFINED;
                    break;
                }

                $config = $config[$key];
            }

            $explanation['result'] = $config;
        }

        return $explanation;
    }

    /**
     * Creates a simple string representation of a explanation array produced by the Explainer::explain method.
     *
     * @param array $explanation
     * @return string
     */
    public static function toString($explanation)
    {
        $msg = str_repeat(" ", $explanation['level'] * 2) . $explanation['file'];
        if($explanation['value'] === self::VALUE_UNDEFINED) {
            $msg .= " did not define a value for " . print_r($explanation['key'], true);
        } else {
            $msg .= " defined a value: " . print_r($explanation['value'], true);
        }

        if(isset($explanation['inherited_value'])) {
            $msg .= " but inherited value " . print_r($explanation['inherited_value'], true);
        }

        if(isset($explanation['unset'])) {
            $msg .= " and unset the probably inherited value";
        }

        $msg .= "\n";

        if(isset($explanation['extended'])) {
            foreach($explanation['extended'] as $extendedExplanation) {
                $msg .= "\n" . Explainer::toString($extendedExplanation);
            }
        }

        if(isset($explanation['result']))
        {
            if ($explanation['result'] === self::VALUE_UNDEFINED) {
                $msg .= "\n==> The value for " . $explanation['key'] . " could not be retrieved.";
            } else {
                $msg .= "\n==> The Resulting value is " . print_r($explanation['result'], true);
            }
        }

        return $msg;
    }
}