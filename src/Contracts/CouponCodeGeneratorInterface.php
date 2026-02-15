<?php

declare(strict_types=1);

namespace Cptw\DiscountKernel\Contracts;

use Cptw\DiscountKernel\Contexts\CodeContext;

interface CouponCodeGeneratorInterface
{
    public function generate(CodeContext $context): string;
}
