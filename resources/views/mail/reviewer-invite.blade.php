<x-mail::message>
# {{ __('mail/reviewer-invite.greeting') }}

{{ __('mail/reviewer-invite.body', ['name' => $municipality->name]) }}

<x-mail::button :url="$acceptUrl">
{{ __('mail/reviewer-invite.button') }}
</x-mail::button>
</x-mail::message>
