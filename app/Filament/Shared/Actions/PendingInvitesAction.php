<?php

namespace App\Filament\Shared\Actions;

use Filament\Actions\Action;

class PendingInvitesAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'pending-invites';
    }

    protected string $widget;

    protected $widgetRecord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('shared/actions.pending_invites.label'));

        $this->color('gray');

        $this->slideOver();

        $this->modalSubmitAction(false);

        $this->modalContent(fn () => view('filament.components.modal-widget', [
            'widget' => $this->getWidget(),
            'record' => $this->getWidgetRecord(),
        ]));
    }

    public function widget($widget): static
    {
        $this->widget = $widget;

        return $this;
    }

    public function getWidget(): string
    {
        return $this->widget;
    }

    public function widgetRecord($widget): static
    {
        $this->widgetRecord = $widget;

        return $this;
    }

    public function getWidgetRecord()
    {
        return $this->widgetRecord;
    }
}
