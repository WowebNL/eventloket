<?php

namespace App\Filament\Shared\Infolists\Components;

use Filament\Infolists\Components\Entry;
use Illuminate\Contracts\Support\Htmlable;

class ThreadMessagesEntry extends Entry
{
    protected string $view = 'filament.infolists.components.thread-messages-entry';

    public function getLabel(): string|Htmlable|null
    {
        return 'Berichten';
    }

    public function getColumnSpan(int|string|null $breakpoint = null): array|int|string|null
    {
        return 'full';
    }
}
