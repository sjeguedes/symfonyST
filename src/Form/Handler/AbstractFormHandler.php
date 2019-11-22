<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use App\Domain\Entity\User;
use App\Domain\ServiceLayer\UserManager;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * Class AbstractFormHandler.
 *
 * Define Form Handler essential responsibilities.
 */
class AbstractFormHandler implements FormHandlerInterface
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
     * @var string|array // TODO: pass this property in UserHelperTrait?
     */
    protected $customError;

    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var string a F.Q.C.N
     */
    protected $formType;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var bool
     */
    private $requestHandled;


    /**
     * AbstractFormHandler constructor.
     *
     * @param FlashBagInterface    $flashBag
     * @param FormFactoryInterface $formFactory
     * @param string               $formType
     * @param RequestStack         $requestStack
     */
    public function __construct(
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        string $formType,
        RequestStack $requestStack
    ) {
        $this->flashBag = $flashBag;
        $this->form = null;
        $this->formFactory = $formFactory;
        $this->formType = $formType;
        $this->request = $requestStack->getCurrentRequest();
        $this->requestHandled = false;
    }

    /**
     * {@inheritDoc}
     */
    public function bindRequest(Request $request) : FormInterface
    {
        if (\is_null($this->form)) {
            throw new \RuntimeException('The form must be initialized first with "initForm" method!');
        }
         $this->requestHandled = true;
        return $this->form->handleRequest($request);
    }

    /**
     * {@inheritDoc}
     */
    public function initForm(array $data = null, string $formType = null, array $options = null) : FormHandlerInterface
    {
        // Set model data with $data parameter
        if (!\is_null($data)) {
            if (!method_exists($this,'initModelData')) {
                throw new \RuntimeException('Final form handler class must implement "InitModelDataInterface" to deal with custom data parameter!');
            }
            // Get particular model data object to pre-populate the form
            $data = $this->initModelData($data);
        }
        $formType = \is_null($formType) ? $this->formType : $formType;
        $options = $options ?? [];
        $this->form = $this->formFactory->create($formType, $data, $options);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function isRequestHandled() : bool
    {
        return $this->requestHandled;
    }

    /**
     * {@inheritDoc}
     */
    public function getForm() : FormInterface
    {
        if (\is_null($this->form)) {
            throw new \RuntimeException('The form must be initialized first with "initForm" method!');
        }
        return $this->form;
    }

    /**
     * Check if chosen filled in data are unique as expected in database.
     *
     * @param UserManager $userService
     * @param string|null $type
     *
     * @return bool
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     *
     * // TODO: transfer this method  as private in UserHelperTrait class!
     */
    protected function checkUserUniqueData(UserManager $userService, string $type = null) : bool
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
            $this->flashBag->add('danger','Registration failed!<br>Try to request again by checking the form fields.');
            return false;
        }
        return true;
    }

    /**
     * Check User instance.
     *
     * @param array $data
     *
     * @return User
     *
     * @throws \Exception
     *
     * // TODO: transfer this method as private in UserHelperTrait class!
     */
    protected function checkUserInstance(array $data) : User
    {
        $object = $data['userToUpdate'] ?? null;
        if (!$object instanceof User || \is_null($object)) {
            throw new \InvalidArgumentException('A instance of User must be set first!');
        }
        return $object;
    }

    /**
     * Check UserManager instance.
     *
     * @param array $data
     *
     * @return UserManager
     *
     * @throws \Exception
     *
     * // TODO: transfer this method as private in UserHelperTrait class!
     */
    protected function checkUserServiceInstance(array $data) : UserManager
    {
        $object = $data['userService'] ?? null;
        if (!$object instanceof UserManager || \is_null($object)) {
            throw new \InvalidArgumentException('A instance of UserManager must be set first!');
        }
        return $object;
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
     *
     * // TODO: transfer this method as private in UserHelperTrait class!
     */
    protected function isUserUnique(string $type, string $value, UserManager $userService) : bool
    {
        if (!\in_array($type, ['email', 'username'])) {
            throw new \InvalidArgumentException('Type of value is unknown!');
        }
        $isUniqueUser = \is_null($userService->getRepository()->loadUserByUsername($value)) ? true : false;
        if (false === $isUniqueUser) {
            switch ($type) {
                case 'email':
                    $uniqueEmailError = 'Please choose another email address!<br>It is already used!';
                    $this->customError = ['email' => $uniqueEmailError];
                    break;
                case 'username':
                    $uniqueUserNameError = 'Please choose another username!<br>Your nickname is already used!';
                    $this->customError =  ['username' => $uniqueUserNameError];
                    break;
            }
        }
        return $isUniqueUser;
    }

    /**
     * {@inheritDoc}
     */
    public function processFormRequest(array $actionData = null) : bool
    {
        if (!$this->isRequestHandled()) {
            throw new \RuntimeException('The form handler must bind the request first!');
        }
        // Check constraints validation
        if (!$this->form->isValid()) {
            // Validation failed.
            $this->flashBag->add('danger', 'Validation failed!<br>Try to login again by checking the form fields.');
            return false;
        }
        // Add custom validation
        if (method_exists($this,'addCustomValidation')) {
            // Custom validation did not pass!
            if (false === $this->addCustomValidation($actionData)) {
                return false;
            }
        }
        // Add action to perform once form is validated
        if (method_exists($this,'addCustomAction')) {
            $this->addCustomAction($actionData);
        }
        return true;
    }
}
