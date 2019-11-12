<?php
declare(strict_types = 1);

namespace App\Domain\ServiceLayer;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepository;
use App\Event\CustomEventFactory;
use App\Event\CustomEventFactoryInterface;
use App\Utils\Traits\UuidHelperTrait;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * Class UserManager.
 *
 * Manage users to handle, and retrieve them as a "service layer".
 */
class UserManager
{
    use LoggerAwareTrait;
    use UuidHelperTrait;

    /**
     * Define time limit to renew a password
     * when requesting renewal permission by email (forgotten password).
     */
    public const PASSWORD_RENEWAL_TIME_LIMIT = 60 * 30; // 30 min. in seconds to use with timestamps

    /**
     * @var CustomEventFactoryInterface
     */
    private $customEventFactory;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var UserRepository
     */
    private $repository;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var UserPasswordEncoderInterface
     */
    private $userPasswordEncoder;

    /**
     * UserManager constructor.
     *
     * @param CustomEventFactoryInterface  $customEventFactory
     * @param EntityManagerInterface       $entityManager
     * @param EventDispatcherInterface     $eventDispatcher
     * @param RequestStack                 $requestStack
     * @param SessionInterface             $session
     * @param UserRepository               $repository
     * @param UserPasswordEncoderInterface $userPasswordEncoder
     * @param LoggerInterface              $logger
     *
     * @return void
     */
    public function __construct(
        CustomEventFactoryInterface $customEventFactory,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger,
        RequestStack $requestStack,
        SessionInterface $session,
        UserRepository $repository,
        UserPasswordEncoderInterface $userPasswordEncoder
    ) {
        $this->customEventFactory = $customEventFactory;
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->repository = $repository;
        $this->request = $requestStack->getCurrentRequest();
        $this->requestStack = $requestStack;
        $this->session =$session;
        $this->userPasswordEncoder = $userPasswordEncoder;
        $this->setLogger($logger);
    }

    /**
     * Get entity manager.
     *
     * @return EntityManagerInterface
     */
    public function getEntityManager() : EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Get User entity repository.
     *
     * @return UserRepository
     */
    public function getRepository() : UserRepository
    {
        return $this->repository;
    }

    /**
     *  Find User by encoded uuid.
     *
     * @param string $encodedUuid an encoded uuid
     *
     * @return User|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findSingleByEncodedUuid(string $encodedUuid) : ?User
    {
        return $this->repository->findOneByUuid($this->decode($encodedUuid));
    }

    /**
     * Find User by email.
     *
     * @param string $email
     *
     * @return User|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findSingleByEmail(string $email) : ?User
    {
        return $this->repository->findOneByEmail($email);
    }

    /**
     * Generate a particular token string.
     *
     * This is used for password renewal token for instance.
     *
     * @param string $tokenId
     *
     * @return string
     */
    public function generateCustomToken(string $tokenId) : string
    {
        return substr(hash('sha256', bin2hex($tokenId . openssl_random_pseudo_bytes(8))),0,15);
    }

    /**
     * Check if password renewal request is outdated when a user tries to renew his password.
     *
     * @param DateTimeInterface|null $renewalRequestDate
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function isPasswordRenewalRequestOutdated(?DateTimeInterface $renewalRequestDate) : bool
    {
        // Outdated personal link is used to access password renewal page.
        // The reason is user password was already changed, and that caused password request date to be set to "null" again!
        if (\is_null($renewalRequestDate)) {
            return true;
        }
        $now = new \DateTime();
        $interval = $now->getTimestamp() - $renewalRequestDate->getTimestamp();
        return $interval > self::PASSWORD_RENEWAL_TIME_LIMIT ? true : false;
    }

    /**
     * Allow renewal request access to dedicated form based on unique user parameters.
     *
     * @param User $user
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function isPasswordRenewalRequestTokenAllowed(User $user) : bool
    {
        // 2 methods can be used: some request query parameters or attributes (placeholders) are expected in url!
        // Token is not used, but expected.
        if (\is_null($this->request->query->get('token')) && \is_null($this->request->attributes->get('renewalToken'))) {
            throw new \RuntimeException('No password renewal token parameters are used in request!');
        }
        // "token" query parameter (method 1) or "userId" attribute (method 2) does not match user token.
        // or renewal request date (forgotten password process) is outdated.
        $token = !\is_null($this->request->query->get('token'))
            ? $this->request->query->get('token')
            : $this->request->attributes->get('renewalToken');
        $isRenewalRequestOutdated = $this->isPasswordRenewalRequestOutdated($user->getRenewalRequestDate());
        if ($token !== $user->getRenewalToken() || $isRenewalRequestOutdated) {
            return false;
        }
        // Create a event to inform Allowed user is allowed to renew his password
        $this->createUserEvent(CustomEventFactory::USER_ALLOWED_TO_RENEW_PASSWORD, $user);
        return true;
    }

    /**
     * Create a event related to user and dispatch it.
     *
     * An auto configured User subscriber listens to that kind of event.
     *
     * @param string $eventContext
     * @param User   $user
     *
     * @return void
     */
    private function createUserEvent(string $eventContext, User $user) : void
    {
        $event = $this->customEventFactory->createFromContext($eventContext, ['user' => $user]);
        $eventName = $this->customEventFactory->getEventNameByContext($eventContext);
        $this->eventDispatcher->dispatch($eventName, $event);
    }

    /**
     * Get user from password renewal request.
     *
     * @return User|null
     *
     * @throws \Exception
     */
    public function getUserFoundInPasswordRenewalRequest() : ?User
    {
        // 2 methods can be used: some request query parameters or attributes (placeholders) are expected in url!
        // User identifier is not used, but expected as mandatory.
        if (\is_null($this->request->query->get('id')) && \is_null($this->request->attributes->get('userId'))) {
            throw new \RuntimeException('No password renewal user identifier parameter is used in request!');
        }
        $encodedUuid = !\is_null($this->request->query->get('id'))
            ? $this->request->query->get('id')
            : $this->request->attributes->get('userId');
        $user = $this->findSingleByEncodedUuid($encodedUuid);
        return $user;
    }

    /**
     * Renew user password by updating corresponding data.
     *
     * @param User   $user
     * @param string $plainPassword
     *
     * @return User
     *
     * @throws \Exception
     */
    public function renewPassword(User $user, string $plainPassword) : User
    {
        // Generate encrypted password with BCrypt
        $newPassword = $this->userPasswordEncoder->encodePassword($user, $plainPassword);
        $user->modifyPassword($newPassword, 'BCrypt');
        $user->modifyUpdateDate(new \DateTime());
        // Reset renewal token request data
        $user->updateRenewalRequestDate(null);
        $user->updateRenewalToken(null);
        $this->getEntityManager()->flush();
        // Return an updated user
        $updatedUser = $this->repository->findOneByUuid($user->getUuid());
        return $updatedUser;
    }

    /**
     * Update user password token by updating corresponding data.
     *
     * @param User $user
     *
     * @return User
     *
     * @throws \Exception
     */
    public function generatePasswordRenewalToken(User $user) : User
    {
        $user->updateRenewalRequestDate(new \Datetime());
        $user->updateRenewalToken($this->generateCustomToken($user->getNickName()));
        $this->getEntityManager()->flush();
        // Return an updated user
        $updatedUser = $this->repository->findOneByUuid($user->getUuid());
        return $updatedUser;
    }
}
