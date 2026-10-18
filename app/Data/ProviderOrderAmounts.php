<?php

namespace App\Data;

final readonly class ProviderOrderAmounts
{
    public function __construct(
        public ?int $unit,
        public ?int $total,
        public ?int $gross,
        public ?int $commission,
        public ?int $net,
    ) {}
}
