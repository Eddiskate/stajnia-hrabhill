<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImageThumbnailService
{
    private const ALLOWED_PATH_PREFIX = '/uploads/';
    private const MAX_WIDTH = 1600;
    private const MIN_WIDTH = 80;
    private const WEBP_QUALITY = 78;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $projectDir,
        private string $appHostname,
        private string $cdnUrl,
    ) {
    }

    /**
     * @return array{path: string, mime: string}|null
     */
    public function getThumbnail(string $src, int $width, string $requestHost): ?array
    {
        $width = max(self::MIN_WIDTH, min(self::MAX_WIDTH, $width));
        $parsed = $this->parseAllowedSource($src, $requestHost);
        if ($parsed === null) {
            return null;
        }

        $cacheDir = $this->projectDir . '/var/thumbs';
        if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0775, true) && !is_dir($cacheDir)) {
            return null;
        }

        $cachePath = $cacheDir . '/' . hash('sha256', $parsed['url'] . '|' . $width . '|v1') . '.webp';
        if (is_file($cachePath) && filesize($cachePath) > 0) {
            return ['path' => $cachePath, 'mime' => 'image/webp'];
        }

        $binary = $this->readSource($parsed);
        if ($binary === null) {
            return null;
        }

        $webp = $this->resizeToWebp($binary, $width);
        if ($webp === null) {
            return null;
        }

        if (@file_put_contents($cachePath, $webp) === false) {
            return null;
        }

        return ['path' => $cachePath, 'mime' => 'image/webp'];
    }

    /**
     * @return array{url: string, path: string, host: string}|null
     */
    private function parseAllowedSource(string $src, string $requestHost): ?array
    {
        $src = trim($src);
        if ($src === '' || str_contains($src, "\0") || str_contains($src, '..')) {
            return null;
        }

        if (str_starts_with($src, '//')) {
            $src = 'https:' . $src;
        }

        if (!preg_match('#^https?://#i', $src)) {
            $path = str_starts_with($src, '/') ? $src : '/' . $src;
            $src = 'https://' . $requestHost . $path;
        }

        $parts = parse_url($src);
        if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'];
        if (!str_starts_with($path, self::ALLOWED_PATH_PREFIX)) {
            return null;
        }

        $allowedHosts = $this->allowedHosts($requestHost);
        if (!in_array($host, $allowedHosts, true)) {
            return null;
        }

        return [
            'url' => $src,
            'path' => $path,
            'host' => $host,
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedHosts(string $requestHost): array
    {
        $hosts = array_filter([
            strtolower($requestHost),
            strtolower($this->appHostname),
        ]);

        $cdnHost = parse_url($this->cdnUrl, PHP_URL_HOST);
        if (is_string($cdnHost) && $cdnHost !== '') {
            $hosts[] = strtolower($cdnHost);
        }

        $expanded = [];
        foreach ($hosts as $host) {
            $expanded[] = $host;
            $expanded[] = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.' . $host;
        }

        return array_values(array_unique($expanded));
    }

    /**
     * @param array{url: string, path: string, host: string} $source
     */
    private function readSource(array $source): ?string
    {
        $local = $this->projectDir . '/public' . $source['path'];
        if (is_file($local) && is_readable($local)) {
            $data = @file_get_contents($local);
            return $data !== false && $data !== '' ? $data : null;
        }

        try {
            $response = $this->httpClient->request('GET', $source['url'], [
                'timeout' => 12,
                'max_redirects' => 2,
                'headers' => [
                    'Accept' => 'image/*,*/*;q=0.8',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->getContent();
            return $data !== '' ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resizeToWebp(string $binary, int $width): ?string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            return null;
        }

        $info = @getimagesizefromstring($binary);
        if ($info === false || empty($info[0]) || empty($info[1])) {
            return null;
        }

        $srcWidth = (int) $info[0];
        $srcHeight = (int) $info[1];
        if ($srcWidth < 1 || $srcHeight < 1) {
            return null;
        }

        @ini_set('memory_limit', '256M');
        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return null;
        }

        $targetWidth = $srcWidth > $width ? $width : $srcWidth;
        $targetHeight = (int) max(1, round($srcHeight * ($targetWidth / $srcWidth)));

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($resized === false) {
            imagedestroy($image);
            return null;
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $targetWidth, $targetHeight, $transparent);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $srcWidth, $srcHeight);
        imagedestroy($image);

        ob_start();
        $ok = imagewebp($resized, null, self::WEBP_QUALITY);
        $webp = ob_get_clean();
        imagedestroy($resized);

        if (!$ok || !is_string($webp) || $webp === '') {
            return null;
        }

        return $webp;
    }
}
