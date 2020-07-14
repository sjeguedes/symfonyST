<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\Entity\Trick;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\MediaManager;
use App\Domain\ServiceLayer\TrickManager;
use App\Domain\ServiceLayer\UserManager;
use App\Domain\ServiceLayer\VideoManager;
use App\Service\Form\Handler\FormHandlerInterface;
use App\Responder\Admin\CreateTrickResponder;
use App\Responder\Redirection\RedirectionResponder;
use App\Utils\Traits\RouterHelperTrait;
use App\Utils\Traits\UuidHelperTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

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
     * @var UserManager
     */
    private $userService;

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
     * @var array|FormHandlerInterface[]
     */
    private $formHandlers;

    /**
     * CreateTrickAction constructor.
     *
     * @param UserManager       $userService
     * @param TrickManager      $trickService
     * @param ImageManager      $imageService
     * @param VideoManager      $videoService
     * @param MediaManager      $mediaService
     * @param RouterInterface   $router
     * @param array             $formHandlers
     */
    public function __construct(
        UserManager  $userService,
        TrickManager $trickService,
        ImageManager $imageService,
        VideoManager $videoService,
        MediaManager $mediaService,
        array $formHandlers,
        RouterInterface $router
    ) {
        $this->userService = $userService;
        $this->trickService = $trickService;
        $this->imageService = $imageService;
        $this->videoService = $videoService;
        $this->mediaService = $mediaService;
        $this->formHandlers = $formHandlers;
        $this->setRouter($router);
    }

    /**
     *  Show trick creation form and validation errors.
     *
     * @Route({
     *     "en": "/{_locale<en>}/{mainRoleLabel<admin|member>}/create-trick"
     * }, name="create_trick", methods={"GET", "POST"})
     *
     * @param RedirectionResponder $redirectionResponder
     * @param CreateTrickResponder $responder
     * @param Request              $request
     *
     * @return Response
     *
     * @throws AccessDeniedException
     * @throws \Exception
     */
    public function __invoke(RedirectionResponder $redirectionResponder, CreateTrickResponder $responder, Request $request) : Response
    {
        // Check access to creation form page
        $this->checkAccessToCreationAction();
        // Get authenticated user
        $authenticatedUser = $this->userService->getAuthenticatedMember();
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
                // Get redirection routing parameters which depend on trick creation result
                $routingParameters = $this->manageTrickCreationResultRouting($authenticatedUser);
                return $redirectionResponder($routingParameters['routeName'], $routingParameters['routeParameters']);
            }
        }
        $data = [
            'trickCreationError' => $this->formHandlers[0]->getTrickCreationError() ?? null,
            'createTrickForm'    => $createTrickForm->createView(),
            'deleteImageForm'    => $deleteImageForm->createView() // Used to delete images with direct upload
            // No need to add a video deletion form since videos are not temporarily saved!
        ];
        return $responder($data);
    }

    /**
     * Check trick creation form access.
     *
     * @return void
     *
     * @throws AccessDeniedException
     */
    private function checkAccessToCreationAction() : void
    {
        // Check access permissions to trick creation page
        $security = $this->userService->getSecurity();
        if (!$security->isGranted('ROLE_USER')) {
            throw new AccessDeniedException("Current user can not create a trick!");
        }
    }

    /**
     * Manage trick creation redirection routing parameters.
     *
     * @param User|UserInterface $authenticatedUser
     *
     * @return array
     */
    private function manageTrickCreationResultRouting(UserInterface $authenticatedUser) : array
    {
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
        return ['routeName' => $routeName, 'routeParameters' => $routeParameters];
    }
}
