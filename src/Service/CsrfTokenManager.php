<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

final class CsrfTokenManager
{
    private const SESSION_KEY = 'admin_csrf_token';

    public function getToken(Request $request): string
    {
        $session = $request->getSession();
        $token = $session->get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function isValid(Request $request, ?string $providedToken): bool
    {
        $storedToken = $request->getSession()->get(self::SESSION_KEY);

        return is_string($storedToken)
            && is_string($providedToken)
            && $providedToken !== ''
            && hash_equals($storedToken, $providedToken);
    }
}
