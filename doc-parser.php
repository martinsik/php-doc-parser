<?php

/**
 * Search for autoload.php file.
 *
 * Based on phpunit/phpunit package by Sebastian Bergmann.
 * URL: https://github.com/sebastianbergmann/phpunit/blob/master/phpunit
 */
foreach (array(__DIR__ . '/../../autoload.php', __DIR__ . '/../vendor/autoload.php', __DIR__ . '/vendor/autoload.php') as $file) {
    if (file_exists($file)) {
        define('COMPOSER_AUTOLOAD_FILE', $file);
        break;
    }
}

unset($file);

if (!defined('COMPOSER_AUTOLOAD_FILE')) {
    fwrite(STDERR,
        'You need to set up the project dependencies using the following commands:' . PHP_EOL .
        'wget http://getcomposer.org/composer.phar' . PHP_EOL .
        'php composer.phar install' . PHP_EOL
    );
    die(1);
}

require COMPOSER_AUTOLOAD_FILE;


// Setup cli commands
$runCmd = new \DocParser\Command\RunCommand();
$singleCmd = new \DocParser\Command\SingleCommand();

$application = new \Symfony\Component\Console\Application();
$application->add($runCmd);
$application->add($singleCmd);
$application->setDefaultCommand($runCmd->getName());
$application->run();