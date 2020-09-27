<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Domain\ServiceLayer\UserManager;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Class LoginFormAuthenticationManager.
 *
 * Define a custom authenticator for login form.
 */
class LoginFormAuthenticationManager extends AbstractFormLoginAuthenticator
{
    use TargetPathTrait;

    /**
     * @var UserManager
     */
    private $userService;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var CsrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * @var UserPasswordEncoderInterface
     */
    private $passwordEncoder;

    /**
     * LoginFormAuthenticator constructor.
     *
     * @param UserManager                  $userService
     * @param RouterInterface              $router
     * @param CsrfTokenManagerInterface    $csrfTokenManager
     * @param UserPasswordEncoderInterface $passwordEncoder
     *
     * @return void
     */
    public function __construct(
        UserManager $userService,
        RouterInterface $router,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserPasswordEncoderInterface $passwordEncoder
    ) {
        $this->userService = $userService;
        $this->router = $router;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request): bool
    {
        return 'connect' === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials(Request $request): array
    {
        $credentials = [
            'username'   => $request->request->get('login')['userName'],
            'password'   => $request->request->get('login')['password'],
            'csrf_token' => $request->request->get('login')['token'],
        ];
        return $credentials;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws CustomUserMessageAuthenticationException
     */
    public function getUser($credentials, UserProviderInterface $userProvider): UserInterface
    {
        $token = new CsrfToken('login_token', $credentials['csrf_token']);
        // CSRF token is not valid.
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new \Exception('Security error: CSRF form token is invalid!');
        }
        $user = $this->userService->getRepository()->loadUserByUsername($credentials['username']);
        // Authentication failed. User value is null.
        if (!$user) {
            throw new CustomUserMessageAuthenticationException(
                'Please check your credentials!' . "\n" . 'User cannot be found.'
            );
        }
        return $user;
    }

    /**
     * {@inheritdoc}
     *
     * @throws CustomUserMessageAuthenticationException
     */
    public function checkCredentials($credentials, UserInterface $user): bool
    {
        $isPasswordMatching = $this->passwordEncoder->isPasswordValid($user, $credentials['password']);
        // Authentication failed. Password value does not match user.
        if (!$isPasswordMatching) {
            throw new CustomUserMessageAuthenticationException(
               'Please check your credentials!' . "\n" . 'User and password do not match.'
            );
        }
        return $isPasswordMatching;
    }

    /**
     * {@inheritdoc}
     *
     * For information to define this config in security.yaml instead of these success and failure callbacks methods:
     * @see https://symfony.com/doc/current/security/form_login.html#changing-the-default-page
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey): RedirectResponse
    {
        $targetPath = $this->getTargetPath($request->getSession(), $providerKey);
        // Target path is defined in configuration.
        if (!\is_null($targetPath)) {
            // Check URI
            switch (true) {
                // Check if user main role is not the same as it is in referer parameter to avoid issue!
                case preg_match('/(member|admin)/', $targetPath, $matches):
                    if (strtolower($token->getUser()->getMainRoleLabel()) !== $matches[1]) {
                        return new RedirectResponse($this->router->generate('home'));
                    }
                    return new RedirectResponse($targetPath);
                // Exclude AJAX request called as referer to avoid issue!
                case preg_match('/delete-comment/', $targetPath):
                case preg_match('/delete-media/', $targetPath):
                case preg_match('/delete-trick/', $targetPath):
                case preg_match('/load-tricks/', $targetPath):
                case preg_match('/load-trick-comments/', $targetPath):
                case preg_match('/load-trick-video/', $targetPath):
                    // By default, redirect to referer if it is not an AJAX request.
                    return new RedirectResponse($this->router->generate('home'));
                default:
                    // Otherwise, redirect to homepage by default
                    return new RedirectResponse($targetPath);
            }
        }
        // Redirect to homepage if no target path (referer) exists!
        return new RedirectResponse($this->router->generate('home'));
    }

    /**
     * Override method to avoid redirection.
     *
     * @param Request $request
     * @param AuthenticationException $exception
     *
     * @return void
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): void
    {
        if ($request->hasSession()) {
            $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        }
    }

    /**
     * {@inheritdoc}
     *
     * Method is not used when authentication failed.
     */
    protected function getLoginUrl(): string
    {
        return $this->router->generate('connect');
    }

    /**
     * {@inheritdoc}
     */
    public function supportsRememberMe(): bool
    {
        return true;
    }
}
