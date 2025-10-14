<x-mail::message>
# {{ __('notification/new-advice-thread.mail.greeting') }}

{{ __('notification/new-advice-thread.mail.body', ['advisory' => $advisory, 'municipality' => $municipality, 'event' => $event]) }}

<x-mail::panel>
{{ $title }}
</x-mail::panel>

<x-mail::button :url="$viewUrl">
    {{ __('notification/new-advice-thread.mail.button') }}
</x-mail::button>
</x-mail::message>
