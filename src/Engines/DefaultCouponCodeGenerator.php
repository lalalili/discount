<?php

declare(strict_types=1);

namespace Lalalili\Discount\Engines;

use DateTimeImmutable;
use DateTimeInterface;
use Lalalili\Discount\Contexts\CodeContext;
use Lalalili\Discount\Contracts\CouponCodeGeneratorInterface;
use Lalalili\Discount\Support\DiscountConfig;
use RuntimeException;

final class DefaultCouponCodeGenerator implements CouponCodeGeneratorInterface
{
    public function generate(CodeContext $context): string
    {
        $attempt = 0;
        $code = '';

        do {
            $code = $this->renderTemplate($context);
            $attempt++;
        } while ($this->couponCodeExists($context, $code) && $attempt < $context->maxAttempts);

        if ($this->couponCodeExists($context, $code)) {
            throw new RuntimeException('Unable to generate unique coupon code after multiple attempts.');
        }

        return $code;
    }

    private function renderTemplate(CodeContext $context): string
    {
        $template = $this->resolveTemplate($context->typeValue);
        $prefix = $this->resolvePrefix($context->typeValue);
        $now = $this->normalizeNow($context->now);

        $rendered = preg_replace_callback(
            '/\{([^{}]+)\}/',
            function (array $matches) use ($context, $prefix, $now): string {
                $token = (string) $matches[1];

                return $this->resolveToken($token, $context, $prefix, $now);
            },
            $template
        );

        if (! is_string($rendered) || $rendered === '') {
            return $prefix . $this->randomCode(11) . $this->userCoordinateOr($context->userId, 'AA');
        }

        return strtoupper($rendered);
    }

    private function resolveTemplate(int $typeValue): string
    {
        $templates = DiscountConfig::get('coupon.code.templates', []);

        if (! is_array($templates)) {
            return '{prefix}{random:11}{user_coord_or:AA}';
        }

        if (array_key_exists($typeValue, $templates) && is_string($templates[$typeValue])) {
            return $templates[$typeValue];
        }

        $stringType = (string) $typeValue;
        if (array_key_exists($stringType, $templates) && is_string($templates[$stringType])) {
            return $templates[$stringType];
        }

        $defaultTemplate = $templates['default'] ?? '{prefix}{random:11}{user_coord_or:AA}';

        return is_string($defaultTemplate) ? $defaultTemplate : '{prefix}{random:11}{user_coord_or:AA}';
    }

    private function resolvePrefix(int $typeValue): string
    {
        $prefixes = DiscountConfig::get('coupon.code.prefixes', []);

        if (! is_array($prefixes)) {
            return '';
        }

        if (array_key_exists($typeValue, $prefixes) && is_string($prefixes[$typeValue])) {
            return $prefixes[$typeValue];
        }

        $stringType = (string) $typeValue;
        if (array_key_exists($stringType, $prefixes) && is_string($prefixes[$stringType])) {
            return $prefixes[$stringType];
        }

        $defaultPrefix = $prefixes['default'] ?? '';

        return is_string($defaultPrefix) ? $defaultPrefix : '';
    }

    private function resolveToken(string $token, CodeContext $context, string $prefix, DateTimeInterface $now): string
    {
        return match ($token) {
            'prefix'      => $prefix,
            'yy'          => $now->format('y'),
            'user_id'     => $context->userId !== null ? (string) $context->userId : '',
            'user_coord'  => $this->userCoordinate($context->userId),
            'count'       => (string) $context->count,
            'count_alpha' => $this->countAlphabet($context->count),
            default       => $this->resolveDynamicToken($token, $context),
        };
    }

    private function resolveDynamicToken(string $token, CodeContext $context): string
    {
        if (str_starts_with($token, 'random:')) {
            $length = (int) substr($token, 7);

            return $this->randomCode($length > 0 ? $length : 11);
        }

        if (str_starts_with($token, 'user_coord_or:')) {
            $fallback = substr($token, 14);

            return $this->userCoordinateOr($context->userId, $fallback !== '' ? $fallback : 'AA');
        }

        return '';
    }

    private function countAlphabet(int $count): string
    {
        $alphabet = range('A', 'Z');

        return $alphabet[$count % 26];
    }

    private function userCoordinate(?int $userId): string
    {
        if ($userId === null) {
            return '';
        }

        $alphabet = range('A', 'Z');
        $normalized = abs($userId);

        return $alphabet[(int) floor(($normalized / 26)) % 26]
            . $alphabet[$normalized % 26];
    }

    private function userCoordinateOr(?int $userId, string $fallback): string
    {
        $coordinate = $this->userCoordinate($userId);

        return $coordinate !== '' ? $coordinate : strtoupper($fallback);
    }

    private function randomCode(int $length): string
    {
        if ($length < 1) {
            throw new RuntimeException('Length must be greater than zero.');
        }

        $bytesLength = max(1, (int) ceil($length / 2));

        return strtoupper(substr(bin2hex(random_bytes($bytesLength)), 0, $length));
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
