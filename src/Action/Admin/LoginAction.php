<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Service\Form\Handler\FormHandlerInterface;
use App\Responder\Admin\LoginResponder;
use App\Responder\Redirection\RedirectionResponder;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class LoginAction.
 *
 * Manage login form authentication.
 */
class LoginAction
{
    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * @var FormHandlerInterface
     */
    private $formHandler;

    /**
     * @var Security
     */
    private $security;

    /**
     * LoginAction constructor.
     *
     * @param FlashBagInterface    $flashBag
     * @param FormHandlerInterface $formHandler
     * @param Security             $security
     *
     * @return void
     */
    public function __construct(FlashBagInterface $flashBag, FormHandlerInterface $formHandler, Security $security) {
        $this->flashBag = $flashBag;
        $this->formHandler = $formHandler;
        $this->security = $security;
    }

    /**
     *  Show login form (user connection) and validation or authentication errors.
     *
     * @Route({
     *     "en": "/{_locale<en>}/login"
     * }, name="connect", methods={"GET", "POST"})
     *
     * @param RedirectionResponder $redirectionResponder
     * @param LoginResponder       $responder
     * @param Request              $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function __invoke(RedirectionResponder $redirectionResponder, LoginResponder $responder, Request $request) : Response
    {
        // Deny access if a user is already authenticated.
        if (!\is_null($this->security->getUser())) {
            $this->flashBag->add(
                'danger',
                nl2br('A user is already authenticated!' . "\n" . 'Please logout first.')
            );
            return $redirectionResponder('home');
        }
        // Set form without initial model data and set the request by binding it
        $loginForm = $this->formHandler->initForm()->bindRequest($request);
        // Process only on submit
        if ($loginForm->isSubmitted()) {
            // Constraints and custom validation: call actions to perform if necessary on success
            $this->formHandler->processFormRequest();
        }
        $data = [
            'lastAuthenticationError' => $this->formHandler->getAuthenticationError() ?? null,
            'loginForm'               => $loginForm->createView()
        ];
        return $responder($data);
    }
}
