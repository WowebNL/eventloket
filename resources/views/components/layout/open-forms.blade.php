<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Eventloket</title>

    <!-- Load Open Forms stylesheet and SDK bundle -->
    <link rel="stylesheet" href="{{ config('services.open_forms.base_url') }}/static/sdk/open-forms-sdk.css" />
    <link rel="stylesheet" href="{{ config('services.open_forms.base_url') }}/static/bundles/public-styles.css" />
    <script src="{{ config('services.open_forms.base_url') }}/static/sdk/open-forms-sdk.js"></script>
</head>
<body>
    {{ $slot }}
</body>
</html>
