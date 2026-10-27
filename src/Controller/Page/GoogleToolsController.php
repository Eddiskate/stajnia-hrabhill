<?php

namespace App\Controller\Page;

use App\Service\ApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GoogleToolsController extends AbstractController
{
    public function __construct(private ApiClient $apiClient)
    {
    }

    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        $urls = [
            $this->url('page_homepage', [], 'daily', '1.0'),
            $this->url('page_post_index', [], 'daily', '0.8'),
            $this->url('page_privacy', [], 'yearly', '0.3'),
        ];

        try {
            $posts = $this->apiClient->getPosts(['limit' => 1000])['data'] ?? [];
            foreach ($posts as $post) {
                if (empty($post['slug'])) {
                    continue;
                }
                $lastmod = (new \DateTime())->format('c');
                if (!empty($post['createdAt'])) {
                    try {
                        $lastmod = (new \DateTime((string) $post['createdAt']))->format('c');
                    } catch (\Exception) {
                    }
                }
                $urls[] = [
                    'loc' => $this->generateUrl('page_post_show', ['slug' => $post['slug']], UrlGeneratorInterface::ABSOLUTE_URL),
                    'lastmod' => $lastmod,
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
                ];
            }
        } catch (\Exception) {
        }

        try {
            $catalogs = $this->apiClient->getCatalogs(['max' => 100])['data'] ?? [];
            foreach ($catalogs as $catalog) {
                if (empty($catalog['slug'])) {
                    continue;
                }
                $urls[] = $this->url('page_post_catalog', ['slug' => $catalog['slug']], 'weekly', '0.5');
            }
        } catch (\Exception) {
        }

        $response = $this->render('page/google_tools/sitemap.xml.twig', [
            'urls' => $urls,
        ]);
        $response->headers->set('Content-Type', 'text/xml; charset=UTF-8');

        return $response;
    }

    #[Route('/robots.txt', name: 'robots', methods: ['GET'])]
    public function robots(): Response
    {
        $response = new Response(sprintf(
            "User-agent: *\nAllow: /\n\nSitemap: %s\n",
            $this->generateUrl('sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL)
        ));
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');

        return $response;
    }

    /**
     * @param array<string, string> $params
     * @return array{loc: string, lastmod: string, changefreq: string, priority: string}
     */
    private function url(string $route, array $params, string $freq, string $priority): array
    {
        return [
            'loc' => $this->generateUrl($route, $params, UrlGeneratorInterface::ABSOLUTE_URL),
            'lastmod' => (new \DateTime())->format('c'),
            'changefreq' => $freq,
            'priority' => $priority,
        ];
    }
}
