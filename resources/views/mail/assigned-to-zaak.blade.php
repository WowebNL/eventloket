<x-mail::message>
# {{ __('notification/assigned-to-zaak.mail.greeting') }}

{{ __('notification/assigned-to-zaak.mail.body', ['event' => $event, 'municipality' => $municipality]) }}

<x-mail::button :url="$viewUrl">
    {{ __('notification/assigned-to-zaak.mail.button') }}
</x-mail::button>
</x-mail::message>
