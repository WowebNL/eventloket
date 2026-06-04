<?php

declare(strict_types=1);

namespace App\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Generates an avatar as an inline SVG data URI using the user's initials on a
 * black background. No external service is contacted, which keeps user names
 * inside the application boundary.
 */
class LocalSvgAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $name = Filament::getNameForDefaultAvatar($record);
        $initials = $this->extractInitials($name);

        return 'data:image/svg+xml;base64,'.base64_encode($this->buildSvg($initials));
    }

    private function extractInitials(string $name): string
    {
        return str($name)
            ->trim()
            ->explode(' ')
            ->filter(fn (string $part): bool => filled($part))
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->join('');
    }

    private function buildSvg(string $initials): string
    {
        $escaped = htmlspecialchars($initials, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                <rect width="100" height="100" fill="#000000"/>
                <text x="50" y="50" dominant-baseline="central" text-anchor="middle"
                      font-family="sans-serif" font-size="40" font-weight="600" fill="#ffffff">
                    {$escaped}
                </text>
            </svg>
            SVG;
    }
}
