<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Domain\Entity\User;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class AuthenticationUserChecker.
 *
 * Check user account particular rules on authentication.
 */
class AuthenticationUserChecker implements UserCheckerInterface
{
    /**
     * Check user instance type.
     *
     * @param UserInterface $user
     *
     * @return void
     *
     * @throws \Exception
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('User instance has invalid type.');
        }
    }

    /**
     * Check user instance type and user account activation.
     *
     * @param UserInterface $user
     *
     * @return void
     *
     * @throws \Exception
     */
    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('User instance has invalid type.');
        }
        if (!$user->getIsActivated()) {
            throw new \Exception('User account is not activated or expired.');
        }
    }
}
