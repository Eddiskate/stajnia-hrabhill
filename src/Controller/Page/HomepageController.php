<?php

namespace App\Controller\Page;

use App\Service\HomeContent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomepageController extends AbstractController
{
    public function __construct(private HomeContent $home)
    {
    }

    #[Route('/', name: 'page_homepage', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('page/homepage/index.html.twig', $this->home->forPage());
    }
}
