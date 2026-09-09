<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\MunicipalityZgwConnectionResource;
use App\Models\MunicipalityZgwConnection;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditMunicipalityZgwConnection extends EditRecord
{
    protected static string $resource = MunicipalityZgwConnectionResource::class;

    /**
     * Set once the user has acknowledged, in the confirmation modal, that saving
     * a critical connection change takes the connection offline. Reset after
     * every save so a later edit prompts again.
     */
    public bool $connectionCriticalChangeConfirmed = false;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Never surface the stored (encrypted) client secret in the form; leaving
     * the field blank keeps the existing secret on save.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['client_secret']);

        return $data;
    }

    /**
     * Keep the vertrouwelijkheid map sparse so unconfigured roles fall back to
     * the hardcoded defaults rather than being stored as an empty map.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return MunicipalityZgwConnectionResource::pruneVertrouwelijkheidMap($data);
    }

    /**
     * Changing an endpoint, credential or the ZGW version silently takes the
     * connection offline ({@see MunicipalityZgwConnectionObserver}). Warn the
     * user about that consequence and require an explicit confirmation before the
     * save goes through, whichever way it was triggered (save button, Enter,
     * mod+s). The observer still enforces the deactivation regardless.
     */
    protected function beforeSave(): void
    {
        if ($this->connectionCriticalChangeConfirmed) {
            return;
        }

        if (! $this->connectionCriticalFieldsAreChanged()) {
            return;
        }

        $this->mountAction('confirmConnectionCriticalChange');

        $this->halt();
    }

    protected function afterSave(): void
    {
        $this->connectionCriticalChangeConfirmed = false;
    }

    /**
     * The modal shown when the user tries to save a change to a critical
     * connection field. Confirming re-runs the save with the acknowledgement
     * flag set; cancelling simply closes the modal and leaves the form untouched.
     */
    public function confirmConnectionCriticalChangeAction(): Action
    {
        return Action::make('confirmConnectionCriticalChange')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalHeading(__('municipality/resources/zgw_connection.actions.save_critical_change.modal_heading'))
            ->modalDescription(__('municipality/resources/zgw_connection.actions.save_critical_change.modal_description'))
            ->modalSubmitActionLabel(__('municipality/resources/zgw_connection.actions.save_critical_change.confirm'))
            ->action(function (): void {
                $this->connectionCriticalChangeConfirmed = true;

                $this->save();
            });
    }

    /**
     * Whether the pending form state changes any field that defines which
     * external ZGW the connection talks to. Mirrors the dirty check in
     * {@see MunicipalityZgwConnectionObserver} so the warning fires exactly when
     * the connection would be deactivated. A left-blank client secret keeps the
     * existing one and therefore does not count as a change.
     */
    protected function connectionCriticalFieldsAreChanged(): bool
    {
        $record = $this->getRecord();

        foreach (MunicipalityZgwConnection::CONNECTION_CRITICAL_FIELDS as $field) {
            if ($field === 'client_secret') {
                if (filled($this->data['client_secret'] ?? null)) {
                    return true;
                }

                continue;
            }

            $new = $this->normalizeConnectionValue($this->data[$field] ?? null);
            $old = $this->normalizeConnectionValue($record->getAttribute($field));

            if ($new !== $old) {
                return true;
            }
        }

        return false;
    }

    /**
     * Treat empty string, empty array and null as the same "unset" value so a
     * blank form field does not read as a change from a null database column.
     */
    private function normalizeConnectionValue(mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return $value;
    }
}
