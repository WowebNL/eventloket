<?php

namespace App\Notifications\Contracts;

use Illuminate\Contracts\Support\Htmlable;

interface HasLabel
{
    public static function getLabel(): string|Htmlable|null;
}
