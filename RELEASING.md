# Releasing Guide

This package is developed in monorepo (`packages/discount`) and mirrored to:

- `https://github.com/lalalili/discount`

Use this SOP for each release.

## 1) Prepare in monorepo

1. Implement changes under `packages/discount`.
2. Run related tests in the main project.
3. Commit with a clear Conventional Commit message.

## 2) Sync to package repository

```bash
TMP_DIR=/tmp/discount-sync
rm -rf "$TMP_DIR"
git clone https://github.com/lalalili/discount.git "$TMP_DIR"
rsync -a --delete --exclude='.git' packages/discount/ "$TMP_DIR"/
cd "$TMP_DIR"
```

Review and commit:

```bash
git status --short
git add -A
git commit -m "feat(discount): ..."
```

## 3) Tag and push

For minor release:

```bash
git tag v2.1.0
git push origin main --tags
```

For patch release:

```bash
git tag v2.1.1
git push origin main --tags
```

## 4) Release note checklist

Create GitHub release note with:

1. New capabilities.
2. Breaking changes (explicitly state none if no breaking change).
3. Upgrade steps.
4. Test coverage list.

## 5) Consumer project update

- Path repository projects: update is immediate from local source.
- External projects:

```bash
composer update lalalili/discount
```

Recommended requirement:

```json
"lalalili/discount": "^2.5"
```

## 6) Current rollout notes

- Publish the existing diagnostics baseline as `2.4.x` first: `PromotionDecision`, normalized skipped reasons, `promotion_refresh_signature`, pipeline metadata, and app input factories.
- Release `2.5.x` after `2.4.x` is stable. `2.5.x` adds `PricingTrace` / `PricingTraceEntry` as public DTOs and keeps `CouponValidationResult::$pricingTrace` optional for backward compatibility.
- Release `2.5.1` after the formatter helper commit is pushed. Tag only the app-adapter helper, README/CHANGELOG notes, and focused formatter tests; do not include local `vendor/` or generated `composer.lock` artifacts.
- Release `2.5.2` after the coupon condition payload helper commit is pushed. Tag only the payload DTO/factory, README/CHANGELOG notes, and focused payload tests; do not include local `vendor/` or generated `composer.lock` artifacts.
- Consumer order for `2.5.1`: tag `lalalili/discount`, update `aitehub` lock files to the tag, then confirm `cptw/packages/discount` remains byte-for-byte aligned with the tag while cptw uses the path repository.
- Consumer order: update `cptw` and `aitehub` lock files/references to `2.4.x`, then update to `2.5.x` after app adapters are deployed.
- Rollback: revert consumer constraint/lock to the last `2.4.x` tag. Coupon behavior remains in app adapters, so rollback does not require a DB migration.
- Explicitly out of scope: DB audit log, moving coupon into promotion refresh, and changing `lalalili/laravelshoppingcart` public APIs.
