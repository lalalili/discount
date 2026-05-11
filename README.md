# lalalili/discount

Config-driven discount and coupon kernel for Laravel projects.

## Scope

This package provides reusable promotion engines, coupon application orchestration, and DTO/context objects:

- Product price calculation (`DiscountEngineInterface`)
- Cart promotion condition generation (`CartPromotionEngineInterface`)
- Coupon eligibility validation (`CouponEligibilityInterface`)
- Coupon code generation (`CouponCodeGeneratorInterface`)
- Coupon discount calculation (`CouponDiscountEngineInterface`)
- Coupon validation orchestration (`CouponApplicationServiceInterface`)

Out of scope (kept in application adapter layer):

- Eloquent queries and persistence implementation details
- Session/Cookie/Auth orchestration
- Admin UI
- Domain-specific flow control (events, jobs, notification orchestration)

`CouponApplicationServiceInterface` is provided by this package, but coupon data lookup / usage checks / inventory updates must be implemented by your app via `CouponRepositoryInterface`.

## Requirements

- PHP `^8.4`
- Laravel `^12.0`

## Public Interfaces

The package API is stable at interface level:

- `DiscountEngineInterface::price(ProductContext $product, PromotionSet $promotions): PriceResult`
- `CartPromotionEngineInterface::apply(CartContext $cart, PromotionSet $promotions): CartAdjustmentResult`
- `CouponEligibilityInterface::validate(CouponContext $coupon, CartContext $cart, UserContext $user): EligibilityResult`
- `CouponCodeGeneratorInterface::generate(CodeContext $context): string`
- `CouponDiscountEngineInterface::discount(float $orderTotal, CouponContext $coupon): CouponDiscountResult`
- `CouponApplicationServiceInterface::validate(CouponKind $kind, string $code, CartContext $cart, UserContext $user): CouponValidationResult`

## Core Types

### Enums

- `CouponAmountMode`: `auto | fixed | rate`
- `CouponKind`: `member | promotion`

### DTOs

- `CouponData`
  - `code`, `kind`, `scope`, `triggerAmount`, `amount`, `amountMode`, `status`, `limitQty`, `leftQty`, `userId`, `attributes`
- `CouponDiscountResult`
  - `valid`, `discount`, `finalTotal`, `reason`, `reasonCode`
- `CouponValidationResult`
  - `eligible`, `coupon`, `discount`, `finalTotal`, `reason`, `reasonCode`, `pricingTrace`
- `PricingTrace`
  - list wrapper for `PricingTraceEntry`
- `PricingTraceEntry`
  - stable array fields: `stage`, `source`, `status`, `scope`, `kind`, `code`, `id`, `amount`, `final_total`, `reason_code`, `reason`, `metadata`

### PricingTrace Contract

`PricingTrace` is an additive public DTO introduced for discount `2.5.x`. It is an in-memory checkout and lifecycle trace, not a persistence/audit-log feature.

Enum values currently emitted by the package/app adapters:

- `source`: `promotion`, `coupon`
- `status`: `applied`, `skipped`, `failed`, `issued`, `restored`
- `stage`: `promotion_refresh`, `coupon_validate`, `coupon_apply`, `coupon_issue`, `coupon_redeem`, `coupon_inventory`, `coupon_restore`

`CartPromotionRefreshResult::$pricingTrace` is normalized from `promotionDecisions` with `stage=promotion_refresh` and `source=promotion`.

`CouponValidationResult::$pricingTrace` is optional and defaults to `null` for backward compatibility. `DefaultCouponApplicationService` populates a `coupon_validate` entry for eligible and failed validation outcomes. `CouponDiscountResult` intentionally does not carry trace data; application services combine discount and validation trace when they apply a coupon.

`Discount\Kernel\Support\PricingTraceFormatter` provides stable helpers for app adapters:

- `normalize()` converts `PricingTrace`, `PricingTraceEntry`, single-entry arrays, and entry lists into a list of arrays.
- `mergeLatestByIdentity()` replaces duplicate entries by `stage/source/kind/id-or-code` and trims the list for cart context storage.
- `summarize()` returns compact counts by stage, source, status, and reason code for pipeline metadata.

Release note for `2.5.1`: the formatter is a public app-adapter helper. It does not change the `PricingTrace` / `PricingTraceEntry` array shape and does not add persistence. Consumer apps should update lock files after the tag, then remove long-lived duplicate merge/summary logic once the helper is available from `vendor/`.

Application cart adapters should store checkout coupon trace under cart context metadata such as:

```php
$cart->withContext($cart->getContext()->with('pricing_trace', [
    'coupon' => $validationResult->pricingTrace?->toArray() ?? [],
]));
```

Coupon cart conditions should include the applied entry in condition attributes:

```php
[
    'attributes' => [
        'pricing_trace_entry' => $entry->toArray(),
    ],
]
```

Out of scope for this stage: DB audit logs, moving coupon processing into promotion refresh, and changing the public API of `lalalili/laravelshoppingcart`.

### Context Changes

`CouponContext` now supports:

- `scope`
- `triggerAmount`
- `amount`
- `amountMode` (`CouponAmountMode|string|null`, optional, default `auto`)

## Install

### Option A: Local path repository

In application `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/discount",
      "options": {
        "symlink": true
      }
    }
  ],
  "require": {
    "lalalili/discount": "^2.1"
  }
}
```

Then run:

```bash
composer update lalalili/discount
```

### Option B: Private VCS repository (recommended for other projects)

In application `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:lalalili/discount.git"
    }
  ],
  "require": {
    "lalalili/discount": "^2.1"
  }
}
```

Then run:

```bash
composer update lalalili/discount
```

## Laravel Setup

Publish default config (optional):

```bash
php artisan vendor:publish --tag=discount-config
```

The package reads mappings from `config/discount.php`.

### Required Binding for Coupon Application Service

To use `CouponApplicationServiceInterface`, your app must bind `CouponRepositoryInterface`:

```php
use Discount\Kernel\Contracts\CouponRepositoryInterface;
use App\Services\Events\Support\EloquentCouponRepository;

$this->app->singleton(CouponRepositoryInterface::class, EloquentCouponRepository::class);
```

## Config-Driven Model

`config/discount.php` sections:

- `event.type_role_map`
- `event.priorities`
- `coupon.scope_map`
- `coupon.code.prefixes`
- `coupon.code.templates`
- `coupon.code.tokens`
- `cart.roles`
- `cart.gift_resolver`

With this design, another project only needs config changes (type mapping, scope mapping, code template, cart role mapping) without rewriting engine logic.

## Minimal Usage

### Product pricing (79折)

```php
use Discount\Kernel\Contexts\ProductContext;
use Discount\Kernel\Contexts\PromotionContext;
use Discount\Kernel\Contexts\PromotionSet;
use Discount\Kernel\Engines\DefaultDiscountEngine;

$engine = new DefaultDiscountEngine();

$result = $engine->price(
    new ProductContext(1000),
    new PromotionSet([
        new PromotionContext(type: 1, sort: 1, discountAmount: 0.79),
    ])
);

$price = $result->price; // 790
```

### Coupon discount calculation (fixed + rate)

```php
use Discount\Kernel\Contexts\CouponContext;
use Discount\Kernel\Engines\DefaultCouponDiscountEngine;
use Discount\Kernel\Enums\CouponAmountMode;

$engine = new DefaultCouponDiscountEngine();

$fixed = $engine->discount(
    1000,
    new CouponContext(scope: 0, triggerAmount: null, amount: 100, amountMode: CouponAmountMode::Fixed)
);

$rate = $engine->discount(
    1000,
    new CouponContext(scope: 0, triggerAmount: null, amount: 0.9, amountMode: CouponAmountMode::Rate)
);
```

### Coupon application service (member / promotion)

```php
use Discount\Kernel\Contexts\CartContext;
use Discount\Kernel\Contexts\UserContext;
use Discount\Kernel\Contracts\CouponApplicationServiceInterface;
use Discount\Kernel\Enums\CouponKind;

$service = app(CouponApplicationServiceInterface::class);

$result = $service->validate(
    CouponKind::Promotion,
    'PROMO123',
    new CartContext(
        orderTotal: 1200,
        allAmount: 1200,
        bookAmount: 1200,
        ebookAmount: 0,
        specificProductsAmount: 1200,
        hasBook: true,
        hasEbook: false,
        hasSpecificProducts: true,
    ),
    new UserContext(123),
);

$isEligible = $result->eligible;
$discount = $result->discount;
```

### Coupon code generation

```php
use Discount\Kernel\Contexts\CodeContext;
use Discount\Kernel\Engines\DefaultCouponCodeGenerator;

$engine = new DefaultCouponCodeGenerator();

$code = $engine->generate(new CodeContext(
    typeValue: 13,
    userId: 123,
    count: 1,
    existsChecker: fn (string $candidate): bool => false,
));
```

### Cart adjustment generation

```php
use Discount\Kernel\Contexts\CartContext;
use Discount\Kernel\Contexts\PromotionContext;
use Discount\Kernel\Contexts\PromotionSet;
use Discount\Kernel\Engines\DefaultCartPromotionEngine;

$engine = new DefaultCartPromotionEngine();

$result = $engine->apply(
    new CartContext(
        orderTotal: 0,
        allAmount: 0,
        bookAmount: 0,
        ebookAmount: 0,
        specificProductsAmount: 0,
        hasBook: false,
        hasEbook: false,
        hasSpecificProducts: false,
        productId: 1001,
        productPrice: 1200,
        selectedGroupRebateEventId: null,
    ),
    new PromotionSet([
        new PromotionContext(type: 1, eventId: 201, name: 'Single discount', discountAmount: 0.8),
    ])
);

$adjustments = $result->adjustments;
```

## Stable Reason Codes

`CouponValidationResult::reasonCode` and `CouponDiscountResult::reasonCode` are stable public contract fields.

- `COUPON_NOT_FOUND`
- `AUTH_REQUIRED`
- `COUPON_ALREADY_USED`
- `COUPON_OUT_OF_STOCK`
- `DISCOUNT_INVALID`
- `ELIGIBILITY_FAILED`

## Cart Promotion Integration Contract

`CartPromotionRefreshResult` exposes both the legacy arrays (`appliedPromotions`, `skippedPromotions`) and the normalized `promotionDecisions` array. New integrations should read `promotionDecisions`; existing callers can continue reading the legacy arrays.

Stable skipped promotion reasons:

- `threshold_not_met`
- `exclusive_conflict`
- `gift_unresolved`
- `gift_out_of_stock`
- `not_selected`

Responsibilities:

- `lalalili/discount` computes item adjustments, cart rebate/gift adjustments, selected type 6 group rebates, totals, and promotion decisions. It does not mutate a shopping cart instance.
- The application adapter loads local Product/Event/Gift models, builds `CartPromotionRefreshInput`, resolves gift stock/fulfillment behavior, and writes the returned conditions/items back to the cart.
- `lalalili/laravelshoppingcart` triggers refresh through `before_totals` pipelines and stores observability data in `CartPipelineResult::metadata` and `snapshot()['pipelines']`.
- Apps may skip refresh when a locally computed `promotion_refresh_signature` is unchanged, but checkout entry should force one final refresh before payment.

Recommended pipeline metadata:

- `promotion_version`
- `refresh_reason`
- `duration_ms`
- `promotion_refresh_signature`
- `applied_count`
- `skipped_count`

## Coupon Flows (App Adapter Layer)

Keep these flows in your application and call kernel engines:

- `PROMOTION (2)`: admin-created promo coupon flow
- `REGISTER (11)`: issue after user registration
- `BIRTHDAY (12)`: monthly birthday scheduler
- `FIRST_ORDER (13)`: issue after first completed order (once per lifetime)

Recommended `FIRST_ORDER (13)` dedup rule:

- Do not issue if existing coupon for same user with `created_by=CouponForFirstOrder` and usable/used status already exists.

Recommended deprecated runtime guard:

- If coupon type in `[21,22]`, skip and log warning (for example `legacy_coupon_type_detected`).

## Versioning Note

Current package `composer.json` version is `2.4.0`.
This release is a minor update from `2.3.x` and does not introduce breaking API changes.
See `CHANGELOG.md` for release notes and `RELEASING.md` for sync/tag SOP.

## Local Quality Checks

Inside package directory:

```bash
composer install
composer analyse
```

## Quick Onboarding for Another Project

1. Install `lalalili/discount` via VCS + tag.
2. Publish or create `config/discount.php`.
3. Map local event/coupon enum values in config.
4. Bind `CouponRepositoryInterface` to your adapter implementation.
5. Set `cart.gift_resolver` (or `null` if gift not used).
6. Keep issuing flows in app adapters (`register`, `birthday`, `first order`).
7. Add runtime skip policy for deprecated coupon types.
8. Run smoke tests for checkout pricing and coupon issuance.
