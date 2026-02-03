# This is a custom shopping cart package for Laravel projects

[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mobilenowgroup/laravel-shopping-cart/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mobilenowgroup/laravel-shopping-cart/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/mobilenowgroup/laravel-shopping-cart/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/mobilenowgroup/laravel-shopping-cart/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)

## Installation

You can install the package via composer:

```bash
composer config repositories.mobilenowgroup/laravel-shopping-cart git git@github.com:MobileNowGroup/laravel-shopping-cart.git
composer require mobilenowgroup/laravel-shopping-cart
php artisan vendor:publish --provider="MobileNowGroup\ShoppingCart\ShoppingCartServiceProvider"
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --provider="MobileNowGroup\ShoppingCart\ShoppingCartServiceProvider" --tag="migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="shopping-cart-config"
```

## Usage
``` text
关于立即购买、购物车页面、部分商品结算的说明：
1. 点击立即购买时:
    将购物车的状态修改为未激活状态，其他正常操作：添加商品到购物车（此时会创建一个为“立即购买”服务的购物车），跳过购物车页面，订单确认等等
    保存订单之后，当前购物车改为未激活状态，将购物车状态改为激活状态
   （可选）监控还未下单就从结算页返回时，将立即结算的购物车状态改为未激活状态，购物车状态改为激活状态
2. 购物车界面时:
    购物车状态为已激活状态
3. 部分商品结算时:
    购物车状态为已激活状态，同时部分商品为选中状态，结算页的商品只展示选中商品，下单成功之后，将购物车中的选中的部分商品删除
```
```php
    //查询购物车信息
    ShoppingCart::getCart();
    
    //添加购物车
    $product = ProductSku::find($productSkuId);
    ShoppingCart::addProduct(
        $product,
        $quantity,
        $isBuyNow
    );
    
    //更新数量；商品条目ID，数量
    ShoppingCart::updateItems($itemId, $requestedQuantity);
    
    //计算总价
    ShoppingCart::collectTotals();
    
    //清空购物车，购物车仍为激活状态
    ShoppingCart::emptyCart();
    
    //移除商品条目
    ShoppingCart::removeItem($itemId);
        
    //应用优惠券code
    ShoppingCart::setCouponCode($couponCode);
    
    //移除优惠券code
    ShoppingCart::removeCouponCode();
    
    //购物车中选中部分商品进行结算
    ShoppingCart::toggleBuy('single', true, $itemId);

```
### tips
```text
多个购物车操作执行时，记得加上事务DB::transaction;

```
```php
# 监听器需要监听多个事件中，所以需要如下配置：
# 在文件App\Providers\EventServiceProvider中，加上：
    protected $listen = [
        # ...
        CollectTotalEvent::class => [
            CollectTotalsListener::class
        ]
    ];
```
```php
# 产品model需要继承Cartable
use MobileNowGroup\ShoppingCart\Contracts\Cartable;
class Product extends Model implements Cartable
{
    #...
}

```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## License

The Proprietary License. Please see [License File](LICENSE.md) for more information.
