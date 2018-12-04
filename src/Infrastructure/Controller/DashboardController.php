<?php

namespace App\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/", name="dashboard_")
 */
class DashboardController extends AbstractController
{
    /**
     * @Route("/", name="index", methods={"GET"})
     */
    public function index(): Response
    {
        return $this->render('base.html.twig');
    }

    /**
     * @Route("/esi-test", name="esi_test", methods={"GET"})
     */
    public function esiTest(Request $request): Response
    {
        $myRandomValue = random_int(0, 1000);

        $response = (new Response())->setSharedMaxAge(random_int(1, 10));
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        return $this->render('esi-test.html.twig', [
            'randValue' => $myRandomValue,
        ], $response);
    }
}
