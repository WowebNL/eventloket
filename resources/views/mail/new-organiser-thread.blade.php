<x-mail::message>
# {{ $isOrganiserMail ? __('notification/new-organiser-thread.organiser_mail.greeting') : __('notification/new-organiser-thread.mail.greeting') }}

{{ $isOrganiserMail ? __('notification/new-organiser-thread.organiser_mail.body', [ 'event' => $event]) : __('notification/new-organiser-thread.mail.body', ['organisation' => $organisation, 'municipality' => $municipality, 'event' => $event]) }}

<x-mail::panel>
{{ $title }}
</x-mail::panel>

<x-mail::button :url="$viewUrl">
    {{ $isOrganiserMail ? __('notification/new-organiser-thread.organiser_mail.button') : __('notification/new-organiser-thread.mail.button') }}
</x-mail::button>
</x-mail::message>
