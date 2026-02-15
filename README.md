# CPTW Discount Kernel

Internal pure-rule kernel for discount and coupon calculations across CPTW projects.

## Scope

This package provides only rule engines and DTO/Context objects:

- Product price calculation (`DiscountEngineInterface`)
- Coupon eligibility validation (`CouponEligibilityInterface`)
- Coupon code generation (`CouponCodeGeneratorInterface`)
- Cart promotion engine contract (`CartPromotionEngineInterface`)

Out of scope (must stay in app adapters):

- Eloquent queries and DB writes
- Session/Cookie/Auth state
- Admin UI (Filament)
- Queue/job orchestration and external APIs

## Requirements

- PHP `^8.4`

## Version compatibility

- `1.x` targets Laravel `12.x` application adapters.
- Runtime logic is framework-agnostic; Laravel binding is handled by host projects.

## Install

### Option A: Path repository (same monorepo)

In application `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/cptw-discount-kernel",
      "options": {
        "symlink": true
      }
    }
  ],
  "require": {
    "cptw/discount-kernel": "^1.0"
  }
}
```

Then:

```bash
composer update cptw/discount-kernel
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
    "cptw/discount-kernel": "^1.0"
  }
}
```

Then:

```bash
composer update cptw/discount-kernel
```

## Laravel binding example

```php
use Cptw\DiscountKernel\Contracts\DiscountEngineInterface;
use Cptw\DiscountKernel\Contracts\CouponEligibilityInterface;
use Cptw\DiscountKernel\Contracts\CouponCodeGeneratorInterface;
use Cptw\DiscountKernel\Engines\DefaultDiscountEngine;
use Cptw\DiscountKernel\Engines\DefaultCouponEligibilityEngine;
use Cptw\DiscountKernel\Engines\DefaultCouponCodeGenerator;

$this->app->singleton(DiscountEngineInterface::class, DefaultDiscountEngine::class);
$this->app->singleton(CouponEligibilityInterface::class, DefaultCouponEligibilityEngine::class);
$this->app->singleton(CouponCodeGeneratorInterface::class, DefaultCouponCodeGenerator::class);
```

## Quick usage

### Price calculation

```php
use Cptw\DiscountKernel\Contexts\ProductContext;
use Cptw\DiscountKernel\Contexts\PromotionContext;
use Cptw\DiscountKernel\Contexts\PromotionSet;
use Cptw\DiscountKernel\Engines\DefaultDiscountEngine;

$engine = new DefaultDiscountEngine();

$result = $engine->price(
    new ProductContext(1000),
    new PromotionSet([
        new PromotionContext(type: 1, sort: 1, discountAmount: 0.8),
    ])
);

$price = $result->price; // 800
```

### Coupon eligibility

```php
use Cptw\DiscountKernel\Contexts\CartContext;
use Cptw\DiscountKernel\Contexts\CouponContext;
use Cptw\DiscountKernel\Contexts\UserContext;
use Cptw\DiscountKernel\Engines\DefaultCouponEligibilityEngine;

$engine = new DefaultCouponEligibilityEngine();

$eligibility = $engine->validate(
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

$isEligible = $eligibility->eligible;
```

### Coupon code generation

```php
use Cptw\DiscountKernel\Contexts\CodeContext;
use Cptw\DiscountKernel\Engines\DefaultCouponCodeGenerator;

$engine = new DefaultCouponCodeGenerator();

$code = $engine->generate(new CodeContext(
    typeValue: 1,
    userId: 123,
    existsChecker: fn (string $candidate) => false,
));
```

## Static analysis (Larastan level 8)

Inside package directory:

```bash
composer install
composer analyse
```

## Release process

1. Update package code and tests in source repository.
2. Run static analysis (`composer analyse`).
3. Sync package directory to `git@github.com:lalalili/discount.git`.
4. Tag version if needed (`v1.x.y`) and update consuming project constraints.
