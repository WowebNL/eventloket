<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Bevestiging van uw aanvraag</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111; line-height: 1.5; max-width: 640px; margin: 0 auto; padding: 24px;">
    <h1 style="font-size: 20px; margin: 0 0 12px;">Bedankt voor uw aanvraag</h1>

    <p>Beste {{ $zaak->organiserUser?->first_name ?? 'organisator' }},</p>

    <p>Wij hebben uw aanvraag voor het evenement
        <strong>{{ $reference?->naam_evenement ?? 'uw evenement' }}</strong>
        in goede orde ontvangen. U vindt de belangrijkste gegevens hieronder.</p>

    <table style="border-collapse: collapse; margin: 16px 0;">
        <tr>
            <td style="padding: 4px 12px 4px 0; color: #555;">Zaaknummer</td>
            <td style="padding: 4px 0;"><strong>{{ $zaak->public_id }}</strong></td>
        </tr>
        <tr>
            <td style="padding: 4px 12px 4px 0; color: #555;">Gemeente</td>
            <td style="padding: 4px 0;">{{ $zaak->zaaktype?->municipality?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 12px 4px 0; color: #555;">Type aanvraag</td>
            <td style="padding: 4px 0;">{{ $zaak->zaaktype?->name ?? '—' }}</td>
        </tr>
        @if ($reference?->start_evenement)
            <tr>
                <td style="padding: 4px 12px 4px 0; color: #555;">Start</td>
                <td style="padding: 4px 0;">
                    {{ $reference->start_evenement_datetime->translatedFormat('j F Y · H:i') }}
                </td>
            </tr>
        @endif
        @if ($reference?->eind_evenement)
            <tr>
                <td style="padding: 4px 12px 4px 0; color: #555;">Einde</td>
                <td style="padding: 4px 0;">
                    {{ $reference->eind_evenement_datetime->translatedFormat('j F Y · H:i') }}
                </td>
            </tr>
        @endif
        <tr>
            <td style="padding: 4px 12px 4px 0; color: #555;">Ingediend op</td>
            <td style="padding: 4px 0;">
                {{ $zaak->created_at?->timezone('Europe/Amsterdam')->translatedFormat('j F Y · H:i') }}
            </td>
        </tr>
    </table>

    <p>In de bijlage vindt u een PDF met het volledige inzendingsbewijs.
       Bewaar deze voor uw administratie.</p>

    <p>U kunt de status van uw aanvraag volgen op uw persoonlijke pagina in Eventloket.</p>

    <p style="margin-top: 32px; color: #888; font-size: 12px;">
        Met vriendelijke groet,<br>
        Eventloket — Veiligheidsregio Zuid-Limburg
    </p>
</body>
</html>
