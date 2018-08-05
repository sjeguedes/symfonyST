<?php

namespace App\Controller;

use App\Repository\TrickRepository;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * Class HomeController
 * This class manages homepage display.
 */
class HomeController extends Controller
{
    /**
     * show homepage.
     *
     * @Route("/", name="home")
     *
     * @param TrickRepository $repository
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index(TrickRepository $repository)
    {
        return $this->render('home/index.html.twig', [
            'tricks' => $repository->findLatestByLimit(15)
        ]);
    }
}
