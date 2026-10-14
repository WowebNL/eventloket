<x-mail::message>

{!! str($content)->sanitizeHtml() !!}
@if (! empty($omittedAttachments))

{{ __('notification/result.mail.omitted_attachments.intro', ['app_name' => config('app.name')]) }}

{{-- The file names come from whoever uploaded the document, so they are not
     safe to hand to the Markdown parser: Blade escapes the HTML in them, but
     the parser runs afterwards and would still turn Markdown syntax in a name
     into real markup, a link included. The names are therefore written as a
     raw HTML list rather than as a Markdown list. A raw HTML block is passed
     through untouched, so nothing inside it is parsed as Markdown, while the
     Blade echo keeps escaping the HTML. Both defences are needed; either one
     alone leaves the other syntax live. --}}
<ul>
@foreach ($omittedAttachments as $bestandsnaam)
<li>{{ $bestandsnaam }}</li>
@endforeach
</ul>
@endif

<x-mail::button :url="$url">
{{ __('Bekijk in :app_name', ['app_name' => config('app.name')]) }}
</x-mail::button>
</x-mail::message>
