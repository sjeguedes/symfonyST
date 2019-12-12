<?php
declare(strict_types = 1);

namespace App\Domain\ServiceLayer;

use App\Domain\DTO\RegisterUserDTO;
use App\Domain\DTO\UpdateProfileDTO;
use App\Domain\Entity\Image;
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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

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
     * @var Security
     */
    private $security;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var PasswordEncoderInterface
     */
    private $userPasswordEncoder;

    /**
     * UserManager constructor.
     *
     * @param CustomEventFactoryInterface  $customEventFactory
     * @param EncoderFactoryInterface      $encoderFactory
     * @param EntityManagerInterface       $entityManager
     * @param RequestStack                 $requestStack
     * @param SessionInterface             $session
     * @param UserRepository               $repository
     * @param Security                     $security
     * @param LoggerInterface              $logger
     *
     * // TODO: reduce dependencies if it's possible
     */
    public function __construct(
        CustomEventFactoryInterface $customEventFactory,
        EncoderFactoryInterface $encoderFactory,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        RequestStack $requestStack,
        SessionInterface $session,
        UserRepository $repository,
        Security $security
    ) {
        $this->customEventFactory = $customEventFactory;
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->request = $requestStack->getCurrentRequest();
        $this->requestStack = $requestStack;
        $this->session = $session; //$this->request->getSession();
        $this->userPasswordEncoder = $encoderFactory->getEncoder(User::class);
        $this->security = $security;
        $this->setLogger($logger);
    }

    /**
     * Create a new User instance.
     *
     * @param RegisterUserDTO $dataModel a DTO
     *
     * @return User
     *
     * @throws \Exception
     */
    public function createUser(RegisterUserDTO $dataModel) : User
    {
        // Create a new instance based on DTO
        $newUser =  new User(
            $dataModel->getFamilyName(),
            $dataModel->getFirstName(),
            $dataModel->getUserName(),
            $dataModel->getEmail(),
            $this->userPasswordEncoder->encodePassword($dataModel->getPasswords(), null)
        );
        // Save data
        $this->getEntityManager()->persist($newUser);
        $this->getEntityManager()->flush();
        return $newUser;
    }

    /**
     * Try to activate user account.
     *
     * @param string $userEncodedId a user uuid encoded for url
     *
     * @return bool
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function activateAccount(string $userEncodedId) : bool
    {
        $userToValidate = $this->findSingleByEncodedUuid($userEncodedId);
        // User is unknown or his account is already activated.
        if (\is_null( $userToValidate) || $userToValidate->getIsActivated()) {
            return false;
        }
        // Update account status
        $userToValidate->modifyIsActivated(true);
        $this->getEntityManager()->flush();
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
    public function createAndDispatchUserEvent(string $eventContext, User $user) : void
    {
        $event = $this->customEventFactory->createFromContext($eventContext, ['user' => $user]);
        $eventName = $this->customEventFactory->getEventNameByContext($eventContext);
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->customEventFactory->getEventDispatcher();
        $eventDispatcher->dispatch($eventName, $event);
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
     * Get current authenticated member (user).
     *
     * @return UserInterface
     */
    public function getAuthenticatedMember() : UserInterface
    {
        return $this->security->getUser();
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
        return substr(hash('sha256', bin2hex($tokenId . openssl_random_pseudo_bytes(8))), 0, 15);
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
        // Create and dispatch an event to inform about the fact user is allowed to renew his password
        $this->createAndDispatchUserEvent(CustomEventFactory::USER_ALLOWED_TO_RENEW_PASSWORD, $user);
        return true;
    }

    /**
     * Remove user avatar image.
     *
     * @param User         $user
     * @param ImageManager $imageService
     *
     * @return void
     *
     * @throws \Exception
     */
    private function removeAvatarImage(User $user, ImageManager $imageService) : void
    {
        $imageService->removeUserAvatar($user);
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
        $newPassword = $this->userPasswordEncoder->encodePassword($plainPassword, null);
        $user->modifyPassword($newPassword, 'BCrypt');
        $user->modifyUpdateDate(new \DateTime());
        // Reset renewal token request data
        $user->updateRenewalRequestDate(null);
        $user->updateRenewalToken(null);
        $this->getEntityManager()->flush();
        // Return an updated user
        $updatedUser = $user;
        return $updatedUser;
    }

    /**
     * Set user avatar image.
     *
     * @param User         $user
     * @param ImageManager $imageService
     * @param UploadedFile $avatarFile
     *
     * @return Image
     *
     * @throws \Exception
     */
    private function setAvatarImage(User $user, ImageManager $imageService, UploadedFile $avatarFile) : Image
    {
        return $imageService->createUserAvatar($user, $avatarFile);
    }

    /**
     * Update a user profile account.
     *
     * @param ImageManager     $imageService
     * @param UpdateProfileDTO $dataModel
     * @param User             $user
     *
     * @return void
     *
     * @throws \Exception
     */
    public function updateUserProfile(UpdateProfileDTO $dataModel, User $user, ImageManager $imageService) : void
    {
        // Update user
        $user->modifyFamilyName($dataModel->getFamilyName())
            ->modifyFirstName($dataModel->getFirstName())
            ->modifyNickName($dataModel->getUserName())
            ->modifyEmail($dataModel->getEmail())
            ->modifyUpdateDate(new \DateTime());
        // Update password only if it's not null
        if (!\is_null($dataModel->getPasswords())) {
            // Don't forget to update user salt if not set to null bellow with $user->modifySalt($salt);
            $updatedPassword = $this->userPasswordEncoder->encodePassword($dataModel->getPasswords(), null);
            $user->modifyPassword($updatedPassword, 'BCrypt');
        }
        // Update avatar image media
        if (!\is_null($dataModel->getAvatar())) {
            // Remove previous avatar image if it exists
            $this->removeAvatarImage($user, $imageService);
            // Save image and corresponding media instances (persistence is used in image service layer.)
            $this->setAvatarImage($user, $imageService, $dataModel->getAvatar());
        }
        // User does not want to keep his avatar (value is set with JavaScript thanks to image remove button): default avatar will be shown!
        if (\is_null($dataModel->getAvatar()) && (true === $dataModel->getRemoveAvatar())) {
            // Remove previous avatar image.
            $this->removeAvatarImage($user, $imageService);
        }
        // Save all other user updated data
        $this->getEntityManager()->flush();
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
        $updatedUser = $user;
        return $updatedUser;
    }
}
