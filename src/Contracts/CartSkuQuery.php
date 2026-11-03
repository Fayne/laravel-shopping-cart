<?php

namespace Fayne\ShoppingCart\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface CartSkuQuery
{
    /** @param class-string<Model&Cartable> $model */
    public function forTenant(string $model, int|string $tenantId): Builder;
}
