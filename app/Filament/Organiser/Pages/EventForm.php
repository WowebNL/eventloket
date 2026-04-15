<?php

namespace App\Filament\Organiser\Pages;

use App\Filament\Organiser\Pages\EventFormSteps\ContactgegevensStep;
use App\Filament\Organiser\Pages\EventFormSteps\EvenementStep;
use App\Filament\Organiser\Pages\EventFormSteps\LocatieStep;
use App\Filament\Organiser\Pages\EventFormSteps\TijdenStep;
use App\Filament\Organiser\Pages\EventFormSteps\VooraankondigingStep;
use App\Models\Organisation;
use App\Models\Users\OrganiserUser;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;

class EventForm extends Page implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    protected static ?string $slug = 'event-form';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.organiser.pages.event-form';

    public function mount(): void
    {
        /** @var OrganiserUser $user */
        $user = Filament::auth()->user();
        /** @var Organisation $tenant */
        $tenant = Filament::getTenant();

        // Prefill contact data from the authenticated user and organisation
        $this->form->fill([
            'voornaam' => $user->first_name,
            'achternaam' => $user->last_name,
            'email' => $user->email,
            'telefoon' => $user->phone ?? '',
            'kvk' => $tenant->coc_number ?? '',
            'organisatie_naam' => $tenant->name,
            'organisatie_email' => $tenant->email ?? '',
            'organisatie_telefoon' => $tenant->phone ?? '',
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Wizard::make([
                    ContactgegevensStep::make(),
                    EvenementStep::make(),
                    LocatieStep::make(),
                    TijdenStep::make(),
                    VooraankondigingStep::make(),
                ])
                    ->submitAction(
                        Action::make('submit')
                            ->label('Aanvraag indienen')
                            ->action('submit')
                    ),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        // TODO: ZGW registration (create zaak, Objects API record, etc.)

        Notification::make()
            ->success()
            ->title('Aanvraag ingediend')
            ->body('Uw evenement aanvraag is succesvol ingediend.')
            ->send();
    }

    public function getTitle(): string
    {
        return '';
    }

    public static function getNavigationLabel(): string
    {
        return __('Nieuwe aanvraag');
    }
}
