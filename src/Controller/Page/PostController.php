<?php

namespace App\Controller\Page;

use App\Service\ApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/artykuly')]
class PostController extends AbstractController
{
    public function __construct(private ApiClient $apiClient)
    {
    }

    #[Route('/', name: 'page_post_index', methods: ['GET'])]
    public function index(): Response
    {
        $response = $this->apiClient->getPosts(['limit' => 50]);

        return $this->render('page/post/index.html.twig', [
            'posts' => $response['data'] ?? [],
        ]);
    }

    #[Route('/artykul/{slug}', name: 'page_post_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $post = $this->apiClient->getPost($slug);
        if (!$post) {
            throw $this->createNotFoundException('Artykuł nie został znaleziony');
        }

        return $this->render('page/post/show.html.twig', [
            'post' => $post,
        ]);
    }

    #[Route('/{slug}', name: 'page_post_catalog', methods: ['GET'])]
    public function catalog(string $slug, Request $request): Response
    {
        $postCatalog = $this->apiClient->getCatalog($slug);
        if (!$postCatalog) {
            throw $this->createNotFoundException('Kategoria nie została znaleziona');
        }

        $response = $this->apiClient->getPostsByCatalog($slug, [
            'limit' => $request->query->get('limit', 50),
        ]);

        return $this->render('page/post/index.html.twig', [
            'postCatalog' => $postCatalog,
            'posts' => $response['data'] ?? [],
        ]);
    }
}
