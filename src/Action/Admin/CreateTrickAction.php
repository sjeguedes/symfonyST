<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\ServiceLayer\TrickManager;
use App\Form\Handler\FormHandlerInterface;
use App\Responder\Admin\CreateTrickResponder;
use App\Responder\Redirection\RedirectionResponder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class CreateTrickAction.
 *
 * Manage trick creation form.
 */
class CreateTrickAction
{
    /**
     * @var TrickManager $trickService
     */
    private $trickService;

    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * @var FormHandlerInterface
     */
    private $formHandler;

    /**
     * CreateTrickAction constructor.
     *
     * @param TrickManager         $trickService
     * @param FlashBagInterface    $flashBag
     * @param FormHandlerInterface $formHandler
     */
    public function __construct(TrickManager $trickService, FlashBagInterface $flashBag, FormHandlerInterface $formHandler) {
        $this->trickService = $trickService;
        $this->flashBag = $flashBag;
        $this->formHandler = $formHandler;
    }

    /**
     *  Show trick creation form and validation errors.
     *
     * @Route({
     *     "en": "/{_locale<en>}/{mainRoleLabel<admin|member>}/create-trick"
     * }, name="create_trick")
     *
     * @param RedirectionResponder  $redirectionResponder
     * @param CreateTrickResponder  $responder
     * @param Request               $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function __invoke(RedirectionResponder $redirectionResponder, CreateTrickResponder $responder, Request $request) : Response
    {
        // Set form without initial model data and set the request by binding it
        // Use form handler as form type option
        $createTrickForm = $this->formHandler->initForm(null, null, ['formHandler' => $this->formHandler])->bindRequest($request);
        // Process only on submit
        if ($createTrickForm->isSubmitted()) {
            // Constraints and custom validation: call actions to perform if necessary on success
            $isFormRequestValid = $this->formHandler->processFormRequest(['trickService' => $this->trickService]);
            if ($isFormRequestValid) {
                return $redirectionResponder('home');
            }
        }
        $data = [
            'trickCreationError' => $this->formHandler->getTrickCreationError() ?? null,
            'createTrickForm'    => $createTrickForm->createView()
        ];
        return $responder($data);
    }
}
