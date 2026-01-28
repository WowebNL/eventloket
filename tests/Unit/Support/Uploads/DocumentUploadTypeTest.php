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
