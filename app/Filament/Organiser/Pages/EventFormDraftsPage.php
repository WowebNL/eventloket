<?php

declare(strict_types=1);

namespace App\Filament\Organiser\Pages;

use App\Enums\MunicipalityFormQuestionType;
use App\Enums\ZaaktypeRole;
use App\EventForm\Persistence\Draft;
use App\EventForm\Persistence\DraftLimitReached;
use App\EventForm\Persistence\DraftStore;
use App\EventForm\Persistence\PrefillLoader;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\Schema\Steps\Vragenboom2Step;
use App\EventForm\State\FormState;
use App\EventForm\Support\ExtraQuestions;
use App\Models\MunicipalityFormQuestion;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Selection screen in front of the event form: shows the autosaved drafts
 * of the logged-in organiser, so they can continue where they left off,
 * discard a draft or start a new (parallel) aanvraag. Without drafts a fresh
 * draft is created right away and the user is sent on to the form, so they
 * never notice this screen.
 *
 * The `?prefill_from_zaak=` contract ("Nieuwe aanvraag met deze gegevens" on
 * a submitted zaak) lands here as well: the prefill goes into a new draft, so
 * existing drafts are never overwritten.
 */
class EventFormDraftsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'aanvraag';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-plus';

    protected string $view = 'filament.organiser.pages.event-form-drafts-page';

    public function mount(): void
    {
        $user = $this->authUser();
        $tenant = $this->tenant();
        $store = app(DraftStore::class);

        $sourceZaakId = request()->query('prefill_from_zaak');

        $prefill = app(PrefillLoader::class)->load(
            $sourceZaakId,
            $user,
            $tenant,
        );

        if ($prefill instanceof FormState) {
            // Same ownership guard as PrefillLoader: never trust the raw
            // query param beyond the organisation of the current tenant.
            $sourceZaak = Zaak::query()
                ->where('id', $sourceZaakId)
                ->where('organisation_id', $tenant->id)
                ->first();

            // No conversion presets for a vooraankondiging that already
            // has a definitive aanvraag (the convert action is hidden
            // then, but the URL can be crafted); plain prefill still works.
            if ($sourceZaak?->isVooraankondiging() && $sourceZaak->opgevolgdDoor()->doesntExist()) {
                $this->applyVooraankondigingConversion($prefill, $sourceZaak);
            }

            try {
                $draft = $store->create($user, $tenant, $prefill, sourceZaakId: $sourceZaak?->id);
            } catch (DraftLimitReached) {
                $this->notifyLimitReachedForHergebruik();

                return;
            }

            // `bron=hergebruik` makes the form show a matching notification
            // (data from an earlier aanvraag) instead of the regular
            // "Concept hervat" one.
            $this->redirect(
                EventFormPage::getUrl(['draft' => $draft, 'bron' => 'hergebruik']),
                navigate: false,
            );

            return;
        }

        if ($store->listFor($user, $tenant)->isEmpty()) {
            // No drafts: skip the selection screen and start a fresh draft
            // right away. create() cannot hit the cap here.
            $draft = $store->create($user, $tenant, FormState::empty());
            $this->redirect(EventFormPage::getUrl(['draft' => $draft]), navigate: false);
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Draft::query()->ownedBy($this->authUser(), $this->tenant()))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('display_name')
                    ->label('Evenement')
                    ->weight('semibold'),
                TextColumn::make('current_step_key')
                    ->label('Voortgang')
                    // Per draft, because the "Aanvullende vragen" step only
                    // counts when the municipality of that draft has
                    // configured questions for it.
                    ->formatStateUsing(function (?string $state, Draft $record): string {
                        $uuids = EventFormSchema::stepUuidsInOrder($this->draftHasAanvullendeVragen($record));

                        return sprintf(
                            'Stap %d van %d',
                            $this->stepPosition($state, $uuids),
                            count($uuids),
                        );
                    })
                    // Without a default Filament renders an empty cell for a
                    // null state and skips formatStateUsing.
                    ->default(''),
                TextColumn::make('updated_at')
                    ->label('Laatst bewerkt')
                    ->dateTime('d-m-Y H:i'),
            ])
            ->recordActions([
                Action::make('doorgaan')
                    ->label('Doorgaan')
                    ->icon('heroicon-o-arrow-right')
                    ->url(fn (Draft $record): string => EventFormPage::getUrl(['draft' => $record])),
                Action::make('verwijderen')
                    ->label('Verwijderen')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Concept verwijderen?')
                    ->modalDescription('Hiermee verwijdert u dit concept en alle ingevulde gegevens. Dit kan niet ongedaan gemaakt worden.')
                    ->modalSubmitActionLabel('Ja, verwijderen')
                    ->action(fn (Draft $record) => app(DraftStore::class)->delete($record)),
            ])
            ->emptyStateHeading('Geen opgeslagen concepten')
            ->emptyStateDescription('Start een nieuwe aanvraag om te beginnen. Uw antwoorden worden tijdens het invullen automatisch opgeslagen.')
            ->paginated(false);
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('startNieuweAanvraag')
                ->label('Start nieuwe aanvraag')
                ->icon('heroicon-o-plus')
                ->action(function () {
                    $store = app(DraftStore::class);

                    try {
                        $draft = $store->create($this->authUser(), $this->tenant(), FormState::empty());
                    } catch (DraftLimitReached) {
                        $this->notifyLimitReached();

                        return;
                    }

                    $this->redirect(EventFormPage::getUrl(['draft' => $draft]), navigate: false);
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'Nieuwe aanvraag';
    }

    public function getSubheading(): ?string
    {
        return 'U heeft opgeslagen concepten. Ga verder waar u gebleven was, of start een nieuwe aanvraag. Uw antwoorden worden tijdens het invullen automatisch opgeslagen.';
    }

    public static function getNavigationLabel(): string
    {
        return __('Nieuwe aanvraag');
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    /**
     * "Definitieve aanvraag indienen" on a vooraankondiging: the copied
     * snapshot still says the organiser wants a vooraankondiging, which
     * would route the new aanvraag straight into the vooraankondiging
     * path again. Flip that choice to a regular aanvraag and preset the
     * link fields in Vragenboom2Step so the vooraankondiging is coupled
     * and its zaaknummer shows up locked in the form.
     */
    private function applyVooraankondigingConversion(FormState $prefill, Zaak $vooraankondiging): void
    {
        $prefill->setVariable('waarvoorWiltUEventloketGebruiken', 'evenement');
        $prefill->setVariable(Vragenboom2Step::HEEFT_VOORAANKONDIGING_FIELD, 'Ja');
        $prefill->setVariable(Vragenboom2Step::VOORAANKONDIGING_ZAAK_FIELD, $vooraankondiging->id);
        $prefill->setVariable(Vragenboom2Step::VOORAANKONDIGING_ZAAKNUMMER_FIELD, $vooraankondiging->public_id);

        $this->restoreExtraQuestionAnswers($prefill, $vooraankondiging);
    }

    /**
     * The generic prefill drops every answer to the municipality's own extra
     * questions, because a plain copy can end up at another municipality with
     * different questions. A conversion is the same event at the same
     * municipality, so the answers given in the vooraankondiging are put back.
     *
     * Only answers to questions the municipality of the vooraankondiging
     * still has active, matched on question id, and only for questions that
     * are asked on the vergunning path. A choice answer keeps only the options
     * the question still offers, so an option that was removed in the meantime
     * is not carried over.
     */
    private function restoreExtraQuestionAnswers(FormState $prefill, Zaak $vooraankondiging): void
    {
        $sourceValues = $vooraankondiging->form_state_snapshot['values'] ?? null;
        $municipality = $vooraankondiging->municipality;

        if (! is_array($sourceValues) || $municipality === null) {
            return;
        }

        $questions = $municipality->formQuestions()->where('is_active', true)->get();

        foreach ($questions as $question) {
            $applies = ExtraQuestions::appliesTo(
                ['show_for_aanvraag_types' => $question->show_for_aanvraag_types],
                ZaaktypeRole::Vergunning,
            );
            if (! $applies) {
                continue;
            }

            $key = ExtraQuestions::fieldKey(['id' => $question->id]);
            if (! array_key_exists($key, $sourceValues)) {
                continue;
            }

            $answer = $this->stillValidAnswer($question, $sourceValues[$key]);
            if ($answer !== null) {
                $prefill->setField($key, $answer);
            }
        }
    }

    /**
     * The copied answer in the shape the question currently expects, or null
     * when nothing of it is still answerable.
     *
     * @return string|list<string>|null
     */
    private function stillValidAnswer(MunicipalityFormQuestion $question, mixed $answer): string|array|null
    {
        $options = is_array($question->options) ? $question->options : [];

        return match ($question->type) {
            MunicipalityFormQuestionType::Text => is_string($answer) && trim($answer) !== '' ? $answer : null,
            MunicipalityFormQuestionType::Radio => is_string($answer) && in_array($answer, $options, true) ? $answer : null,
            MunicipalityFormQuestionType::Checkboxes => is_array($answer)
                ? (array_values(array_filter($answer, fn (mixed $option): bool => is_string($option) && in_array($option, $options, true))) ?: null)
                : null,
        };
    }

    /**
     * 1-based position of a step UUID in the wizard; unknown or empty is step 1.
     *
     * @param  list<string>  $uuids
     */
    private function stepPosition(?string $stepKey, array $uuids): int
    {
        if ($stepKey === null || $stepKey === '') {
            return 1;
        }

        $index = array_search($stepKey, $uuids, true);

        return $index === false ? 1 : $index + 1;
    }

    /**
     * Whether the wizard for this draft contains the "Aanvullende vragen"
     * step. Same check as `EventFormPage::hasAanvullendeVragen()`, but on the
     * stored state of the draft.
     */
    private function draftHasAanvullendeVragen(Draft $draft): bool
    {
        $snapshot = $draft->state;

        if (! is_array($snapshot)) {
            return false;
        }

        return ExtraQuestions::hasAny(FormState::fromSnapshot($snapshot));
    }

    private function notifyLimitReached(): void
    {
        Notification::make()
            ->danger()
            ->title('Maximum aantal concepten bereikt')
            ->body(sprintf(
                'U heeft het maximum van %d concepten bereikt. Verwijder eerst een concept om een nieuwe aanvraag te starten.',
                DraftStore::MAX_DRAFTS,
            ))
            // Stays until the user dismisses it: the text is too long to read
            // within the default auto-dismiss.
            ->persistent()
            ->send();
    }

    /**
     * Cap notification specific to "Nieuwe aanvraag met deze gegevens": makes
     * explicit that reuse needs a new draft as well, so it is not possible
     * while the maximum is reached.
     */
    private function notifyLimitReachedForHergebruik(): void
    {
        Notification::make()
            ->danger()
            ->title('Hergebruik niet mogelijk')
            ->body(sprintf(
                'Een nieuwe aanvraag met gegevens van een eerdere aanvraag wordt als nieuw concept gestart, en u heeft al het maximum van %d openstaande concepten. Verwijder hieronder eerst een concept dat u niet meer nodig heeft en probeer het daarna opnieuw.',
                DraftStore::MAX_DRAFTS,
            ))
            // Stays until the user dismisses it: the text is too long to read
            // within the default auto-dismiss.
            ->persistent()
            ->send();
    }

    private function authUser(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }

    private function tenant(): Organisation
    {
        /** @var Organisation $tenant */
        $tenant = Filament::getTenant();

        return $tenant;
    }
}
