# Changelog

All notable changes to `lalalili/discount` are documented in this file.

## [2.1.0] - 2026-02-18

### Added

- Coupon mode support with `CouponAmountMode` (`auto`, `fixed`, `rate`).
- Coupon kind separation with `CouponKind` (`member`, `promotion`).
- `CouponDiscountEngineInterface` and default implementation.
- `CouponApplicationServiceInterface` and default implementation.
- `CouponRepositoryInterface` adapter contract for app-layer data access.
- `CouponData`, `CouponDiscountResult`, and `CouponValidationResult` DTOs.
- Stable `reasonCode` outputs for coupon validation/discount workflows.

### Changed

- `CouponContext` now supports `amountMode` with backward-compatible auto inference.
- README updated with external-project onboarding, stable reason code contract, and release references.

### Compatibility

- Non-breaking minor release from `2.0.x`.
- Existing fixed-amount coupon data remains compatible.
- Missing `amountMode` defaults to `auto` inference.
