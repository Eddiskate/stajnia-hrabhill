<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class JazdyClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private string $jazdyApiUrl,
        private string $jazdyApiKey,
    ) {
    }

    /**
     * @return array{ok: bool, state: string, error: string, leadHours: int, slotMinutes: int, slots: list<array<string, mixed>>}
     */
    public function slots(): array
    {
        if ($this->jazdyApiKey === '' || $this->jazdyApiUrl === '') {
            return $this->unavailable('disabled', 'Zapis online włączymy, gdy kalendarz jazd będzie podłączony. Na razie napisz na Instagramie.');
        }

        return $this->cache->get('jazdy_open_slots_v1', function (ItemInterface $item) {
            $data = $this->fetchSlots();
            $item->expiresAfter($data['ok'] ? 30 : 10);

            return $data;
        });
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, error?: string, reservation?: array<string, mixed>}
     */
    public function reserve(array $payload): array
    {
        if ($this->jazdyApiKey === '' || $this->jazdyApiUrl === '') {
            return ['ok' => false, 'error' => 'Zapis online nie jest jeszcze włączony.'];
        }

        try {
            $response = $this->httpClient->request('POST', $this->endpoint('/api/public/reservations'), [
                'headers' => $this->headers(),
                'json' => $payload,
                'timeout' => 12,
            ]);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Kalendarz chwilowo nie odpowiada. Spróbuj za moment albo napisz na Instagramie.'];
        }

        $this->cache->delete('jazdy_open_slots_v1');

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'reservation' => \is_array($data['reservation'] ?? null) ? $data['reservation'] : []];
        }

        $message = \is_string($data['error'] ?? null) ? $data['error'] : 'Nie udało się zapisać terminu.';
        if (\strlen($message) > 240) {
            $message = 'Nie udało się zapisać terminu.';
        }

        return ['ok' => false, 'error' => $message];
    }

    /**
     * @return array{ok: bool, state: string, error: string, leadHours: int, slotMinutes: int, slots: list<array<string, mixed>>}
     */
    private function fetchSlots(): array
    {
        $tz = new \DateTimeZone('Europe/Warsaw');
        $from = new \DateTimeImmutable('today', $tz);
        $to = $from->modify('+13 days');

        try {
            $response = $this->httpClient->request('GET', $this->endpoint('/api/public/slots'), [
                'headers' => $this->headers(),
                'query' => [
                    'from' => $from->format('Y-m-d'),
                    'to' => $to->format('Y-m-d'),
                ],
                'timeout' => 8,
            ]);
            $status = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (\Throwable) {
            return $this->unavailable('down', 'Kalendarz chwilowo nie odpowiada. Spróbuj za moment albo napisz na Instagramie.');
        }

        if ($status === 401 || $status === 503) {
            return $this->unavailable('disabled', 'Zapis online nie jest jeszcze włączony. Napisz do nas na Instagramie.');
        }

        if ($status !== 200 || !\is_array($payload['slots'] ?? null)) {
            return $this->unavailable('down', 'Kalendarz chwilowo nie odpowiada. Spróbuj za moment albo napisz na Instagramie.');
        }

        $slots = $payload['slots'];

        return [
            'ok' => true,
            'state' => $slots === [] ? 'empty' : 'ok',
            'error' => $slots === [] ? 'W najbliższych dwóch tygodniach nie ma wolnego okna. Napisz na Instagramie.' : '',
            'leadHours' => (int) ($payload['leadHours'] ?? 12),
            'slotMinutes' => (int) ($payload['slotMinutes'] ?? 60),
            'slots' => $slots,
        ];
    }

    /**
     * @return array{ok: bool, state: string, error: string, leadHours: int, slotMinutes: int, slots: list<array<string, mixed>>}
     */
    private function unavailable(string $state, string $error): array
    {
        return [
            'ok' => false,
            'state' => $state,
            'error' => $error,
            'leadHours' => 12,
            'slotMinutes' => 60,
            'slots' => [],
        ];
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->jazdyApiUrl, '/') . $path;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'X-API-Key' => $this->jazdyApiKey,
            'Accept' => 'application/json',
        ];
    }
}
