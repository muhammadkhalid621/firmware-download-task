<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminAuthenticator;
use App\Template\PhpTemplateRenderer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminSecurityController
{
    public function __construct(
        private readonly PhpTemplateRenderer $renderer,
        private readonly AdminAuthenticator $authenticator,
    ) {
    }

    #[Route('/admin/login', name: 'admin_login', methods: ['GET'])]
    public function login(Request $request): Response
    {
        if ($this->authenticator->isAuthenticated($request)) {
            return new RedirectResponse('/admin/software-versions');
        }

        return new Response($this->renderer->render('app_shell.php', [
            'pageTitle' => 'Admin Login',
            'appName' => 'admin-login',
            'payload' => [
                'loginUrl' => '/api/admin/login',
                'redirectTo' => '/admin/software-versions',
            ],
        ]));
    }

    #[Route('/admin/logout', name: 'admin_logout', methods: ['POST'])]
    public function logout(Request $request): RedirectResponse
    {
        if ($request->hasSession()) {
            $this->authenticator->logout($request->getSession());
        }

        return new RedirectResponse('/admin/login');
    }
}
