<x-mail::message>
# {{ __('mail/advisory-invite.greeting') }}

{{ __('mail/advisory-invite.body', ['name' => $advisory->name]) }}

<x-mail::button :url="$acceptUrl">
{{ __('mail/advisory-invite.button') }}
</x-mail::button>
</x-mail::message>
