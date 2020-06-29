<?php

declare(strict_types = 1);

namespace App\Tests\Service\Security\Voter;

use App\Domain\Entity\Trick;
use App\Domain\Entity\TrickGroup;
use App\Domain\Entity\User;
use App\Service\Security\Voter\TrickVoter;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Class TrickVoterTest.
 *
 * This is unit testing for TrickVoter service.
 */
class TrickVoterTest extends TestCase
{
    /**
     * @var TrickVoter
     */
    private $voter;

    /**
     * @var array
     */
    private $uuidData;

    /**
     * Setup one trick voter instance.
     *
     * @throws \Exception
     */
    public function setUp() : void
    {
        $this->voter = new TrickVoter();
    }

    /**
     * Mock a TrickGroup entity.
     *
     * @param UuidInterface $uuid
     *
     * @return TrickGroup
     */
    private function createTrickGroup(UuidInterface $uuid) : TrickGroup
    {
        $trickGroup = $this->createMock(TrickGroup::class);
        $trickGroup->method('getUuid')->willReturn($uuid);
        return $trickGroup;
    }

    /**
     * Mock a User entity.
     *
     * @param UuidInterface $uuid
     * @param array         $roles
     *
     * @return User
     *
     * @throws \Exception
     */
    private function createUser(UuidInterface $uuid, array $roles = null) : User
    {
        $user = $this->createMock(User::class);
        $user->method('getUuid')->willReturn($uuid);
        $user->method('getRoles')->willReturn($roles);
        return $user;
    }

    /**
     * Get user token depending on authentication.
     *
     * @param User|null $user
     *
     * @return TokenInterface
     */
    private function createUserToken(?User $user) : TokenInterface
    {
        $token = new AnonymousToken('secret', 'anonymous');
        if ($user) {
            $token = new UsernamePasswordToken(
                $user, 'credentials', 'memory', $user->getRoles()
            );
        }
        return $token;
    }

    /**
     * Create a set of uuid instances for objects used in tests.
     *
     * @return array
     *
     * @throws \Exception
     */
    private function createUuidData() : array
    {
        // Prepare a trick group uuid and two user uuid
        return $this->uuidData = [
            'trickGroupUuid' => Uuid::uuid4(),
            'userUuid1'      => Uuid::uuid4(),
            'userUuid2'      => Uuid::uuid4()
        ];
    }

    /**
     * Provide anonymous user trick update or delete authorization cases data
     * to test denied or granted access.
     *
     * @return \Generator
     *
     * @throws \Exception
     */
    public function getAnonymousUserUpdateOrDeleteDataProvider() : \Generator
    {
        // Get uuid instances
        $this->createUuidData();
        // An anonymous user is not allowed to update or delete published tricks.
        yield 'Anonymous user cannot update or delete published tricks' => [
            TrickVoter::AUTHOR_OR_ADMIN_CAN_UPDATE_OR_DELETE_TRICKS,
            new Trick(
                $this->createTrickGroup($this->uuidData['trickGroupUuid']),
                $this->createUser($this->uuidData['userUuid1'], []),
                'Trick test name',
                'Trick test description',
                null,
                true
            ),
            null,
            Voter::ACCESS_DENIED
        ];
        // An anonymous user is not allowed to update or delete unpublished tricks.
        yield 'Anonymous user cannot update or delete unpublished tricks' => [
            TrickVoter::AUTHOR_OR_ADMIN_CAN_UPDATE_OR_DELETE_TRICKS,
            new Trick(
                $this->createTrickGroup($this->uuidData['trickGroupUuid']),
                $this->createUser($this->uuidData['userUuid1'], []),
                'Trick test name',
                'Trick test description',
                null,
                false
            ),
            null,
            Voter::ACCESS_DENIED
        ];
    }

    /**
     * Provide authenticated simple member trick update or delete authorization cases data
     * to test denied or granted access.
     *
     * Please note this data provider is focused on all published or unpublished tricks!
     *
     * @return \Generator
     *
     * @throws \Exception
     */
    public function getAuthenticatedMemberUpdateOrDeleteDataProvider() : \Generator
    {
        // Get uuid instances
        $this->createUuidData();
        // An simple member non-author (non-owner) is not allowed to update or delete published tricks.
        yield 'Simple member non-author cannot update or delete published tricks' => [
            TrickVoter::AUTHOR_OR_ADMIN_CAN_UPDATE_OR_DELETE_TRICKS,
            new Trick(
                $this->createTrickGroup($this->uuidData['trickGroupUuid']),
                $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER']),
                'Trick test name',
                'Trick test description',
                null,
                true
            ),
            $this->createUser($this->uuidData['userUuid2'], ['ROLE_USER']),
            Voter::ACCESS_DENIED
        ];
        // An simple member author (owner) is allowed to update or delete his published tricks.
        yield 'Simple member author can update or delete his published tricks' => [
            TrickVoter::AUTHOR_OR_ADMIN_CAN_UPDATE_OR_DELETE_TRICKS,
            new Trick(
                $this->createTrickGroup($this->uuidData['trickGroupUuid']),
                $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER']),
                'Trick test name',
                'Trick test description',
                null,
                true
            ),
            $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER']),
            Voter::ACCESS_GRANTED
        ];
        // An simple member non-author (non-owner) is not allowed to update or delete his unpublished tricks.
        yield 'Simple member non-author cannot update or delete unpublished tricks' => [
            TrickVoter::AUTHOR_OR_ADMIN_CAN_UPDATE_OR_DELETE_TRICKS,
            new Trick(
                $this->createTrickGroup($this->uuidData['trickGroupUuid']),
                $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER']),
                'Trick test name',
                'Trick test description',
                null,
                false
            ),
            $this->createUser($this->uuidData['userUuid2'], ['ROLE_USER']),
            Voter::ACCESS_DENIED
        ];
        // An simple member author (owner) is allowed to update or delete his unpublished tricks.
        yield 'Simple member author can update or delete his unpublished tricks' => [
            TrickVoter::AUTHOR_OR_ADMIN_CAN_UPDATE_OR_DELETE_TRICKS,
            new Trick(
                $this->createTrickGroup($this->uuidData['trickGroupUuid']),
                $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER']),
                'Trick test name',
                'Trick test description',
                null,
                false
            ),
            $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER']),
            Voter::ACCESS_GRANTED
        ];
    }

    /**
     * Provide authenticated administrator trick update or delete authorization cases data
     * to test denied or granted access.
     *
     * Please note this data provider is focused on all published or unpublished tricks!
     *
     * @return \Generator
     *
     * @throws \Exception
     */
    public function getAuthenticatedAdminUpdateOrDeleteDataProvider() : \Generator
    {
        // Get uuid instances
        $this->createUuidData();
        // An administrator non-author (non-owner) is allowed to update or delete published tricks.
        yield 'Administrator non-author can update or delete published tricks' => [
            TrickVoter::AUTHOR_OR_ADMIN_CAN_UPDATE_OR_DELETE_TRICKS,
            new Trick(
                $this->createTrickGroup($this->uuidData['trickGroupUuid']),
                $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER', 'ROLE_ADMIN']),
                'Trick test name',
                'Trick test description',
                null,
                true
            ),
            $this->createUser($this->uuidData['userUuid2'], ['ROLE_USER', 'ROLE_ADMIN']),
            Voter::ACCESS_GRANTED
        ];
        // An administrator author (owner) is allowed to update or delete his published tricks.
        yield 'Administrator author can update or delete his published tricks' => [
            TrickVoter::AUTHOR_OR_ADMIN_CAN_UPDATE_OR_DELETE_TRICKS,
            new Trick(
                $this->createTrickGroup($this->uuidData['trickGroupUuid']),
                $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER', 'ROLE_ADMIN']),
                'Trick test name',
                'Trick test description',
                null,
                true
            ),
            $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER', 'ROLE_ADMIN']),
            Voter::ACCESS_GRANTED
        ];
        // An administrator non-author (non-owner) is allowed to update or delete his unpublished tricks.
        yield 'Administrator non-author can update or delete unpublished tricks' => [
            TrickVoter::AUTHOR_OR_ADMIN_CAN_UPDATE_OR_DELETE_TRICKS,
            new Trick(
                $this->createTrickGroup($this->uuidData['trickGroupUuid']),
                $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER', 'ROLE_ADMIN']),
                'Trick test name',
                'Trick test description',
                null,
                false
            ),
            $this->createUser($this->uuidData['userUuid2'], ['ROLE_USER', 'ROLE_ADMIN']),
            Voter::ACCESS_GRANTED
        ];
        // An administrator author (owner) is allowed to update or delete his unpublished tricks.
        yield 'Administrator author can update or delete his unpublished tricks' => [
            TrickVoter::AUTHOR_OR_ADMIN_CAN_UPDATE_OR_DELETE_TRICKS,
            new Trick(
                $this->createTrickGroup($this->uuidData['trickGroupUuid']),
                $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER', 'ROLE_ADMIN']),
                'Trick test name',
                'Trick test description',
                null,
                false
            ),
            $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER', 'ROLE_ADMIN']),
            Voter::ACCESS_GRANTED
        ];
    }

    /**
     * Provide anonymous user trick view authorization cases data
     * to test denied or granted access.
     *
     * Please note this data provider is focused on unpublished tricks only!
     *
     * @return \Generator
     *
     * @throws \Exception
     */
    public function getAnonymousUserViewDataProvider() : \Generator
    {
        // Get uuid instances
        $this->createUuidData();
        // An anonymous user is not allowed to view unpublished tricks.
        yield 'Anonymous user cannot view unpublished tricks' => [
            TrickVoter::AUTHOR_OR_ADMIN_CAN_VIEW_UNPUBLISHED_TRICKS,
            new Trick(
                $this->createTrickGroup($this->uuidData['trickGroupUuid']),
                $this->createUser($this->uuidData['userUuid1'], []),
                'Trick test name',
                'Trick test description',
                null,
                false
            ),
            null,
            Voter::ACCESS_DENIED
        ];
        // An anonymous user is not allowed to view unpublished tricks.
        yield 'Anonymous user can view published tricks' => [
            TrickVoter::AUTHOR_OR_ADMIN_CAN_VIEW_UNPUBLISHED_TRICKS,
            new Trick(
                $this->createTrickGroup($this->uuidData['trickGroupUuid']),
                $this->createUser($this->uuidData['userUuid1'], []),
                'Trick test name',
                'Trick test description',
                null,
                true
            ),
            null,
            Voter::ACCESS_GRANTED
        ];
    }

    /**
     * Provide authenticated simple member trick view authorization cases data
     * to test denied or granted access.
     *
     * Please note this data provider is focused on all published or unpublished tricks!
     *
     * @return \Generator
     *
     * @throws \Exception
     */
    public function getAuthenticatedMemberViewDataProvider() : \Generator
    {
        // Get uuid instances
        $this->createUuidData();
        // A simple member non-author (non-owner) is not allowed to view his unpublished tricks.
        yield 'Simple member non-author cannot view unpublished tricks' => [
            TrickVoter::AUTHOR_OR_ADMIN_CAN_VIEW_UNPUBLISHED_TRICKS,
            new Trick(
                $this->createTrickGroup($this->uuidData['trickGroupUuid']),
                $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER']),
                'Trick test name',
                'Trick test description',
                null,
                false
            ),
            $this->createUser($this->uuidData['userUuid2'], ['ROLE_USER']),
            Voter::ACCESS_DENIED
        ];
        // A simple member author (owner) is allowed to view his unpublished tricks.
        yield 'Simple member author can view his unpublished tricks' => [
            TrickVoter::AUTHOR_OR_ADMIN_CAN_VIEW_UNPUBLISHED_TRICKS,
            new Trick(
                $this->createTrickGroup($this->uuidData['trickGroupUuid']),
                $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER']),
                'Trick test name',
                'Trick test description',
                null,
                false
            ),
            $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER']),
            Voter::ACCESS_GRANTED
        ];
    }


    /**
     * Provide authenticated administrator trick view authorization cases data
     * to test denied or granted access.
     *
     * Please note this data provider is focused on all published or unpublished tricks!
     *
     * @return \Generator
     *
     * @throws \Exception
     */
    public function getAuthenticatedAdminViewDataProvider() : \Generator
    {
        // Get uuid instances
        $this->createUuidData();
        // An administrator non-author (non-owner) is allowed to view his unpublished tricks.
        yield 'Administrator non-author can view unpublished tricks' => [
            TrickVoter::AUTHOR_OR_ADMIN_CAN_VIEW_UNPUBLISHED_TRICKS,
            new Trick(
                $this->createTrickGroup($this->uuidData['trickGroupUuid']),
                $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER']),
                'Trick test name',
                'Trick test description',
                null,
                false
            ),
            $this->createUser($this->uuidData['userUuid2'], ['ROLE_USER', 'ROLE_ADMIN']),
            Voter::ACCESS_GRANTED
        ];
        // An administrator author (owner) is allowed to view his unpublished tricks.
        yield 'Administrator author can view his unpublished tricks' => [
            TrickVoter::AUTHOR_OR_ADMIN_CAN_VIEW_UNPUBLISHED_TRICKS,
            new Trick(
                $this->createTrickGroup($this->uuidData['trickGroupUuid']),
                $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER']),
                'Trick test name',
                'Trick test description',
                null,
                false
            ),
            $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER', 'ROLE_ADMIN']),
            Voter::ACCESS_GRANTED
        ];
    }

    /**
     *
     * @throws \Exception
     */
    /*public function testUserCanViewPublishedTricks() : void
    {
        // Get uuid instances
        $this->createUuidData();
        $canPublishedTrickBeViewed = $this->voter->
    }*/

    /**
     * Test if an anonymous user can update or delete trick.
     *
     * @dataProvider getAnonymousUserUpdateOrDeleteDataProvider
     *
     * @param string    $attribute
     * @param Trick     $trick
     * @param User|null $user
     * @param int       $expectedVote
     *
     * @return void
     */
    public function testVoteIfAnonymousCanUpdateOrDeleteTrick(
        string $attribute,
        Trick $trick,
        ?User $user,
        $expectedVote
    ) : void {
        // Get TrickVoter instance
        $voter = $this->voter;
        // Get user token (anonymous or authenticated one)
        $token = $this->createUserToken($user);
        $this->assertSame(
            $expectedVote,
            $voter->vote($token, $trick, [$attribute])
        );
    }

    /**
     * Test if an authenticated member can update or delete trick.
     *
     * @dataProvider getAuthenticatedMemberUpdateOrDeleteDataProvider
     *
     * @param string    $attribute
     * @param Trick     $trick
     * @param User|null $user
     * @param int       $expectedVote
     *
     * @return void
     */
    public function testVoteIfAuthenticatedMemberCanUpdateOrDeleteTrick(
        string $attribute,
        Trick $trick,
        ?User $user,
        $expectedVote
    ) : void {
        // Get TrickVoter instance
        $voter = $this->voter;
        // Get user token (anonymous or authenticated one)
        $token = $this->createUserToken($user);
        $this->assertSame(
            $expectedVote,
            $voter->vote($token, $trick, [$attribute])
        );
    }

    /**
     * * Test if an authenticated administrator can update or delete trick.
     *
     * @dataProvider getAuthenticatedAdminUpdateOrDeleteDataProvider
     *
     * @param string    $attribute
     * @param Trick     $trick
     * @param User|null $user
     * @param int       $expectedVote
     *
     * @return void
     */
    public function testVoteIfAuthenticatedAdminCanUpdateOrDeleteTrick(
        string $attribute,
        Trick $trick,
        ?User $user,
        $expectedVote
    ) : void {
        // Get TrickVoter instance
        $voter = $this->voter;
        // Get user token (anonymous or authenticated one)
        $token = $this->createUserToken($user);
        $this->assertSame(
            $expectedVote,
            $voter->vote($token, $trick, [$attribute])
        );
    }

    /**
     * Test if an anonymous user can view trick.
     *
     * @dataProvider getAnonymousUserViewDataProvider
     *
     * @param string    $attribute
     * @param Trick     $trick
     * @param User|null $user
     * @param int       $expectedVote
     *
     * @return void
     */
    public function testVoteIfAnonymousCanViewTrick(
        string $attribute,
        Trick $trick,
        ?User $user,
        $expectedVote
    ) : void {
        // Get TrickVoter instance
        $voter = $this->voter;
        // Get user token (anonymous or authenticated one)
        $token = $this->createUserToken($user);
        $this->assertSame(
            $expectedVote,
            $voter->vote($token, $trick, [$attribute])
        );
    }

    /**
     * Test if an authenticated member can view trick.
     *
     * @dataProvider getAuthenticatedMemberViewDataProvider
     *
     * @param string    $attribute
     * @param Trick     $trick
     * @param User|null $user
     * @param int       $expectedVote
     *
     * @return void
     */
    public function testVoteIfAuthenticatedMemberCanViewTrick(
        string $attribute,
        Trick $trick,
        ?User $user,
        $expectedVote
    ) : void {
        // Get TrickVoter instance
        $voter = $this->voter;
        // Get user token (anonymous or authenticated one)
        $token = $this->createUserToken($user);
        $this->assertSame(
            $expectedVote,
            $voter->vote($token, $trick, [$attribute])
        );
    }

    /**
     * * Test if an authenticated administrator can view trick.
     *
     * @dataProvider getAuthenticatedAdminViewDataProvider
     *
     * @param string    $attribute
     * @param Trick     $trick
     * @param User|null $user
     * @param int       $expectedVote
     *
     * @return void
     */
    public function testVoteIfAuthenticatedAdminCanViewTrick(
        string $attribute,
        Trick $trick,
        ?User $user,
        $expectedVote
    ) : void {
        // Get TrickVoter instance
        $voter = $this->voter;
        // Get user token (anonymous or authenticated one)
        $token = $this->createUserToken($user);
        $this->assertSame(
            $expectedVote,
            $voter->vote($token, $trick, [$attribute])
        );
    }

    /**
     * Test if a wrong role attribute is passed to TrickVoter::voteOnAttribute().
     *
     * Please note direct usage of this method TrickVoter::voteOnAttribute() is not expected!
     *
     * @throws \Exception
     */
    public function testVoteWithWrongAttribute(): void
    {
        // Get uuid instances
        $this->createUuidData();
        // Get trick test
        $trick = new Trick(
            $this->createTrickGroup($this->uuidData['trickGroupUuid']),
            $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER']),
            'Trick test name',
            'Trick test description',
            null
        );
        // Get user (can be authenticated or anonymous user, it doesn't matter)
        $user = $this->createUser($this->uuidData['userUuid1'], ['ROLE_USER']);
        // Get anonymous user token (it is sufficient to test exception.)
        $token = $this->createUserToken($user);
        // Get TrickVoter instance
        $voter = $this->voter;
        $this->assertSame(
            Voter::ACCESS_ABSTAIN,
            $voter->vote($token, $trick, ['WRONG_ROLE_ATTRIBUTE'])
        );
    }

    /**
     * Clear setup to free memory.
     */
    public function tearDown() : void
    {
        $this->voter = null;
        $this->uuidData = [];
    }
}
