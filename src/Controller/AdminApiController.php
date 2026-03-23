<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\SoftwareVersionRepository;
use App\Service\AdminAuthenticator;
use App\Service\AuditLogger;
use App\Service\CsrfTokenManager;
use App\Service\RequestRateLimiter;
use App\Service\SoftwareVersionValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminApiController
{
    public function __construct(
        private readonly AdminAuthenticator $authenticator,
        private readonly SoftwareVersionRepository $repository,
        private readonly SoftwareVersionValidator $validator,
        private readonly RequestRateLimiter $rateLimiter,
        private readonly CsrfTokenManager $csrfTokenManager,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    #[Route('/api/admin/login', name: 'api_admin_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        if ($limited = $this->guardRateLimit($request, 'admin_login', 10, 300)) {
            return $limited;
        }

        $payload = $this->payload($request);
        if ($csrf = $this->guardCsrf($request, $payload['csrfToken'] ?? null)) {
            return $csrf;
        }

        $success = $this->authenticator->login(
            $request,
            (string) ($payload['username'] ?? ''),
            (string) ($payload['password'] ?? ''),
        );

        if (!$success) {
            return $this->errorResponse('AUTH_INVALID_CREDENTIALS', 'Invalid admin credentials.', Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse(['success' => true, 'redirectTo' => '/admin/software-versions']);
    }

    #[Route('/api/admin/software-versions', name: 'api_admin_versions_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        if ($auth = $this->authorize($request)) {
            return $auth;
        }

        if ($limited = $this->guardRateLimit($request, 'admin_list', 120, 60)) {
            return $limited;
        }

        return new JsonResponse($this->repository->paginate(
            trim((string) $request->query->get('q', '')),
            trim((string) $request->query->get('filter', 'all')),
            max(1, $request->query->getInt('page', 1)),
            max(1, min(50, $request->query->getInt('perPage', 10))),
        ));
    }

    #[Route('/api/admin/software-versions', name: 'api_admin_versions_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if ($auth = $this->authorize($request)) {
            return $auth;
        }

        if ($limited = $this->guardRateLimit($request, 'admin_write', 30, 60)) {
            return $limited;
        }

        $values = $this->normalizeValues($this->payload($request));
        if ($csrf = $this->guardCsrf($request, $values['csrfToken'] ?? null)) {
            return $csrf;
        }

        $errors = $this->validator->validate($values);
        if ($errors !== []) {
            return $this->errorResponse('VALIDATION_FAILED', 'Validation failed.', Response::HTTP_UNPROCESSABLE_ENTITY, [], ['errors' => $errors]);
        }

        $this->repository->create($values);
        $this->auditLogger->log('software_version_created', [
            'ip' => $request->getClientIp(),
            'name' => $values['name'],
            'system_version_alt' => $values['system_version_alt'],
        ]);

        return new JsonResponse(['success' => true, 'redirectTo' => '/admin/software-versions?notice=' . urlencode('Created software version.')]);
    }

    #[Route('/api/admin/software-versions/{id}', name: 'api_admin_versions_update', methods: ['PUT'])]
    public function update(Request $request, string $id): JsonResponse
    {
        if ($auth = $this->authorize($request)) {
            return $auth;
        }

        if ($limited = $this->guardRateLimit($request, 'admin_write', 30, 60)) {
            return $limited;
        }

        $values = $this->normalizeValues($this->payload($request));
        if ($csrf = $this->guardCsrf($request, $values['csrfToken'] ?? null)) {
            return $csrf;
        }

        $errors = $this->validator->validate($values, $id);
        if ($errors !== []) {
            return $this->errorResponse('VALIDATION_FAILED', 'Validation failed.', Response::HTTP_UNPROCESSABLE_ENTITY, [], ['errors' => $errors]);
        }

        if (!$this->repository->update($id, $values)) {
            return $this->errorResponse('SOFTWARE_VERSION_NOT_FOUND', 'Software version not found.', Response::HTTP_NOT_FOUND);
        }

        $this->auditLogger->log('software_version_updated', [
            'ip' => $request->getClientIp(),
            'id' => $id,
            'name' => $values['name'],
            'system_version_alt' => $values['system_version_alt'],
        ]);

        return new JsonResponse(['success' => true, 'redirectTo' => '/admin/software-versions?notice=' . urlencode('Updated software version.')]);
    }

    #[Route('/api/admin/software-versions/{id}', name: 'api_admin_versions_delete', methods: ['DELETE'])]
    public function delete(Request $request, string $id): JsonResponse
    {
        if ($auth = $this->authorize($request)) {
            return $auth;
        }

        if ($limited = $this->guardRateLimit($request, 'admin_write', 30, 60)) {
            return $limited;
        }

        $payload = $this->payload($request);
        if ($csrf = $this->guardCsrf($request, $payload['csrfToken'] ?? null)) {
            return $csrf;
        }

        $deleted = $this->repository->delete($id);
        if (!$deleted) {
            return $this->errorResponse('SOFTWARE_VERSION_NOT_FOUND', 'Software version was not found.', Response::HTTP_NOT_FOUND);
        }

        $this->auditLogger->log('software_version_deleted', [
            'ip' => $request->getClientIp(),
            'id' => $id,
        ]);

        return new JsonResponse(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = $request->request->all();

        if ($payload !== []) {
            return $payload;
        }

        try {
            $decoded = $request->toArray();
        } catch (\Throwable) {
            $decoded = [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function normalizeValues(array $payload): array
    {
        return [
            'name' => trim((string) ($payload['name'] ?? '')),
            'system_version' => trim((string) ($payload['system_version'] ?? '')),
            'system_version_alt' => trim((string) ($payload['system_version_alt'] ?? '')),
            'link' => trim((string) ($payload['link'] ?? '')),
            'st' => trim((string) ($payload['st'] ?? '')),
            'gd' => trim((string) ($payload['gd'] ?? '')),
            'latest' => filter_var($payload['latest'] ?? false, FILTER_VALIDATE_BOOL),
            'csrfToken' => isset($payload['csrfToken']) ? (string) $payload['csrfToken'] : null,
        ];
    }

    private function authorize(Request $request): ?JsonResponse
    {
        if ($this->authenticator->isAuthenticated($request)) {
            return null;
        }

        return $this->errorResponse('AUTH_REQUIRED', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
    }

    private function guardRateLimit(Request $request, string $bucket, int $limit, int $windowSeconds): ?JsonResponse
    {
        $state = $this->rateLimiter->hit($request, $bucket, $limit, $windowSeconds);

        if ($state['allowed']) {
            return null;
        }

        return $this->errorResponse(
            'RATE_LIMIT_EXCEEDED',
            'Rate limit exceeded. Please wait and try again.',
            Response::HTTP_TOO_MANY_REQUESTS,
            [
                'Retry-After' => (string) $state['retryAfter'],
                'X-RateLimit-Limit' => (string) $state['limit'],
                'X-RateLimit-Remaining' => '0',
            ]
        );
    }

    private function guardCsrf(Request $request, ?string $providedToken): ?JsonResponse
    {
        if ($this->csrfTokenManager->isValid($request, $providedToken)) {
            return null;
        }

        return $this->errorResponse('CSRF_INVALID', 'Invalid or missing CSRF token.', Response::HTTP_FORBIDDEN);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $extra
     */
    private function errorResponse(string $code, string $message, int $status, array $headers = [], array $extra = []): JsonResponse
    {
        return new JsonResponse(
            array_merge([
                'success' => false,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
                'message' => $message,
            ], $extra),
            $status,
            $headers
        );
    }
}
