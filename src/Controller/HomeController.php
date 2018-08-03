<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * Class HomeController
 * manage homepage display.
 */
class HomeController extends Controller
{
    /**
     * show homepage.
     *
     * @Route("/", name="home")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index()
    {
        $tricks = [];
        for ($i = 1; $i <= 45; ++$i) {
            $tricks[$i] = new \stdClass();
            $tricks[$i]->title = 'Trick '.$i;
            $tricks[$i]->description = 'Here is content for trick '.$i.'<br> with 2 lines.';
            $tricks[$i]->trickGroup = 'Ollies';
        }

        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'tricks' => $tricks,
        ]);
    }
}
