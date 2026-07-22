<?php

namespace App\Filament\Shared\Resources\Zaken\Pages;

use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Widgets\ActivityLogWidget;
use App\Filament\Shared\Resources\Zaken\ZaakResource;
use App\Jobs\Zaak\AddBesluitZGW;
use App\Jobs\Zaak\AddFinalStatusZGW;
use App\Jobs\Zaak\AddResultaatZGW;
use App\Models\Users\MunicipalityUser;
use App\Models\Zaak;
use App\Notifications\AssignedToZaak;
use App\Notifications\Result;
use App\Notifications\ZaakReleased;
use App\Services\Zgw\ZgwConnectionConfig;
use App\Services\Zgw\ZgwResource;
use App\ValueObjects\FinishZaakObject;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Woweb\Zgw\Data\Generated\Catalogi\BesluitTypeData;
use Woweb\Zgw\Facades\Zgw;

class ViewZaak extends ViewRecord
{
    #[Locked]
    public $sessionFormData = [];

    #[Locked]
    public $activeStep = 1;

    #[Locked]
    public $formBesluittypen = [];

    #[Locked]
    public $formResultaattypen = [];

    protected static string $resource = ZaakResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        activity('views')
            ->event('view')
            ->performedOn($this->record)
            ->withProperties(array_filter([
                'url' => request()->fullUrl(),
            ]))
            ->log('Viewed record');
    }

    #[On('refreshZaak')]
    public function refresh(): void {}

    public function refreshResultaat(): void
    {
        $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('claim_zaak')
                ->label(__('municipality/resources/zaak.header_actions.claim_zaak.label'))
                ->icon('heroicon-o-hand-raised')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('municipality/resources/zaak.header_actions.claim_zaak.confirmation.title'))
                ->modalDescription(__('municipality/resources/zaak.header_actions.claim_zaak.confirmation.description'))
                ->visible(fn (Zaak $record) => ! $record->is_imported
                    && ! $record->reference_data->resultaat
                    && ! $record->reviewer_user_id
                    && in_array(auth()->user()->role, [Role::Reviewer, Role::Coordinator, Role::ReviewerMunicipalityAdmin]))
                ->action(function (Zaak $record) {
                    // Guard against a colleague claiming between page load and click.
                    $record->refresh();

                    if ($record->reviewer_user_id) {
                        Notification::make()
                            ->warning()
                            ->title(__('municipality/resources/zaak.header_actions.claim_zaak.notification.already_assigned'))
                            ->send();

                        $this->dispatch('refreshZaak');

                        return;
                    }

                    $record->reviewer_user_id = auth()->id();
                    $record->save();

                    Notification::make()
                        ->success()
                        ->title(__('municipality/resources/zaak.header_actions.claim_zaak.notification.claimed'))
                        ->send();

                    $this->dispatch('refreshZaak');
                }),
            Action::make('release_zaak')
                ->label(__('municipality/resources/zaak.header_actions.release_zaak.label'))
                ->icon('heroicon-o-user-minus')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('municipality/resources/zaak.header_actions.release_zaak.confirmation.title'))
                ->modalDescription(__('municipality/resources/zaak.header_actions.release_zaak.confirmation.description'))
                ->visible(fn (Zaak $record) => ! $record->is_imported
                    && ! $record->reference_data->resultaat
                    && $record->reviewer_user_id === auth()->id()
                    && in_array(auth()->user()->role, [Role::Reviewer, Role::Coordinator, Role::ReviewerMunicipalityAdmin]))
                ->action(function (Zaak $record) {
                    $releasedBy = auth()->user();

                    $record->reviewer_user_id = null;
                    $record->save();

                    // With no reviewer assigned this resolves to the coordinators,
                    // or falls back to all reviewers when the municipality has none.
                    foreach ($record->getMunicipalityHandlers() as $handler) {
                        if ($handler->id === $releasedBy->id) {
                            continue;
                        }

                        $handler->notify(new ZaakReleased($record, $releasedBy));
                    }

                    Notification::make()
                        ->success()
                        ->title(__('municipality/resources/zaak.header_actions.release_zaak.notification.released'))
                        ->send();

                    $this->dispatch('refreshZaak');
                }),
            Action::make('assign_reviewer')
                ->label(fn (Zaak $record) => $record->reviewer_user_id
                    ? __('municipality/resources/zaak.header_actions.assign_reviewer.label_change')
                    : __('municipality/resources/zaak.header_actions.assign_reviewer.label'))
                ->icon('heroicon-o-user-plus')
                ->color('gray')
                ->visible(fn (Zaak $record) => ! $record->is_imported
                    && ! $record->reference_data->resultaat
                    && in_array(auth()->user()->role, [Role::Coordinator, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Admin]))
                ->fillForm(fn (Zaak $record) => ['reviewer_user_id' => $record->reviewer_user_id])
                ->schema(function (Zaak $record) {
                    $municipality = $record->municipality;

                    return [
                        Select::make('reviewer_user_id')
                            ->label(__('municipality/resources/zaak.header_actions.assign_reviewer.form.reviewer.label'))
                            ->placeholder(__('municipality/resources/zaak.header_actions.assign_reviewer.form.reviewer.placeholder'))
                            ->options($municipality->allReviewerUsers()->get()->pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),
                    ];
                })
                ->action(function (Zaak $record, array $data) {
                    $previousReviewerId = $record->reviewer_user_id;
                    $newReviewerId = $data['reviewer_user_id'] ?? null;

                    $record->reviewer_user_id = $newReviewerId;
                    $record->save();

                    if ($newReviewerId && $newReviewerId !== $previousReviewerId) {
                        $reviewer = $record->reviewerUser;
                        if ($reviewer) {
                            $reviewer->notify(new AssignedToZaak($record));
                        }
                    }

                    Notification::make()
                        ->success()
                        ->title($newReviewerId
                            ? __('municipality/resources/zaak.header_actions.assign_reviewer.notification.assigned')
                            : __('municipality/resources/zaak.header_actions.assign_reviewer.notification.unassigned'))
                        ->send();

                    $this->dispatch('refreshZaak');
                }),
            Action::make('activity')
                ->visible(fn (Zaak $record) => auth()->user()->can('viewActivity', $record))
                ->label('Bekijk activiteiten')
                ->color('gray')
                ->slideOver()
                ->modalSubmitAction(false)
                ->modalContent(fn () => view('filament.components.modal-widget', [
                    'widget' => ActivityLogWidget::class,
                    'record' => $this->record,
                ])),
            Action::make('delete_zaak')
                ->label(__('resources/zaak.actions.delete_zaak.label'))
                ->color('danger')
                ->visible(fn (Zaak $record) => ! $record->trashed() && auth()->user()->role === Role::Admin)
                ->schema([
                    Checkbox::make('delete_in_openzaak')
                        ->label(__('resources/zaak.actions.delete_zaak.checkbox.delete_in_openzaak'))
                        ->visible(fn (Zaak $record) => (bool) $record->zgw_zaak_url)
                        ->default(false),
                ])
                ->modalHeading(__('resources/zaak.actions.delete_zaak.confirmation.title'))
                ->modalDescription(__('resources/zaak.actions.delete_zaak.confirmation.description'))
                ->requiresConfirmation()
                ->action(function (Zaak $record, array $data) {
                    // Verify authorization via policy
                    if (! auth()->user()->can('delete', $record)) {
                        Notification::make()
                            ->danger()
                            ->title(__('resources/zaak.actions.delete_zaak.unauthorized'))
                            ->send();

                        return;
                    }

                    // Optionally delete in OpenZaak
                    if (($data['delete_in_openzaak'] ?? false) && $record->zgw_zaak_url) {
                        try {
                            $uuid = basename($record->zgw_zaak_url);
                            $deleted = Zgw::connection($record->zgwConnectionName())->zaken()->zaken()->delete($uuid);

                            if ($deleted) {
                                // Clear zgw_zaak_url so the zaak cannot be restored
                                $record->zgw_zaak_url = null;
                                $record->save();
                            } else {
                                Notification::make()
                                    ->warning()
                                    ->title(__('resources/zaak.actions.delete_zaak.error_open_zaak'))
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            // The underlying error goes to the log only; the user
                            // sees a generic message.
                            Log::warning('ViewZaak: deleting the zaak in OpenZaak failed', [
                                'zaak_id' => $record->id,
                                'exception' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->warning()
                                ->title(__('resources/zaak.actions.delete_zaak.error_open_zaak'))
                                ->send();
                        }
                    }

                    // Soft delete the zaak
                    $record->delete();

                    // Clear cache
                    $record->clearZgwCache();

                    Notification::make()
                        ->success()
                        ->title(__('resources/zaak.actions.delete_zaak.success'))
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),
            Action::make('finish_zaak')
                ->label(__('municipality/resources/zaak.header_actions.finish_zaak.label'))
                ->schema([
                    Wizard::make([
                        Step::make(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.label'))
                            ->columns(12)
                            ->schema([
                                Select::make('result_type')
                                    ->label(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.result_type.label'))
                                    ->options(function (Zaak $record) {
                                        $this->formResultaattypen = (Zgw::connection($record->zgwConnectionName())->catalogi()->resultaattypen()->index([
                                            'zaaktype' => $record->openzaak->zaaktype,
                                        ])->collect()->filter(fn ($item) => $item['omschrijvingGeneriek'] !== 'Ingetrokken')->pluck('omschrijving', 'url')->toArray());

                                        return $this->formResultaattypen;
                                    })
                                    ->afterStateUpdated(function (?string $state, Set $set, Zaak $record) {
                                        if ($state) {
                                            $resultType = ZgwResource::byUrl($record->zgwConnectionName(), $state);
                                            $besluittypen = [];
                                            if (isset($resultType['besluittypen']) && count($resultType['besluittypen']) > 0 && isset($resultType['besluittypeOmschrijving']) && $resultType['besluittypeOmschrijving']) {
                                                foreach ($resultType['besluittypen'] as $key => $besluittype) {
                                                    $besluittypen[$besluittype] = $resultType['besluittypeOmschrijving'][$key];
                                                }
                                            }
                                            $this->formBesluittypen = $besluittypen;
                                            $set('message_title', __('Uw aanvraag is :result', ['result' => strtolower($resultType['omschrijving'])]));
                                        }
                                    })
                                    ->required()
                                    ->default(fn (Field $component) => $this->getDefaultValue($component))
                                    ->columnSpan(8)
                                    ->live(),
                                IconEntry::make('result_has_besluit')
                                    ->label(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.result_has_besluit.label'))
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->helperText(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.result_has_besluit.helper_text'))
                                    ->disabled()
                                    ->live()
                                    ->hidden(fn (Get $get) => ! $get('result_type'))
                                    ->state(function () {
                                        return count($this->formBesluittypen) > 0;
                                    })
                                    ->columnSpan(4)
                                    ->default(fn (Field $component) => $this->getDefaultValue($component)),
                                Textarea::make('result_toelichting')
                                    ->label(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.result_toelichting.label'))
                                    ->helperText(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.result_toelichting.helper_text'))
                                    ->rows(3)
                                    ->maxLength(1000)
                                    ->default(fn (Field $component) => $this->getDefaultValue($component))
                                    ->columnSpan(12)
                                    ->hidden(fn (Get $get) => ! $get('result_type') || count($this->formBesluittypen) > 0),
                            ])
                            ->afterValidation(function (ViewZaak $livewire) {
                                $this->setSessionData($livewire, 2);
                            }),
                        Step::make(__('Besluit'))
                            ->visible(fn (Get $get) => $get('result_has_besluit'))
                            ->columns(12)
                            ->schema([
                                Select::make('besluit_type')
                                    ->label(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.besluit_type.label'))
                                    ->options(fn () => $this->formBesluittypen)
                                    ->required()
                                    ->default(fn (Field $component) => $this->getDefaultValue($component))
                                    ->live()
                                    ->columnSpan(6),
                                DatePicker::make('datum_besluit')
                                    ->label(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.datum_besluit.label'))
                                    ->required()
                                    ->default(fn (Field $component) => $this->getDefaultValue($component, date('Y-m-d')))
                                    ->maxDate(now())
                                    ->columnSpan(6),
                                Select::make('besluit_documenten')
                                    ->label(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.besluit_documenten.label'))
                                    ->options(
                                        function (Zaak $record, Get $get) {
                                            if (! $get('besluit_type')) {
                                                return [];
                                            }
                                            $besluittype = BesluitTypeData::from(ZgwResource::byUrl($record->zgwConnectionName(), $get('besluit_type')));

                                            // dd($record->documenten);
                                            return $record->documenten->filter(function ($document) use ($besluittype) {
                                                return in_array($document->informatieobjecttype, $besluittype->informatieobjecttypen);
                                            })->pluck('titel', 'url')->toArray();

                                        }
                                    )
                                    ->helperText(fn (Select $component, Get $get) => $get('besluit_type') && empty($component->getOptions()) ? __('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.besluit_documenten.helper_text') : null)
                                    // ->hintIcon('heroicon-o-information-circle')
                                    // ->hintIconTooltip('test')
                                    ->multiple()
                                    ->required()
                                    ->default(fn (Field $component) => $this->getDefaultValue($component))
                                    ->columnSpan(12),
                                Textarea::make('besluit_toelichting')
                                    ->label(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.besluit_toelichting.label'))
                                    ->helperText(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.besluit_toelichting.helper_text'))
                                    ->rows(3)
                                    ->maxLength(1000)
                                    ->default(fn (Field $component) => $this->getDefaultValue($component))
                                    ->columnSpan(12),
                                DatePicker::make('ingangsdatum')
                                    ->label(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.ingangsdatum.label'))
                                    ->required()
                                    ->default(fn (Field $component) => $this->getDefaultValue($component))
                                    ->columnSpan(6),
                                DatePicker::make('vervaldatum')
                                    ->label(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.vervaldatum.label'))
                                    ->default(fn (Field $component) => $this->getDefaultValue($component))
                                    ->columnSpan(6),

                            ])
                            ->afterValidation(function (ViewZaak $livewire) {
                                $this->setSessionData($livewire, 3);
                            }),
                        Step::make(__('Bericht naar organisator'))
                            ->schema([
                                TextInput::make('message_title')
                                    ->label(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.message_title.label'))
                                    ->default(fn (Field $component) => $this->getDefaultValue($component, __('Uw aanvraag is afgerond')))
                                    ->required(),
                                RichEditor::make('message_content')
                                    ->label(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.message_content.label'))
                                    ->default(fn (Field $component) => $this->getDefaultValue($component))
                                    ->toolbarButtons([
                                        ['bold', 'italic', 'underline', 'strike',  'link'],
                                        ['bulletList', 'orderedList'],
                                        ['undo', 'redo'],
                                    ])
                                    ->required(),
                                Select::make('message_documenten')
                                    ->label(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.message_documenten.label'))
                                    ->options(fn (Zaak $record) => $record->documenten->whereIn('vertrouwelijkheidaanduiding', ZgwConnectionConfig::documentVisibilityForRole($record->zgwConnectionName(), Role::Organiser))->pluck('titel', 'url')->toArray())
                                    ->multiple()
                                    ->required(fn (Get $get) => $get('result_has_besluit'))
                                    ->helperText(fn (Get $get) => $get('result_has_besluit') ? __('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.message_documenten.helper_text') : null)
                                    ->default(fn (Field $component) => $this->getDefaultValue($component)),
                            ])
                            ->afterValidation(function (ViewZaak $livewire, Get $get) {
                                $activeStep = $get('result_has_besluit') ? 4 : 3;
                                $this->setSessionData($livewire, $activeStep);
                            }),
                        Step::make(__('Samenvatting'))
                            ->columns(2)
                            ->schema(function (Get $get) {
                                $schema = [];
                                $hasBesluit = $get('result_has_besluit');
                                foreach ($this->sessionFormData as $key => $value) {
                                    if (! $hasBesluit) {
                                        if (in_array($key, ['besluit_type', 'datum_besluit', 'besluit_documenten', 'ingangsdatum', 'vervaldatum'])) {
                                            continue;
                                        }
                                    }

                                    if ($key == 'result_has_besluit') {
                                        continue;
                                    }

                                    match ($key) {
                                        'result_type' => $value = $this->formResultaattypen[$value] ?? $value,
                                        'besluit_type' => $value = $this->formBesluittypen[$value] ?? $value,
                                        'besluit_documenten', 'message_documenten' => $value = is_array($value) ? count($value).' '.__(':value', ['value' => count($value) > 1 ? 'documenten' : 'document']) : '-',
                                        'datum_besluit', 'ingangsdatum', 'vervaldatum' => $value = $value ? date('d-m-Y', strtotime($value)) : '-',
                                        'message_content' => $value = $value ? RichContentRenderer::make($value) : '-',
                                        default => null
                                    };

                                    $schema[] = TextEntry::make('summary_'.$key)
                                        ->label(__('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.'.$key.'.label'))
                                        ->state($value ?? '-');
                                }

                                return $schema;
                            }),
                    ])
                        ->startOnStep($this->activeStep)
                        ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                            <x-filament::button
                                type="submit"
                                wire:target="callMountedAction"
                            >
                                {{ __('municipality/resources/zaak.header_actions.finish_zaak.steps.result.schema.submit.label') }}
                            </x-filament::button>
                        BLADE))),
                ])
                ->action(function (Zaak $record, array $data) {
                    /** @var MunicipalityUser $user */
                    $user = auth()->user();
                    $finishZaakObject = new FinishZaakObject(
                        zaak: $record,
                        user: $user,
                        resultaattype: $data['result_type'],
                        besluittype: $data['besluit_type'] ?? null,
                        datum_besluit: $data['datum_besluit'] ?? null,
                        ingangsdatum: $data['ingangsdatum'] ?? null,
                        vervaldatum: $data['vervaldatum'] ?? null,
                        besluit_toelichting: $data['besluit_toelichting'] ?? null,
                        besluit_documenten: $data['besluit_documenten'] ?? null,
                        result_toelichting: $data['result_toelichting'] ?? null,
                        message_title: $data['message_title'],
                        message_content: $data['message_content'],
                        message_documenten: $data['message_documenten'] ?? null,
                    );

                    Bus::chain(array_filter([
                        $finishZaakObject->besluittype ? new AddBesluitZGW($finishZaakObject) : null,
                        new AddResultaatZGW($finishZaakObject),
                        new AddFinalStatusZGW($finishZaakObject),
                        function () use ($record, $finishZaakObject) {
                            if ($record->organisation?->users) {
                                foreach ($record->organisation->users as $recipient) {
                                    /** @var MunicipalityUser $recipient */
                                    $recipient->notify(new Result(
                                        zaak: $record,
                                        tenant: $record->organisation,
                                        title: $finishZaakObject->message_title,
                                        message: $finishZaakObject->message_content,
                                        attachmentUrls: $finishZaakObject->message_documenten,
                                    ));
                                }
                            }
                        },
                    ]))->dispatch();

                    /**
                     * TODO: needs correct TGet and TSet generics of ZaakReferenceData to work with static analysis, code works but gives error in static analysis.
                     *
                     * @disregard
                     */
                    $record->reference_data = new ZaakReferenceData(...array_merge($record->reference_data->toArray(), ['resultaat' => __('wordt momementeel verwerkt...')])); // @phpstan-ignore assign.propertyReadOnly

                    $record->save();

                    $this->sessionFormData = [];
                    $this->activeStep = 1;
                    $this->formResultaattypen = [];
                    $this->formBesluittypen = [];

                    Notification::make()
                        ->title(__('De afronding van de zaak wordt op de achtergrond verwerkt.'))
                        ->success()
                        ->send();

                    $this->dispatch('refreshZaak');
                })
                ->closeModalByClickingAway(false)
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->visible(fn (Zaak $record) => ! $record->reference_data->resultaat && ! $record->is_imported && $record->behandelaarCanChangeStatus() && in_array(auth()->user()->role, [Role::Reviewer, Role::Coordinator, Role::ReviewerMunicipalityAdmin])),
        ];
    }

    private function setSessionData(ViewZaak $livewire, int $step = 1)
    {
        $this->sessionFormData = Arr::has($livewire->mountedActions, '0.data') ? array_merge($this->sessionFormData, Arr::get($livewire->mountedActions, '0.data')) : $this->sessionFormData;
        $this->activeStep = $step;
    }

    private function getDefaultValue(Field $component, $default = null)
    {
        return Arr::get($this->sessionFormData, $component->getName(), $default);
    }
}
