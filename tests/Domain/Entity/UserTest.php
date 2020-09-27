<?php

declare(strict_types=1);

namespace App\Tests\Domain\Entity;

use App\Domain\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Class UserTest.
 *
 * This is unit testing for User entity.
 */
class UserTest extends TestCase
{
    /**
     * @var User
     */
    private $user;

    /**
     * Setup one user instance.
     *
     */
    public function setUp(): void
    {
        $this->user = new User(
            'RYAN',
            'Mike',
            'Miky',
            'admin1@test.com',
            '$2y$10$FG3LeBBCW0J4o.j3TYCoSOeAQqrDO/2Ovbpitv.yB8sJvMq37WPEq'
        );
    }

    /**
     * Test if main role label is correct.
     *
     * @dataProvider getRolesProvider
     *
     * @param array  $roles
     * @param string $label
     *
     * @throws \Exception
     */
    public function testGetMainRoleLabel(array $roles, string $label): void
    {
        $this->user->modifyRoles($roles);
        $this->assertEquals($label, $this->user->getMainRoleLabel());
    }

    /**
     * @return \Generator
     */
    public function getRolesProvider(): \Generator
    {
        yield [['ROLE_SUPER_ADMIN'], 'Admin'];
        yield [['ROLE_ADMIN'], 'Admin'];
        yield [['ROLE_USER'], 'Member'];
        yield [['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], 'Admin'];
        yield [['ROLE_USER', 'ROLE_ADMIN'], 'Admin'];
        yield [['ROLE_USER', 'ROLE_SUPER_ADMIN'], 'Admin'];
    }

    /**
     * Test if update date is before creation date and throws an exception.
     *
     * @throws \Exception
     */
    public function testModifyUpdateDateCanNotBeSetBeforeCreation(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->user->modifyUpdateDate(new \DateTime('-1days'));
    }

    /**
     * Test if password update format matches BCrypt hash.
     *
     * @see https://stackoverflow.com/questions/31417387/regular-expression-to-find-bcrypt-hash
     *
     * @throws \Exception
     */
    public function testModifyPasswordHasBCryptFormat(): void
    {
        $this->user->modifyPassword('$2y$10$mAN1D4rwZT0wnxRM2er/0OfzgpZelwL6PSTNoqC3p/EmfV3lV5DSe', 'BCrypt');
        $password = $this->user->getPassword();
        $this->assertRegExp('/^\$2[ayb]\$.{56}$/', $password);
    }

    /**
     * Test if renewal token value does not match expected length and throws an exception.
     *
     * @dataProvider getWrongRenewalTokensDataProvider
     *
     * @param string $token
     *
     * @throws \Exception
     */
    public function testGenerateRenewalTokenHasAWrongFormat(string $token): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->user->updateRenewalToken($token);

    }

    /**
     * @return \Generator
     */
    public function getWrongRenewalTokensDataProvider(): \Generator
    {
        yield [substr(hash('sha256', 'test1' . bin2hex(openssl_random_pseudo_bytes(8))), 0, 0)];
        yield [substr(hash('sha256', 'test2' . bin2hex(openssl_random_pseudo_bytes(8))), 0, 13)];
        yield [substr(hash('sha256', 'test3' . bin2hex(openssl_random_pseudo_bytes(8))), 0, 20)];
    }

    /**
     * Test if nickname (username) has a valid format.
     *
     * Look at allowed unicode characters.
     */
    public function testIsNickNameValidated(): void
    {
        $nickname = $this->user->getNickName();
        $this->assertRegExp('/^[\w-]{3,15}$/u', $nickname);
    }

    /**
     * Test if nickname (username) has a valid format.
     *
     * @dataProvider getWrongNickNameDataProvider
     *
     * @param string $username
     *
     * @throws \Exception
     */
    public function testIsNickNameValidatedHasWrongFormat(string $username): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->user->modifyNickName($username);
    }

    /**
     * @return \Generator
     */
    public function getWrongNickNameDataProvider(): \Generator
    {
        yield ['No'];
        yield ['a-very-long-user-nickname'];
        yield ['a-nickname-@'];
        yield ['a nickname'];
    }

    /**
     * Test if renewal token has a valid format.
     *
     * Look at chosen principle based on hash with 15 characters.
     *
     * @throws \Exception
     */
    public function testUpdateRenewalTokenHasAValidFormat(): void
    {
        $this->user->updateRenewalToken(
            // Defined principle to generate a token
            substr(hash('sha256', bin2hex('test' . openssl_random_pseudo_bytes(8))), 0, 15)
        );
        $token = $this->user->getRenewalToken();
        $this->assertRegExp('/^[a-z0-9]{15}$/', $token);
    }

    /**
     * Test if renewal request date is before creation date and throws an exception.
     *
     * @throws \Exception
     */
    public function testUpdateRenewalRequestDateCanNotBeSetBeforeCreation(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->user->updateRenewalRequestDate(new \DateTime('-1days'));
    }

    /**
     * Clear setup to free memory.
     */
    public function tearDown(): void
    {
        $this->user = null;
    }
}
