<x-mail::message>
# {{ __('notification/zaak-released.mail.greeting') }}

{{ __('notification/zaak-released.mail.body', ['event' => $event, 'municipality' => $municipality, 'releasedBy' => $releasedBy]) }}

<x-mail::button :url="$viewUrl">
    {{ __('notification/zaak-released.mail.button') }}
</x-mail::button>
</x-mail::message>
