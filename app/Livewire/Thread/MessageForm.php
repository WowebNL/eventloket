<?php

namespace App\Livewire\Thread;

use App\Enums\AdviceStatus;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Filament\Shared\Resources\Threads\Actions\AssignAction;
use App\Filament\Shared\Resources\Threads\Actions\AssignToSelfAction;
use App\Filament\Shared\Resources\Zaken\Actions\NewDocumentVersionAction;
use App\Filament\Shared\Resources\Zaken\Actions\UploadDocumentAction;
use App\Models\Message;
use App\Models\Thread;
use App\ValueObjects\MessageDocument;
use App\ValueObjects\ZGW\Informatieobject;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Woweb\Openzaak\Openzaak;

/**
 * @property \Filament\Schemas\Schema|mixed $form
 */
class MessageForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Thread $thread;

    public ?array $data = [];

    public array $documents = [];

    protected $listeners = ['thread-updated' => '$refresh'];

    public function mount(): void
    {
        $this->form->fill();
    }

    #[Computed]
    public function resolvedDocuments(): Collection
    {
        if (empty($this->documents)) {
            return collect();
        }

        return collect($this->documents)->map(function ($documentData) {
            $document = $this->thread->zaak->documenten->firstWhere('url', $documentData['url']);

            if ($document->versie !== $documentData['versie']) {
                $document = new Informatieobject(...(new Openzaak)->get($document->url.'?versie='.$documentData['versie'])->toArray());
            }

            return $document ? [
                'document' => $document,
                'versie' => $documentData['versie'],
                'url' => $documentData['url'],
            ] : null;
        })->filter();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                RichEditor::make('body')
                    ->label('Plaats een reactie')
                    ->required(fn (): bool => empty($this->documents))
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'strike', 'link'],
                        ['h1', 'h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
                        ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $this->authorize('post-message', $this->thread);

        $data = $this->form->getState();

        Message::create(array_filter([
            'thread_id' => $this->thread->id,
            'user_id' => auth()->id(),
            'body' => $data['body'] == '<p></p>' ? null : $data['body'],
            'documents' => $this->documents,
        ]));

        if ($this->thread->type == ThreadType::Advice && $this->thread->advice_status === AdviceStatus::Asked && auth()->user()->role === Role::Advisor) {
            $this->thread->update(['advice_status' => AdviceStatus::AdvisoryReplied]);
        }

        $this->form->fill(); // reset form state
        $this->documents = []; // Clear attached documents

        $this->dispatch('thread-updated');

        Notification::make()
            ->title('Reactie toegevoegd')
            ->success()
            ->send();
    }

    public function attachAction(): Action
    {
        $zaak = $this->thread->zaak;

        return Action::make('attach')
            ->label('Bestand bijvoegen')
            ->icon('heroicon-o-paper-clip')
            ->color('gray')
            ->schema([
                ToggleButtons::make('type')
                    ->hiddenLabel()
                    ->default('new')
                    ->live()
                    ->options([
                        'new' => 'Nieuw bestand',
                        'version' => 'Nieuwe versie van bestaand bestand',
                    ])
                    ->icons([
                        'new' => Heroicon::OutlinedArrowUpTray,
                        'version' => Heroicon::OutlinedPlusCircle,
                    ])
                    ->inline(),

                Section::make()
                    ->contained(false)
                    ->visible(fn (Get $get): bool => $get('type') === 'new')
                    ->schema(UploadDocumentAction::schema($zaak)),

                Select::make('existing_document')
                    ->label('Selecteer bestaand document')
                    ->options(fn () => $zaak->documenten->pluck('titel', 'uuid')->toArray())
                    ->required()
                    ->visible(fn (Get $get): bool => $get('type') === 'version')
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) use ($zaak) {
                        if ($state) {
                            $documentTitle = $zaak->documenten->firstWhere('uuid', $get('existing_document'))?->titel;

                            $set('titel', $documentTitle);
                        }
                    }),

                Section::make()
                    ->contained(false)
                    ->visible(fn (Get $get): bool => $get('type') === 'version' && $get('existing_document') !== null)
                    ->schema(NewDocumentVersionAction::schema()),

            ])
            ->action(function (array $data) {
                $this->authorize('post-message', $this->thread);

                if ($data['type'] === 'new') {
                    $document = UploadDocumentAction::uploadDocument($data, $this->thread->zaak);
                    $this->documents[] = MessageDocument::make($document->url, (int) $document->versie)->toArray();

                    Notification::make()
                        ->title('Document toegevoegd')
                        ->success()
                        ->send();
                } elseif ($data['type'] === 'version') {
                    NewDocumentVersionAction::createNewDocumentVersion($data['existing_document'], $data, $this->thread->zaak);

                    $this->thread->zaak->refresh(); // Need to refresh to re-initialize documenten
                    $document = $this->thread->zaak->documenten->firstWhere('uuid', $data['existing_document']);

                    $this->documents[] = MessageDocument::make($document->url, (int) $document->versie)->toArray();

                    Notification::make()
                        ->title('Nieuwe versie toegevoegd')
                        ->success()
                        ->send();
                }
            });
    }

    public function assignAction(): Action
    {
        return AssignAction::make()
            ->record($this->thread);
    }

    public function assignToSelfAction(): Action
    {
        return AssignToSelfAction::make()
            ->record($this->thread);
    }

    public function render(): View
    {
        return view('livewire.thread.message-form');
    }
}
