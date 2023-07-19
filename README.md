![GitHub repo logo](/dist/img/logo.png)

# phpUpdater
![License](https://img.shields.io/github/license/LouisOuellet/php-updater?style=for-the-badge)
![GitHub repo size](https://img.shields.io/github/repo-size/LouisOuellet/php-updater?style=for-the-badge&logo=github)
![GitHub top language](https://img.shields.io/github/languages/top/LouisOuellet/php-updater?style=for-the-badge)
![Version](https://img.shields.io/github/v/release/LouisOuellet/php-updater?label=Version&style=for-the-badge)

## Description
phpUpdater is a Library for PHP Applications. Using GitHub Repository as the source, it allows you to use releases as updates for your application.

## Features
 - Support for private repositories
 - Easy to use
 - Provides Backup and restore functionnalities
 - Simple upgrade method
 - Integration with [phpDB](https://github.com/LouisOuellet/php-database)

## Why you might need it?

## Can I use this?
Sure!

## License
This software is distributed under the [MIT](https://opensource.org/license/mit/) license. Please read [LICENSE](LICENSE) for information on the software availability and distribution.

## Requirements
 - PHP >= 5.5.0
 - GitHub Repository
 - GitHub Access Token

## To Do
 - Add support for public repositories

## Security
Please disclose any vulnerabilities found responsibly – report security issues to the maintainers privately.

## Installation
Using Composer:
```sh
composer require laswitchtech/php-database
```

## How do I use it?
Here is a full example:
```php
<?php

// Import phpUpdater class into the global namespace
// These must be at the top of your script, not inside a function
use LaswitchTech\phpUpdater\phpUpdater;

// Load Composer's autoloader
require 'vendor/autoload.php';

// Configure phpUpdater, you can also use a config file
$phpUpdater->config("owner","GITHUB_USERNAME")->config("repository","GITHUB_REPOSITORY")->config("token","GITHUB_ACCESS_TOKEN");

// Initialize Updater
$phpUpdater = new phpUpdater();

// Backup
$phpUpdater->backup();

// Restore
$phpUpdater->restore();

// Upgrade
$phpUpdater->upgrade();
```