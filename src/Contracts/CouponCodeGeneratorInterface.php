<?php

declare(strict_types=1);

namespace Discount\Kernel\Contracts;

use Discount\Kernel\Contexts\CodeContext;

interface CouponCodeGeneratorInterface
{
    public function generate(CodeContext $context): string;
}
