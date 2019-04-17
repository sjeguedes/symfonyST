<?php
declare(strict_types = 1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepository;
use App\Utils\Traits\SessionHelperTrait;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Class UserManager.
 *
 * Manage users to retrieve as a "service layer".
 */
class UserManager
{
    use LoggerAwareTrait;
    use SessionHelperTrait;

    /**
     * @var UserRepository
     */
    private $repository;

    /**
     * UserManager constructor.
     *
     * @param UserRepository   $repository
     * @param LoggerInterface  $logger
     *
     * @return void
     */
    public function __construct(
        UserRepository $repository,
        LoggerInterface $logger
    ) {
        $this->repository = $repository;
        $this->setLogger($logger);
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
}
