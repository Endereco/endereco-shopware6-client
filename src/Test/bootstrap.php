<?php

use Composer\Autoload\ClassLoader;

include_once __DIR__.'/../../../../../vendor/autoload.php';

$classLoader = new ClassLoader();
$classLoader->addPsr4("Endereco\\Shopware6Client\\", __DIR__.'/../../src', true);
$classLoader->register();
