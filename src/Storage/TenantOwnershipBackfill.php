<?php

namespace Fayne\ShoppingCart\Storage;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class TenantOwnershipBackfill
{
    /**
     * @param array<string, int|string> $carts Tenant IDs keyed by cart ULID.
     * @param array<string, int|string> $checkouts Tenant IDs keyed by Checkout ULID.
     */
    public function apply(array $carts, array $checkouts): void
    {
        DB::transaction(function () use ($carts, $checkouts): void {
            $cartTable = config('shopping-cart.models.carts.table', 'shopping_carts');

            foreach ([$cartTable => $carts, 'checkout' => $checkouts] as $table => $mapping) {
                foreach ($mapping as $id => $tenant) {
                    if (trim((string) $tenant) === '') {
                        throw new InvalidArgumentException('Tenant ID must not be empty.');
                    }

                    $updated = DB::table($table)->where('id', $id)->whereNull('tenant_id')
                        ->update(['tenant_id' => (string) $tenant]);

                    if ($updated !== 1) {
                        throw new RuntimeException("Expected one unassigned record in {$table} for {$id}.");
                    }
                }
            }

            $duplicates = DB::table($cartTable)->whereNotNull('tenant_id')->where('is_active', true)
                ->select('tenant_id', 'customer_id', 'session_id')
                ->groupBy('tenant_id', 'customer_id', 'session_id')
                ->havingRaw('COUNT(*) > 1')->exists();

            if ($duplicates) {
                throw new RuntimeException('Resolve duplicate active carts before assigning tenant ownership.');
            }
        });
    }
}
