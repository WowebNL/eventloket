<x-mail::message>
# {{ __('mail/organisation-invite.greeting') }}

{{ __('mail/organisation-invite.body', ['name' => $organisation->name]) }}

<x-mail::button :url="$acceptUrl">
{{ __('mail/organisation-invite.button') }}
</x-mail::button>
</x-mail::message>
