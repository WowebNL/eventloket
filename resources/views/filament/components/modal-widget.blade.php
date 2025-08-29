@props([
    'widget',
    'record'
])

<div class="[&_.fi-ta-ctn]:![box-shadow:none] [&_.fi-ta-header-heading]:hidden -mx-6 gu-compact">
    @livewire($widget, ['record' => $record])
</div>
