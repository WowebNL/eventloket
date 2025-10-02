<?php

namespace App\Livewire\Thread;

use App\Models\Zaak;
use App\ValueObjects\ZGW\Informatieobject;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;
use Woweb\Openzaak\Openzaak;

class Document extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Zaak $zaak;

    public string $documentUrl;

    public int $versie;

    public int $latestVersion;

    private Informatieobject $document;

    public function mount(): void
    {
        $this->document = $this->zaak->documenten->firstWhere('url', $this->documentUrl);
        $this->latestVersion = (int) $this->document->versie;

        if ((int) $this->document->versie !== $this->versie) {
            $this->document = new Informatieobject(...(new Openzaak)->get($this->documentUrl.'?versie='.$this->versie)->toArray());
        }
    }

    public function viewAction(): Action
    {
        return Action::make('view')
            ->label(__('Bekijken'))
            ->url(fn (): string => route('zaak.documents.view', [
                'zaak' => $this->zaak->id,
                'documentuuid' => $this->document->uuid,
                'type' => 'view',
                'version' => $this->versie,
            ]))
            ->openUrlInNewTab()
            ->icon('heroicon-o-eye')
            ->link()
            ->size('sm');
    }

    public function downloadAction(): Action
    {
        return Action::make('download')
            ->label(__('Downloaden'))
            ->url(fn (): string => route('zaak.documents.view', [
                'zaak' => $this->zaak->id,
                'documentuuid' => $this->document->uuid,
                'type' => 'download',
            ]))
            ->openUrlInNewTab()
            ->icon('heroicon-o-arrow-down-tray')
            ->link()
            ->size('sm');
    }

    public function render()
    {
        return view('livewire.thread.document');
    }
}
