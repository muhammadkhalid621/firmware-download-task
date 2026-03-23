<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\SoftwareVersionRepository;
use App\Service\AdminAuthenticator;
use App\Service\CsrfTokenManager;
use App\Service\SoftwareVersionValidator;
use App\Template\PhpTemplateRenderer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminSoftwareVersionController
{
    public function __construct(
        private readonly PhpTemplateRenderer $renderer,
        private readonly SoftwareVersionRepository $repository,
        private readonly SoftwareVersionValidator $validator,
        private readonly AdminAuthenticator $authenticator,
        private readonly CsrfTokenManager $csrfTokenManager,
    ) {
    }

    #[Route('/admin/software-versions', name: 'admin_software_versions', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($redirect = $this->requireAuthenticated($request)) {
            return $redirect;
        }

        return new Response(
            $this->renderer->render('app_shell.php', [
                'pageTitle' => 'Software Versions Admin',
                'appName' => 'admin',
                'payload' => [
                    'notice' => (string) $request->query->get('notice', ''),
                    'apiBase' => '/api/admin/software-versions',
                    'logoutUrl' => '/admin/logout',
                    'customerPageUrl' => '/carplay/software-download',
                    'createUrl' => '/admin/software-versions/new',
                    'editBaseUrl' => '/admin/software-versions',
                    'initialPage' => 1,
                    'perPage' => 10,
                    'csrfToken' => $this->csrfTokenManager->getToken($request),
                ],
            ])
        );
    }

    #[Route('/admin/software-versions/new', name: 'admin_software_versions_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($redirect = $this->requireAuthenticated($request)) {
            return $redirect;
        }

        $values = $this->formValuesFromRequest($request);
        $errors = [];

        if ($request->isMethod('POST')) {
            $errors = $this->validator->validate($values);

            if ($errors === []) {
                $this->repository->create($values);

                return $this->redirectWithNotice('Created software version.');
            }
        }

        return new Response(
            $this->renderer->render('app_shell.php', [
                'pageTitle' => 'Add software version',
                'appName' => 'admin-form',
                'payload' => [
                    'mode' => 'create',
                    'values' => $values,
                    'errors' => $errors,
                    'submitLabel' => 'Create',
                    'cancelUrl' => '/admin/software-versions',
                    'apiUrl' => '/api/admin/software-versions',
                    'csrfToken' => $this->csrfTokenManager->getToken($request),
                ],
            ])
        );
    }

    #[Route('/admin/software-versions/{id}/edit', name: 'admin_software_versions_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        if ($redirect = $this->requireAuthenticated($request)) {
            return $redirect;
        }

        $existing = $this->repository->find($id);

        if ($existing === null) {
            return new Response('Not found', Response::HTTP_NOT_FOUND);
        }

        $values = $request->isMethod('POST') ? $this->formValuesFromRequest($request) : $existing;
        $errors = [];

        if ($request->isMethod('POST')) {
            $errors = $this->validator->validate($values, $id);

            if ($errors === []) {
                $this->repository->update($id, $values);

                return $this->redirectWithNotice('Updated software version.');
            }
        }

        return new Response(
            $this->renderer->render('app_shell.php', [
                'pageTitle' => 'Edit software version',
                'appName' => 'admin-form',
                'payload' => [
                    'mode' => 'edit',
                    'id' => $id,
                    'values' => $values,
                    'errors' => $errors,
                    'submitLabel' => 'Save changes',
                    'cancelUrl' => '/admin/software-versions',
                    'apiUrl' => '/api/admin/software-versions/' . rawurlencode($id),
                    'csrfToken' => $this->csrfTokenManager->getToken($request),
                ],
            ])
        );
    }

    #[Route('/admin/software-versions/{id}/delete', name: 'admin_software_versions_delete', methods: ['POST'])]
    public function delete(Request $request, string $id): RedirectResponse
    {
        if ($redirect = $this->requireAuthenticated($request)) {
            return $redirect;
        }

        $deleted = $this->repository->delete($id);

        return $this->redirectWithNotice($deleted ? 'Deleted software version.' : 'Software version was not found.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formValuesFromRequest(Request $request): array
    {
        return [
            'name' => trim((string) $request->request->get('name', '')),
            'system_version' => trim((string) $request->request->get('system_version', '')),
            'system_version_alt' => trim((string) $request->request->get('system_version_alt', '')),
            'link' => trim((string) $request->request->get('link', '')),
            'st' => trim((string) $request->request->get('st', '')),
            'gd' => trim((string) $request->request->get('gd', '')),
            'latest' => $request->request->get('latest') === '1',
        ];
    }

    private function requireAuthenticated(Request $request): ?RedirectResponse
    {
        if (!$this->authenticator->isAuthenticated($request)) {
            return new RedirectResponse('/admin/login');
        }

        return null;
    }

    private function redirectWithNotice(string $notice): RedirectResponse
    {
        return new RedirectResponse('/admin/software-versions?notice=' . urlencode($notice));
    }
}
