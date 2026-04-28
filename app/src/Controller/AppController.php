<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class AppController extends AbstractController
{
    /**
     * Sirve el shell HTML para la SPA. Vue Router toma las rutas desde aquí.
     * Cualquier ruta que no empieza con /api/ y no es asset cae en este controller.
     */
    #[Route('/{any}', name: 'app_spa', requirements: ['any' => '^(?!api|build|_).*'], defaults: ['any' => ''], methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('base.html.twig');
    }

    #[Route('/api/login', name: 'app_login', methods: ['POST'])]
    public function login(): Response
    {
        // Manejado por security firewall (json_login). Este endpoint es sólo placeholder de routing.
        throw new AuthenticationException('Login no procesado por el firewall.');
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Logout interceptado por el firewall.');
    }
}
