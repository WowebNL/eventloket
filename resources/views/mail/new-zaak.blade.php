<x-mail::message>
# {{ __("notification/new-zaak.mail.greeting.$type") }}

{{
    __("notification/new-zaak.mail.body.$type", [
        'event' => $event,
        'municipality' => $municipality,
    ])
}}

<x-mail::button :url="$viewUrl">
    {{ __("notification/new-zaak.mail.button.$type") }}
</x-mail::button>
</x-mail::message>
