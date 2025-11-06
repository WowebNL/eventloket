<?php

namespace App\Filament\Shared\Resources\MunicipalityVariables\Pages;

use App\Enums\MunicipalityVariableType;
use App\Models\MunicipalityVariable;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMunicipalityVariable extends EditRecord
{
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var MunicipalityVariable $record */
        $record->update($data);

        if ($record->type === MunicipalityVariableType::ReportQuestion && isset($data['order']) && $data['order']) {
            if ($record->order !== $data['order']) {
                $currentOrder = $record->order;
                // set the record to a temporary key to avoid unique key constraint issues
                $tempKey = 'temp_'.MunicipalityVariableType::ReportQuestion->value.'_'.$data['order'];
                $record->key = $tempKey;
                $record->save();

                // find the record that currently has the desired order and set it to the current order (switch places)
                $recordToUpdate = MunicipalityVariable::where('municipality_id', $record->municipality_id)
                    ->where('type', MunicipalityVariableType::ReportQuestion)
                    ->where('key', MunicipalityVariableType::ReportQuestion->value.'_'.$data['order'])
                    ->first();

                if ($recordToUpdate) {
                    $recordToUpdate->key = MunicipalityVariableType::ReportQuestion->value.'_'.$currentOrder;
                    $recordToUpdate->save();
                }

                // set the updated record's key to the desired order
                $record->key = MunicipalityVariableType::ReportQuestion->value.'_'.$data['order'];
                $record->save();
            }
        }

        return $record;
    }
}
