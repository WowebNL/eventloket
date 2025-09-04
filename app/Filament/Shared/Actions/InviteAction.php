<?php

namespace App\Filament\Shared\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;

class InviteAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'invite';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->icon('heroicon-o-envelope');

        $this->modalSubmitActionLabel(__('shared/actions.invite.modal_submit_action_label'));

        $this->modalWidth(Width::Medium);
    }

    public function modelLabel(string|Closure|null $label): static
    {
        $this->modelLabel = $label;

        $this->label(__('shared/actions.invite.label', ['model' => $label]));

        return $this;
    }
}
