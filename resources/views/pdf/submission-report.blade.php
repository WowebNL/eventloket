<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Aanvraagformulier {{ $zaak->public_id }}</title>
    <style>
        @page { margin: 1.4cm 1.4cm 1.6cm 1.4cm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 8.5pt; line-height: 1.35; margin: 0; padding: 0; }
        h1 { font-size: 15pt; margin: 0 0 2pt 0; }
        h2 { font-size: 10.5pt; margin: 10pt 0 3pt 0; border-bottom: 1px solid #ccc; padding-bottom: 2pt; page-break-after: avoid; }
        .meta { color: #555; font-size: 8pt; margin-bottom: 10pt; }
        .meta strong { color: #111; }
        table.kv { width: 100%; table-layout: fixed; border-collapse: collapse; margin: 0 0 4pt 0; }
        table.kv th, table.kv td { text-align: left; vertical-align: top; padding: 1.5pt 8pt 1.5pt 0; border-bottom: 1px solid #eee; word-wrap: break-word; overflow-wrap: break-word; }
        table.kv td { padding-right: 0; padding-left: 8pt; }
        table.kv th { width: 50%; color: #444; font-weight: normal; }
        table.kv td { color: #111; width: 50%; }
        .row-label { background: #f0f0f0; font-weight: bold; padding: 2pt 0; }
        table.sub { width: 100%; table-layout: fixed; border-collapse: collapse; margin: 0; }
        table.sub th, table.sub td { text-align: left; vertical-align: top; padding: 1pt 8pt 1pt 0; border-bottom: 1px dashed #eee; font-size: 8pt; word-wrap: break-word; overflow-wrap: break-word; }
        table.sub td { padding-right: 0; padding-left: 8pt; }
        table.sub th { width: 50%; color: #555; font-weight: normal; }
        table.sub td { width: 50%; }
        table.tijden { width: 100%; border-collapse: collapse; margin: 2pt 0; }
        table.tijden th, table.tijden td { text-align: left; padding: 2pt 6pt; border: 1px solid #ddd; font-size: 8pt; }
        table.tijden thead th { background: #f0f0f0; font-weight: bold; color: #111; }
        table.tijden tbody th { font-weight: bold; color: #111; width: 28%; }
        .map-img { display: block; margin: 2pt 0; max-width: 100%; }
        .footer { position: fixed; bottom: -1.5cm; left: 0; right: 0; text-align: center; font-size: 9pt; color: #888; }
        .muted { color: #888; font-style: italic; }
        section { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>Aanvraagformulier {{ $naamEvenement }}</h1>
    <div class="meta">
        <div><strong>Zaaknummer:</strong> {{ $zaak->public_id }}</div>
        @if (! empty($risicoClassificatie))
            <div><strong>Risicoclassificatie:</strong> {{ $risicoClassificatie }}</div>
        @endif
        @if (! empty($indieningstermijnStatus))
            <div>
                <strong>Indieningstermijn:</strong>
                @if ($indieningstermijnStatus['withinDeadline'])
                    Binnen termijn ({{ $indieningstermijnStatus['weeks'] }} weken)
                @else
                    <span style="color: #d97706;">Buiten termijn ({{ $indieningstermijnStatus['weeks'] }} weken)</span>
                @endif
            </div>
        @endif
        <div><strong>Zaaktype:</strong> {{ $zaak->zaaktype?->name ?? '—' }}</div>
        @if (! empty($gemeenteNaam))
            <div><strong>Gemeente:</strong> {{ $gemeenteNaam }}</div>
        @endif
        <div><strong>Organisator:</strong> {{ $zaak->organisation?->name ?? '—' }}</div>
        <div><strong>Aanvrager:</strong> {{ $zaak->organiserUser?->name ?? '—' }}</div>
        <div><strong>Ingediend op:</strong> {{ $zaak->created_at?->timezone('Europe/Amsterdam')->translatedFormat('j F Y H:i') }}</div>
    </div>

    @forelse ($sections as $section)
        <section>
            <h2>{{ $section['title'] }}</h2>
            <table class="kv">
                @foreach ($section['entries'] as $entry)
                    @if (! empty($entry['table']))
                        {{-- Step-specifieke overzichts-tabel (zoals
                             het tijden-overzicht op de Tijden-stap):
                             een label-rij gevolgd door een echte
                             tabel met header en rijen. --}}
                        <tr>
                            <th colspan="2" class="row-label">{{ strip_tags((string) $entry['label']) }}</th>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding: 0;">
                                <table class="tijden">
                                    <thead>
                                        <tr>
                                            @foreach ($entry['table']['header'] as $kop)
                                                <th>{{ $kop }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($entry['table']['rows'] as $rij)
                                            <tr>
                                                @foreach ($rij as $i => $cel)
                                                    @if ($i === 0)
                                                        <th>{{ $cel }}</th>
                                                    @else
                                                        <td>{{ $cel === '' ? '—' : $cel }}</td>
                                                    @endif
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @elseif (! empty($entry['sub']))
                        {{-- Sub-entries: een rij in een Repeater (bv. een
                             locatie of route). Toon de label-rij als kop
                             en daaronder een sub-tabel met alle velden. --}}
                        <tr>
                            <th colspan="2" class="row-label">{{ strip_tags((string) $entry['label']) }}</th>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding: 0;">
                                <table class="sub">
                                    @foreach ($entry['sub'] as $subEntry)
                                        <tr>
                                            <th>{{ strip_tags((string) $subEntry['label']) }}</th>
                                            <td>
                                                @if (! empty($subEntry['svg']))
                                                    {!! $subEntry['svg'] !!}
                                                @else
                                                    {!! nl2br(e($subEntry['value'])) !!}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @else
                        <tr>
                            <th>{{ strip_tags((string) $entry['label']) }}</th>
                            <td>
                                @if (! empty($entry['svg']))
                                    {!! $entry['svg'] !!}
                                @elseif (! empty($entry['files']))
                                    {{-- FileUpload met één of meerdere bijlagen:
                                         <ul> per bestandsvraag. --}}
                                    <ul style="margin: 0; padding-left: 1.1em;">
                                        @foreach ($entry['files'] as $bestand)
                                            <li>{{ $bestand }}</li>
                                        @endforeach
                                    </ul>
                                @elseif (! empty($entry['list']))
                                    @php $lijstTelt = count($entry['list']); @endphp
                                    <ul style="margin: 0; padding-left: {{ $lijstTelt > 1 ? '1.1em' : '0' }}; list-style: {{ $lijstTelt > 1 ? 'disc' : 'none' }};">
                                        @foreach ($entry['list'] as $item)
                                            <li>{{ $item }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    {!! nl2br(e($entry['value'])) !!}
                                @endif
                            </td>
                        </tr>
                    @endif
                @endforeach
            </table>
        </section>
    @empty
        <p class="muted">Geen ingevulde velden gevonden.</p>
    @endforelse

    <section>
        <h2>Akkoordverklaring</h2>
        <table class="kv">
            <tr>
                <th>Akkoord met verwerking persoonsgegevens</th>
                <td>
                    @if (! empty($akkoordGegeven))
                        Ja, op {{ $zaak->created_at?->timezone('Europe/Amsterdam')->translatedFormat('j F Y · H:i') ?? '—' }}
                    @else
                        Niet vastgelegd
                    @endif
                </td>
            </tr>
        </table>
    </section>

    <div class="footer">
        Eventloket — Veiligheidsregio Zuid-Limburg · Gegenereerd op {{ now()->timezone('Europe/Amsterdam')->translatedFormat('j F Y H:i') }}
    </div>
</body>
</html>
