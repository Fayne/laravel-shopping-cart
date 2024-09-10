<?php

namespace MobileNowGroup\ShoppingCart\Models;

use App\Models\Traits\Transactionable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MobileNowGroup\ShoppingCart\Exceptions\CartException;
use MobileNowGroup\ShoppingCart\Exceptions\ErrorConst;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart;
use Modules\Product\Models\Product;

class ProductSku extends Model
{
    use HasFactory, Transactionable;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    /**
     * @return BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @param $data
     * @return array
     *
     * @throws CartException
     */
    public function prepareForCart($data): array
    {
        $data['quantity'] = (int) $data['quantity'] ?? 1;

        $data = $this->getQtyRequest($data);

        if ($data['quantity'] > $this->qty) {
            throw new CartException(ErrorConst::ERROR_REQUESTED_QUANTITY_NOT_AVAILABLE);
        }

        $price = round($this->price, 2);

        return [
            [
                'product_id' => $this->product->id,
                'product_sku_id' => $this->id,
                'sku' => $this->sku,
                'quantity' => $data['quantity'],
                'name' => $this->product->name,
                'price' => $price,
                'base_price' => $price,
                'total' => $price * $data['quantity'],
                'base_total' => $price * $data['quantity'],
                'weight' => $this->weight ?? 0,
                'total_weight' => ($this->weight ?? 0) * $data['quantity'],
                'base_total_weight' => ($this->weight ?? 0) * $data['quantity'],
                'additional' => $data,
            ],
        ];
    }
    /**
     * Get request quantity.
     *
     * @param  array $data
     * @return array
     */
    public function getQtyRequest($data): array
    {
        if ($item = ShoppingCart::getItemByProduct()) {
            $data['quantity'] += $item->quantity;
        }

        return $data;
    }
}
