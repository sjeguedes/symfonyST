<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\ServiceLayer\UserManager;
use App\Form\Handler\FormHandlerInterface;
use App\Responder\Admin\RegisterResponder;
use App\Responder\Redirection\RedirectionResponder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class RegisterAction.
 *
 * Manage user registration form.
 */
class RegisterAction
{
    /**
     * @var UserManager $userService
     */
    private $userService;

    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * @var FormHandlerInterface
     */
    private $formHandler;

    /**
     * RenewPasswordAction constructor.
     *
     * @param UserManager          $userService
     * @param FlashBagInterface    $flashBag
     * @param FormHandlerInterface $formHandler
     *
     */
    public function __construct(UserManager $userService, FlashBagInterface $flashBag, FormHandlerInterface $formHandler) {
        $this->userService = $userService;
        $this->flashBag = $flashBag;
        $this->formHandler = $formHandler;
    }

    /**
     *  Show registration form (user registration) and validation errors.
     *
     * @Route("/{_locale}/register", name="registration")
     *
     * @param RedirectionResponder  $redirectionResponder
     * @param RegisterResponder     $responder
     * @param Request               $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function __invoke(RedirectionResponder $redirectionResponder, RegisterResponder $responder, Request $request) : Response
    {
        // Set form without initial model data and set the request by binding it
        $registrationForm = $this->formHandler->initForm()->bindRequest($request);
        // Process only on submit
        if ($registrationForm->isSubmitted()) {
            // Constraints and custom validation: call actions to perform if necessary on success
            $this->formHandler->processFormRequest(['userService' => $this->userService]);
        }
        $data = [
            'uniqueUserError'  => null, // TODO: add error content here!
            'registrationForm' => $registrationForm->createView()
        ];
        return $responder($data);
    }
}
