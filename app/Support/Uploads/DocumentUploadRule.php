<?php

namespace App\Support\Uploads;

use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Named validation rule class for document uploads.
 *
 * Must be a named class (not anonymous) so that var_export() can reconstruct it
 * via __set_state(), making it compatible with `php artisan config:cache`.
 */
final class DocumentUploadRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        $allowedMimeTypes = array_values((array) config('app.document_file_types', []));

        // Ensure a misconfiguration fails fast.
        DocumentUploadType::assertConfigurationIsSafe($allowedMimeTypes);

        $files = is_array($value) ? $value : [$value];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            if (DocumentUploadType::isAllowed($file, $allowedMimeTypes)) {
                continue;
            }

            $fail(__('Bestandstype niet toegestaan.'));
        }
    }

    /**
     * Required for var_export() / config:cache compatibility.
     * Called when the cached config is loaded and this object is reconstructed.
     *
     * @param  array<string, mixed>  $array
     */
    public static function __set_state(array $array): static
    {
        return new self;
    }
}
