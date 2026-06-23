<?php

namespace Tests\Feature\EventForm\Pages;

class FakeSubmitEventForm
{
    public int $aantalAanroepen = 0;

    public function __construct(
        public ?\Throwable $gooitException = null,
        public mixed $resultaat = null,
    ) {}

    public function execute(mixed ...$args): mixed
    {
        $this->aantalAanroepen++;
        if ($this->gooitException) {
            throw $this->gooitException;
        }

        return $this->resultaat;
    }
}
