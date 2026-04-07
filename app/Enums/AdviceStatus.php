<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AdviceStatus: string implements HasLabel
{
    case Concept = 'concept';
    case Asked = 'asked';
    case InProgress = 'in_progress';
    case AdvisoryReplied = 'advisory_replied';
    case Approved = 'approved';
    case NeedsMoreInfo = 'needs_more_info';
    case ApprovedWithConditions = 'approved_with_conditions';
    case Rejected = 'rejected';
    case NoReaction = 'no_reaction';

    /** @return self[] */
    public static function activeStatuses(): array
    {
        return [
            self::Asked,
            self::InProgress,
            self::AdvisoryReplied,
            self::NeedsMoreInfo,
        ];
    }

    public function isActive(): bool
    {
        return in_array($this, self::activeStatuses(), true);
    }

    public function getLabel(): string
    {
        return __("enums/advice-status.{$this->value}.label");
    }
}
