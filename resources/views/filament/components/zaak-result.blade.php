<div>
<span class="text-xl font-bold text-gray-900">
    {{ __('Resultaat') }}:
</span>

    <span class="text-xl text-blue-700 font-medium" 
    @if(str_contains($resultaat, 'wordt momementeel verwerkt'))
    wire:poll.5s="refreshResultaat"
    @endif
    >
    {{ $resultaat }}
    </span>
</div>