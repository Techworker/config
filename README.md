# Configuration interpreter including a ServiceProvider for Silex

A configuration **file loader** *and* interpreter including a ServiceProvider for Silex.

## Installation

I don't want to overcrowd packagist since there are already a lot of config packages.
Let's see if this project collects some stars and decide on that value if a packagist
entry is necessary.

That said, at the moment you either have to download the archive from github by
yourself, check it out via git or define the following directive in your
composer.json file to install it via composer:

``` javascript
{
    "repositories": [
        // your other repositories
        // the techworker/config repository
        {
            "type": "vcs",
            "url": "https://github.com/techworker/config"
        }
    ]
}
```

## Features

This library..

 - does not depend on built-in configuration readers.
 - adds variables to configuration values.
 - can extend configurations from other configuration files of the same type.
 - supports vertical and horizontal inheritance / overwrite.
 - can unset inherited values from other configurations.
 - can explain how a configuration value is constructed in case of complex hierarchies
   and multiple configuration files.
 - contains a ServiceProvider for silex.

What all that means will be explained in the next sections.

## Usage

Simplest of all examples is as follows:

```php
use \Techworker\Config\Loader as ConfigLoader;

// this method will be used by the config loader to read a json file
// and return the contents
$jsonParser = function($file) {
    return json_decode(file_get_contents($file), true);
}

$loader = new ConfigLoader('my_config.json', $jsonParser);
$config = $loader->load();

// now you can access your configuration
echo $config['your_config_key_whatever'];
```

### Using Variables

See `Variables in configuration files` section.

## Config parsers

The type of configuration format is up to you, this library does not implement
any configuration file reader. That's good for you, because you are not bound
to any configuration format and can use your preferred one.

When you create the instance you have to tell her how she can read a configuration
file.

You can tell it to her by providing a `callable` function that handles the parsing
of your configuration file.

``` php
$app->register(new ConfigServiceProvider('my_config_file.ext', function($file) {
    // read file and return data
});
```

Since the reading task is really simple in most cases, I'll give you some simple
examples for the most common configuration formats (JSON. PHP and YAML).

### JSON

``` php
$jsonParser = function($file) {
    return json_decode(file_get_contents($file), true);
}
```

### YAML

Uses the symfony/yaml library.

``` php
$yamlParser = function($file) {
    return \Symfony\Component\Yaml\Yaml::parse($file) ?: [];
}
```

### PHP

``` php
$phpParser = function($file) {
    return include($file);
}
```

The PHP file should return an array:

``` php
return [
    'key' => 'value'
];
```

## Variables in configuration files

Since configuration files are static (except PHP files), you can add placeholders
to the instance which can then be used in the configuration files and are
automatically replaced.

A placeholder inside of the configuration files is identified by enclosed `%` (percent)
chars.

An example can be a directory variable.

```php
$loader = new ConfigLoader('my_config.yml', $yamlParser, [
    'MY_DIRECTORY' => './path/to/my/directory'
]);
```

And in your my_config.yml you can do the following:

```yaml
file: %MY_DIRECTORY%/file.ext
```

If you read the configuration your `file` value will be read, the `%MY_DIRECTORY%`
variable replaced and the resulting value is: `./path/to/my/directory/file.ext`.

You can use as many variables as you want.

Another possible use case for this feature is to provide sensitive informations
from outside of your configuration files like your API Keys or DB credentials.

```php
$loader = new ConfigLoader('my_config.yml', $yamlParser, [
    'SECURE-APIKEY' => getenv('SECURE-APIKEY') // or wherever you can get that value
]);
```

#### Variables in your configuration files

You can also add variables to your configuration files.

```yaml
%MY_DIRECTORY%: ./path/to/my/directory
file: %MY_DIRECTORY%/file.ext
```

This will result with the same result as in the example above. The variables can only
be defined in the root level and are **only** available in the current file.

## Extending Configurations

In most cases you have a single configuration file. But at some point you may want
to split up your configurations or want to maintain multiple configurations for your
different environments.

### Horizontal configuration

Imaging you have a large configuration file and want to split it up in multiple files.
This is what I call horizontal configuration.

```yaml
# main.yml
debug: false

some_other_configs:
# and so on

# database config
database:
  username: %DB_USER%
  password: %DB_PASSWORD%
  host: localhost
  name: app
```

We can put the database things in another file and import it.

```yaml
# database.yml

# database config
database:
  username: %DB_USER%
  password: %DB_PASSWORD%
  host: localhost
  name: app
```

```yaml
# main.yml
@extends:
  - database.yml
#  - other.yml

debug: false

some_other_configs:
# and so on
```

So when you read the `main.yml` config file, the `database.yml` file is read too and
automatically merged in your configuration.

It's up to you how many coniguration files you want to maintain.

### Vertical configuration

This library supports the extension of configuration values, which means you can
create some type of hierarchy for your config values. Imagine you have multiple
environments where your project gets installed. A typical setup would be the
following:

 - You have a `development` environment (runs local)
 - You have a `staging` environment (runs on your server in testing mode)
 - You have a `production` environment (runs on your server)

For simplicity, I will use 2 configuration directives which are very common:

 - A `debug` flag which tells the application that it runs in debug mode.
 - A `database` structure which contains the information to connect to the database.


At first we create a global.yml configuration which contains configuration values
which are the same, independent from the environment.

```yaml
#global.yml
# global config
company:
  name: Techworker
  address:
    street: Brandvorwerkkstr. 52-54
    zip: 04275
    country: Germany
```

Then we create a production.yml configuration which extends the `global.yml`.

``` yaml
#production.yml
@extends:
  - global.yml

debug: false

database:
  username: production
  password: production
  host: production-host
  name: app
  charset: utf-8

# some common config values
# ...
```

When we read this configuration, we will have a common production setup including
the values from the global.yml.

Now we create a staging.yml configuration file which should get read on
your staging system:

``` yaml
#staging.yml
@extends:
  - production

database:
  username: staging
  password: staging
  host: staging-host

# some common config values
# ...
```

When we read this configuration file the following happens:

 - The loader will read the staging.yml file
 - The loader sees that this file extends other configurations (in this case the `production.yml`)
 - The loader loads the configuration from the `production.yml`
 - The loader sees that this file extends other configurations (in this case the `global.yml`)
 - The loader merges the config from the `global.yml` file with the config from the `production.yml`.
 - The loader merges the config from the `production.yml` file with the config from the `staging.yml`.

The result would be the same as defining the following `staging.yml` without the @extends keyword:

```yaml
#staging.yml
company:
  name: Techworker
  address:
    street: Brandvorwerkkstr. 52-54
    zip: 04275
    country: Germany

debug: false

database:
  username: staging
  password: staging
  host: staging-host
  name: app
  charset: utf-8

# some common config values
# ...
```

As you can see, the production config values `debug`, `database.name` and `database.charset` were
automatically merged into the staging configuration.

Now we create a development.yml configuration file which should get read on
your development system:

``` yaml
#development.yml
@extends:
  - production

debug: true

database:
  username: development
  password: development
  host: development-host
  debug-mode: true

# some common config values
# ...
```

When we read this configuration file the following happens:

 - The loader will read the development.yml file
 - The loader sees that this file extends other configurations (in this case the `production.yml`)
 - The loader loads the configuration from the `production.yml`
 - The loader sees that this file extends other configurations (in this case the `global.yml`)
 - The loader loads the configuration from the `global.yml`
 - The loader merges the config from the `global.yml` file with the config from the `production.yml`.
 - The loader merges the config from the `production.yml` file with the config from the `development.yml`.

The result would be the same as defining the following `development.yml` without the @extends keyword:

```yaml
#development.yml
company:
  name: Techworker
  address:
    street: Brandvorwerkkstr. 52-54
    zip: 04275
    country: Germany

debug: true

database:
  username: development
  password: development
  host: development-host
  debug-mode: true
  name: app
  charset: utf-8

# some common config values
# ...
```

As you can see, by extending from other configuration you can avoid redundant definitions of
configuration values.

## Unsetting derived values

You can unset values from a extended configuration by using the `@unset` directive. This can
be useful if you want to completely remove a configuration value from a derive file.

Example:

```yaml
# global.yml
company:
  name: Techworker
  address:
    street: Brandvorwerkkstr. 52-54
    zip: 04275
    country: Germany
```

```yaml
# production.yml
@extends:
  - global.yml
@unset:
  - company
```

When reading the production.yml you will end up with an empty array, because the company key from
the globals.yml got removed.

You can also define a hierarchy for removing nested keys:

```yaml
# production.yml
@extends:
  - global.yml
@unset:
  - company::address::street
```

This will give you an array with all the values from the `global.yml` but without the `street` key
in `$config['company']['address']`.

So instead of setting production values implivitely to null or removing them in the
code you can do that inside of your configuration.

## Explainer

Configuration inheritance is a powerful mechanism but can become quite complex. To overcome this
complexity and help you finding out, how a value is constructed in the hierarchy, you can use
the `Explainer` class.

The explainer class can tell you how a value got constructed in the process of reading multiple
files. Let's stick with our config files above and read the `development.yml`. After that we want
to know how the value from `$config['database']['username']` was constructed.

```php
use \Techworker\Config\Explainer;

$loader = new ConfigLoader('my_config.yml', $yamlParser, [
    'SECURE-APIKEY' => getenv('SECURE-APIKEY') // or wherever you can get that value
]);
$config = $loader->load();

echo Explainer::toString(Explainer::explain("database::username", $app));
```

The output will be as follows:

```
development.yml defined a value: development
  production.yml defined a value: production
    global.yml did not define a value for database::username

==> The Resulting value is development
```

Cool, isn't it. The Explainer will also recognize inherited values or removed values
via `@unset`. The Explainer functionality is only available if you set `$app['debug']`
to true.

If you look at your `$config` you will recognize some keys which are preceeded
by `csp.`. These are internal debug values and are used by the explain feature. These will not
be available if you created the loader instance with `debug` det to true.

## Unsetting values

TODO: Explain unsetting, look @tests to see how it works.

## Silex Service Provider

Inside of this package you will also get a ServiceProvider for Silex, which simplifies
the use inside of silex.

```
use \Techworker\Config\Intergration\Silex as ConfigServiceProvider;

$app = new \Silex\Application();
$app->register(new ConfigServiceProvider('my_config.yml', $yamlParser);

// now you can access your configuration from your application instance
echo $app['your_config_key_whatever'];
```

### Using a prefix

Instead of loading the configuration into the global application instance on the root
level, you can load it into a sub key of your instance by providing a prefix parameter.

```php
$app->register(new ConfigServiceProvider('my_config.json', $jsonParser, [], 'config');
```

Your configurations will then be available in `$app['config']`.

### Referencing a parser by string

```php
$app['my_config_parser'] = $app->protect(function($file) {
    // read file and return data
});

$app->register(new ConfigServiceProvider('my_config_file.ext', 'my_config_parser');
```