<?php

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Fayne\ShoppingCart\ShoppingCartServiceProvider;

require_once __DIR__ . '/bootstrap.php';

$app = Container::getInstance();
if ($app instanceof Application) {
    $app->register(ShoppingCartServiceProvider::class);
}
