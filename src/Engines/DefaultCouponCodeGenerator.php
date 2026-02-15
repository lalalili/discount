<?php

declare(strict_types=1);

namespace Cptw\DiscountKernel\Engines;

use Cptw\DiscountKernel\Contexts\CodeContext;
use Cptw\DiscountKernel\Contracts\CouponCodeGeneratorInterface;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;

final class DefaultCouponCodeGenerator implements CouponCodeGeneratorInterface
{
    public function generate(CodeContext $context): string
    {
        if ($context->typeValue === 12) {
            return $this->buildBirthdayCouponCode($context);
        }

        $attempts = 0;
        $code = '';

        do {
            $code = $this->buildGenericCouponCode($context->typeValue, $context->userId);
            $attempts++;
        } while ($this->couponCodeExists($context, $code) && $attempts < $context->maxAttempts);

        if ($this->couponCodeExists($context, $code)) {
            throw new RuntimeException('Unable to generate unique coupon code after multiple attempts.');
        }

        return $code;
    }

    private function buildBirthdayCouponCode(CodeContext $context): string
    {
        if ($context->userId === null) {
            throw new RuntimeException('User id is required for generating coupon number.');
        }

        $now = $this->normalizeNow($context->now);
        $alphabet = range('A', 'Z');

        return $this->resolvePrefix($context->typeValue)
            . $now->format('y')
            . $this->userCoordinate($context->userId)
            . $context->userId
            . $alphabet[$context->count % 26]
            . $context->count;
    }

    private function buildGenericCouponCode(int $typeValue, ?int $userId): string
    {
        $suffix = $userId !== null
            ? $this->userCoordinate($userId)
            : 'AA';

        return $this->resolvePrefix($typeValue) . $this->uniqidReal(11) . $suffix;
    }

    private function resolvePrefix(int $typeValue): string
    {
        return match ($typeValue) {
            1  => 'MC',
            2  => 'PC',
            11 => 'RC',
            12 => 'BC',
            13, 14 => 'LC',
            default => '',
        };
    }

    private function userCoordinate(int $userId): string
    {
        $alphabet = range('A', 'Z');
        $normalized = abs($userId);

        return $alphabet[(int) floor(($normalized / 26)) % 26]
            . $alphabet[$normalized % 26];
    }

    private function uniqidReal(int $length = 13): string
    {
        if ($length < 1) {
            throw new RuntimeException('Length must be greater than zero.');
        }

        $bytesLength = max(1, (int) ceil($length / 2));
        $bytes = random_bytes($bytesLength);

        return strtoupper(substr(bin2hex($bytes), 0, $length));
    }

    private function couponCodeExists(CodeContext $context, string $code): bool
    {
        if (! is_callable($context->existsChecker)) {
            return false;
        }

        return (bool) call_user_func($context->existsChecker, $code);
    }

    private function normalizeNow(DateTimeInterface|string|null $now): DateTimeInterface
    {
        if ($now instanceof DateTimeInterface) {
            return $now;
        }

        if (is_string($now) && $now !== '') {
            return new DateTimeImmutable($now);
        }

        return new DateTimeImmutable();
    }
}
