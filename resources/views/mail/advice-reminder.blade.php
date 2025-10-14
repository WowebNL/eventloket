<x-mail::message>
# {{ __('notification/advice-reminder.mail.greeting') }}

{{ __('notification/advice-reminder.mail.body', ['advisory' => $advisory, 'municipality' => $municipality, 'event' => $event, 'when' => $when]) }}

<x-mail::panel>
{{ $title }}
</x-mail::panel>

<x-mail::button :url="$viewUrl">
    {{ __('notification/advice-reminder.mail.button') }}
</x-mail::button>
</x-mail::message>
