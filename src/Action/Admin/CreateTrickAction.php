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
use App\Utils\Traits\RouterHelperTrait;
use App\Utils\Traits\UuidHelperTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Class CreateTrickAction.
 *
 * Manage trick creation form.
 */
class CreateTrickAction
{
    use RouterHelperTrait;
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
     * @var array|FormHandlerInterface[]
     */
    private $formHandlers;

    /**
     * @var Security
     */
    private $security;

    /**
     * CreateTrickAction constructor.
     *
     * @param TrickManager      $trickService
     * @param ImageManager      $imageService
     * @param VideoManager      $videoService
     * @param MediaManager      $mediaService
     * @param FlashBagInterface $flashBag
     * @param RouterInterface   $router
     * @param array             $formHandlers
     * @param Security          $security
     */
    public function __construct(
        TrickManager $trickService,
        ImageManager $imageService,
        VideoManager $videoService,
        MediaManager $mediaService,
        FlashBagInterface $flashBag,
        array $formHandlers,
        RouterInterface $router,
        Security $security
    ) {
        $this->trickService = $trickService;
        $this->imageService = $imageService;
        $this->videoService = $videoService;
        $this->mediaService = $mediaService;
        $this->flashBag = $flashBag;
        $this->formHandlers = $formHandlers;
        $this->setRouter($router);
        $this->security = $security;
    }

    /**
     *  Show trick creation form and validation errors.
     *
     * @Route({
     *     "en": "/{_locale<en>}/{mainRoleLabel<admin|member>}/create-trick"
     * }, name="create_trick")
     *
     * @param RedirectionResponder $redirectionResponder
     * @param CreateTrickResponder $responder
     * @param Request              $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function __invoke(RedirectionResponder $redirectionResponder, CreateTrickResponder $responder, Request $request) : Response
    {
        // Get authenticated user
        $authenticatedUser = $this->security->getUser();
        // Use form handler as form type option
        $options = ['formHandler' => $this->formHandlers[0], 'userRoles' => $authenticatedUser->getRoles()];
        // Set form without initial model data and set the request by binding it
        $createTrickForm = $this->formHandlers[0]->initForm(null, null, $options)->bindRequest($request);
        // Use router and user main role label as form type options
        $options = ['router' => $this->router, 'userMainRoleLabel' => $authenticatedUser->getMainRoleLabel()];
        // Init ajax delete image form (used to delete temporary saved images) to pass it to trick creation view
        $deleteImageForm = $this->formHandlers[1]->initForm(null, null, $options)->getForm();
        // Process only on submit
        if ($createTrickForm->isSubmitted()) {
            // Constraints and custom validation: call actions to perform if necessary on success
            $isFormRequestValid = $this->formHandlers[0]->processFormRequest([
                'trickService' => $this->trickService,
                'imageService' => $this->imageService,
                'videoService' => $this->videoService,
                'mediaService' => $this->mediaService
            ]);
            if ($isFormRequestValid) {
                /** @var Trick $newTrick */
                $newTrick = $this->formHandlers[0]->getNewTrick();
                // Failure (redirect to empty trick creation form page)
                if (\is_null($newTrick)) {
                    $routeName = 'create_trick';
                    $routeParameters = ['mainRoleLabel' => lcfirst($authenticatedUser->getMainRoleLabel())];
                // Success (redirect to new trick page)
                } else {
                    $routeName = 'show_single_trick';
                    $routeParameters = ['slug' => $newTrick->getSlug(), 'encodedUuid' => $this->encode($newTrick->getUuid())];
                }
                return $redirectionResponder($routeName, $routeParameters);
            }
        }
        $data = [
            'trickCreationError' => $this->formHandlers[0]->getTrickCreationError() ?? null,
            'createTrickForm'    => $createTrickForm->createView(),
            'deleteImageForm'    => $deleteImageForm->createView() // Used to delete images with direct upload
        ];
        return $responder($data);
    }
}
