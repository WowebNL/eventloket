<?php

namespace App\Livewire\Thread;

use App\Models\Zaak;
use App\Services\Zgw\ZgwResource;
use App\ValueObjects\ZGW\Informatieobject;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Document extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Locked]
    public Zaak $zaak;

    #[Locked]
    public string $documentUrl;

    #[Locked]
    public int $versie;

    #[Locked]
    public int $latestVersion;

    /**
     * Null when the document is no longer among the documents the caller may
     * see: it was removed from the zaak, is not (or no longer) established, or
     * is not visible to this role. The thread then says so instead of failing.
     */
    private ?Informatieobject $document;

    public function mount(): void
    {
        $this->document = $this->zaak->documenten->firstWhere('url', $this->documentUrl);

        if ($this->document === null) {
            $this->latestVersion = $this->versie;

            return;
        }

        $this->latestVersion = (int) $this->document->versie;

        if ((int) $this->document->versie !== $this->versie) {
            $this->document = new Informatieobject(...ZgwResource::showDocumentVersion($this->zaak->zgwConnectionName(), $this->document->uuid, $this->versie));
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
