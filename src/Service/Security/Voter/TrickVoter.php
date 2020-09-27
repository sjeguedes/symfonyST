<?php

declare(strict_types=1);

namespace App\Service\Security\Voter;

use App\Domain\Entity\Trick;
use App\Domain\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class TrickVoter.
 *
 * Check user member permissions as concerns a trick.
 */
class TrickVoter extends Voter
{
    // Define a trick author (has at least "ROLE_USER" permissions) particular permission
    // which can view his unpublished tricks.
    // CAUTION! An administrator (has at least "ROLE_ADMIN" permissions) can view all created unpublished tricks.
    // These tricks will be moderated by an administrator.
    const AUTHOR_OR_ADMIN_CAN_VIEW_UNPUBLISHED_TRICKS = 'AUTHOR_OR_ADMIN_CAN_VIEW_UNPUBLISHED_TRICKS';
    // Define a trick author (has at least "ROLE_USER" permissions) particular permission
    // which can update or delete his own created tricks.
    // CAUTION! An administrator (has at least "ROLE_ADMIN" permissions) can update or delete all created tricks.
    const AUTHOR_OR_ADMIN_CAN_UPDATE_OR_DELETE_TRICKS = 'AUTHOR_OR_ADMIN_CAN_UPDATE_OR_DELETE_TRICKS';
    // Define more precise roles added to a simple member:
    private const ATTRIBUTES = [
        self::AUTHOR_OR_ADMIN_CAN_VIEW_UNPUBLISHED_TRICKS, // also an administrator!
        self::AUTHOR_OR_ADMIN_CAN_UPDATE_OR_DELETE_TRICKS, // also an administrator!
    ];

    /**
     * Determine if the attribute and subject are supported by this voter.
     *
     * {@inheritDoc}
     */
    protected function supports($attribute, $subject): bool
    {
        // Check if voter supports (is concerned by) attribute argument (must be one of theses voter new roles).
        // This votes only on "Tricks" objects
        return $subject instanceof Trick && \in_array($attribute, self::ATTRIBUTES);
    }

    /**
     * Perform a single access check operation on a given attribute, subject and token.
     *
     * {@inheritDoc}
     *
     * @throws \LogicException
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        /** @var User|UserInterface $user */
        $user = $token->getUser();
        // An anonymous user is not allowed since we are in authentication context!
        if (!$user || !$user instanceof UserInterface) {
            $user = null;
        }
        // A user with "administrator" role ("ROLE_ADMIN") is allowed to have these permissions!
        if ($user instanceof UserInterface && \in_array(User::ADMIN_ROLE, $user->getRoles())) {
            return true;
        }
        // Get concerned trick as subject
        $trick = $subject;
        // check permissions for an authenticated user which is a simple member ("ROLE_USER")
        switch ($attribute) {
            case self::AUTHOR_OR_ADMIN_CAN_VIEW_UNPUBLISHED_TRICKS:
                return $this->canViewHisUnpublishedTricks($trick, $user);
            case self::AUTHOR_OR_ADMIN_CAN_UPDATE_OR_DELETE_TRICKS:
                return $this->canUpdateOrDeleteHisOwnCreatedTricks($trick, $user);
            default:
                // This exception should not be reached thanks to Voter::vote() method!
                throw new \LogicException('Technical error: this exception should not be reached! Attribute permission type is unknown!');
        }
    }

    /**
     * Check if a user can view an unpublished (not moderated) trick.
     *
     * Please note he must be the author of this trick or must be an administrator.
     *
     * @param Trick     $trick
     * @param User|null $user
     *
     * @return bool
     */
    private function canViewHisUnpublishedTricks(Trick $trick, ?User $user): bool
    {
        // here we evaluate unpublished trick context only!
        if (true === $trick->getIsPublished()) {
            return true;
        }
        // If authenticated user is a simple member and is author (using uuid comparison),
        // he can view trick, despite its unpublished state (it is not moderated).
        $roles = \is_null($user) ? [] : $user->getRoles();
        if (\in_array(User::DEFAULT_ROLE, $roles) && $trick->getUser()->getUuid() === $user->getUuid()) {
            return true;
        }
        return false;
    }

    /**
     * Check if a user can update or delete a trick.
     *
     * Please note he must be the author of this trick or must be an administrator.
     *
     * @param Trick     $trick
     * @param User|null $user
     *
     * @return bool
     */
    private function canUpdateOrDeleteHisOwnCreatedTricks(Trick $trick, ?User $user): bool
    {
        // If authenticated user is the author (using uuid comparison), he can update or delete it,
        // obviously even if it is not moderated (unpublished) yet!
        $roles = \is_null($user) ? [] : $user->getRoles();
        if (\in_array(User::DEFAULT_ROLE, $roles) && $trick->getUser()->getUuid() === $user->getUuid()) {
            return true;
        }
        return false;
    }
}
