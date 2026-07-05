# Changelog

All notable changes to `lalalili/discount` are documented in this file.

## [3.0.0] - 2026-06-22

### Changed (BREAKING)

- 套件 root namespace 由 `Discount\Kernel` 改名為 `Lalalili\Discount`。

  **Migration**：host 端所有 `use Discount\Kernel\...` 匯入需改為 `use Lalalili\Discount\...`；設定檔、container binding、type-hint 與 `class-string` 參照一併更新。無公開 API 方法簽章或行為變更，僅命名空間搬遷。

- 移除 Composer 寫死的 `version` 欄位，版本改由 tag 驅動；並新增 CI / release workflow。

## [2.5.3] - 2026-06-21

### Added

- 新增促銷刷新指紋工具（promotion refresh fingerprint），供 app adapter 偵測促銷設定變更以決定是否需重新計價。

## [2.5.2] - 2026-05-11

### Added

- `CouponConditionPayload` and `CouponConditionPayloadFactory` for app adapters to build stable checkout coupon condition payloads without depending on `lalalili/laravelshoppingcart`.
- README onboarding notes for new projects integrating coupon condition payloads, pricing trace entries, and app-layer order lifecycle responsibilities.

### Compatibility

- No existing interface changes.
- Coupon condition payload generation remains an app-adapter helper; order lifecycle, persistence, and shopping cart mutations remain out of scope.

## [2.5.1] - 2026-05-11

### Added

- `PricingTraceFormatter` for app adapters to normalize trace payloads, replace duplicate trace entries by identity, trim cart-context storage, and summarize trace counts for pipeline metadata.

### Compatibility

- No DTO wire-shape changes.
- `PricingTraceFormatter` is an adapter helper; DB audit logs, coupon promotion-refresh migration, and shopping cart public API changes remain out of scope.

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
