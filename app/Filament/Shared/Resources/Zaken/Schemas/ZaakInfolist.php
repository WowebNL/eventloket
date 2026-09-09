<?php

namespace App\Filament\Shared\Resources\Zaken\Schemas;

use App\Enums\AdviceStatus;
use App\Enums\Role;
use App\EventForm\Support\DagenRepeater;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Filament\Shared\Resources\Zaken\Schemas\Components\LocationsTab;
use App\Filament\Shared\Resources\Zaken\ZaakResource\RelationManagers\AdviceThreadRelationManager;
use App\Filament\Shared\Resources\Zaken\ZaakResource\RelationManagers\OrganiserThreadsRelationManager;
use App\Livewire\Zaken\BesluitenInfolist;
use App\Livewire\Zaken\DeelzakenTable;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Users\MunicipalityUser;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Notifications\ZaakStatusChanged;
use App\Services\Zgw\ZaaktypeBlueprint;
use App\Services\Zgw\ZgwResource;
use App\Support\RisicoClassificatie;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Woweb\Zgw\Data\Generated\Catalogi\EigenschapData;
use Woweb\Zgw\Data\Generated\Catalogi\StatusTypeData;
use Woweb\Zgw\Facades\Zgw;

class ZaakInfolist
{
    /**
     * Roles that handle a case and may therefore see the case parties:
     * municipality staff, advisory staff and platform admins. The organiser
     * role is deliberately absent — the organiser panel renders the same
     * information schema.
     *
     * @var list<Role>
     */
    private const CASE_HANDLER_ROLES = [
        Role::MunicipalityAdmin,
        Role::ReviewerMunicipalityAdmin,
        Role::Coordinator,
        Role::Reviewer,
        Role::Advisor,
        Role::Admin,
    ];

    public static function isCaseHandler(): bool
    {
        return in_array(auth()->user()?->role, self::CASE_HANDLER_ROLES, true);
    }

    /**
     * Whether the current user may see the case parties (submitter name,
     * organisation name, event address and chamber of commerce number).
     *
     * Case handlers always may. An organiser may too, but only for cases of
     * an organisation they belong to. This matters because the same schema
     * feeds the calendar modal, which also lists events of other
     * organisations; the per-organisation check keeps those parties hidden
     * there while showing them on the organiser's own cases.
     */
    public static function canSeeCaseParties(Zaak $record): bool
    {
        if (self::isCaseHandler()) {
            return true;
        }

        $user = auth()->user();

        return $user instanceof OrganiserUser && $user->canAccessOrganisation($record->organisation_id);
    }

    /**
     * The ZGW eigenschap naam for the internal zaaknummer on this zaak. Without
     * a koppeling that translates the name this is the logical key itself, so
     * the shared hoofdkoppeling keeps working exactly as before.
     */
    private static function internZaaknummerEigenschapNaam(Zaak $record): string
    {
        return ZaaktypeBlueprint::eigenschapNaam(
            MunicipalityZaaktypeMapping::forZaaktype($record->zaaktype),
            'intern_zaaknummer',
        );
    }

    public static function informationschema(): array
    {
        return [
            // Event: what and when. Grouped first so the case reads from the
            // event, through the parties, to the case administration.
            TextEntry::make('reference_data.naam_evenement')
                ->label(__('resources/zaak.columns.naam_evenement.label')),
            TextEntry::make('reference_data.start_evenement_datetime')
                ->dateTime(config('app.datetime_format'))
                ->label(__('resources/zaak.columns.start_evenement.label')),
            TextEntry::make('reference_data.eind_evenement_datetime')
                ->dateTime(config('app.datetime_format'))
                ->label(__('resources/zaak.columns.eind_evenement.label')),
            self::dagenEntry('dagen_evenement', __('resources/zaak.columns.dagen_evenement.label')),
            TextEntry::make('reference_data.start_opbouw')
                ->dateTime(config('app.datetime_format'))
                ->label(__('resources/zaak.columns.start_opbouw.label'))
                ->visible(fn ($state) => ! empty($state)),
            TextEntry::make('reference_data.eind_opbouw')
                ->dateTime(config('app.datetime_format'))
                ->label(__('resources/zaak.columns.eind_opbouw.label'))
                ->visible(fn ($state) => ! empty($state)),
            self::dagenEntry('dagen_opbouw', __('resources/zaak.columns.dagen_opbouw.label')),
            TextEntry::make('reference_data.start_afbouw')
                ->dateTime(config('app.datetime_format'))
                ->label(__('resources/zaak.columns.start_afbouw.label'))
                ->visible(fn ($state) => ! empty($state)),
            TextEntry::make('reference_data.eind_afbouw')
                ->dateTime(config('app.datetime_format'))
                ->label(__('resources/zaak.columns.eind_afbouw.label'))
                ->visible(fn ($state) => ! empty($state)),
            self::dagenEntry('dagen_afbouw', __('resources/zaak.columns.dagen_afbouw.label')),
            TextEntry::make('reference_data.locaties_evenement')
                ->label(__('resources/zaak.columns.locaties_evenement.label'))
                ->visible(fn ($state) => ! empty($state)),
            TextEntry::make('openzaak.zaakAddresses')
                ->label(__('municipality/resources/zaak.columns.adres_evenement.label'))
                ->listWithLineBreaks()
                ->visible(fn (Zaak $record, ?array $state) => self::canSeeCaseParties($record) && ! empty($state)),
            TextEntry::make('reference_data.aanwezigen')
                ->label(__('resources/zaak.columns.aanwezigen.label'))
                ->visible(fn ($state) => ! empty($state)),
            TextEntry::make('reference_data.types_evenement')
                ->label(__('resources/zaak.columns.types_evenement.label'))
                ->bulleted()
                ->formatStateUsing(fn ($state) => Str::ucfirst(Str::lower(Str::headline($state))))
                ->visible(fn ($state) => ! empty($state)),

            // Parties: organiser (organisation) and submitter (user).
            TextEntry::make('reference_data.organisator')
                ->label(__('municipality/resources/zaak.columns.organisator.label'))
                ->visible(fn () => in_array(auth()->user()->role, [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Coordinator, Role::Reviewer])),
            TextEntry::make('organiserUser.name')
                ->label(__('resources/zaak.columns.naam-organiser.label'))
                ->visible(fn (Zaak $record, ?string $state) => self::canSeeCaseParties($record) && ! empty($state)),
            TextEntry::make('organisation.name')
                ->label(__('municipality/resources/zaak.columns.naam_organisatie.label'))
                ->visible(fn (Zaak $record, ?string $state) => self::canSeeCaseParties($record) && ! empty($state) && $record->organisation && ! $record->organisation->isPersonal()),
            TextEntry::make('organisation.coc_number')
                ->label(__('municipality/resources/zaak.columns.kvk_nummer_organisatie.label'))
                ->visible(fn (Zaak $record, ?string $state) => self::canSeeCaseParties($record) && ! empty($state)),
            // Contact details of the organisation and the submitter. These
            // carry the same per-organisation gate as the four fields above:
            // today they never leak (the calendar scope does not load these
            // columns for the organiser), but the explicit gate keeps a later
            // widening of that scope from turning them into a cross-organisation
            // leak on the shared calendar modal.
            TextEntry::make('organisation.phone')
                ->label(__('resources/zaak.columns.telefoon.label'))
                ->visible(fn (Zaak $record, ?string $state) => self::canSeeCaseParties($record) && ! empty($state)),
            TextEntry::make('organiseruser.phone')
                ->label(__('resources/zaak.columns.telefoon-organiser.label'))
                ->visible(fn (Zaak $record, ?string $state) => self::canSeeCaseParties($record) && ! empty($state)),
            TextEntry::make('organisation.email')
                ->label(__('resources/zaak.columns.email.label'))
                ->visible(fn (Zaak $record, ?string $state) => self::canSeeCaseParties($record) && ! empty($state)),
            TextEntry::make('organiserUser.email')
                ->label(__('resources/zaak.columns.email-organiser.label'))
                ->visible(fn (Zaak $record, ?string $state) => self::canSeeCaseParties($record) && ! empty($state)),

            // Case administration: identifiers, type, links, status.
            TextEntry::make('public_id')
                ->icon('heroicon-o-identification')
                ->label(__('resources/zaak.columns.public_id.label')),
            TextEntry::make('zaaktype.name')
                ->label(__('resources/zaak.columns.zaaktype.label')),
            // Issue #10: show the vooraankondiging link in both directions.
            // On the definitive aanvraag: which vooraankondiging it replaces;
            // on the vooraankondiging: which aanvraag replaced it. Rendered
            // in every panel that uses this schema (municipality, advisor,
            // admin and, via the organiser infolist, the organiser).
            TextEntry::make('vervangt_vooraankondiging')
                ->label(__('resources/zaak.columns.vervangt_vooraankondiging.label'))
                ->state(fn (Zaak $record): ?string => $record->vervangtVooraankondiging->first()?->public_id)
                ->url(fn (Zaak $record): ?string => self::zaakViewUrl($record->vervangtVooraankondiging->first()))
                ->color('primary')
                ->icon('heroicon-o-link')
                ->visible(fn (Zaak $record): bool => $record->vervangtVooraankondiging->isNotEmpty()),
            TextEntry::make('opgevolgd_door')
                ->label(__('resources/zaak.columns.opgevolgd_door.label'))
                ->state(fn (Zaak $record): ?string => $record->opgevolgdDoor->first()?->public_id)
                ->url(fn (Zaak $record): ?string => self::zaakViewUrl($record->opgevolgdDoor->first()))
                ->color('primary')
                ->icon('heroicon-o-link')
                ->visible(fn (Zaak $record): bool => $record->opgevolgdDoor->isNotEmpty()),
            TextEntry::make('reference_data.risico_classificatie')
                ->label(__('resources/zaak.columns.risico_classificatie.label'))
                ->formatStateUsing(fn (?string $state) => RisicoClassificatie::label($state))
                ->visible(fn ($state) => ! empty($state)),
            TextEntry::make('municipality.name')
                ->label(__('Ingediend bij gemeente')),
            TextEntry::make('reference_data.status_name')
                ->label(__('resources/zaak.columns.status.label'))
                ->visible(function (Zaak $record) {
                    $user = auth()->user();
                    // Only show for municipality users, reviewers, and advisors
                    // For organisers, only show for their own cases
                    if ($user instanceof OrganiserUser) {
                        return $user->canAccessOrganisation($record->organisation_id);
                    }

                    return in_array($user->role, [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Coordinator, Role::Reviewer, Role::Advisor, Role::Admin]);
                }),
            TextEntry::make('reference_data.resultaat')
                ->label(__('resources/zaak.columns.resultaat.label'))
                ->visible(function (Zaak $record) {
                    if (empty($record->reference_data->resultaat)) {
                        return false;
                    }
                    $user = auth()->user();
                    // Only show for municipality users, reviewers, and advisors
                    // For organisers, only show for their own cases
                    if ($user instanceof OrganiserUser) {
                        return $user->canAccessOrganisation($record->organisation_id);
                    }

                    return in_array($user->role, [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Coordinator, Role::Reviewer, Role::Advisor, Role::Admin]);
                }),
        ];
    }

    /**
     * Per-day start and end times of a multi-day period. Only shown when the
     * organiser actually supplied them; a single-day event keeps telling its
     * story through the start and end entries above.
     */
    private static function dagenEntry(string $key, string $label): TextEntry
    {
        return TextEntry::make("reference_data.{$key}")
            ->label($label)
            ->listWithLineBreaks()
            ->state(function (Zaak $record) use ($key): array {
                return array_map(
                    fn (array $rij): string => sprintf('%s · %s – %s', $rij['datum'], $rij['start'], $rij['eind']),
                    DagenRepeater::alsTabelRijen($record->reference_data->{$key}),
                );
            })
            ->visible(fn ($state): bool => is_array($state) && $state !== []);
    }

    public static function resultaatSection(): Section
    {
        return Section::make(__('Resultaat'))
            ->description(__('Het resultaat van deze zaak is vastgesteld.'))
            ->columns(2)
            ->schema([
                TextEntry::make('openzaak.resultaattype.omschrijving')
                    ->label(__('Resultaat')),
                TextEntry::make('openzaak.resultaat.toelichting')
                    ->label(__('Toelichting op het resultaat'))
                    ->visible(fn (Zaak $record) => $record->openzaak->resultaat && Arr::has($record->openzaak->resultaat, 'toelichting') && $record->openzaak->resultaat['toelichting']),
                TextEntry::make('openzaak.status_name')
                    ->label(__('Huidige status')),
                TextEntry::make('openzaak.status.datumStatusGezet')
                    ->label(__('Status gezet op'))
                    ->date(config('app.date_format')),
            ])
            ->columnSpan(4)
            ->visible(fn (Zaak $record) => ($record->openzaak && $record->openzaak->resultaat) ? true : false);
    }

    public static function configure(Schema $schema): Schema
    {
        /** @var Zaak $zaak */
        $zaak = $schema->model;

        return $schema
            ->components(array_filter([
                $zaak->reference_data->resultaat ? new HtmlString(Blade::render('filament.components.zaak-result', ['resultaat' => $zaak->reference_data->resultaat])) : null,
                Grid::make()
                    ->columns(12)
                    ->columnSpanFull()
                    ->schema([
                        Section::make(__('municipality/resources/zaak.infolist.sections.information.label'))
                            ->description(__('municipality/resources/zaak.infolist.sections.information.description'))
                            ->columns(2)
                            ->schema(array_merge(self::informationschema(), [
                                TextEntry::make('openzaak.uiterlijkeEinddatumAfdoening')
                                    ->date(config('app.date_format'))
                                    ->label(__('municipality/resources/zaak.columns.uiterlijkeEinddatumAfdoening.label')),
                            ]))
                            ->columnSpan(fn ($record) => ! $record->is_imported && ($record->reference_data->resultaat || in_array(auth()->user()->role, [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Coordinator, Role::Reviewer, Role::Admin])) ? 8 : 12),
                        Section::make(__('municipality/resources/zaak.infolist.sections.actions.label'))
                            ->description(__('municipality/resources/zaak.infolist.sections.actions.description'))
                            ->schema([
                                TextEntry::make('reviewerUser.name')
                                    ->label(__('municipality/resources/zaak.infolist.sections.actions.reviewer_user.label'))
                                    ->placeholder(__('municipality/resources/zaak.infolist.sections.actions.reviewer_user.placeholder')),
                                TextEntry::make('reference_data.risico_classificatie')
                                    ->label(__('resources/zaak.columns.risico_classificatie.label'))
                                    ->formatStateUsing(fn (?string $state) => RisicoClassificatie::label($state))
                                    ->suffix(function (Zaak $record) {
                                        if (! empty($record->reference_data->risico_toelichting)) {
                                            return new HtmlString(
                                                Blade::render(
                                                    '<span class="ms-2" x-data="{}" x-tooltip="{ content: @js($toelichting), theme: $store.theme }">
                                                        <x-filament::icon icon="heroicon-o-information-circle" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                                                    </span>',
                                                    ['toelichting' => $record->reference_data->risico_toelichting]
                                                )
                                            );
                                        }

                                        return null;
                                    })
                                    ->afterLabel(Schema::end([
                                        Icon::make('heroicon-o-pencil-square')
                                            ->visible(fn (Zaak $record): bool => $record->behandelaarCanEditRisicoClassificatie()),
                                        Action::make('editRisicoClassificatie')
                                            ->visible(fn (Zaak $record): bool => $record->behandelaarCanEditRisicoClassificatie())
                                            ->label(__('municipality/resources/zaak.infolist.sections.actions.actions.edit_risico_classificatie.label'))
                                            ->fillForm(function (Zaak $record): array {
                                                /** @var ZaakReferenceData $referenceData */
                                                $referenceData = $record->reference_data;

                                                return [
                                                    'risico_classificatie' => $referenceData->risico_classificatie,
                                                ];
                                            })
                                            ->schema([
                                                Select::make('risico_classificatie')
                                                    ->label(__('municipality/resources/zaak.infolist.sections.actions.actions.edit_risico_classificatie.fields.risico_classificatie.label'))
                                                    ->options(RisicoClassificatie::options())->required(),
                                                Textarea::make('risico_toelichting')
                                                    ->label(__('municipality/resources/zaak.infolist.sections.actions.actions.edit_risico_classificatie.fields.risico_classificatie_toelichting.label'))
                                                    ->rows(3)
                                                    ->maxLength(255)
                                                    ->helperText(__('municipality/resources/zaak.infolist.sections.actions.actions.edit_risico_classificatie.fields.risico_classificatie_toelichting.helper_text'))
                                                    ->required(),
                                            ])
                                            ->action(function ($data, $record) {
                                                try {
                                                    $openzaak = Zgw::connection($record->zgwConnectionName());
                                                    $success = true;
                                                    $eigenschappen = ['risico_classificatie' => null, 'risico_toelichting' => null];

                                                    // The catalogus may name these eigenschappen differently;
                                                    // the koppeling holds the translation, so resolve both
                                                    // names once and match on them everywhere below.
                                                    $mapping = MunicipalityZaaktypeMapping::forZaaktype($record->zaaktype);
                                                    $naam = [
                                                        'risico_classificatie' => ZaaktypeBlueprint::eigenschapNaam($mapping, 'risico_classificatie'),
                                                        'risico_toelichting' => ZaaktypeBlueprint::eigenschapNaam($mapping, 'risico_toelichting'),
                                                    ];

                                                    // Find existing eigenschappen
                                                    foreach ($record->openzaak->eigenschappen as $item) {
                                                        if ($item->naam === $naam['risico_classificatie']) {
                                                            $eigenschappen['risico_classificatie'] = $item;
                                                        } elseif ($item->naam === $naam['risico_toelichting']) {
                                                            $eigenschappen['risico_toelichting'] = $item;
                                                        }

                                                        if ($eigenschappen['risico_classificatie'] && $eigenschappen['risico_toelichting']) {
                                                            break;
                                                        }
                                                    }

                                                    // Load catalogi eigenschappen if needed
                                                    $catalogiEigenschappen = null;
                                                    if (! $eigenschappen['risico_classificatie'] || ! $eigenschappen['risico_toelichting']) {
                                                        $catalogiEigenschappen = $openzaak->catalogi()->eigenschappen()->index(['zaaktype' => $record->openzaak->zaaktype])->collect()->map(fn ($eigenschap) => EigenschapData::from($eigenschap));
                                                    }

                                                    // Handle risico_classificatie
                                                    if ($eigenschappen['risico_classificatie']) {
                                                        // Eigenschap exists, update it
                                                        $openzaak->zaken()->zaken()->zaakeigenschappen($record->openzaak->uuid)->patch($eigenschappen['risico_classificatie']->uuid, [
                                                            'waarde' => $data['risico_classificatie'],
                                                        ]);
                                                    } else {
                                                        // Eigenschap doesn't exist, create it
                                                        $catalogiEigenschap = $catalogiEigenschappen->firstWhere('naam', $naam['risico_classificatie']);
                                                        if ($catalogiEigenschap) {
                                                            $openzaak->zaken()->zaken()->zaakeigenschappen($record->openzaak->uuid)->store([
                                                                'zaak' => $record->openzaak->url,
                                                                'eigenschap' => (string) $catalogiEigenschap->url,
                                                                'waarde' => $data['risico_classificatie'],
                                                            ]);
                                                        } else {
                                                            $success = false;
                                                        }
                                                    }

                                                    // Handle risico_toelichting
                                                    if ($eigenschappen['risico_toelichting']) {
                                                        // Eigenschap exists, update it
                                                        $openzaak->zaken()->zaken()->zaakeigenschappen($record->openzaak->uuid)->patch($eigenschappen['risico_toelichting']->uuid, [
                                                            'waarde' => $data['risico_toelichting'],
                                                        ]);
                                                    } else {
                                                        // Eigenschap doesn't exist, create it
                                                        $catalogiEigenschap = $catalogiEigenschappen->firstWhere('naam', $naam['risico_toelichting']);
                                                        if ($catalogiEigenschap) {
                                                            $openzaak->zaken()->zaken()->zaakeigenschappen($record->openzaak->uuid)->store([
                                                                'zaak' => $record->openzaak->url,
                                                                'eigenschap' => (string) $catalogiEigenschap->url,
                                                                'waarde' => $data['risico_toelichting'],
                                                            ]);
                                                        } else {
                                                            $success = false;
                                                        }
                                                    }

                                                    if ($success) {
                                                        // update local reference for dispaying the new value immidiately
                                                        $record->reference_data = new ZaakReferenceData(...array_merge($record->reference_data->toArray(), ['risico_classificatie' => $data['risico_classificatie'], 'risico_toelichting' => $data['risico_toelichting']]));
                                                        $record->save();

                                                        // Clear the cached ZGW data so a subsequent edit in the same
                                                        // session reads the freshly stored eigenschappen instead of
                                                        // re-taking the create branch on a stale cache (which would
                                                        // attempt a duplicate and be rejected by the backend).
                                                        $record->clearZgwCache();

                                                        Notification::make()
                                                            ->success()
                                                            ->title(__('Risico classificatie en toelichting zijn gewijzigd'))
                                                            ->send();
                                                    } else {
                                                        Notification::make()
                                                            ->danger()
                                                            ->title(__('Er is iets misgegaan bij het wijzigen van de risico classificatie'))
                                                            ->send();
                                                    }
                                                } catch (\Throwable $e) {
                                                    report($e);

                                                    Notification::make()
                                                        ->danger()
                                                        ->title(__('Er is iets misgegaan bij het wijzigen van de risico classificatie'))
                                                        ->send();
                                                }
                                            }),
                                    ])),
                                TextEntry::make('reference_data.intern_zaaknummer')
                                    ->label(__('resources/zaak.columns.intern_zaaknummer.label'))
                                    ->placeholder(__('municipality/resources/zaak.infolist.sections.actions.actions.edit_intern_zaaknummer.placeholder'))
                                    ->afterLabel(Schema::end([
                                        Icon::make('heroicon-o-pencil-square'),
                                        Action::make('editInternZaaknummer')
                                            ->label(__('municipality/resources/zaak.infolist.sections.actions.actions.edit_intern_zaaknummer.label'))
                                            ->fillForm(function (Zaak $record): array {
                                                /** @var ZaakReferenceData $referenceData */
                                                $referenceData = $record->reference_data;

                                                return [
                                                    'intern_zaaknummer' => $referenceData->intern_zaaknummer,
                                                ];
                                            })
                                            ->schema([
                                                TextInput::make('intern_zaaknummer')
                                                    ->label(__('municipality/resources/zaak.infolist.sections.actions.actions.edit_intern_zaaknummer.fields.intern_zaaknummer.label'))
                                                    ->maxLength(255)
                                                    ->required(),
                                            ])
                                            ->action(function (array $data, Zaak $record) {
                                                $openzaak = Zgw::connection($record->zgwConnectionName());
                                                $eigenschapNaam = self::internZaaknummerEigenschapNaam($record);
                                                $eigenschap = Arr::first($record->openzaak->eigenschappen, fn ($item) => $item->naam === $eigenschapNaam);
                                                $writtenToZgw = true;

                                                if ($eigenschap) {
                                                    $openzaak->zaken()->zaken()->zaakeigenschappen($record->openzaak->uuid)->patch($eigenschap->uuid, [
                                                        'waarde' => $data['intern_zaaknummer'],
                                                    ]);
                                                } else {
                                                    $catalogiEigenschap = $openzaak->catalogi()->eigenschappen()->index(['zaaktype' => $record->openzaak->zaaktype])
                                                        ->collect()
                                                        ->map(fn ($item) => EigenschapData::from($item))
                                                        ->firstWhere('naam', $eigenschapNaam);

                                                    if ($catalogiEigenschap) {
                                                        $openzaak->zaken()->zaken()->zaakeigenschappen($record->openzaak->uuid)->store([
                                                            'zaak' => $record->openzaak->url,
                                                            'eigenschap' => (string) $catalogiEigenschap->url,
                                                            'waarde' => $data['intern_zaaknummer'],
                                                        ]);
                                                    } else {
                                                        // The zaaktype does not know the eigenschap. Every
                                                        // eigenschap is optional, so keep the internal
                                                        // zaaknummer in Eventloket instead of failing the
                                                        // action. It is written to the zaaksysteem on the
                                                        // next edit if the eigenschap is added later.
                                                        $writtenToZgw = false;
                                                    }
                                                }

                                                $record->reference_data = new ZaakReferenceData(...array_merge($record->reference_data->toArray(), ['intern_zaaknummer' => $data['intern_zaaknummer']]));
                                                $record->save();
                                                $record->clearZgwCache();

                                                if ($writtenToZgw) {
                                                    Notification::make()
                                                        ->success()
                                                        ->title(__('Intern zaaknummer is gewijzigd'))
                                                        ->send();

                                                    return;
                                                }

                                                Notification::make()
                                                    ->success()
                                                    ->title(__('municipality/resources/zaak.infolist.sections.actions.actions.edit_intern_zaaknummer.notifications.saved_locally.title'))
                                                    ->body(__('municipality/resources/zaak.infolist.sections.actions.actions.edit_intern_zaaknummer.notifications.saved_locally.body'))
                                                    ->send();
                                            }),
                                        Action::make('deleteInternZaaknummer')
                                            ->label(__('municipality/resources/zaak.infolist.sections.actions.actions.delete_intern_zaaknummer.label'))
                                            ->icon('heroicon-o-trash')
                                            ->color('danger')
                                            ->iconButton()
                                            ->requiresConfirmation()
                                            ->visible(fn (Zaak $record) => ! empty($record->reference_data->intern_zaaknummer))
                                            ->action(function (Zaak $record) {
                                                $eigenschapNaam = self::internZaaknummerEigenschapNaam($record);
                                                $eigenschap = Arr::first($record->openzaak->eigenschappen, fn ($item) => $item->naam === $eigenschapNaam);

                                                if ($eigenschap) {
                                                    Zgw::connection($record->zgwConnectionName())->zaken()->zaken()->zaakeigenschappen($record->openzaak->uuid)->delete($eigenschap->uuid);
                                                }

                                                $record->reference_data = new ZaakReferenceData(...array_merge($record->reference_data->toArray(), ['intern_zaaknummer' => null]));
                                                $record->save();
                                                $record->clearZgwCache();

                                                Notification::make()
                                                    ->success()
                                                    ->title(__('Intern zaaknummer is verwijderd'))
                                                    ->send();
                                            }),
                                    ])),
                                TextEntry::make('reference_data.status_name')
                                    ->label(__('resources/zaak.columns.status.label'))
                                    ->afterLabel(Schema::end([
                                        Icon::make('heroicon-o-pencil-square'),
                                        Action::make('editStatus')
                                            ->label(__('municipality/resources/zaak.infolist.sections.actions.actions.edit_status.label'))
                                            ->fillForm(function (Zaak $record): array {
                                                return [
                                                    'status' => $record->openzaak->statustype_url,
                                                ];
                                            })
                                            ->schema([
                                                Select::make('status')
                                                    ->label(__('resources/zaak.columns.status.label'))
                                                    ->options(function () use ($zaak) {
                                                        return Zgw::connection($zaak->zgwConnectionName())->catalogi()->statustypen()->index(['zaaktype' => $zaak->openzaak->zaaktype])->collect()->where('isEindstatus', false)->pluck('omschrijving', 'url')->toArray();
                                                    })->required(),
                                            ])
                                            ->action(function (array $data, Zaak $record) {
                                                if ($data['status'] != $record->openzaak->statustype_url) {
                                                    $oldStatus = $record->reference_data->status_name;
                                                    $openzaak = Zgw::connection($record->zgwConnectionName());
                                                    $statusType = StatusTypeData::from(ZgwResource::byUrl($record->zgwConnectionName(), $data['status']));

                                                    $openzaak->zaken()->statussen()->store([
                                                        'zaak' => $record->openzaak->url,
                                                        'datumStatusGezet' => Carbon::now()->setTimezone('UTC')->toAtomString(),
                                                        'statustoelichting' => __('Status gezet via :app', ['app' => config('app.name')]),
                                                        'statustype' => $data['status'],
                                                    ]);

                                                    if ($statusType->volgnummer == 1) {
                                                        $record->handled_status_set_by_user_id = null;
                                                    } else {
                                                        $record->handled_status_set_by_user_id = auth()->id();
                                                    }

                                                    /** @disregard */
                                                    $record->reference_data = new ZaakReferenceData(...array_merge($record->reference_data->toArray(), ['status_name' => $statusType->omschrijving])); // @phpstan-ignore assign.propertyReadOnly
                                                    $record->save();

                                                    $record->clearZgwCache();

                                                    if ($oldStatus != $record->reference_data->status_name) {
                                                        foreach ($record->organisation->users as $user) {
                                                            /** @var OrganiserUser $user */
                                                            $user->notify(new ZaakStatusChanged($record, $oldStatus));
                                                        }
                                                    }

                                                    Notification::make()
                                                        ->success()
                                                        ->title(__('Status is gewijzigd'))
                                                        ->send();
                                                } else {
                                                    Notification::make()
                                                        ->info()
                                                        ->title(__('De geselecteerde status is gelijk aan de huidige status'))
                                                        ->send();
                                                }
                                            })
                                            ->modalSubmitAction(fn (Action $action) => $action->label(__('Opslaan'))),
                                    ]))
                                    ->formatStateUsing(function (Zaak $record, $state) {
                                        /** @var MunicipalityUser $user */
                                        $user = $record->handledStatusSetByUser;
                                        if ($user) {
                                            $state .= new HtmlString(Blade::render(
                                                '<p class="text-xs text-gray-500 dark:text-gray-400 mt-2">'.__('municipality/resources/zaak.infolist.sections.actions.handled_status_set_by.label', ['user' => '<span class="font-medium">'.e($user->name).'</span>']).'</p>'
                                            ));
                                        }

                                        return $state;
                                    })
                                    ->html(true),
                                // ->afterContent(function (Zaak $record) {
                                //     if ($record->handled_status_set_by_user_id) {
                                //         $user = $record->handledStatusSetByUser;
                                //         if ($user) {
                                //             return new HtmlString(Blade::render(
                                //                 '<p class="text-xs text-gray-500 dark:text-gray-400 mt-2">'.__('municipality/resources/zaak.infolist.sections.actions.handled_status_set_by.label', ['user' => '<span class="font-medium">'.$user->name.'</span>']).'</p>'
                                //             ));
                                //         }
                                //     }
                                //     return null;
                                // })
                            ])
                            ->columnSpan(4)
                            ->hidden(fn (Zaak $record) => $record->is_imported || $record->reference_data->resultaat || ! $record->behandelaarCanChangeStatus() || ! in_array(auth()->user()->role, [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Coordinator, Role::Reviewer, Role::Admin])),
                        self::resultaatSection(),
                        Tabs::make('Tabs')
                            ->persistTabInQueryString()
                            ->hidden(fn (Zaak $record) => $record->is_imported)
                            ->tabs([
                                Tab::make('besluiten')
                                    ->label(__('municipality/resources/zaak.infolist.tabs.decisions.label'))
                                    ->icon('heroicon-o-briefcase')
                                    ->schema([
                                        Livewire::make(BesluitenInfolist::class, ['zaak' => $schema->model])->key('besluiten-table-'.($schema->model->id ?? 'new')),
                                    ])
                                    ->visible(fn (Zaak $record) => $record->showsTab('besluiten') && $record->besluiten->count() > 0),
                                Tab::make('documents')
                                    ->label(__('municipality/resources/zaak.infolist.tabs.documents.label'))
                                    ->icon('heroicon-o-document')
                                    ->schema([
                                        Livewire::make(ZaakDocumentsTable::class, ['zaak' => $schema->model])->key('documents-table-'.($schema->model->id ?? 'new')),
                                    ])
                                    ->visible(fn (Zaak $record) => $record->showsTab('bestanden')),
                                Tab::make('Organisatievragen')
                                    ->label(__('municipality/resources/zaak.infolist.tabs.messages.label'))
                                    ->icon('heroicon-o-chat-bubble-left')
                                    ->visible(fn (Zaak $record) => $record->showsTab('organisatievragen') && (Filament::getCurrentPanel()->getId() === 'municipality' || Filament::getCurrentPanel()->getId() === 'admin'))
                                    ->badge(function (Zaak $record) {
                                        $count = auth()->user()
                                            ->unreadMessages()
                                            ->whereHas('thread', fn ($query) => $query->organiser()->where('zaak_id', $record->id))
                                            ->count();

                                        return $count > 0 ? $count : null;
                                    })
                                    ->schema([
                                        Livewire::make(OrganiserThreadsRelationManager::class, fn (Zaak $record) => ['ownerRecord' => $record, 'pageClass' => ViewZaak::class])->key('organiser-threads-'.($schema->model->id ?? 'new')),
                                    ]),
                                Tab::make('advice_requests')
                                    ->label(__('municipality/resources/zaak.infolist.tabs.advice_requests.label'))
                                    ->icon('heroicon-o-question-mark-circle')
                                    ->visible(fn (Zaak $record) => $record->showsTab('adviesvragen'))
                                    ->badge(function (Zaak $record) {
                                        $count = auth()->user()
                                            ->unreadMessages()
                                            ->whereHas('thread', fn ($query) => $query->advice()->where('zaak_id', $record->id)->where('advice_status', '!=', AdviceStatus::Concept))
                                            ->count();

                                        return $count > 0 ? $count : null;
                                    })
                                    ->schema([
                                        Livewire::make(AdviceThreadRelationManager::class, fn (Zaak $record) => ['ownerRecord' => $record, 'pageClass' => ViewZaak::class])->key('advice-threads-'.($schema->model->id ?? 'new')),
                                    ]),
                                LocationsTab::make(),
                                Tab::make('related_cases')
                                    ->label(__('municipality/resources/zaak.infolist.tabs.related_cases.label'))
                                    ->icon('heroicon-o-share')
                                    ->visible(fn (Zaak $record) => $record->data_object_url && Zaak::where('data_object_url', $record->data_object_url)->where('id', '!=', $record->id)->exists())
                                    ->schema([
                                        Livewire::make(DeelzakenTable::class, ['zaak' => $schema->model])->key('deelzaken-table-'.($schema->model->id ?? 'new')),
                                    ]),
                            ])
                            ->columnSpanFull(),
                        Section::make(__('Geimporteerde gegevens'))
                            ->hidden(fn (Zaak $record) => ! $record->is_imported)
                            ->schema([
                                KeyValueEntry::make('imported_data')
                                    ->hiddenLabel()
                                    ->keyLabel(__('Sleutel'))
                                    ->valueLabel(__('Waarde'))
                                    ->columns(1),
                            ])->columnSpanFull(),

                    ]),
            ]));
    }

    /**
     * View URL for a related zaak, resolved via the current panel so the
     * link stays inside the panel of the viewer (municipality, advisor,
     * admin or organiser).
     */
    private static function zaakViewUrl(?Zaak $zaak): ?string
    {
        if (! $zaak instanceof Zaak) {
            return null;
        }

        return Filament::getResourceUrl($zaak, 'view');
    }
}
