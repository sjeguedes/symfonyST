<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\MediaManager;
use App\Service\Form\Handler\FormHandlerInterface;
use App\Responder\Json\JsonResponder;
use App\Responder\Redirection\RedirectionResponder;
use App\Utils\Traits\RouterHelperTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Class DeleteImageAction.
 *
 * Manage image deletion form.
 */
class AjaxDeleteImageAction
{
    use RouterHelperTrait;

    /**
     * @var ImageManager
     */
    private $imageService;

    /**
     * @var MediaManager
     */
    private $mediaService;

    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * @var FormHandlerInterface
     */
    private $formHandler;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var Security
     */
    private $security;


    /**
     * CreateTrickAction constructor.
     *
     * @param ImageManager         $imageService
     * @param MediaManager         $mediaService
     * @param FlashBagInterface    $flashBag
     * @param FormHandlerInterface $formHandler
     * @param RouterInterface      $router
     * @param Security             $security
     */
    public function __construct(
        ImageManager $imageService,
        MediaManager $mediaService,
        FlashBagInterface $flashBag,
        FormHandlerInterface $formHandler,
        RouterInterface $router,
        Security $security
    ) {
        $this->imageService = $imageService;
        $this->mediaService = $mediaService;
        $this->flashBag = $flashBag;
        $this->formHandler = $formHandler;
        $this->setRouter($router);
        $this->security = $security;
    }

    /**
     *  Manage image deletion form and validation errors.
     *
     * @Route({
     *     "en": "/{_locale<en>}/{mainRoleLabel<admin|member>}/delete-image"
     * }, name="delete_image", methods={"DELETE"})
     *
     * @param JsonResponder        $jsonResponder
     * @param RedirectionResponder $redirectionResponder
     * @param Request              $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function __invoke(JsonResponder $jsonResponder, RedirectionResponder $redirectionResponder, Request $request) : Response
    {
        // Get authenticated user main role label
        $userMainRoleLabel = lcfirst($this->security->getUser()->getMainRoleLabel());
        // Use router, user main role label, and media owner type as form type options
        $options = [
            'router'            => $this->router,
            'userMainRoleLabel' => $userMainRoleLabel
        ];
        // Set form without initial model data and set the request by binding it
        $deleteImageForm = $this->formHandler->initForm(null, null, $options)->bindRequest($request);
        // Process only on submitted AJAX request
        if ($request->isXmlHttpRequest()) {
            // Constraints and custom validation: call actions to perform if necessary on success
            $isFormRequestValid = $this->formHandler->processFormRequest([
                'imageService' => $this->imageService,
                'mediaService' => $this->mediaService
            ]);
            // Form is not valid!
            if (!$isFormRequestValid) {
                $data = $this->formHandler->getImageDeletionError();
            // Get success message or error message if removal process failed!
            } else {
                if (\is_null($data = $this->formHandler->getImageDeletionSuccess())) {
                    $data = $this->formHandler->getImageDeletionError();
                }
            }
            return $jsonResponder($data);
        }
        // Redirect to home if request is not expected!
        return $redirectionResponder('home');
    }
}
