<?php

declare(strict_types=1);

namespace Lalalili\Discount\Contracts;

use Lalalili\Discount\Contexts\CodeContext;

interface CouponCodeGeneratorInterface
{
    public function generate(CodeContext $context): string;
}
