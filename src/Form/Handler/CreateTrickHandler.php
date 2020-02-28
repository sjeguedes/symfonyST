<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use App\Domain\ServiceLayer\TrickManager;
use App\Form\Type\Admin\CreateTrickType;
use App\Utils\Traits\CSRFTokenHelperTrait;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class CreateTrickHandler.
 *
 * Handle the form request when a member tries to create a new trick.
 * Call any additional validations and actions.
 */
final class CreateTrickHandler extends AbstractUploadFormHandler
{
    use CSRFTokenHelperTrait;

    /**
     * @var csrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * RegisterHandler constructor.
     *
     * @param CsrfTokenManagerInterface   $csrfTokenManager
     * @param FlashBagInterface           $flashBag
     * @param FormFactoryInterface        $formFactory
     * @param RequestStack                $requestStack
     */
    public function __construct(
        csrfTokenManagerInterface $csrfTokenManager,
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        RequestStack $requestStack
    ) {
        parent::__construct($flashBag, $formFactory, CreateTrickType::class, $requestStack);
        $this->csrfTokenManager = $csrfTokenManager;
        $this->customError = null;
    }

    /**
     * Add custom validation to check once form constraints are validated.
     *
     * @param array $actionData some data to handle
     *
     * @return bool
     *
     * @throws \Exception
     *
     * @see AbstractFormHandler::processFormRequest()
     */
    protected function addCustomValidation(array $actionData) : bool
    {
        $csrfToken = $this->request->request->get('create_trick')['token'];
        // CSRF token is not valid.
        if (false === $this->isCSRFTokenValid('create_trick_token', $csrfToken)) {
            throw new \Exception('Security error: CSRF form token is invalid!');
        }
        // Check TrickManager instance in passed data
        $this->checkNecessaryData($actionData);
        $trickService = $actionData['trickService'];
        // TODO: add all custom validations by feeding potential custom error with return state and flasbag in case of failure!
        // DTO is in valid state but:
        // 1. trick name (title) must not exist in database.
        return false;
    }

    /**
     * Add custom action once form is validated.
     *
     * @param array $actionData some data to handle
     *
     * @return void
     *
     * @throws \Exception
     *
     * @see AbstractFormHandler::processFormRequest()
     */
    protected function addCustomAction(array $actionData) : void
    {
        // Check UserManager instance in passed data
        $this->checkNecessaryData($actionData);
        /** @var TrickManager $trickService */
        $trickService = $actionData['trickService'];
        // TODO: do stuff for trick creation (add TrickManager method)!
        // Create a new trick in database with the validated DTO
        //$newTrick = $trickService->createTrick($this->form->getData());
        // 1. Retrieve image Media entities thanks to images saved names with loop
        // 2. Create the two other images (medium and thumb) physically on server
        // 3. Update "isMain" and "description" data in Media entities
        // 4. Create videos Media and Video entities, and then retrieve Medias entities to provide with loop
        // 5. Create trick by merging authenticated user, images et videos entities
        // TODO: do stuff for trick creation success flashbag!
        // Creation success notification
        /*if ($isTrickCreated) {
            $this->flashBag->add(
                'success',
                'The trick was created successfully!<br>Please check trick list on website to look at content.');
        }*/
    }

    /**
     * Get the trick creation error.
     *
     * @return array|null
     */
    public function getTrickCreationError() : ?array
    {
        return $this->customError;
    }
}
