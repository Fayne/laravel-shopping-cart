<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Fayne Shopping Cart

简体中文 | [English](README.en.md)

面向 Laravel 应用的购物车与结账状态管理包，支持商品行管理、商品选择、立即购买、优惠券扩展、结账快照和可替换的存储适配器。适用于 PHP 8.2+、Laravel 12 或 13（Laravel 13 需 PHP 8.3+）。

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

Laravel 会通过 Composer 自动发现服务提供者和 `ShoppingCart` Facade。迁移会由服务提供者自动加载；如需自行管理迁移文件，可显式发布： 包内迁移是全新安装基线。已经执行旧版包迁移的数据库需要单独的升级方案。

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
    public function getPriceInMinorUnits(): int { /* 当前单价的最小货币单位整数 */ }
    public function getCurrency(): string { /* 币种代码 */ }
    public function getSku(): string { /* SKU 编码 */ }
    public function getName(): string { /* 展示名称 */ }
    public function getWeight(): float|int|null { /* 重量或 null */ }
    public function canBePurchased(): bool { /* 是否可售 */ }
}
```

模型还需是 Eloquent 模型并提供主键及多态类型，库存校验由结账校验器负责。请确保 `getPriceInMinorUnits()` 返回当前有效价格，并在商品下架或不可购买时让 `canBePurchased()` 返回 `false`。

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

- 初始表结构包含结账行折扣快照，并对商品和 SKU 使用 RESTRICT 外键。已被结账历史引用的商品应归档。
- 迁移不会重建历史折扣。核对历史未支付结账、订单头与行金额，不要重算已支付交易。
- 当前内置存储配置都以数据库为事实来源；cache/redis/layered 名称不再意味着模型快照缓存。
- 商品金额按行以分进行四舍五入；当前税费和运费为零。订单适配器需使用行 `discount_amount` 及折后 `total_amount`。
- `CheckoutValidating`、库存校验、订单创建、支付和外部库存系统需由宿主按自己的幂等/补偿规则接通。SQLite 测试无法证明 MySQL/PostgreSQL 行锁行为。
- `cart:migrate-data` 只检查内置驱动读取，不会复制购物车；性能基准命令只能在可丢弃测试数据上使用。

宿主自定义存储也须遵循服务的客户归属校验、加锁及金额汇总规则。

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

## 租户接入预览

宿主可绑定 `Fayne\ShoppingCart\Contracts\CartContextResolver`，从可信上下文提供租户和已认证消费者 ID。配置 `shopping-cart.tenancy.enabled` 后，缺少解析器的访问会被拒绝。可信消费者与游客的事务锁按租户和身份隔离。旧安装的购物车外键仍可能引用宿主用户表，独立消费者表接入前需要处理该外键。游客可通过宿主服务调用购物车。解析器须提供服务端会话身份，购物车按租户与会话隔离，登录时不会自动合并。设置 `shopping-cart.routes.guests=true` 可启用基于 Laravel `web` 会话和 CSRF 保护的购物车路由。解析器必须从服务端会话读取身份。内置结账仍要求认证，Commerce 下单交接待实现。

### 历史记录租户回填

在脱敏备份上先运行迁移。宿主按历史购物车和 Checkout 的 ULID 提供各自的可信租户映射，在事务中按主键和 `tenant_id IS NULL` 更新对应父记录。每次更新应核对影响行数，并在提交前检查同一租户和消费者是否有重复活动车。仅凭消费者 ID 无法推断租户。未能归属的记录保留空值，在租户模式下不可见。完成数据核对与恢复演练后再切换流量。

可在离线升级脚本中调用 `Fayne\ShoppingCart\Storage\TenantOwnershipBackfill::apply($carts, $checkouts)`，两个参数分别是以旧购物车及 Checkout ULID 为键的租户映射。遇到未知记录、已分配记录或重复活动车时整批回滚。切换期间应暂停写入，恢复时使用迁移前备份保留原始归属。

使用独立消费者表时，请在首次运行购物车迁移前设置 `CART_ENFORCE_USER_FOREIGN_KEY=false`。消费者删除后的购物车清理由宿主负责。消费者 ID 仍使用整数列。

### Commerce 报价交接

调用 `ShoppingCart::selectedItemsForQuote()` 获取当前购物车的选中行。SKU 模型须实现 `Fayne\ShoppingCart\Contracts\Cartable`。交接数据仅含 `cart_item_id`、`sku_id`、`quantity` 和 `selected`。Commerce 用 SKU 和数量调用自己的报价服务，重新校验租户、价格、币种、库存及促销。

Commerce 在同一数据库连接的订单事务内创建订单后，调用 `ShoppingCart::consumeOrderItems($cartId, $orderId, $quotedItems)`。购物车会重新核对选中状态、SKU 和数量。状态已变时回滚订单并重新报价。相同订单及报价的消费请求可重试。


Commerce 当前的 `QuoteProducts::execute()` 接受整数 `variant_id`，可按下面的方式转换投影。运费和消费者 ID 由宿主提供。

```php
$selected = ShoppingCart::selectedItemsForQuote();
$requestedLines = array_map(static fn (array $line): array => [
    'variant_id' => $line['sku_id'],
    'quantity' => $line['quantity'],
], $selected);
$quote = app(\Fayne\Commerce\Pricing\Actions\QuoteProducts::class)
    ->execute($requestedLines, $shippingAmount, customerId: $customerId);
```

请在宿主设置可信租户及消费者上下文，并在订单事务中调用 `consumeOrderItems()`。Commerce 使用自己的订单入口。

宿主可用 `ShoppingCart::transaction()` 包裹读取选中项、Commerce 报价和下单，以及消费购物项。这样事务先取得购物车归属锁，再访问订单和库存。用同一连接执行整段流程，发生冲突时让异常退出事务。

租户接入时，将 `CartCurrencyResolver` 绑定到宿主币种服务，并绑定 `CartSkuQuery` 以按可信租户约束 SKU 查询。购物车展示字段当前按两位小数币种换算，Commerce 订单金额使用最小货币单位整数。

默认 SKU 模型为 `Fayne\ShoppingCart\Models\CartSku`，数据存放在 `shopping_cart_skus`。宿主可将 `shopping-cart.models.skus.model` 改为自己的 Eloquent 模型，该模型须实现 `Cartable`。内置 HTTP API 使用 `sku` 列查找编码。租户模式还需要绑定 `CartSkuQuery`。
