<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\Entity\Trick;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\MediaManager;
use App\Domain\ServiceLayer\TrickManager;
use App\Domain\ServiceLayer\VideoManager;
use App\Form\Handler\FormHandlerInterface;
use App\Responder\Admin\CreateTrickResponder;
use App\Responder\Redirection\RedirectionResponder;
use App\Utils\Traits\UuidHelperTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

/**
 * Class CreateTrickAction.
 *
 * Manage trick creation form.
 */
class CreateTrickAction
{
    use UuidHelperTrait;

    /**
     * @var TrickManager
     */
    private $trickService;

    /**
     * @var ImageManager
     */
    private $imageService;

    /**
     * @var VideoManager
     */
    private $videoService;

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
     * @var Security
     */
    private $security;


    /**
     * CreateTrickAction constructor.
     *
     * @param TrickManager         $trickService
     * @param ImageManager         $imageService
     * @param VideoManager         $videoService
     * @param MediaManager         $mediaService
     * @param FlashBagInterface    $flashBag
     * @param FormHandlerInterface $formHandler
     * @param Security             $security
     */
    public function __construct(
        TrickManager $trickService,
        ImageManager $imageService,
        VideoManager $videoService,
        MediaManager $mediaService,
        FlashBagInterface $flashBag,
        FormHandlerInterface $formHandler,
        Security $security
    ) {
        $this->trickService = $trickService;
        $this->imageService = $imageService;
        $this->videoService = $videoService;
        $this->mediaService = $mediaService;
        $this->flashBag = $flashBag;
        $this->formHandler = $formHandler;
        $this->security = $security;
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
        $options = ['formHandler' => $this->formHandler];
        $createTrickForm = $this->formHandler->initForm(null, null, $options)->bindRequest($request);
        // Process only on submit
        if ($createTrickForm->isSubmitted()) {
            // Constraints and custom validation: call actions to perform if necessary on success
            $isFormRequestValid = $this->formHandler->processFormRequest([
                'trickService' => $this->trickService,
                'imageService' => $this->imageService,
                'videoService' => $this->videoService,
                'mediaService' => $this->mediaService
            ]);
            if ($isFormRequestValid) {
                /** @var Trick $newTrick */
                $newTrick = $this->formHandler->getNewTrick();
                // Success
                if (!\is_null($newTrick)) {
                    return $redirectionResponder(
                        'show_single_trick', ['slug' => $newTrick->getSlug(), 'encodedUuid' => $this->encode($newTrick->getUuid())]
                    );
                // Failure
                } else {
                    $label = lcfirst($this->security->getUser()->getMainRoleLabel());
                    return $redirectionResponder(
                        'create_trick', ['mainRoleLabel' => $label]
                    );
                }
            }
        }
        $data = [
            'trickCreationError' => $this->formHandler->getTrickCreationError() ?? null,
            'createTrickForm'    => $createTrickForm->createView()
        ];
        return $responder($data);
    }
}
