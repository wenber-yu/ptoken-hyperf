<?php

declare(strict_types=1);

namespace Wenbo\PToken\Hyperf\Annotations;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class PTokenAuth extends AbstractAnnotation
{
    public function __construct(
        public readonly bool $exclude = false,
    ) {}
}
