<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\RequestRateLimiter;
use App\Service\SoftwareMatcher;
use App\Template\PhpTemplateRenderer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SoftwareDownloadController
{
    public function __construct(
        private readonly PhpTemplateRenderer $renderer,
        private readonly SoftwareMatcher $matcher,
        private readonly RequestRateLimiter $rateLimiter,
    ) {
    }

    #[Route('/carplay/software-download', name: 'customer_software_download', methods: ['GET'])]
    public function page(): Response
    {
        return new Response($this->renderer->render('app_shell.php', [
            'pageTitle' => 'Software Download',
            'appName' => 'customer',
            'payload' => [],
        ]));
    }

    #[Route('/api2/carplay/software/version', name: 'api_software_version', methods: ['POST'])]
    public function check(Request $request): JsonResponse
    {
        $state = $this->rateLimiter->hit($request, 'firmware_lookup', 30, 60);
        if (!$state['allowed']) {
            return new JsonResponse(
                ['success' => false, 'message' => 'Too many requests. Please try again shortly.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                [
                    'Retry-After' => (string) $state['retryAfter'],
                    'X-RateLimit-Limit' => (string) $state['limit'],
                    'X-RateLimit-Remaining' => '0',
                ]
            );
        }

        $payload = $request->request->all();

        if ($payload === []) {
            try {
                $payload = $request->toArray();
            } catch (\Throwable) {
                $payload = [];
            }
        }

        return new JsonResponse(
            $this->matcher->match(
                $payload['version'] ?? null,
                $payload['hwVersion'] ?? null,
            ),
            Response::HTTP_OK,
            [
                'X-RateLimit-Limit' => (string) $state['limit'],
                'X-RateLimit-Remaining' => (string) $state['remaining'],
            ]
        );
    }
}
