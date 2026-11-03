<?php

namespace Fayne\ShoppingCart\Console\Commands;

use Illuminate\Console\Command;
use Fayne\ShoppingCart\Models\ShoppingCart;
use Fayne\ShoppingCart\Storage\CartStorageManager;

/** All built-in drivers share the database; switching drivers must never duplicate rows. */
class MigrateCartDataCommand extends Command
{
    protected $signature = 'cart:migrate-data
        {--from= : Source storage driver}
        {--to= : Target storage driver}
        {--batch-size=100 : Number of carts per batch}
        {--dry-run : Inspect without reading target storage}
        {--customer-id= : Inspect one customer}
        {--force : Skip confirmation}';

    protected $description = 'Verify a cart storage driver switch without copying database records';

    public function handle(CartStorageManager $manager): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $size = (int) $this->option('batch-size');
        $drivers = ['database', 'cache', 'redis', 'layered'];
        if (!in_array($from, $drivers, true) || !in_array($to, $drivers, true) || $from === $to || $size < 1) {
            $this->error('Choose different built-in source/target drivers and a positive batch size.');

            return self::FAILURE;
        }
        if (!$this->option('force') && !$this->option('dry-run') && !$this->confirm('Verify the target driver without copying database records?')) {
            return self::SUCCESS;
        }
        $target = $manager->driver($to);
        $query = ShoppingCart::where('is_active', true);
        if ($customer = $this->option('customer-id')) { $query->where('customer_id', $customer); }
        $count = 0;
        $query->chunkById($size, function ($carts) use ($target, &$count) {
            foreach ($carts as $cart) {
                if (!$this->option('dry-run')) { $target->getCart((string) $cart->customer_id); }
                $count++;
            }
        });
        $this->info("Verified {$count} carts. No database rows copied; change shopping-cart.storage.default to switch drivers.");

        return self::SUCCESS;
    }
}
