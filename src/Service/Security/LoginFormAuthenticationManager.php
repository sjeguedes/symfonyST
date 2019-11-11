<?php

declare(strict_types = 1);

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
    public function supports(Request $request) : bool
    {
        return 'connection' === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials(Request $request) : array
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
    public function getUser($credentials, UserProviderInterface $userProvider) : UserInterface
    {
        $token = new CsrfToken('login_token', $credentials['csrf_token']);
        // CSRF token is not valid.
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new \Exception('Security error: CSRF form token is invalid!');
        }
        $user = $this->userService->getRepository()->loadUserByUsername($credentials['username']);
        // Authentication failed. User value is null.
        if (!$user) {
            throw new CustomUserMessageAuthenticationException('Please check your credentials!<br>User can not be found.');
        }
        return $user;
    }

    /**
     * {@inheritdoc}
     *
     * @throws CustomUserMessageAuthenticationException
     */
    public function checkCredentials($credentials, UserInterface $user) : bool
    {
        $isPasswordMatching = $this->passwordEncoder->isPasswordValid($user, $credentials['password']);
        // Authentication failed. Password value does not match user.
        if (!$isPasswordMatching) {
            throw new CustomUserMessageAuthenticationException('Please check your credentials!<br>User and password do not match.');
        }
        return $isPasswordMatching;
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey) : RedirectResponse
    {
        $targetPath = $this->getTargetPath($request->getSession(), $providerKey);
        // Target path is defined in configuration.
        if (!\is_null($targetPath)) {
            return new RedirectResponse($targetPath);
        }
        // Otherwise, redirect by default to homepage.
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
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception) : void
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
    protected function getLoginUrl() : string
    {
        return $this->router->generate('connection');
    }

    /**
     * {@inheritdoc}
     */
    public function supportsRememberMe() : bool
    {
        return true;
    }
}
