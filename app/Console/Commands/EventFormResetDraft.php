<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\EventForm\Persistence\Draft;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Wist alle concept-opslag voor een opgegeven user — gebruikt door de
 * Playwright-walkthrough om bij elke run met een schoon leeg formulier
 * te beginnen, ongeacht wat de user eerder zelf heeft ingevuld in
 * dezelfde DB-sessie.
 */
class EventFormResetDraft extends Command
{
    protected $signature = 'eventform:reset-draft {--email= : E-mailadres van de user wiens drafts gewist worden}';

    protected $description = 'Wist concept-opslag (draft) voor een gebruiker, typisch voor Playwright-walkthroughs';

    public function handle(): int
    {
        $email = (string) $this->option('email');
        if ($email === '') {
            $this->error('Geef --email=<adres> op.');

            return self::INVALID;
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->warn("Geen user gevonden met e-mail '{$email}' — niks te wissen.");

            return self::SUCCESS;
        }

        $count = Draft::where('user_id', $user->id)->count();
        Draft::where('user_id', $user->id)->delete();

        $this->info("Draft(s) gewist voor {$email}: {$count}");

        return self::SUCCESS;
    }
}
