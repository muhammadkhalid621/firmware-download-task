<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class AdminAuthenticator
{
    private const SESSION_FLAG = 'admin_authenticated';

    public function __construct(
        private readonly string $adminUsername,
        private readonly string $adminPassword,
    ) {
    }

    public function isAuthenticated(Request $request): bool
    {
        return $request->hasSession() && $request->getSession()->get(self::SESSION_FLAG, false) === true;
    }

    public function login(Request $request, string $username, string $password): bool
    {
        if (!hash_equals($this->adminUsername, trim($username)) || !hash_equals($this->adminPassword, $password)) {
            return false;
        }

        $session = $request->getSession();
        $session->migrate(true);
        $session->set(self::SESSION_FLAG, true);

        return true;
    }

    public function logout(SessionInterface $session): void
    {
        $session->remove(self::SESSION_FLAG);
        $session->invalidate();
    }
}
