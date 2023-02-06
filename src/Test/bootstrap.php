<?php

use Composer\Autoload\ClassLoader;

include_once dirname(__FILE__, 3) . '/vendor/autoload.php';

$classLoader = new ClassLoader();
$classLoader->addPsr4("Endereco\\Shopware6Client\\", dirname(__FILE__, 3) . '/src', true);
$classLoader->register();
