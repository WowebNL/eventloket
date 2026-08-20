<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\BlueprintFindingType;
use App\Enums\Role;
use App\Enums\ZaaktypeKoppelingIssue;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Zaaktype;
use App\ValueObjects\ZGW\BlueprintFinding;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Warns platform admins and the municipality's koppeling-beheerders about a
 * zaaktype-koppeling problem detected from a ZGW zaaktypen notification (or a
 * targeted sync): no valid definitief version anymore (with or without a main
 * fallback), a restore, missing blueprint prerequisites on a new version, or
 * an unavailable main-catalogus zaaktype.
 */
class ZaaktypeKoppelingWarning extends BaseNotification
{
    /**
     * @param  list<BlueprintFinding>  $findings
     * @param  string|null  $fallbackZaaktypeName  The linked main fallback for Unavailable; null means no fallback was found and new aanvragen for this role will fail.
     */
    public function __construct(
        protected Zaaktype $zaaktype,
        protected ZaaktypeKoppelingIssue $issue,
        protected ?Municipality $municipality = null,
        protected array $findings = [],
        protected ?string $fallbackZaaktypeName = null,
    ) {}

    public static function getLabel(): string|Htmlable|null
    {
        return __('notification/zaaktype-koppeling-warning.label');
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__("notification/zaaktype-koppeling-warning.mail.subject.{$this->issue->value}", [
                'zaaktype' => $this->zaaktype->name,
            ]))
            ->markdown('mail.zaaktype-koppeling-warning', [
                'issue' => $this->issue->value,
                'body' => $this->body(),
                'findingLines' => $this->findingLines(),
                'viewUrl' => $this->getViewUrl($notifiable),
            ]);
    }

    public function toDatabase(User $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title(__("notification/zaaktype-koppeling-warning.database.title.{$this->issue->value}", [
                'zaaktype' => $this->zaaktype->name,
            ]))
            ->body(implode("\n", [$this->body(), ...$this->findingLines()]))
            ->actions([
                Action::make('view')
                    ->label(__('View'))
                    ->url($this->getViewUrl($notifiable))
                    ->markAsRead(),
            ]);

        match ($this->issue) {
            ZaaktypeKoppelingIssue::Restored => $notification->success(),
            ZaaktypeKoppelingIssue::BlueprintIncomplete => $notification->warning(),
            default => $notification->danger(),
        };

        return $notification->getDatabaseMessage();
    }

    public function logSubject(): Model
    {
        return $this->zaaktype;
    }

    private function body(): string
    {
        $municipalityName = $this->municipality->name ?? '';

        $body = __("notification/zaaktype-koppeling-warning.body.{$this->issue->value}", [
            'zaaktype' => $this->zaaktype->name,
            'municipality' => $municipalityName,
        ]);

        if ($this->issue === ZaaktypeKoppelingIssue::Unavailable) {
            $body .= ' '.($this->fallbackZaaktypeName !== null
                ? __('notification/zaaktype-koppeling-warning.body.fallback_active', ['fallback' => $this->fallbackZaaktypeName])
                : __('notification/zaaktype-koppeling-warning.body.fallback_missing'));
        }

        return $body;
    }

    /**
     * Human-readable line per blueprint finding.
     *
     * @return list<string>
     */
    private function findingLines(): array
    {
        return array_map(function (BlueprintFinding $finding): string {
            $slot = str_starts_with($finding->slot, 'eigenschap:')
                ? __('notification/zaaktype-koppeling-warning.slot.eigenschap', ['key' => substr($finding->slot, strlen('eigenschap:'))])
                : __("notification/zaaktype-koppeling-warning.slot.{$finding->slot}");

            return $finding->type === BlueprintFindingType::Missing
                ? __('notification/zaaktype-koppeling-warning.finding.missing', ['slot' => $slot])
                : __('notification/zaaktype-koppeling-warning.finding.mapped_value_not_found', ['slot' => $slot, 'expected' => $finding->expected]);
        }, $this->findings);
    }

    private function getViewUrl(User $notifiable): string
    {
        if ($notifiable->role === Role::Admin || $this->municipality === null) {
            return route('filament.admin.resources.zaaktypes.index');
        }

        return route('filament.municipality.settings.resources.municipality-zaaktype-mappings.index', [
            'tenant' => $this->municipality->id,
        ]);
    }
}
