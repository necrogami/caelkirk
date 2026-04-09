<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Auth;

use App\Foundation\Repository\UserRepository;
use App\Foundation\Service\SocialAuthService;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\RateLimiting\Middleware\RateLimitMiddleware;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Security\Middleware\SecurityHeadersMiddleware;
use Marko\Session\Contracts\SessionInterface;
use Marko\View\ViewInterface;

#[Middleware([SecurityHeadersMiddleware::class, CsrfMiddleware::class, RateLimitMiddleware::class])]
class SocialAuthController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly SocialAuthService $socialAuthService,
        private readonly UserRepository $userRepository,
        private readonly GuardInterface $guard,
        private readonly ConfigRepositoryInterface $config,
        private readonly SessionInterface $session,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Get('/auth/{provider}')]
    public function redirect(string $provider): Response
    {
        $providerConfig = $this->getProviderConfig($provider);

        if ($providerConfig === null) {
            return Response::redirect('/login');
        }

        $url = $this->socialAuthService->getAuthorizationUrl($provider, $providerConfig);

        return Response::redirect($url);
    }

    #[Get('/auth/{provider}/callback')]
    public function callback(Request $request, string $provider): Response
    {
        $code = $request->query('code');
        $state = $request->query('state');

        if ($code === null || !$this->socialAuthService->validateState($state)) {
            return Response::redirect('/login');
        }

        // In a real implementation, exchange the code for a token and fetch user profile
        // via HTTP calls to the provider's token and user endpoints.
        // For now, this is a placeholder that would be filled with Guzzle/cURL calls.
        $profile = $this->exchangeCodeForProfile($provider, $code);

        if ($profile === null) {
            return Response::redirect('/login');
        }

        $result = $this->socialAuthService->handleCallback($provider, $profile);

        return match ($result['action']) {
            'login', 'created' => $this->loginAndRedirect($result['user']),
            'verify_password' => $this->showVerifyForm($provider, $result['user'], $result['profile']),
            'link_via_settings' => Response::redirect('/login'),
            default => Response::redirect('/login'),
        };
    }

    #[Post('/auth/verify-link')]
    public function verifyAndLink(Request $request): Response
    {
        $provider = $this->session->get('social_link_provider');
        $profile = $this->session->get('social_link_profile');
        $userId = $this->session->get('social_link_user_id');
        $password = $request->post('password', '');

        if ($provider === null || $profile === null || $userId === null) {
            return Response::redirect('/login');
        }

        $user = $this->userRepository->find($userId);

        if ($user === null) {
            return Response::redirect('/login');
        }

        $linked = $this->socialAuthService->verifyAndLink($user, $password, $provider, $profile);

        if (!$linked) {
            return $this->view->render('foundation::auth/verify-social', [
                'error' => 'Incorrect password',
                'provider' => $provider,
                'csrfToken' => $this->csrfTokenManager->get(),
            ]);
        }

        $this->session->remove('social_link_provider');
        $this->session->remove('social_link_profile');
        $this->session->remove('social_link_user_id');

        $this->guard->login($user);

        return Response::redirect('/game');
    }

    private function loginAndRedirect(object $user): Response
    {
        $this->guard->login($user);
        return Response::redirect('/game');
    }

    private function showVerifyForm(string $provider, object $user, array $profile): Response
    {
        $this->session->set('social_link_provider', $provider);
        $this->session->set('social_link_profile', $profile);
        $this->session->set('social_link_user_id', $user->id);

        return $this->view->render('foundation::auth/verify-social', [
            'provider' => $provider,
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }

    private function getProviderConfig(string $provider): ?array
    {
        $key = "social_auth.providers.$provider";
        if (!$this->config->has($key)) {
            return null;
        }
        return $this->config->getArray($key);
    }

    /**
     * Exchange OAuth code for user profile.
     * This is a placeholder — real implementation uses HTTP client
     * to call provider token + user endpoints.
     */
    private function exchangeCodeForProfile(string $provider, string $code): ?array
    {
        // TODO: Implement real OAuth token exchange with Guzzle/cURL
        // This will be completed when we have provider credentials configured.
        // For now, return null to prevent any unauthenticated access.
        return null;
    }
}
