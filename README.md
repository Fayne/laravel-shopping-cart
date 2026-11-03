<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Fayne Shopping Cart

简体中文 | [English](README.en.md)

面向 Laravel 应用的购物车与结账状态管理包，支持商品行管理、商品选择、立即购买、优惠券扩展、结账快照和可替换的存储适配器。适用于 PHP 8.2+、Laravel 10+。

## 功能特性

| 能力 | 说明 |
| --- | --- |
| 购物车操作 | 获取、添加、更新数量、移除商品、清空、选择部分商品结账 |
| 立即购买 | 将立即购买商品隔离到临时结账购物车，可在返回时恢复正常购物车 |
| 金额汇总 | 按分计算行金额与购物车汇总，保留折扣快照 |
| 优惠券扩展 | 通过宿主校验器计算并保存每行折扣，结账前重新校验 |
| 结账保护 | 事务内串行化购物车写入，支持宿主库存校验/预留回调和结账历史快照 |
| Laravel 集成 | 服务提供者、Facade、API 路由、事件、配置、迁移与 Artisan 运维命令 |
| 存储驱动 | database、cache、redis、layered 配置名；为保证一致性，当前所有内置驱动以数据库为权威数据源 |

本包负责购物车状态与结账快照，不负责创建宿主订单、支付幂等、支付状态、库存释放、税费/运费或履约。以上能力必须由宿主应用实现。

## 安装

```bash
composer require fayne/shopping-cart
php artisan vendor:publish --provider="Fayne\ShoppingCart\ShoppingCartServiceProvider" --tag=config
php artisan migrate
```

Laravel 会通过 Composer 自动发现服务提供者和 `ShoppingCart` Facade。迁移会由服务提供者自动加载；如需自行管理迁移文件，可显式发布：

```bash
php artisan vendor:publish --provider="Fayne\ShoppingCart\ShoppingCartServiceProvider" --tag=migrations
```

使用私有 Composer 仓库时，先在宿主项目配置该仓库，再执行 `composer require fayne/shopping-cart`。首次部署前请阅读下方的生产配置和[生产环境接入要求](#生产环境接入要求)。

## 配置

发布的配置文件为 `config/shopping-cart.php`。生产环境至少需要设置：

```php
// config/shopping-cart.php
return [
    'checkout' => [
        'validator' => App\Shopping\ValidateCartStock::class,
        'currency' => 'CNY',
    ],
    'coupon_validator' => App\Shopping\ValidateCartCoupon::class,
];
```

`checkout.validator` 是可调用类，接收 `(Cartable $sku, int $quantity, ShoppingCart $cart)` 并返回布尔值。它必须在结账事务中验证并锁定或预留库存。未配置校验器时结账被拒绝。

`coupon_validator` 接收 `(string $code, ShoppingCart $cart, Fayne\ShoppingCart\ShoppingCart $service)` 并返回布尔值。校验器负责为符合条件的行设置 `discount_amount` 和 `base_discount_amount`，并清除不再适用的折扣。没有校验器时优惠券无法应用。

宿主需要提供有效的用户认证，使 `auth()->id()` 返回客户 ID。默认客户外键面向整数 `users.id`；若使用 ULID/UUID，请在安装前调整客户列和外键。商品/SKU 类、订单类和订单号生成器请按 `config/shopping-cart.php` 中的宿主集成项配置。

## 商品模型契约

可加入购物车的商品模型须实现 `Fayne\ShoppingCart\Contracts\Cartable`：

```php
use Fayne\ShoppingCart\Contracts\Cartable;
use Illuminate\Database\Eloquent\Model;

class ProductSku extends Model implements Cartable
{
    public function getPrice(): float { /* 当前含税/业务单价，按宿主定义 */ }
    public function getSku(): string { /* SKU 编码 */ }
    public function getName(): string { /* 展示名称 */ }
    public function getWeight(): float|int|null { /* 重量或 null */ }
    public function canBePurchased(): bool { /* 是否可售 */ }
}
```

模型还需是 Eloquent 模型并提供主键及多态类型，库存校验由结账校验器负责。请确保 `getPrice()` 返回当前有效价格，并在商品下架或不可购买时让 `canBePurchased()` 返回 `false`。

## 使用方法

可使用自动注册的 `ShoppingCart` Facade：

```php
use Fayne\ShoppingCart\Facades\ShoppingCart;

$cart = ShoppingCart::getCart();
ShoppingCart::addProduct($sku, quantity: 2);
ShoppingCart::updateItems($itemId, quantity: 3);
ShoppingCart::setCartItemSelection($itemId, selected: true);
ShoppingCart::collectTotals();
ShoppingCart::setCouponCode('WELCOME');
ShoppingCart::removeCouponCode();
ShoppingCart::removeItem($itemId);
ShoppingCart::emptyCart();
```

立即购买请调用 `POST /modules/shopping-cart/api/cart/instant-purchase`，返回普通购物车时调用同一路径的 `DELETE`。路径基于默认配置。

购物车服务会在自身写操作中使用数据库事务。订单创建应由宿主在一个清晰的事务/幂等边界中执行，并在订单成功后完成购物车状态流转。不要直接修改购物车或商品项模型来绕过服务的归属校验、锁和金额汇总。

## 事件与 API 路由

服务提供者默认加载包内路由并注册购物车操作及汇总事件。可通过 `routes.enabled`、`routes.prefix`、`events.enabled` 配置。上线前请检查实际注册的路由、认证中间件和 CSRF/API 边界，确保客户只能访问自己的购物车。

可监听 `Fayne\ShoppingCart\Events\CheckoutValidating` 执行同步的结账校验或同数据库连接上的库存预留。事件运行在事务中，事务重试可能重新调用监听器；监听器必须保持幂等。不要将库存校验事件排队执行，也不要在事务中执行不可补偿的外部副作用。

## 生产环境接入要求

- 部署新增的 `2026_11_02_000001_preserve_checkout_history` 迁移前，先备份并在脱敏数据副本上演练。迁移增加行折扣历史并将商品/SKU 外键改为 RESTRICT；被历史结账引用的商品需要归档，不能硬删除。
- 迁移不会重建历史折扣。核对历史未支付结账、订单头与行金额，不要重算已支付交易。
- 当前内置存储配置都以数据库为事实来源；cache/redis/layered 名称不再意味着模型快照缓存。
- 商品金额按行以分进行四舍五入；当前税费和运费为零。订单适配器需使用行 `discount_amount` 及折后 `total_amount`。
- `CheckoutValidating`、库存校验、订单创建、支付和外部库存系统需由宿主按自己的幂等/补偿规则接通。SQLite 测试无法证明 MySQL/PostgreSQL 行锁行为。
- `cart:migrate-data` 只检查内置驱动读取，不会复制购物车；性能基准命令只能在可丢弃测试数据上使用。

宿主自定义存储也须遵循服务的客户归属校验、加锁及金额汇总规则。Laravel 10 的历史测试结果仅用于兼容性验证。

完成目标数据库并发测试、真实历史数据迁移演练和宿主库存/支付联调后，才具备生产放行条件。详见[验收证据](docs/production-acceptance-2026-11-03.md)。

## 本地开发

```bash
composer test
composer analyse
composer format
```

不要提交本包的 `composer.lock`。真实 MySQL/Redis 验收测试需要显式指定一次性测试服务端口，详情见[验收证据](docs/production-acceptance-2026-11-03.md)。

## License

Proprietary License，详见 [LICENSE.md](LICENSE.md)。

[生产放行清单](docs/production-release-gates.md)
