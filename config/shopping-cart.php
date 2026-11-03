<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default tax rate
    |--------------------------------------------------------------------------
    |
    | This default tax rate will be used when you make a class implement the
    | Taxable interface and use the HasTax trait.
    |
    */

    'tax' => 0,

    // Callable(code, cart, service): bool; reject coupons until a host validator is provided.
    'coupon_validator' => null,

    /*
    |--------------------------------------------------------------------------
    | ShoppingCart database settings
    |--------------------------------------------------------------------------
    |
    | Here you can set the connection that the shoppingcart should use when
    | storing and restoring a cart.
    |
    */

    'database' => [
        'connection' => null,
        'table' => 'shopping_carts',
    ],

    /*
    |--------------------------------------------------------------------------
    | Destroy the cart on user logout
    |--------------------------------------------------------------------------
    |
    | When this option is set to 'true' the cart will automatically
    | destroy all cart instances when the user logs out.
    |
    */

    'destroy_on_logout' => false,

    /*
    |--------------------------------------------------------------------------
    | Default number format
    |--------------------------------------------------------------------------
    |
    | This defaults will be used for the formatted numbers if you don't
    | set them in the method call.
    |
    */

    'format' => [
        'decimals' => 2,
        'decimal_point' => '.',
        'thousand_separator' => ',',
    ],
    'models' => [
        'users' => [
            'table' => 'users',
            'foreign_id' => 'user_id',
            'enforce_cart_foreign_key' => env('CART_ENFORCE_USER_FOREIGN_KEY', true),
        ],
        'carts' => [
            'table' => 'shopping_carts',
            'foreign_id' => 'cart_id',
        ],
        'cart_items' => [
            'table' => 'shopping_cart_items',
        ],
        'products' => [
            'model' => 'MobileNowGroup\Product\Models\Product',
        ],
        'skus' => [
            'model' => \Fayne\ShoppingCart\Models\CartSku::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching options for the shopping cart.
    |
    */

    'cache' => [
        'enabled' => env('CART_CACHE_ENABLED', true),
        'ttl' => env('CART_CACHE_TTL', 3600),
        'store' => env('CART_CACHE_STORE', 'default'),
        'prefix' => env('CART_CACHE_PREFIX', 'cart'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events Configuration
    |--------------------------------------------------------------------------
    |
    | Configure event handling for the shopping cart.
    |
    */

    'events' => [
        'enabled' => env('CART_EVENTS_ENABLED', true),
        'listeners' => [
            'cart_operation' => true,
            'collect_totals' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Configure routing options for the shopping cart.
    |
    */

    'routes' => [
        'enabled' => env('CART_ROUTES_ENABLED', true),
        'guests' => false,
        'prefix' => env('CART_ROUTES_PREFIX', 'api/cart'),
        'name' => env('CART_ROUTES_NAME', 'api.cart.'),
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the storage layer for shopping cart data. This allows you to
    | choose between different storage strategies for optimal performance
    | in different environments.
    |
    */

    'storage' => [
        /*
        |--------------------------------------------------------------------------
        | Default Storage Driver
        |--------------------------------------------------------------------------
        |
        | This option controls the default storage driver that will be used to
        | store shopping cart data. You may set this to any of the drivers
        | defined in the "drivers" array below.
        |
        | Supported: "database", "cache", "redis", "layered"
        |
        */

        'default' => env('CART_STORAGE_DRIVER', 'database'),

        /*
        |--------------------------------------------------------------------------
        | Storage Drivers
        |--------------------------------------------------------------------------
        |
        | Here you may configure the storage drivers for your application's
        | shopping cart. Each driver has its own configuration options.
        |
        */

        'drivers' => [
            'database' => [
                'driver' => 'database',
            ],

            'cache' => [
                'driver' => 'cache',
                'store' => env('CART_CACHE_STORE', 'default'),
                'prefix' => env('CART_CACHE_PREFIX', 'cart'),
                'ttl' => env('CART_CACHE_TTL', 3600), // 1 hour
            ],

            'redis' => [
                'driver' => 'redis',
                'connection' => env('CART_REDIS_CONNECTION', 'default'),
                'prefix' => env('CART_REDIS_PREFIX', 'cart'),
                'ttl' => env('CART_REDIS_TTL', 86400), // 24 hours
            ],

            'layered' => [
                'driver' => 'layered',
                'enable_cache' => env('CART_LAYERED_CACHE', true),
                'enable_redis' => env('CART_LAYERED_REDIS', false),
                'cache' => [
                    'store' => env('CART_CACHE_STORE', 'default'),
                    'prefix' => env('CART_CACHE_PREFIX', 'cart'),
                    'ttl' => env('CART_CACHE_TTL', 3600),
                ],
                'redis' => [
                    'connection' => env('CART_REDIS_CONNECTION', 'default'),
                    'prefix' => env('CART_REDIS_PREFIX', 'cart'),
                    'ttl' => env('CART_REDIS_TTL', 86400),
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance monitoring options for the shopping cart.
    | This includes logging, caching, and threshold settings.
    |
    */

    'performance' => [
        'log_enabled' => env('CART_PERFORMANCE_LOG_ENABLED', true),
        'log_channel' => env('CART_PERFORMANCE_LOG_CHANNEL', 'default'),
        'cache_enabled' => env('CART_PERFORMANCE_CACHE_ENABLED', true),
        'thresholds' => [
            'execution_time' => [
                'default' => 100, // milliseconds
            ],
            'memory_usage' => [
                'default' => 1048576, // 1MB in bytes
            ],
        ],
    ],

    'order_class' => env('ORDER_CLASS', 'MobileNowGroup\\OMS\\Models\\Order'),

    /*
    |--------------------------------------------------------------------------
    | Checkout Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the checkout process behavior.
    |
    */
    'checkout' => [
        // Callable(sku, quantity, cart): bool. Required before checkout is enabled.
        'validator' => null,
        'currency' => 'CNY',
        // Checkout session expiration time in minutes
        'expiration' => env('CART_CHECKOUT_EXPIRATION_MINUTES', 30),

        // Rules that must pass before checkout can proceed
        'rules' => [
            // 'check_stock' => true,
            // 'check_min_amount' => false,
        ],
    ],

    'order_number_generator_class' => 'MobileNowGroup\\OMS\\Support\\OrderNumberGenerator',
];
