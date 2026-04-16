<?php

namespace App\Console\Commands\ZgwInstanceSetup;

use App\Models\Application;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Token;
use ReallySimpleJWT\Build;
use ReallySimpleJWT\Encoders\EncodeHS256;
use ReallySimpleJWT\Helper\Validator;
use ReallySimpleJWT\Interfaces\Secret as SecretInterface;

class RegisterOpenNotificatiesAbonnement extends Command
{
    protected $signature = 'app:register-open-notificaties-abonnement
                            {--url= : Basis-URL van de Open Notificaties API (bijv. http://host.docker.internal:8002)}
                            {--client-id= : Client ID voor de Open Notificaties API}
                            {--client-secret= : Client secret voor de Open Notificaties API}
                            {--dry-run : Simuleer de actie zonder daadwerkelijk iets aan te maken of op te slaan}';

    protected $description = 'Maakt een Passport-token aan voor de Open Notificaties webhook en registreert een abonnement op kanalen: zaken, besluiten, documenten, zaaktypen';

    private bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $baseUrl = $this->option('url');
        $clientId = $this->option('client-id');
        $clientSecret = $this->option('client-secret');

        if (empty($baseUrl) || empty($clientId) || empty($clientSecret)) {
            $this->error('--url, --client-id en --client-secret zijn verplicht.');
            $this->line('Voorbeeld: sail artisan app:register-open-notificaties-abonnement --url=http://host.docker.internal:8002 --client-id=eventloket --client-secret=geheim');

            return Command::FAILURE;
        }

        $callbackUrl = URL::route('api.open-notifications.listen');

        if ($this->dryRun) {
            $this->warn('DRY-RUN modus: er worden geen wijzigingen doorgevoerd.');
        }

        $this->newLine();
        $passportToken = $this->createPassportToken();
        if ($passportToken === null) {
            return Command::FAILURE;
        }

        $this->newLine();

        return $this->registerAbonnement($baseUrl, $clientId, $clientSecret, $callbackUrl, $passportToken);
    }

    // ---------------------------------------------------------------
    // Phase 1 — Passport token aanmaken
    // ---------------------------------------------------------------
    private function createPassportToken(): ?string
    {
        $this->info('=== Phase 1: Passport token aanmaken ===');

        // Find or create the Application record
        $this->line('Application "open-notificaties" zoeken of aanmaken...');

        /** @var Application $application */
        $application = Application::firstOrCreate(['name' => 'open-notificaties']);

        if ($application->wasRecentlyCreated) {
            $this->info('  Application aangemaakt (id: '.$application->id.')');
        } else {
            $this->line('  Bestaande application gevonden (id: '.$application->id.')');
        }

        // Find or create the Passport Client
        $this->line('Passport client zoeken of aanmaken...');

        $existingClient = Client::where('owner_type', Application::class)
            ->where('owner_id', $application->id)
            ->where('revoked', false)
            ->first();

        $clientSecret = Str::random(40);

        if ($existingClient) {
            $this->line('  Bestaande Passport client gevonden (id: '.$existingClient->id.')');
            $this->warn('  Het client secret is niet meer zichtbaar (opgeslagen als hash).');

            if (! $this->confirm('  Wil je de bestaande client intrekken en een nieuwe aanmaken?', false)) {
                $this->info('Gestopt. Voer opnieuw uit als je een nieuwe client wilt aanmaken.');

                return null;
            }

            if (! $this->dryRun) {
                $existingClient->update(['revoked' => true]);
                $this->line('  Bestaande client ingetrokken.');
            }
        }

        if ($this->dryRun) {
            $this->line('  [DRY-RUN] Nieuwe Passport client zou worden aangemaakt');
            $this->line('  [DRY-RUN] Nieuw token zou worden aangevraagd via /oauth/token');
            $this->line('  [DRY-RUN] expires_at zou worden ingesteld op NULL');

            return 'dry-run-token-placeholder';
        }

        /** @var Client $client */
        $client = Client::create([
            'owner_type' => Application::class,
            'owner_id' => $application->id,
            'name' => 'open-notificaties',
            'secret' => $clientSecret,
            'grant_types' => ['client_credentials'],
            'redirect_uris' => [],
            'revoked' => false,
        ]);

        $this->info('  Nieuwe Passport client aangemaakt (id: '.$client->id.')');

        // Request a client credentials token
        $this->line('Token ophalen via /oauth/token...');

        $tokenResponse = Http::asForm()->post(config('app.url').'/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $clientSecret,
            'scope' => 'notifications:receive',
        ]);

        if (! $tokenResponse->successful()) {
            $this->error('Token ophalen mislukt: HTTP '.$tokenResponse->status().' — '.$tokenResponse->body());

            return null;
        }

        $accessTokenString = $tokenResponse->json('access_token');

        // Parse the JWT to extract the token ID (jti claim) and set expires_at to null
        $parts = explode('.', $accessTokenString);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            $tokenId = $payload['jti'] ?? null;

            if ($tokenId) {
                Token::where('id', $tokenId)->update(['expires_at' => null]);
                $this->info('  expires_at ingesteld op NULL (nooit verlopen)');
            }
        }

        $this->newLine();
        $this->warn('╔══════════════════════════════════════════════════════════════╗');
        $this->warn('║  BELANGRIJK: Sla dit token veilig op — het wordt maar        ║');
        $this->warn('║  eenmaal getoond. Dit is de auth-waarde voor het abonnement. ║');
        $this->warn('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();
        $this->line('Bearer '.$accessTokenString);
        $this->newLine();

        return $accessTokenString;
    }

    // ---------------------------------------------------------------
    // Phase 2 — Abonnement registreren bij Open Notificaties API
    // ---------------------------------------------------------------
    private function registerAbonnement(string $baseUrl, string $clientId, string $clientSecret, string $callbackUrl, string $passportToken): int
    {
        $this->info('=== Phase 2: Abonnement registreren ===');

        $baseUrl = rtrim($baseUrl, '/');

        $jwt = $this->buildJwt($clientId, $clientSecret);
        $headers = [
            'Authorization' => 'Bearer '.$jwt,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Check for existing abonnement
        $this->line("Bestaande abonnementen ophalen van $baseUrl/api/v1/abonnement ...");
        $listResponse = Http::withHeaders($headers)->get($baseUrl.'/api/v1/abonnement');

        if ($listResponse->successful()) {
            $existing = collect($listResponse->json('results') ?? $listResponse->json() ?? [])
                ->first(fn (array $a) => ($a['callbackUrl'] ?? '') === $callbackUrl);

            if ($existing) {
                $this->warn('Er bestaat al een abonnement met callbackUrl: '.$callbackUrl);
                $this->line('Abonnement UUID: '.($existing['uuid'] ?? '(onbekend)'));

                if (! $this->confirm('Toch een nieuw abonnement aanmaken?', false)) {
                    $this->info('Gestopt. Bestaand abonnement blijft ongewijzigd.');

                    return Command::SUCCESS;
                }
            }
        } else {
            $this->warn('Kon bestaande abonnementen niet ophalen (HTTP '.$listResponse->status().'), doorgaan met aanmaken...');
        }

        $kanalen = [
            ['naam' => 'zaken', 'filters' => (object) []],
            ['naam' => 'besluiten', 'filters' => (object) []],
            ['naam' => 'documenten', 'filters' => (object) []],
            ['naam' => 'zaaktypen', 'filters' => (object) []],
        ];

        $payload = [
            'callbackUrl' => $callbackUrl,
            'auth' => 'Bearer '.$passportToken,
            'kanalen' => $kanalen,
        ];

        $this->newLine();
        $this->line('Abonnement payload:');
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->newLine();

        if ($this->dryRun) {
            $this->line('[DRY-RUN] POST naar '.$baseUrl.'/api/v1/abonnement zou worden verstuurd');

            return Command::SUCCESS;
        }

        $this->line('Abonnement aanmaken...');
        $response = Http::withHeaders($headers)->post($baseUrl.'/api/v1/abonnement', $payload);

        if (! $response->successful()) {
            $this->error('Abonnement aanmaken mislukt: HTTP '.$response->status().' — '.$response->body());

            return Command::FAILURE;
        }

        $this->info('Abonnement succesvol aangemaakt!');
        $this->newLine();
        $this->line(json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }

    private function buildJwt(string $clientId, string $clientSecret): string
    {
        $payload = [
            'iss' => $clientId,
            'iat' => Carbon::now()->timestamp,
            'client_id' => $clientId,
            'user_id' => 'application',
            'user_representation' => 'Application background task',
        ];

        // Use Build directly with a permissive secret validator so that local
        // secrets that don't meet ReallySimpleJWT's complexity requirements
        // (min 12 chars, upper/lower/digit/special) still work.
        $laxSecret = new class implements SecretInterface
        {
            public function validate(string $secret): bool
            {
                return strlen($secret) > 0;
            }
        };

        $builder = new Build('JWT', new Validator, $laxSecret, new EncodeHS256);

        foreach ($payload as $key => $value) {
            $builder->setPayloadClaim($key, $value);
        }

        return $builder->setSecret($clientSecret)->build()->getToken();
    }
}
