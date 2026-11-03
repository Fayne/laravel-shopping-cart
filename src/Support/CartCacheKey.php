<?php

namespace Fayne\ShoppingCart\Support;

use Fayne\ShoppingCart\Contracts\CartContextResolver;

final class CartCacheKey
{
    public static function scoped(string $key): string
    {
        if (!app()->bound(CartContextResolver::class)) {
            abort_if(config('shopping-cart.tenancy.enabled', false), 503);

            return $key;
        }

        $context = app(CartContextResolver::class);
        $tenant = (string) $context->tenantId();
        $customer = $context->customerId();
        $session = $customer === null ? $context->sessionId() : null;
        abort_if(trim($tenant) === '', 503);
        abort_if($customer === null && trim((string) $session) === '', 401);

        return $key . ':scope:' . hash('sha256', serialize([
            $tenant, $customer === null ? null : (string) $customer, $session,
        ]));
    }
}
