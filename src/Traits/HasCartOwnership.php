<?php

namespace Fayne\ShoppingCart\Traits;

use Fayne\ShoppingCart\Contracts\CartContextResolver;
use Illuminate\Database\Eloquent\Builder;

trait HasCartOwnership
{
    protected static function bootHasCartOwnership(): void
    {
        static::addGlobalScope('cartOwnership', function (Builder $query): void {
            if ($context = static::cartContext()) {
                $query->where($query->qualifyColumn('tenant_id'), $context->tenantId())
                    ->where($query->qualifyColumn(static::cartOwnerColumn()), $context->customerId());
                if ($context->customerId() === null) {
                    $query->where($query->qualifyColumn('session_id'), $context->sessionId());
                }
            } else {
                $query->whereNull($query->qualifyColumn('tenant_id'));
            }
        });

        static::creating(function ($model): void {
            if ($context = static::cartContext()) {
                $model->tenant_id = $context->tenantId();
                $model->{static::cartOwnerColumn()} = $context->customerId();
                if (static::cartOwnerColumn() === 'customer_id') {
                    $model->session_id = $context->customerId() === null ? $context->sessionId() : null;
                }
            }
        });
    }

    protected static function cartContext(): ?CartContextResolver
    {
        if (!app()->bound(CartContextResolver::class)) {
            abort_if(config('shopping-cart.tenancy.enabled', false), 503);

            return null;
        }

        $context = app(CartContextResolver::class);
        abort_if(trim((string) $context->tenantId()) === '', 503);
        abort_if($context->customerId() === null
            && (static::cartOwnerColumn() !== 'customer_id' || trim((string) $context->sessionId()) === ''), 401);

        return $context;
    }
}
