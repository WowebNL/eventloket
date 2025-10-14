<x-mail::message>
# {{ __('notification/new-organiser-thread.mail.greeting') }}

{{ __('notification/new-organiser-thread.mail.body', ['organisation' => $organisation, 'municipality' => $municipality, 'event' => $event]) }}

<x-mail::panel>
{{ $title }}
</x-mail::panel>

<x-mail::button :url="$viewUrl">
    {{ __('notification/new-organiser-thread.mail.button') }}
</x-mail::button>
</x-mail::message>
