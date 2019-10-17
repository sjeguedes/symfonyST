<?php
declare(strict_types = 1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepository;
use App\Utils\Traits\UuidHelperTrait;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * Class UserManager.
 *
 * Manage users to retrieve as a "service layer".
 */
class UserManager
{
    use LoggerAwareTrait;
    use UuidHelperTrait;

    /**
     * Time limit to renew a password when requesting renewal permission by email (forgotten password).
     */
    const PASSWORD_RENEWAL_TIME_LIMIT = 60 * 15; // 15 min in seconds to use with timestamps

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var UserRepository
     */
    private $repository;

    /**
     * @var UserPasswordEncoderInterface
     */
    private $userPasswordEncoder;

    /**
     * UserManager constructor.
     *
     * @param EntityManagerInterface       $entityManager
     * @param UserRepository               $repository
     * @param UserPasswordEncoderInterface $userPasswordEncoder
     * @param LoggerInterface              $logger
     *
     * @return void
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $repository,
        UserPasswordEncoderInterface $userPasswordEncoder,
        LoggerInterface $logger
    ) {
        $this->repository = $repository;
        $this->userPasswordEncoder = $userPasswordEncoder;
        $this->entityManager = $entityManager;
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
    public function findSingleByUuid(string $encodedUuid) : ?User
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
        return substr(hash('sha256', bin2hex($tokenId . openssl_random_pseudo_bytes(8))), 0, 15);
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
        if (\is_null($renewalRequestDate)) {
            throw new \InvalidArgumentException('Password renewal request date can not be null!');
        }
        $now = new \DateTime();
        $interval = $now->getTimestamp() - $renewalRequestDate->getTimestamp();
        return $interval > self::PASSWORD_RENEWAL_TIME_LIMIT ? true : false;
    }

    /**
     * Allow renewal request access to dedicated form based on unique user parameters.
     *
     * @param User    $user
     * @param Request $request
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function isPasswordRenewalRequestTokenAllowed(User $user, Request $request) : bool
    {
        // 2 methods can be used: some request query parameters or attributes (placeholders) are expected in url!
        // Token is not used, but expected.
        if (\is_null($request->query->get('token')) && \is_null($request->attributes->get('renewalToken'))) {
            throw new \RuntimeException('No password renewal token parameters are used in request!');
        }
        // "token" query parameter (method 1) or "userId" attribute (method 2) does not match user token.
        // or renewal request date (forgotten password process) is outdated.
        $token = !\is_null($request->query->get('token')) ? $request->query->get('token') :  $request->attributes->get('renewalToken');
        $isRenewalRequestOutdated = $this->isPasswordRenewalRequestOutdated($user->getRenewalRequestDate());
        if ($token !== $user->getRenewalToken() || $isRenewalRequestOutdated) {
            return false;
        }
        return true;
    }

    /**
     * Get user from password renewal request.
     *
     * @param Request $request
     *
     * @return User|null
     *
     * @throws \Exception
     */
    public function getUserFoundInPasswordRenewalRequest(Request $request) : ?User
    {
        // 2 methods can be used: some request query parameters or attributes (placeholders) are expected in url!
        // User identifier is not used, bur expected.
        if (\is_null($request->query->get('id')) && \is_null($request->attributes->get('userId'))) {
            throw new \RuntimeException('No password renewal user identifier parameters are used in request!');
        }
        $encodedUuid = !\is_null($request->query->get('id')) ? $request->query->get('id') : $request->attributes->get('userId');
        $user = $this->findSingleByUuid($encodedUuid);
        return $user;
    }

    /**
     * Renew user password by updating corresponding data.
     *
     * @param User   $user
     * @param string $plainPassword
     *
     * @return void
     *
     * @throws \Exception
     */
    public function renewPassword(User $user, string $plainPassword) : void
    {
        // Generate encrypted password with BCrypt
        $newPassword = $this->userPasswordEncoder->encodePassword($user, $plainPassword);
        $user->modifyPassword($newPassword, 'BCrypt');
        $user->modifyUpdateDate(new \DateTime('now'));
        // Reset renewal token request data
        $user->updateRenewalRequestDate(null);
        $user->updateRenewalToken(null);
        $this->getEntityManager()->flush();
    }
}
