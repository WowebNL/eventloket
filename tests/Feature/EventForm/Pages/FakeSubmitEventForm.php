<?php

namespace Tests\Feature\EventForm\Pages;

use App\EventForm\State\FormState;

class FakeSubmitEventForm
{
    public int $aantalAanroepen = 0;

    /** The state of the last call, so a test can assert on what was handed over. */
    public ?FormState $ontvangenState = null;

    public function __construct(
        public ?\Throwable $gooitException = null,
        public mixed $resultaat = null,
    ) {}

    public function execute(mixed ...$args): mixed
    {
        $this->aantalAanroepen++;
        $this->ontvangenState = ($args[0] ?? null) instanceof FormState ? $args[0] : null;

        if ($this->gooitException) {
            throw $this->gooitException;
        }

        return $this->resultaat;
    }
}
