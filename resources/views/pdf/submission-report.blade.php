<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Inzendingsbewijs — {{ $zaak->public_id }}</title>
    <style>
        @page { margin: 2cm 2cm 2.5cm 2cm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 10.5pt; line-height: 1.45; }
        h1 { font-size: 18pt; margin: 0 0 4pt 0; }
        h2 { font-size: 12pt; margin: 14pt 0 6pt 0; border-bottom: 1px solid #ccc; padding-bottom: 3pt; page-break-after: avoid; }
        .meta { color: #555; font-size: 10pt; margin-bottom: 16pt; }
        .meta strong { color: #111; }
        table.kv { width: 100%; border-collapse: collapse; margin: 0 0 8pt 0; }
        table.kv th, table.kv td { text-align: left; vertical-align: top; padding: 4pt 6pt; border-bottom: 1px solid #eee; }
        table.kv th { width: 42%; color: #444; font-weight: normal; }
        table.kv td { color: #111; }
        .footer { position: fixed; bottom: -1.5cm; left: 0; right: 0; text-align: center; font-size: 9pt; color: #888; }
        .muted { color: #888; font-style: italic; }
        section { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>Inzendingsbewijs</h1>
    <div class="meta">
        <div><strong>Zaaknummer:</strong> {{ $zaak->public_id }}</div>
        <div><strong>Zaaktype:</strong> {{ $zaak->zaaktype?->name ?? '—' }}</div>
        <div><strong>Ingediend op:</strong> {{ $zaak->created_at?->timezone('Europe/Amsterdam')->translatedFormat('j F Y H:i') }}</div>
        <div><strong>Organisator:</strong> {{ $zaak->organisation?->name ?? '—' }}</div>
    </div>

    @forelse ($sections as $section)
        <section>
            <h2>{{ $section['title'] }}</h2>
            <table class="kv">
                @foreach ($section['entries'] as $entry)
                    <tr>
                        <th>{!! strip_tags((string) $entry['label']) !!}</th>
                        <td>{!! nl2br(e($entry['value'])) !!}</td>
                    </tr>
                @endforeach
            </table>
        </section>
    @empty
        <p class="muted">Geen ingevulde velden gevonden.</p>
    @endforelse

    <div class="footer">
        Eventloket — Veiligheidsregio Zuid-Limburg · Gegenereerd op {{ now()->timezone('Europe/Amsterdam')->translatedFormat('j F Y H:i') }}
    </div>
</body>
</html>
