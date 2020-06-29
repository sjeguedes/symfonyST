<?php

declare(strict_types = 1);

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
    public function setUp() : void
    {
        $this->user = new User(
            'RYAN',
            'Mike',
            'Rooky',
            'member1@test.com',
            '$2y$10$Gh6f0Z.QgweSv5EW6TqHF.oV.lztgNEDStkz2agtQ1EGL3rDogeFi'
        );
    }

    /**
     * Test if update date is before creation date and throws an exception.
     *
     * @throws \Exception
     */
    public function testModifyUpdateDateCanNotBeSetBeforeCreation() : void
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
    public function testModifyPasswordHasBCryptFormat() : void
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
    public function testGenerateRenewalTokenHasAWrongFormat(string $token) : void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->user->updateRenewalToken($token);

    }

    /**
     * @return \Generator
     */
    public function getWrongRenewalTokensDataProvider() : \Generator
    {
        yield [substr(hash('sha256', 'test1' . bin2hex(openssl_random_pseudo_bytes(8))), 0, 0)];
        yield [substr(hash('sha256', 'test2' . bin2hex(openssl_random_pseudo_bytes(8))), 0, 13)];
        yield [substr(hash('sha256', 'test3' . bin2hex(openssl_random_pseudo_bytes(8))), 0, 20)];
    }

    /**
     * Test if renewal token has a valid format.
     *
     * Look at chosen principle based on hash with 15 characters.
     *
     * @throws \Exception
     */
    public function testUpdateRenewalTokenHasAValidFormat() : void
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
    public function testUpdateRenewalRequestDateCanNotBeSetBeforeCreation() : void
    {
        $this->expectException(\RuntimeException::class);
        $this->user->updateRenewalRequestDate(new \DateTime('-1days'));
    }

    /**
     * Clear setup to free memory.
     */
    public function tearDown() : void
    {
        $this->user = null;
    }
}
