<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        $this->changeCatalogDeleteAction('restrict');
        Schema::table('checkout_items', fn (Blueprint $table) => $table->decimal('discount_amount', 10, 2)->default(0));
    }

    public function down(): void
    {
        Schema::table('checkout_items', fn (Blueprint $table) => $table->dropColumn('discount_amount'));
        $this->changeCatalogDeleteAction('cascade');
    }

    private function changeCatalogDeleteAction(string $action): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Laravel 10 cannot drop SQLite foreign keys. Preserve the complete table
            // definition, including host columns, indexes and triggers, while rebuilding.
            $name = DB::getTablePrefix() . 'checkout_items';
            $grammar = DB::connection()->getQueryGrammar();
            $original = $grammar->wrapTable('checkout_items');
            $temporary = $grammar->wrapTable('checkout_items_history_upgrade');
            $definition = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$name])->sql;
            $definition = preg_replace_callback('/^CREATE TABLE\s+(?:"[^"]+"|`[^`]+`|\S+)/i', fn () => 'CREATE TABLE ' . $temporary, $definition);
            $columns = array_filter(DB::select('PRAGMA table_xinfo(' . $original . ')'), fn ($column) => $column->hidden === 0);
            $columns = implode(', ', array_map(fn ($column) => $grammar->wrap($column->name), $columns));
            $definition = preg_replace('/(foreign key\s*\(["`]?(?:product_id|sku_id)["`]?\)\s*references\s*["`]?\w+["`]?\s*\(["`]?id["`]?\)\s*on delete\s+)(cascade|restrict)/i', '${1}' . $action, $definition);
            $objects = DB::select("SELECT sql FROM sqlite_master WHERE tbl_name = ? AND type IN ('index', 'trigger') AND sql IS NOT NULL", [$name]);
            $foreignKeys = (bool) DB::selectOne('PRAGMA foreign_keys')->foreign_keys;
            Schema::disableForeignKeyConstraints();
            try {
                DB::transaction(function () use ($definition, $objects, $temporary, $original, $columns) {
                    DB::statement($definition);
                    DB::statement("INSERT INTO {$temporary} ({$columns}) SELECT {$columns} FROM {$original}");
                    Schema::drop('checkout_items');
                    Schema::rename('checkout_items_history_upgrade', 'checkout_items');
                    foreach ($objects as $object) {
                        DB::statement($object->sql);
                    }
                });
            } finally {
                if ($foreignKeys) {
                    Schema::enableForeignKeyConstraints();
                }
            }

            return;
        }
        Schema::table('checkout_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['sku_id']);
        });
        Schema::table('checkout_items', function (Blueprint $table) use ($action) {
            $table->foreign('product_id')->references('id')->on('products')->onDelete($action);
            $table->foreign('sku_id')->references('id')->on('product_skus')->onDelete($action);
        });
    }
};
