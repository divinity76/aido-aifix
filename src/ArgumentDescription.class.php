<?php

declare(strict_types=1);

#[Attribute(Attribute::TARGET_PARAMETER)]
class ArgumentDescription
{
    public function __construct(
        public string $description,
        public mixed $example = null,
    ) {}
}
