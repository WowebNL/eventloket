<x-mail::message>
# {{ __('notification/new-zaak-document.mail.greeting') }}

{{
    __('notification/new-zaak-document.mail.body.' . $type, [
        'event' => $event,
        'municipality' => $municipality,
    ])
}}

<x-mail::button :url="$viewUrl">
    {{ __('notification/new-zaak-document.mail.button') }}
</x-mail::button>
</x-mail::message>
