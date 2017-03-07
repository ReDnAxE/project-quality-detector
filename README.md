Project Quality Inspector
-------------------------

ProjectQualityInspector is a PHP script `pqi` which checks project custom good practices.
This generic quality checking tool will check your projects through various configurable rules.

[![Latest Stable Version](https://img.shields.io/packagist/v/rednaxe/project-quality-inspector.svg?style=flat-square)](https://packagist.org/packages/rednaxe/project-quality-inspector)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg?style=flat-square)](https://php.net/)
[![Build Status](https://img.shields.io/travis/ReDnAxE/project-quality-inspector/master.svg?style=flat-square)](https://travis-ci.org/ReDnAxE/project-quality-inspector)

This tool is for you if you want to check in your project if :
* a file or directory (or a pathname pattern) should exist, or should not exist
* a composer package should exist, or should not exist
* a composer package version should not be wildcarded
...

Requirements
------------

ProjectQualityInspector requires PHP version 5.6 or greater

Installation
------------

You can install the component in 2 different ways:

* Install it via Composer (``rednaxe/project-quality-inspector`` on [Packagist](https://packagist.org/packages/rednaxe/project-quality-inspector));

Simply add a (development-time) dependency on ``rednaxe/project-quality-inspector`` to your project's ``composer.json`` file if you use [Composer](https://getcomposer.org/) to manage the dependencies of your project:
```bash
composer require --dev rednaxe/project-quality-inspector ^1.1.0
```

* Use the official Git repository (https://github.com/rednaxe/project-quality-inspector).

Usage
-----

First, you have to create a pqi.yml configuration file in your project. If there is no configuration file in current directory, by default the example pqi.yml will be used.

The first level of configuration is up to you. When you will run the command, you have to specify the section in configuration file, for example :
```bash
$ ./bin/pqi mycustomconfig
```

For Symfony 2.*, you will have to add ``run`` key like this :

```bash
$ ./bin/pqi run mycustomconfig
```

You can use ``-c`` or ``--configFile``, and ``-b`` or ``--baseDir`` options to respectively change the configuration file, and the inspection base directory :
```bash
$ ./bin/pqi mycustomconfig -c config/pqi.yml -b Back/src
```

You can also add a ``common`` section, which will be always merged to the selected section :
```yaml
mycustomconfig:
    config-files-exists-rule:
        - "appveyor.yml"
mysecondcustomconfig:
    config-files-exists-rule:
        - ".travis.yml"
common:
    config-files-exists-rule:
        - ".gitignore.yml"
```

Rules
-----

A rule is loaded when the rule key is present in configuration.
Here is a list of existing rules, and possible configurations :

* config-files-exists-rule config example:

```yaml
mycustomconfig:
    config-files-exists-rule:
        - "ruleset.xml"
        - "app/phpunit.xml"
        - "!web/app_*.php"
        - "web/app.php"
        - "tests/"
        - { filename: "app/phpunit.xml", reason: "This file is required for testing code" }
        - "composer.json"
```

* composer-config-rule config example:

```yaml
mycustomconfig:
    composer-config-rule:
        file: "composer.json"
        disallow-wildcard-versioning: true
        packages:
            - { value: "!h4cc/alice-fixtures-bundle", reason: "This package is no more maintained" }
            - "phpunit-bridge"
            - "bruli/php-git-hooks"
            - "php-cs-fixer"
```

TODO
----

* Creating PHP Archive [PHP Archive (PHAR)](https://php.net/phar)
* Tests
* Find more rules ;)