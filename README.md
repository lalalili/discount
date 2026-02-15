# Discount Kernel

Config-driven discount and coupon kernel for Laravel projects.

## Scope

This package provides reusable promotion engines and DTO/context objects:

- Product price calculation (`DiscountEngineInterface`)
- Cart promotion condition generation (`CartPromotionEngineInterface`)
- Coupon eligibility validation (`CouponEligibilityInterface`)
- Coupon code generation (`CouponCodeGeneratorInterface`)

Out of scope (kept in application adapter layer):

- Eloquent queries and persistence
- Session/Cookie/Auth orchestration
- Admin UI
- Domain-specific flow control

## Requirements

- PHP `^8.4`
- Laravel `^12.0`

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
    "lalalili/discount": "^2.0"
  }
}
```

Then run:

```bash
composer update lalalili/discount
```

### Option B: Private VCS repository

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
    "lalalili/discount": "^2.0"
  }
}
```

Then run:

```bash
composer update lalalili/discount
```

## Laravel setup

Publish default config (optional):

```bash
php artisan vendor:publish --tag=discount-config
```

The package reads `config/discount.php` for all rule mappings.

## Config-driven model

`config/discount.php` sections:

- `event.type_role_map`
- `event.priorities`
- `coupon.scope_map`
- `coupon.code.prefixes`
- `coupon.code.templates`
- `coupon.code.tokens`
- `cart.roles`
- `cart.gift_resolver`

With this design, another project only needs to change config values (type mapping, scope mapping, code template, cart role mapping) without rewriting engine logic.

## Minimal usage

### Product pricing

```php
use Discount\Kernel\Contexts\ProductContext;
use Discount\Kernel\Contexts\PromotionContext;
use Discount\Kernel\Contexts\PromotionSet;
use Discount\Kernel\Engines\DefaultDiscountEngine;

$engine = new DefaultDiscountEngine();

$result = $engine->price(
    new ProductContext(1000),
    new PromotionSet([
        new PromotionContext(type: 1, sort: 1, discountAmount: 0.8),
    ])
);

$price = $result->price;
```

### Coupon eligibility

```php
use Discount\Kernel\Contexts\CartContext;
use Discount\Kernel\Contexts\CouponContext;
use Discount\Kernel\Contexts\UserContext;
use Discount\Kernel\Engines\DefaultCouponEligibilityEngine;

$engine = new DefaultCouponEligibilityEngine();

$result = $engine->validate(
    new CouponContext(scope: 0, triggerAmount: 500, amount: 100),
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
    new UserContext(123)
);

$isEligible = $result->eligible;
```

### Coupon code generation

```php
use Discount\Kernel\Contexts\CodeContext;
use Discount\Kernel\Engines\DefaultCouponCodeGenerator;

$engine = new DefaultCouponCodeGenerator();

$code = $engine->generate(new CodeContext(
    typeValue: 12,
    userId: 123,
    count: 5,
    existsChecker: fn (string $candidate) => false,
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
        new PromotionContext(type: 7, eventId: 201, name: 'Unique discount', discountAmount: 0.8),
    ])
);

$adjustments = $result->adjustments;
```

## Local quality checks

Inside package directory:

```bash
composer install
composer analyse
```

## Quick onboarding for another project

1. Install `lalalili/discount`.
2. Publish or create `config/discount.php`.
3. Map local event/coupon type values in config.
4. Set `cart.gift_resolver` to your app resolver class.
5. Bind or use default engines in your service layer.
6. Run project smoke tests for pricing/coupon/cart paths.
