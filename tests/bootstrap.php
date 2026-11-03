<?php

$autoload = getenv('SHOPPING_CART_VENDOR_AUTOLOAD') ?: __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    $autoload = __DIR__ . '/../../../vendor/autoload.php';
}
$loader = require $autoload;
$loader->addPsr4('Fayne\\ShoppingCart\\', __DIR__ . '/../src');
$loader->addPsr4('Fayne\\ShoppingCart\\Tests\\', __DIR__);
