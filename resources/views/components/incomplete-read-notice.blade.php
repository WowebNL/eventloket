{{--
    Says out loud that a list on screen is shorter than it should be.

    A gap that looks like a complete list is worse than an error, so anything
    that leaves resources out because an external API refused them puts this
    above the list. The wording is passed in: what is missing differs per list,
    and the reader should be told which one.
--}}
@props(['title', 'description'])

<div
    role="status"
    class="mb-4 flex items-start gap-3 rounded-xl bg-warning-50 p-4 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:ring-warning-400/30"
>
    <x-filament::icon icon="heroicon-o-exclamation-circle" class="mt-0.5 h-5 w-5 shrink-0 text-warning-500" />

    <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $title }}</p>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
    </div>
</div>
