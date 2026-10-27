<?php

namespace App\Controller\Page;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LegalController extends AbstractController
{
    #[Route('/polityka-prywatnosci', name: 'page_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('page/privacy.html.twig');
    }
}
