<?php

declare(strict_types=1);

namespace App\Filament\Organiser\Pages;

use App\EventForm\Persistence\Draft;
use App\EventForm\Persistence\DraftLimitReached;
use App\EventForm\Persistence\DraftStore;
use App\EventForm\Persistence\PrefillLoader;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\State\FormState;
use App\Models\Organisation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Keuzescherm vóór het evenementformulier: toont de automatisch
 * opgeslagen concepten van de ingelogde organisator zodat 'ie kan
 * doorgaan waar 'ie gebleven was, een concept kan weggooien of een
 * nieuwe (parallelle) aanvraag kan starten. Zonder concepten wordt
 * direct een vers concept aangemaakt en doorgestuurd naar het
 * formulier — de gebruiker merkt dit scherm dan niet op.
 *
 * Het `?prefill_from_zaak=`-contract ("Nieuwe aanvraag met deze
 * gegevens" op een ingediende zaak) landt ook hier: de prefill wordt
 * in een níéuw concept gezet zodat bestaande concepten nooit worden
 * overschreven.
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

        $prefill = app(PrefillLoader::class)->load(
            request()->query('prefill_from_zaak'),
            $user,
            $tenant,
        );

        if ($prefill instanceof FormState) {
            try {
                $draft = $store->create($user, $tenant, $prefill);
            } catch (DraftLimitReached) {
                $this->notifyLimitReachedForHergebruik();

                return;
            }

            // `bron=hergebruik` laat het formulier een passende melding
            // tonen ("gegevens van een eerdere aanvraag") i.p.v. de
            // gewone "Concept hervat"-melding.
            $this->redirect(
                EventFormPage::getUrl(['draft' => $draft, 'bron' => 'hergebruik']),
                navigate: false,
            );

            return;
        }

        if ($store->listFor($user, $tenant)->isEmpty()) {
            // Geen concepten: sla het keuzescherm over en start direct
            // een vers concept. create() kan hier niet op de cap stuiten.
            $draft = $store->create($user, $tenant, FormState::empty());
            $this->redirect(EventFormPage::getUrl(['draft' => $draft]), navigate: false);
        }
    }

    public function table(Table $table): Table
    {
        $totalSteps = count(EventFormSchema::stepUuidsInOrder());

        return $table
            ->query(Draft::query()->ownedBy($this->authUser(), $this->tenant()))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('display_name')
                    ->label('Evenement')
                    ->weight('semibold'),
                TextColumn::make('current_step_key')
                    ->label('Voortgang')
                    ->formatStateUsing(fn (?string $state): string => sprintf(
                        'Stap %d van %d',
                        $this->stepPosition($state),
                        $totalSteps,
                    ))
                    // Zonder default rendert Filament een lege cel bij
                    // null-state en wordt formatStateUsing overgeslagen.
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

    /** 1-based positie van een step-UUID in de wizard; onbekend/leeg = stap 1. */
    private function stepPosition(?string $stepKey): int
    {
        if ($stepKey === null || $stepKey === '') {
            return 1;
        }

        $index = array_search($stepKey, EventFormSchema::stepUuidsInOrder(), true);

        return $index === false ? 1 : $index + 1;
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
            // Blijft staan tot de gebruiker 'm zelf wegklikt — de tekst is
            // te lang om binnen de standaard auto-dismiss te lezen.
            ->persistent()
            ->send();
    }

    /**
     * Cap-melding specifiek voor "Nieuwe aanvraag met deze gegevens":
     * maakt expliciet dat ook hergebruik een nieuw concept nodig heeft
     * en dus niet kan zolang het maximum openstaat.
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
            // Blijft staan tot de gebruiker 'm zelf wegklikt — de tekst is
            // te lang om binnen de standaard auto-dismiss te lezen.
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
