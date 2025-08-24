<x-mail::message>
# {{ __('mail/admin-invite.greeting') }}

{{ __('mail/admin-invite.body') }}

<x-mail::button :url="$acceptUrl">
{{ __('mail/admin-invite.button') }}
</x-mail::button>
</x-mail::message>
