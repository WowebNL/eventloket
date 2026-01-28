<?php

namespace App\Support\Uploads;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class DocumentUploadType
{
    /**
     * MIME types that should never be accepted as document uploads.
     * This is intentionally conservative and focuses on common executable/script formats.
     *
     * @return array<int, string>
     */
    public static function disallowedExecutableMimeTypes(): array
    {
        return [
            // PHP
            'application/x-php',
            'application/php',
            'application/x-httpd-php',
            'application/x-httpd-php-source',
            'text/x-php',
            'text/php',

            // Python
            'text/x-python',
            'application/x-python-code',

            // Shell / scripts
            'application/x-sh',
            'application/x-shellscript',
            'text/x-shellscript',
            'text/x-sh',
            'application/x-bash',
            'application/x-csh',
            'application/x-tcsh',

            // Perl / Ruby
            'text/x-perl',
            'application/x-perl',
            'text/x-ruby',
            'application/x-ruby',

            // Node / JS
            'application/javascript',
            'text/javascript',
            'application/x-javascript',

            // Windows binaries / installers
            'application/x-msdownload',
            'application/x-msdos-program',
            'application/x-dosexec',
            'application/vnd.microsoft.portable-executable',
            'application/x-ms-installer',
            'application/x-bat',
            'application/x-msi',

            // ELF / macOS binaries (commonly reported)
            'application/x-executable',
            'application/x-pie-executable',
            'application/x-mach-binary',

            // Java archives
            'application/java-archive',
            'application/x-java-archive',
            'application/x-jar',

            // Generic executable labels
            'application/x-binary',
            'application/x-object',
        ];
    }

    /**
     * Wildcards that should never be used for uploads.
     *
     * @return array<int, string>
     */
    public static function disallowedWildcards(): array
    {
        return [
            'application/*',
            'text/*',
        ];
    }

    /**
     * Fail-fast if the configured upload allowlist contains dangerous entries.
     *
     * @param  array<int, string>  $allowedMimeTypes
     */
    public static function assertConfigurationIsSafe(array $allowedMimeTypes): void
    {
        foreach (self::disallowedWildcards() as $wildcard) {
            if (in_array($wildcard, $allowedMimeTypes, true)) {
                throw new \LogicException("Unsafe upload configuration: [{$wildcard}] is not allowed in app.document_file_types.");
            }
        }

        foreach (self::disallowedExecutableMimeTypes() as $mime) {
            if (in_array($mime, $allowedMimeTypes, true)) {
                throw new \LogicException("Unsafe upload configuration: executable MIME type [{$mime}] is not allowed in app.document_file_types.");
            }
        }
    }

    /**
     * @param  array<int, string>  $allowedMimeTypes
     */
    public static function isAllowed(UploadedFile $file, array $allowedMimeTypes): bool
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        // For email-like formats, enforce content / magic validation regardless of MIME type.
        // This avoids accepting arbitrary content disguised with a safe MIME type.
        if (in_array($extension, ['eml', 'emlx', 'msg'], true)) {
            return match ($extension) {
                'eml', 'emlx' => self::looksLikeRfc822Email($file),
                'msg' => self::looksLikeOutlookMsg($file),
            };
        }

        $mimeType = $file->getMimeType();

        if (is_string($mimeType) && self::mimeTypeIsAllowed($mimeType, $allowedMimeTypes)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<int, string>  $allowedMimeTypes
     */
    private static function mimeTypeIsAllowed(string $mimeType, array $allowedMimeTypes): bool
    {
        foreach ($allowedMimeTypes as $allowed) {
            if (! is_string($allowed) || $allowed === '') {
                continue;
            }

            if ($mimeType === $allowed) {
                return true;
            }

            if (str_ends_with($allowed, '/*')) {
                $prefix = substr($allowed, 0, -1); // keep trailing '/'

                if (str_starts_with($mimeType, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Laravel closure validation rule suitable for Filament FileUpload.
     */
    public static function fileUploadRule(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            $allowedMimeTypes = array_values((array) config('app.document_file_types', []));

            // Ensure a misconfiguration fails fast.
            self::assertConfigurationIsSafe($allowedMimeTypes);

            $files = is_array($value) ? $value : [$value];

            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                if (self::isAllowed($file, $allowedMimeTypes)) {
                    continue;
                }

                $fail(__('Bestandstype niet toegestaan.'));
            }
        };
    }

    /**
     * Determines the MIME type to send to external APIs (e.g. OpenZaak) based on the original filename.
     * Falls back to storage MIME detection when no mapping exists.
     */
    public static function determineFormaat(string $storedPath, ?string $originalFileName = null): string
    {
        $fileName = (string) ($originalFileName ?? '');
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $map = config('app.document_mime_type_mappings', []);

        if ($extension !== '' && is_array($map) && array_key_exists($extension, $map)) {
            return (string) $map[$extension];
        }

        return (string) Storage::mimeType($storedPath);
    }

    private static function looksLikeRfc822Email(UploadedFile $file): bool
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            return false;
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $head = @fread($handle, 16384);
        @fclose($handle);

        if (! is_string($head) || $head === '') {
            return false;
        }

        // Reject binary files (NUL bytes) masquerading as text.
        if (str_contains($head, "\0")) {
            return false;
        }

        // EMLX starts with a length header line, then the RFC822 message.
        // Look for common RFC822 header fields within the first chunk.
        return (bool) preg_match('/\r?\n(?:from|to|cc|bcc|subject|date|message-id|mime-version):/i', "\n".$head);
    }

    private static function looksLikeOutlookMsg(UploadedFile $file): bool
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            return false;
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $magic = @fread($handle, 8);
        @fclose($handle);

        if (! is_string($magic) || strlen($magic) !== 8) {
            return false;
        }

        // OLE Compound File header (used by Outlook .msg).
        return bin2hex($magic) === 'd0cf11e0a1b11ae1';
    }
}
