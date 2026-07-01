<?php

use App\Support\Uploads\DocumentUploadType;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    // Ensure the test uses the real configured allowlist.
    expect(config('app.document_file_types'))->toBeArray();
});

test('it allows all configured mime types (including wildcards)', function () {
    $allowed = array_values((array) config('app.document_file_types', []));

    foreach ($allowed as $allowedMime) {
        if (! is_string($allowedMime) || $allowedMime === '') {
            continue;
        }

        // Wildcards like image/* should accept a concrete subtype.
        $reportedMime = str_ends_with($allowedMime, '/*')
            ? (substr($allowedMime, 0, -1).'png')
            : $allowedMime;

        $file = File::create('test.bin', 1)->mimeType($reportedMime);

        $validator = Validator::make(
            ['file' => $file],
            ['file' => [DocumentUploadType::fileUploadRule()]],
        );

        expect($validator->passes())
            ->toBeTrue("Expected allowed MIME {$allowedMime} (reported as {$reportedMime}) to pass validation");
    }
});

test('it rejects a comprehensive list of executable mime types', function () {
    $executableMimeTypes = DocumentUploadType::disallowedExecutableMimeTypes();

    // Hard safety: ensure we never accidentally allow these via configuration.
    $allowed = array_values((array) config('app.document_file_types', []));

    foreach ($executableMimeTypes as $mime) {
        expect(in_array($mime, $allowed, true))->toBeFalse("Executable MIME {$mime} must not be in app.document_file_types");

        $file = File::create('malware.bin', 1)->mimeType($mime);

        $validator = Validator::make(
            ['file' => $file],
            ['file' => [DocumentUploadType::fileUploadRule()]],
        );

        expect($validator->passes())
            ->toBeFalse("Expected executable MIME {$mime} to be rejected");
    }

    // Also reject overly-broad wildcards that would permit executables.
    $dangerousWildcards = DocumentUploadType::disallowedWildcards();

    foreach ($dangerousWildcards as $wildcard) {
        expect(in_array($wildcard, $allowed, true))
            ->toBeFalse("Wildcard {$wildcard} must not be in app.document_file_types");
    }
});

test('it allows eml even when reported as text/html, if it looks like an email', function () {
    $content = <<<'EML'
From: sender@example.com
To: receiver@example.com
Subject: Test email
Date: Tue, 1 Jan 2030 10:00:00 +0000
Message-ID: <test@example.com>
MIME-Version: 1.0
Content-Type: text/html; charset=UTF-8

<html><body><p>Hello</p></body></html>
EML;

    $file = File::createWithContent('mail.eml', $content)->mimeType('text/html');

    $validator = Validator::make(
        ['file' => $file],
        ['file' => [DocumentUploadType::fileUploadRule()]],
    );

    expect($validator->passes())->toBeTrue();
});

test('it rejects eml with binary content (nul bytes)', function () {
    $content = "From: sender@example.com\n\0\nSubject: nope\n";

    $file = File::createWithContent('mail.eml', $content)->mimeType('text/plain');

    $validator = Validator::make(
        ['file' => $file],
        ['file' => [DocumentUploadType::fileUploadRule()]],
    );

    expect($validator->passes())->toBeFalse();
});

test('it allows msg when it has the OLE magic bytes (even if mime is generic)', function () {
    $oleMagic = hex2bin('d0cf11e0a1b11ae1');
    $content = $oleMagic.str_repeat("\0", 32);

    $file = File::createWithContent('mail.msg', $content)->mimeType('application/octet-stream');

    $validator = Validator::make(
        ['file' => $file],
        ['file' => [DocumentUploadType::fileUploadRule()]],
    );

    expect($validator->passes())->toBeTrue();
});

test('it allows a genuine gpx file even when detected as text/xml', function () {
    $content = <<<'GPX'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="Test" xmlns="http://www.topografix.com/GPX/1/1">
  <trk><name>Route</name><trkseg>
    <trkpt lat="50.85" lon="5.69"></trkpt>
    <trkpt lat="50.86" lon="5.70"></trkpt>
  </trkseg></trk>
</gpx>
GPX;

    $file = File::createWithContent('route.gpx', $content)->mimeType('text/xml');

    $validator = Validator::make(
        ['file' => $file],
        ['file' => [DocumentUploadType::fileUploadRule()]],
    );

    expect($validator->passes())->toBeTrue();
});

test('it rejects an arbitrary text/xml file that is renamed to .gpx', function () {
    $content = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<note><to>Tove</to><from>Jani</from><body>Not a route</body></note>
XML;

    $file = File::createWithContent('fake.gpx', $content)->mimeType('text/xml');

    $validator = Validator::make(
        ['file' => $file],
        ['file' => [DocumentUploadType::fileUploadRule()]],
    );

    expect($validator->passes())->toBeFalse();
});

test('it still rejects plain xml files (text/xml is not broadly allowed)', function () {
    // Genuine GPX content, but without the .gpx extension the content gate is
    // not triggered and text/xml is not on the MIME allowlist.
    $content = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gpx xmlns="http://www.topografix.com/GPX/1/1"><trk></trk></gpx>
XML;

    $file = File::createWithContent('route.xml', $content)->mimeType('text/xml');

    $validator = Validator::make(
        ['file' => $file],
        ['file' => [DocumentUploadType::fileUploadRule()]],
    );

    expect($validator->passes())->toBeFalse();
});

test('it rejects a gpx file that misses the topografix namespace', function () {
    $content = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1"><trk></trk></gpx>
XML;

    $file = File::createWithContent('route.gpx', $content)->mimeType('text/xml');

    $validator = Validator::make(
        ['file' => $file],
        ['file' => [DocumentUploadType::fileUploadRule()]],
    );

    expect($validator->passes())->toBeFalse();
});

test('it rejects a gpx file with binary content (nul bytes)', function () {
    $content = "<?xml version=\"1.0\"?>\0<gpx xmlns=\"http://www.topografix.com/GPX/1/1\"></gpx>";

    $file = File::createWithContent('route.gpx', $content)->mimeType('text/xml');

    $validator = Validator::make(
        ['file' => $file],
        ['file' => [DocumentUploadType::fileUploadRule()]],
    );

    expect($validator->passes())->toBeFalse();
});

test('storedFileIsAllowed content-checks stored gpx files by extension', function () {
    $write = function (string $content): string {
        $path = tempnam(sys_get_temp_dir(), 'gpxtest');
        file_put_contents($path, $content);

        return $path;
    };

    $realGpx = $write(<<<'GPX'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1"><trk></trk></gpx>
GPX);
    $fakeGpx = $write('<?xml version="1.0"?><note><body>nope</body></note>');
    $realPdf = $write('%PDF-1.4');

    try {
        // The disk reports text/xml for both XML files; only genuine GPX passes.
        expect(DocumentUploadType::storedFileIsAllowed($realGpx, 'text/xml', 'route.gpx'))->toBeTrue()
            ->and(DocumentUploadType::storedFileIsAllowed($fakeGpx, 'text/xml', 'route.gpx'))->toBeFalse()
            // A .gpx name on non-XML content is still rejected by the content check.
            ->and(DocumentUploadType::storedFileIsAllowed($realPdf, 'application/pdf', 'route.gpx'))->toBeFalse()
            // Non-gpx extensions fall back to the MIME allowlist.
            ->and(DocumentUploadType::storedFileIsAllowed($realPdf, 'application/pdf', 'report.pdf'))->toBeTrue()
            ->and(DocumentUploadType::storedFileIsAllowed($realGpx, 'text/xml', 'route.xml'))->toBeFalse();
    } finally {
        @unlink($realGpx);
        @unlink($fakeGpx);
        @unlink($realPdf);
    }
});

test('determineFormaat maps .gpx to the canonical gpx mime type', function () {
    Storage::fake();
    config(['filesystems.default' => 'local']);

    $path = 'documents/route.gpx';
    Storage::put($path, '<?xml version="1.0"?><gpx xmlns="http://www.topografix.com/GPX/1/1"></gpx>');

    expect(DocumentUploadType::determineFormaat($path, 'route.gpx'))->toBe('application/gpx+xml');
});

test('determineFormaat prefers extension mapping over storage mime detection', function () {
    Storage::fake();
    config(['filesystems.default' => 'local']);

    $path = 'documents/mail.eml';
    Storage::put($path, "From: a@b.com\nSubject: x\n\nHi");

    // Even if storage MIME detection differs in some environments,
    // the mapping should win for .eml.
    $formaat = DocumentUploadType::determineFormaat($path, 'mail.eml');

    expect($formaat)->toBe('message/rfc822');
});

test('extensionForMimeType resolves standard and custom mime types', function () {
    expect(DocumentUploadType::extensionForMimeType('application/pdf'))->toBe('pdf')
        ->and(DocumentUploadType::extensionForMimeType('message/rfc822'))->toBe('eml')
        ->and(DocumentUploadType::extensionForMimeType('application/vnd.ms-outlook'))->toBe('msg')
        ->and(DocumentUploadType::extensionForMimeType('application/octet-stream-unknown'))->toBeNull()
        ->and(DocumentUploadType::extensionForMimeType(''))->toBeNull();
});

test('ensureFileNameHasExtension appends an extension only when missing', function () {
    expect(DocumentUploadType::ensureFileNameHasExtension('Vergunningaanvraag', 'application/pdf'))->toBe('Vergunningaanvraag.pdf')
        ->and(DocumentUploadType::ensureFileNameHasExtension('rapport.pdf', 'application/pdf'))->toBe('rapport.pdf')
        ->and(DocumentUploadType::ensureFileNameHasExtension('', 'application/pdf'))->toBe('document.pdf')
        ->and(DocumentUploadType::ensureFileNameHasExtension('naamloos', 'application/octet-stream-unknown'))->toBe('naamloos');
});
