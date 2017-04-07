<?php

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

// Composer autoloading
include __DIR__ . '/../vendor/autoload.php';

$config = include __DIR__ . '/../config/module.config.php';
$artaxService = new \ArtaxComposer\Service\ArtaxService($config['artax_composer']);

$result = $artaxService
    ->setUri('https://httpbin.org/ip')
    ->get();

die(var_dump($result));