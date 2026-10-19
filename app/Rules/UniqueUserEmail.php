<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Checks that no account uses the address yet, whatever case it was typed in.
 *
 * User stores email addresses in lowercase, and PostgreSQL compares strings
 * case sensitively. A plain unique rule on the raw input therefore misses an
 * existing account when only the case differs, and the insert then fails on
 * the unique index instead of returning a validation error.
 *
 * Soft deleted accounts count as taken, because the unique index still holds
 * their address.
 */
final class UniqueUserEmail implements ValidationRule
{
    public function __construct(private readonly ?Model $ignore = null) {}

    /**
     * The rule for a Filament field, ignoring the record the form edits (if any),
     * the same way Filament's own unique() does by default.
     */
    public static function forField(): Closure
    {
        return static fn (Field $component): self => new self($component->getRecord());
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Anything else is rejected by the field's own email rule.
        if (! is_string($value) || $value === '') {
            return;
        }

        $query = User::withTrashed()->where('email', strtolower($value));

        if ($this->ignore instanceof User && $this->ignore->exists) {
            $query->whereKeyNot($this->ignore->getOriginal($this->ignore->getKeyName()));
        }

        if ($query->exists()) {
            $fail('validation.unique')->translate();
        }
    }
}
