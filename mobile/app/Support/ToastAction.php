<?php

namespace App\Support;

class ToastAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $label,
        public string $id,
        public array $data = [],
    ) {}
}
