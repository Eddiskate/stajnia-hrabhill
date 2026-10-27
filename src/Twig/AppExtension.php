<?php

namespace App\Twig;

use App\Service\ApiClient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    /** @var list<array<string, mixed>>|null */
    private ?array $catalogs = null;

    public function __construct(
        private ApiClient $apiClient,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('img_thumb', [$this, 'thumbUrl']),
            new TwigFilter('optimize_html_images', [$this, 'optimizeHtmlImages'], ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nav_catalogs', [$this, 'navCatalogs']),
            new TwigFunction('recent_posts', [$this, 'recentPosts']),
        ];
    }

    public function thumbUrl(?string $url, int $width = 640): string
    {
        if (!$url) {
            return '';
        }

        if (preg_match('/\.svg(\?|$)/i', $url)) {
            return $url;
        }

        return $this->urlGenerator->generate('media_thumb', [
            'src' => $url,
            'w' => $width,
        ]);
    }

    public function optimizeHtmlImages(?string $html, int $width = 960): string
    {
        if (!$html) {
            return '';
        }

        return (string) preg_replace_callback('/<img\b([^>]*)>/i', function (array $match) use ($width) {
            $attrs = $match[1];

            if (preg_match('/\bsrc\s*=\s*([\'"])(.*?)\1/i', $attrs, $srcMatch)) {
                $thumb = htmlspecialchars($this->thumbUrl($srcMatch[2], $width), ENT_QUOTES);
                $attrs = preg_replace('/\bsrc\s*=\s*([\'"]).*?\1/i', 'src="' . $thumb . '"', $attrs, 1);
            }

            if (!preg_match('/\bloading\s*=/i', $attrs)) {
                $attrs .= ' loading="lazy"';
            }
            if (!preg_match('/\bdecoding\s*=/i', $attrs)) {
                $attrs .= ' decoding="async"';
            }

            return '<img' . $attrs . '>';
        }, $html);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function navCatalogs(int $max = 10): array
    {
        if ($this->catalogs === null) {
            $response = $this->apiClient->getCatalogs(['max' => 10]);
            $this->catalogs = $response['data'] ?? [];
        }

        return array_slice($this->catalogs, 0, $max);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentPosts(int $max = 5): array
    {
        $response = $this->apiClient->getRecentPosts($max);

        return $response['data'] ?? [];
    }
}
