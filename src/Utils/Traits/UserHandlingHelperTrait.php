<?php

declare(strict_types = 1);

namespace App\Utils\Traits;

use App\Domain\ServiceLayer\UserManager;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * Trait UserHandlingHelperTrait.
 *
 * Enable handling of User instance to perform some controls on it.
 */
trait UserHandlingHelperTrait
{
    /**
     * @var FlashBagInterface
     */
    protected $flashBag;

    /**
     * @var FormInterface
     */
    protected $form;

    /**
     * Check if chosen filled in data are unique as expected in database.
     *
     * @param UserManager $userService
     * @param string|null $type
     *
     * @return bool
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function checkUserUniqueData(UserManager $userService, string $type = null) : bool
    {
        $isEmailUnique = true;
        $isUsernameUnique = true;
        if (\is_null($type) || 'email' === $type) {
            // Filled in email already exists in database.
            $emailToCheck = $this->form->getData()->getEmail(); // or $this->form->get('email')->getData()
            $isEmailUnique = $this->isUserUnique('email', $emailToCheck, $userService);
        }
        // Execute second database query only if email is unique.
        if ((\is_null($type) && $isEmailUnique) || 'username' === $type) {
            // Filled in username already exists in database.
            $userNameToCheck = $this->form->getData()->getUserName(); // or $this->form->get('userName')->getData()
            $isUsernameUnique = $this->isUserUnique('username', $userNameToCheck, $userService);
        }
        if (false === $isEmailUnique || false === $isUsernameUnique) {
            $this->flashBag->add(
                'danger',
                nl2br('Form validation failed!' . "\n" .
                'Try to request again by checking the form fields.')
            );
            return false;
        }
        return true;
    }

    /**
     * Check if a user is unique according to a type property.
     *
     * @param string      $type
     * @param string      $value
     * @param UserManager $userService
     *
     * @return bool
     *
     * @throws \Exception
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function isUserUnique(string $type, string $value, UserManager $userService) : bool
    {
        if (!\in_array($type, ['email', 'username'])) {
            throw new \InvalidArgumentException('Type of value is unknown!');
        }
        $isUniqueUser = \is_null($userService->getRepository()->loadUserByUsername($value)) ? true : false;
        if (false === $isUniqueUser) {
            switch ($type) {
                case 'email':
                    $uniqueEmailError = nl2br('Please choose another email address!' . "\n" . 'It is already used!');
                    $this->customError = ['email' => $uniqueEmailError];
                    break;
                case 'username':
                    $uniqueUserNameError = nl2br('Please choose another username!' . "\n" . 'Your nickname is already used!');
                    $this->customError = ['username' => $uniqueUserNameError];
                    break;
            }
        }
        return $isUniqueUser;
    }

    /**
     * Make a particular slug with a user nickname.
     *
     * @param string $nickname
     *
     * @return string
     *
     * @throws \Exception
     */
    public function makeSlugWithNickName(string $nickname) : string
    {
        if (!extension_loaded('iconv')) {
            throw new \Exception('Sorry, iconv module is not loaded!');
        }
        // Save the old locale and set the new locale to UTF-8
        $oldLocale = setlocale(LC_ALL, '0');
        setlocale(LC_ALL, 'en_US.UTF-8');
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $nickname);
        $clean = strtolower($clean);
        // Optional for actual nickname format, this is already cleaned by form!
        $clean = trim($clean); // delete space before and after
        // Optional for actual nickname format, indeed space character is not allowed!
        $clean = preg_replace('/\s/', '-', $clean);
        // Revert back to the old locale
        setlocale(LC_ALL, $oldLocale);
        return $clean;
    }

}
