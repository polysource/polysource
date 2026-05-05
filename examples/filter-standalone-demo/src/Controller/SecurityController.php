<?php

declare(strict_types=1);

namespace Polysource\Demo\FilterStandalone\Controller;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Login form + logout endpoint.
 *
 * Routes:
 *   - GET/POST /login   form_login entry point
 *   - GET/POST /logout  handled by Symfony Security (firewall config)
 */
final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $auth): Response
    {
        return $this->render('security/login.html.twig', [
            'last_username' => $auth->getLastUsername(),
            'error' => $auth->getLastAuthenticationError(),
        ]);
    }

    /**
     * Stub — never actually called. The firewall intercepts /logout
     * before it reaches the controller. Symfony just needs the route
     * declared for path generation in templates / redirects.
     */
    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): Response
    {
        throw new LogicException('This endpoint is handled by the Symfony Security firewall and should never be reached.');
    }
}
