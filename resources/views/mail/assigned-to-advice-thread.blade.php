<x-mail::message>
# {{ __('notification/assigned-to-advice-thread.mail.greeting') }}

{{ __('notification/assigned-to-advice-thread.mail.body', ['advisory' => $advisory, 'municipality' => $municipality, 'event' => $event]) }}

<x-mail::panel>
{{ $title }}
</x-mail::panel>

<x-mail::button :url="$viewUrl">
    {{ __('notification/assigned-to-advice-thread.mail.button') }}
</x-mail::button>
</x-mail::message>
