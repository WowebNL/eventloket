<x-mail::message>
# {{ $isOrganiserMail ? __('notification/new-organiser-thread-message.organiser_mail.greeting') : __('notification/new-organiser-thread-message.mail.greeting') }}

{{ $isOrganiserMail ? __('notification/new-organiser-thread-message.organiser_mail.body', ['event' => $event]) : __('notification/new-organiser-thread-message.mail.body', ['sender' => $sender, 'organisation' => $organisation, 'event' => $event]) }}

<x-mail::panel>
    {{ $title }}
</x-mail::panel>

<x-mail::button :url="$viewUrl">
    {{ $isOrganiserMail ? __('notification/new-organiser-thread-message.organiser_mail.button') : __('notification/new-organiser-thread-message.mail.button') }}
</x-mail::button>
</x-mail::message>
