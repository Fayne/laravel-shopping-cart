<?php

namespace Fayne\ShoppingCart\Support;

use Fayne\ShoppingCart\Contracts\Cartable;
use Fayne\ShoppingCart\Contracts\CartContextResolver;
use Fayne\ShoppingCart\Contracts\CartSkuQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CartSku
{
    /** @return class-string<Model&Cartable> */
    public static function model(): string
    {
        $model = config('shopping-cart.models.skus.model');
        abort_unless(is_string($model) && is_subclass_of($model, Model::class)
            && is_subclass_of($model, Cartable::class), 503, 'Configure an Eloquent SKU model implementing Cartable.');

        return $model;
    }

    public static function query(): Builder
    {
        $model = self::model();
        if (app()->bound(CartContextResolver::class)) {
            $tenantId = app(CartContextResolver::class)->tenantId();
            abort_if(trim((string) $tenantId) === '' || !app()->bound(CartSkuQuery::class), 503, 'A trusted tenant SKU query is required.');

            return app(CartSkuQuery::class)->forTenant($model, $tenantId);
        }

        abort_if(config('shopping-cart.tenancy.enabled', false), 503);

        return $model::query();
    }
}
