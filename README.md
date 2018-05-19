# Changelog Generator

[![Build Status](https://travis-ci.org/jwage/changelog-generator.svg)](https://travis-ci.org/jwage/changelog-generator)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jwage/changelog-generator/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jwage/changelog-generator/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jwage/changelog-generator/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jwage/changelog-generator/?branch=master)

This library will generate a changelog markdown document from a GitHub milestone. It is based off of
[weierophinney/changelog_generator](https://github.com/weierophinney/changelog_generator).

## Features

- [Shows total issues, pull requests and contributors in a GitHub milestone](https://github.com/jwage/changelog-generator#003)
- [Filter by GitHub labels](https://github.com/jwage/changelog-generator#filtering-by-labels)
- [Grouped by GitHub labels](https://github.com/jwage/changelog-generator#003)
- [Write changelog to a file](https://github.com/jwage/changelog-generator#write-to-file)
- [Multi-Project configuration](https://github.com/jwage/changelog-generator#configuration-file)
- [Connects related pull requests and issues to reduce redundant lines in the changelog](https://github.com/jwage/changelog-generator#connecting-issues--pull-requests)

## Installation

You can install with composer:

    $ composer require jwage/changelog-generator

Or you can download the latest `changelog-generator.phar` file from the [releases](https://github.com/jwage/changelog-generator/releases) pages.

## Example

Here is what an example changelog looks like. It was generated from the ``0.0.3`` milestone in GitHub for this project:

### 0.0.3

- Total issues resolved: **7**
- Total pull requests resolved: **7**
- Total contributors: **1**

#### Enhancement

 - [15: Look for changelog-generator-config.php file by default if no --confi&hellip;](https://github.com/jwage/changelog-generator/pull/15) thanks to @jwage
 - [13: Allow changelog configurations to be provided via a php config file.](https://github.com/jwage/changelog-generator/pull/13) thanks to @jwage
 - [12: Allow changelog to be filtered by label names.](https://github.com/jwage/changelog-generator/pull/12) thanks to @jwage
 - [11: Add total contributors to changelog.](https://github.com/jwage/changelog-generator/pull/11) thanks to @jwage
 - [9: Improve changelog totals to be split by issue and pull requests.](https://github.com/jwage/changelog-generator/pull/9) thanks to @jwage
 - [7: Link pull requests and issues together to improve the changelog format.](https://github.com/jwage/changelog-generator/pull/7) thanks to @jwage
 - [6: Allow changelog to be written to a file.](https://github.com/jwage/changelog-generator/pull/6) thanks to @jwage

## Basic Usage

Generate a change log based on a GitHub milestone with the following command:

    $ ./vendor/bin/changelog-generator generate --user=doctrine --repository=migrations --milestone=2.0

## Write to File

Write the generated changelog to a file with the `--file` option. If you don't provide a value, it will be written
to the current working directory in a file named `CHANGELOG.md`:

    $ ./vendor/bin/changelog-generator generate --user=doctrine --repository=migrations --milestone=2.0 --file

You can pass a value to `--file` to specify where the changelog file should be written:

    $ ./vendor/bin/changelog-generator generate --user=doctrine --repository=migrations --milestone=2.0 --file=changelog.md

By default it will overwrite the file contents but you can pass the `--append` option to append the changelog to
the existing contents.

    $ ./vendor/bin/changelog-generator generate --user=doctrine --repository=migrations --milestone=2.0 --file=changelog.md --append

## Connecting Issues & Pull Requests

To make the changelog easier to read, we try to connect pull requests to issues by looking for `#{ISSUE_NUMBER}` in the body
of the pull request. When the user of the issue and pull request are different github users, the changelog line will look like the following:

- [634: Fix namespace in bin/doctrine-migrations.php](https://github.com/doctrine/migrations/pull/634) thanks to @Majkl578 and @jwage

## Filtering by Labels

You can filter the changelog by label names using the ``--label`` option:

    $ ./vendor/bin/changelog-generator generate --user=doctrine --repository=migrations --milestone=2.0 --label=Enhancement --label=Bug

## Configuration File

You can provide a PHP configuration file to the changelog generator if you don't want to provide the data manually each time.
Put the following contents in a file named `config.php`:

```php
<?php

declare(strict_types=1);

use ChangelogGenerator\ChangelogConfig;

return [
    'changelog-generator' => new ChangelogConfig(
        'jwage',
        'changelog-generator',
        '0.0.3',
        ['Enhancement', 'Bug']
    ),
    'another-project' => new ChangelogConfig(
        // ...
    )
];
```

Then you can use the configuration file like the following:

    $ ./vendor/bin/changelog-generator generate --config=config.php

By default it will generate a changelog for the first changelog config in the array returned by the file. You can use the
`--project` option if you want to generate a changelog for a specific project in the config file:

    $ ./vendor/bin/changelog-generator generate --config=config.php --project=another-project

By default if you name your config file `changelog-generator-config.php`, the changelog generator will look for that file
if no `--config` option is passed.

    $ ./vendor/bin/changelog-generator generate
