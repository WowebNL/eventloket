<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DestructionListStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case ReadyToReview = 'ready_to_review';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Deleting = 'deleting';
    case Deleted = 'deleted';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return __("enums/destruction_list_status.{$this->value}.label");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::ReadyToReview => 'info',
            self::ChangesRequested => 'warning',
            self::Approved => 'success',
            self::Deleting => 'warning',
            self::Deleted => 'success',
            self::Failed => 'danger',
        };
    }

    /**
     * @return array<DestructionListStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::ReadyToReview],
            self::ReadyToReview => [self::ChangesRequested, self::Approved],
            self::ChangesRequested => [self::ReadyToReview],
            self::Approved => [self::Deleting],
            self::Deleting => [self::Deleted, self::Failed],
            self::Failed => [self::Deleting],
            self::Deleted => [],
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested]);
    }
}
