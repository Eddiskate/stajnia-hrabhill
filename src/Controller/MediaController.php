<?php

namespace App\Controller;

use App\Service\ImageThumbnailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MediaController extends AbstractController
{
    #[Route('/media/thumb', name: 'media_thumb', methods: ['GET'])]
    public function thumb(Request $request, ImageThumbnailService $thumbnails): Response
    {
        $src = (string) $request->query->get('src', '');
        $width = (int) $request->query->get('w', 640);

        if ($src === '') {
            throw $this->createNotFoundException();
        }

        $thumb = $thumbnails->getThumbnail($src, $width, $request->getHost());
        if ($thumb === null) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($thumb['path']);
        $response->headers->set('Content-Type', $thumb['mime']);
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setSharedMaxAge(31536000);
        $response->headers->addCacheControlDirective('immutable');

        return $response;
    }
}
