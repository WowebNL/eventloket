@livewire(
    \App\Livewire\ConnectionVerifier::class,
    ['connection' => $connection],
    key('connection-verifier-'.$connection->getKey())
)
