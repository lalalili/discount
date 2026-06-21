## Discount Kernel

`lalalili/discount` is a host-agnostic discount and coupon kernel. Keep Eloquent models, table names, enum mappings, and stock checks in the host app.

### Extension Points

- Bind `Discount\Kernel\Contracts\CouponRepositoryInterface` in the host app to adapt coupon storage.
- Implement `Discount\Kernel\Contracts\PromotionContextMapperInterface` in the host app to map campaign/event models into `PromotionContext`.
- Use `Discount\Kernel\Support\PromotionRefreshFingerprint` to compute promotion refresh signatures; pass only host attributes that should affect pricing.
- Use `CartPromotionRefreshServiceInterface` for cart promotion refreshes and apply returned item/cart adjustments in the host cart layer.

### Boundaries

- Do not import host `Product`, `Event`, `Coupon`, or `Order` classes into this package.
- Do not encode host enum values in package code; configure them in `config/discount.php`.
