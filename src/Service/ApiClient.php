<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class ApiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private string $apiBaseUrl,
        private string $apiKey,
    ) {
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
    }

    public function getPosts(array $params = []): array
    {
        return $this->getCached('/posts', $params, 60);
    }

    public function getRecentPosts(int $max = 5): array
    {
        return $this->getCached('/posts/recent', ['max' => $max], 60);
    }

    public function getPost(string $slug): ?array
    {
        $response = $this->getCached('/posts/' . $slug, [], 60);

        return $response['data'] ?? null;
    }

    public function getPostsByCatalog(string $catalogSlug, array $params = []): array
    {
        return $this->getCached('/posts/catalog/' . $catalogSlug, $params, 60);
    }

    public function getCatalogs(array $params = []): array
    {
        return $this->getCached('/catalogs', $params, 300);
    }

    public function getCatalog(string $slug): ?array
    {
        $response = $this->getCached('/catalogs/' . $slug, [], 300);

        return $response['data'] ?? null;
    }

    private function getCached(string $endpoint, array $params, int $ttl): array
    {
        $key = 'api_' . hash('sha256', $endpoint . '|' . json_encode($params));

        return $this->cache->get($key, function (ItemInterface $item) use ($endpoint, $params, $ttl) {
            $data = $this->get($endpoint, $params);
            $failed = isset($data['success']) && $data['success'] === false;
            $item->expiresAfter($failed ? 15 : $ttl);

            return $data;
        });
    }

    private function get(string $endpoint, array $params = []): array
    {
        try {
            $url = $this->apiBaseUrl . $endpoint;
            if ($params !== []) {
                $url .= '?' . http_build_query($params);
            }

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'X-API-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                ],
                'timeout' => 8,
            ]);

            return $response->toArray();
        } catch (TransportExceptionInterface $e) {
            return [
                'success' => false,
                'error' => 'API connection failed: ' . $e->getMessage(),
                'data' => [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'API request failed: ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }
}
