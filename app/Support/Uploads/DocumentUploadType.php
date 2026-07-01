<?php

namespace App\Support\Uploads;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;

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

        // For formats whose safety cannot be established from the (spoofable)
        // MIME type alone, enforce content / magic validation regardless of the
        // reported MIME type. This avoids accepting arbitrary content disguised
        // with a safe MIME type, and lets us accept GPX (an XML dialect that is
        // commonly detected as the deliberately-not-allowlisted text/xml)
        // without opening up arbitrary XML.
        if (in_array($extension, ['eml', 'emlx', 'msg', 'gpx'], true)) {
            return match ($extension) {
                'eml', 'emlx' => self::looksLikeRfc822Email($file),
                'msg' => self::looksLikeOutlookMsg($file),
                'gpx' => self::looksLikeGpx((string) $file->getRealPath()),
            };
        }

        $mimeType = $file->getMimeType();

        if (is_string($mimeType) && self::mimeTypeIsAllowed($mimeType, $allowedMimeTypes)) {
            return true;
        }

        return false;
    }

    /**
     * Valideer een reeds opgeslagen bestand op basis van z'n MIME-type
     * (zoals door de disk gedetecteerd) tegen de geconfigureerde allowlist.
     *
     * Bedoeld voor flows die geen `UploadedFile` meer hebben — zoals een
     * queue-job die een eerder opgeslagen bijlage naar OpenZaak uploadt en
     * de upload-validatie niet via de form-request heeft zien langskomen.
     */
    public static function storedMimeTypeIsAllowed(string $mimeType): bool
    {
        $allowedMimeTypes = array_values((array) config('app.document_file_types', []));
        self::assertConfigurationIsSafe($allowedMimeTypes);

        return self::mimeTypeIsAllowed($mimeType, $allowedMimeTypes);
    }

    /**
     * Content-aware allowlist check for files that already live on disk.
     *
     * Mirrors {@see isAllowed()} for extensions whose safety cannot be
     * established from a MIME type alone. GPX is XML and is commonly detected
     * as text/xml, which we deliberately keep off the allowlist; we therefore
     * confirm the actual bytes are GPX. Every other extension falls back to the
     * MIME allowlist, so existing behaviour is unchanged.
     *
     * @param  string  $absolutePath  Absolute filesystem path to the stored file.
     * @param  string  $detectedMimeType  MIME type as detected by the storage disk.
     */
    public static function storedFileIsAllowed(string $absolutePath, string $detectedMimeType, ?string $originalFileName = null): bool
    {
        $extension = strtolower(pathinfo((string) $originalFileName, PATHINFO_EXTENSION));

        if ($extension === '') {
            $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        }

        if ($extension === 'gpx') {
            return self::looksLikeGpx($absolutePath);
        }

        return self::storedMimeTypeIsAllowed($detectedMimeType);
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
     * Validation rule suitable for Filament FileUpload and plain Laravel validators.
     *
     * Returns a named ValidationRule instance so that var_export() can reconstruct
     * it via __set_state(), making it compatible with `php artisan config:cache`.
     */
    public static function fileUploadRule(): DocumentUploadRule
    {
        return new DocumentUploadRule;
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

    /**
     * Derives a file extension (without leading dot) for a given MIME type.
     *
     * Prefers the application's own ext => mime mappings so that custom types
     * (e.g. message/rfc822 => eml, application/vnd.ms-outlook => msg) round-trip
     * correctly, then falls back to Symfony's MIME database for standard types.
     */
    public static function extensionForMimeType(string $mimeType): ?string
    {
        $mimeType = strtolower(trim($mimeType));

        if ($mimeType === '') {
            return null;
        }

        $map = config('app.document_mime_type_mappings', []);

        if (is_array($map)) {
            foreach ($map as $extension => $mappedMime) {
                if (is_string($mappedMime) && strtolower($mappedMime) === $mimeType) {
                    return (string) $extension;
                }
            }
        }

        $extensions = MimeTypes::getDefault()->getExtensions($mimeType);

        return $extensions[0] ?? null;
    }

    /**
     * Ensures a filename carries an extension, deriving one from the MIME type
     * when it is missing. Filenames that already have an extension are returned
     * unchanged; an empty filename falls back to "document".
     */
    public static function ensureFileNameHasExtension(string $fileName, string $mimeType): string
    {
        $fileName = trim($fileName);

        if ($fileName === '') {
            $fileName = 'document';
        }

        if (pathinfo($fileName, PATHINFO_EXTENSION) !== '') {
            return $fileName;
        }

        $extension = self::extensionForMimeType($mimeType);

        if ($extension === null || $extension === '') {
            return $fileName;
        }

        return $fileName.'.'.$extension;
    }

    /**
     * Confirms that a file is a genuine GPX document by inspecting its bytes.
     *
     * GPX is an XML dialect, so we require an XML prologue, the <gpx> root
     * element and the mandatory Topografix GPX namespace. Requiring all three
     * means a plain text/xml file (which we intentionally keep off the
     * allowlist) cannot pass by accident, while every schema-valid GPX export
     * does.
     */
    private static function looksLikeGpx(string $path): bool
    {
        if ($path === '') {
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

        // Reject binary files (NUL bytes) masquerading as XML.
        if (str_contains($head, "\0")) {
            return false;
        }

        // Strip an optional UTF-8 BOM before inspecting the document prologue.
        if (str_starts_with($head, "\xEF\xBB\xBF")) {
            $head = substr($head, 3);
        }

        // The document must open with the XML declaration or directly with the
        // <gpx> root element.
        $prologue = ltrim($head);

        if (preg_match('/^<\?xml\b/i', $prologue) !== 1 && preg_match('/^<gpx\b/i', $prologue) !== 1) {
            return false;
        }

        // Never accept documents that declare a DOCTYPE: real GPX files carry
        // none, and refusing them avoids XML entity-expansion tricks.
        if (preg_match('/<!DOCTYPE/i', $head) === 1) {
            return false;
        }

        // Require both the <gpx> root element and the official Topografix GPX
        // namespace (used by GPX 1.0 and 1.1). Both are mandated by the schema.
        return preg_match('/<gpx\b/i', $head) === 1
            && preg_match('#https?://www\.topografix\.com/GPX#i', $head) === 1;
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
