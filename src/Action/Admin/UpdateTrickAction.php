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
use App\Responder\Admin\UpdateTrickResponder;
use App\Responder\Redirection\RedirectionResponder;
use App\Service\Form\Handler\FormHandlerInterface;
use App\Service\Security\Voter\TrickVoter;
use App\Utils\Traits\RouterHelperTrait;
use App\Utils\Traits\UuidHelperTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class UpdateTrickAction.
 *
 * Manage trick update form.
 */
class UpdateTrickAction
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
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * @var array|FormHandlerInterface[]
     */
    private $formHandlers;

    /**
     * UpdateTrickAction constructor.
     *
     * @param UserManager       $userService,
     * @param TrickManager      $trickService
     * @param ImageManager      $imageService
     * @param VideoManager      $videoService
     * @param MediaManager      $mediaService
     * @param FlashBagInterface $flashBag
     * @param RouterInterface   $router
     * @param array             $formHandlers
     */
    public function __construct(
        UserManager  $userService,
        TrickManager $trickService,
        ImageManager $imageService,
        VideoManager $videoService,
        MediaManager $mediaService,
        FlashBagInterface $flashBag,
        array $formHandlers,
        RouterInterface $router
    ) {
        $this->userService = $userService;
        $this->trickService = $trickService;
        $this->imageService = $imageService;
        $this->videoService = $videoService;
        $this->mediaService = $mediaService;
        $this->flashBag = $flashBag;
        $this->formHandlers = $formHandlers;
        $this->setRouter($router);
    }

    /**
     *  Show trick update form and validation errors.
     *
     * @Route({
     *     "en": "/{_locale<en>}/{mainRoleLabel<admin|member>}/update-trick/{slug<[\w-]+>}-{encodedUuid<\w+>}"
     * }, name="update_trick", methods={"GET", "POST"})
     *
     * @param RedirectionResponder $redirectionResponder
     * @param UpdateTrickResponder $responder
     * @param Request              $request
     *
     * @return Response
     *
     * @throws AccessDeniedException
     * @throws \Exception
     * @throws NotFoundHttpException
     */
    public function __invoke(RedirectionResponder $redirectionResponder, UpdateTrickResponder $responder, Request $request) : Response
    {
        // Check access to update form page
        $trickToUpdate = $this->checkAccessToUpdateAction($request);
        // Get authenticated user
        $authenticatedUser = $this->userService->getAuthenticatedMember();
        // Use form handler, trick to update and user roles as form type options
        $options = ['formHandler' => $this->formHandlers[0], 'trickToUpdate' => $trickToUpdate, 'userRoles' => $authenticatedUser->getRoles()];
        // Set form with initial model data and set the request by binding it
        $updateTrickForm = $this->formHandlers[0]->initForm(['trickToUpdate' => $trickToUpdate], null, $options)->bindRequest($request);
        // Use router and user main role label as form type options
        $options = ['router' => $this->router, 'userMainRoleLabel' => $authenticatedUser->getMainRoleLabel()];
        // Init ajax delete image form (used to delete temporary saved images) to pass it to trick update view
        $deleteImageForm = $this->formHandlers[1]->initForm(null, null, $options)->getForm();
        // Process only on submit
        if ($updateTrickForm->isSubmitted()) {
            // Constraints and custom validation: call actions to perform if necessary on success
            $isFormRequestValid = $this->formHandlers[0]->processFormRequest([
                'trickService'  => $this->trickService,
                'trickToUpdate' => $trickToUpdate,
                'imageService'  => $this->imageService,
                'videoService'  => $this->videoService,
                'mediaService'  => $this->mediaService
            ]);

            if ($isFormRequestValid) {
                // Get redirection routing parameters which depend on trick update result
                $routingParameters = $this->manageTrickUpdateResultRouting($authenticatedUser, $trickToUpdate);
                return $redirectionResponder($routingParameters['routeName'], $routingParameters['routeParameters']);
            }
        }
        $data = [
            'trickModerationState'  => $trickToUpdate->getIsPublished(),
            'trickToUpdate'         => $trickToUpdate,
            'trickUpdateError'      => $this->formHandlers[0]->getTrickUpdateError() ?? null,
            'updateTrickForm'       => $updateTrickForm->createView(),
            'deleteImageForm'       => $deleteImageForm->createView(), // Used to delete images with direct upload
            // TODO: need to add 'deleteVideoForm' here!
            'videoURLProxyPath'     => $this->trickService->generateURLFromRoute(
                'load_trick_video_url_check', ['url' => ''],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
        ];
        return $responder($data);
    }

    /**
     * Check trick update form access.
     *
     * @param Request $request
     *
     * @return Trick
     *
     * @throws AccessDeniedException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws NotFoundHttpException
     */
    private function checkAccessToUpdateAction(Request $request) : Trick
    {
        // Check if a trick can be retrieved thanks to its uuid
        $trick = $this->trickService->findSingleToUpdateInFormByEncodedUuid($request->attributes->get('encodedUuid'));
        if (\is_null($trick)) {
            throw new NotFoundHttpException('Sorry, no trick was found due to wrong identifier!');
        }
        // Check access permissions to trick update page
        $security = $this->userService->getSecurity();
        if (!$security->isGranted(TrickVoter::AUTHOR_OR_ADMIN_CAN_UPDATE_OR_DELETE_TRICKS, $trick)) {
            throw new AccessDeniedException("Current user can not update this trick!");
        }
        return $trick;
    }

    /**
     * Manage trick update redirection routing parameters.
     *
     * @param User|UserInterface $authenticatedUser
     * @param Trick              $trick
     *
     * @return array
     */
    private function manageTrickUpdateResultRouting(UserInterface $authenticatedUser, Trick $trick) : array
    {
        // Get updated trick, or null if an issue occurred!
        /** @var Trick $updatedTrick */
        $updatedTrick = $this->formHandlers[0]->getUpdatedTrick();
        // Failure (redirect to reinitialized trick update form page)
        if (\is_null($updatedTrick)) {
            $routeName = 'update_trick';
            $routeParameters = [
                'mainRoleLabel' => lcfirst($authenticatedUser->getMainRoleLabel()),
                'slug' => $trick->getSlug(),
                'encodedUuid' => $this->encode($trick->getUuid())
            ];
        // Success (redirect to new trick page)
        } else {
            $routeName = 'show_single_trick';
            $routeParameters = ['slug' => $updatedTrick->getSlug(), 'encodedUuid' => $this->encode($updatedTrick->getUuid())];
        }
        return ['routeName' => $routeName, 'routeParameters' => $routeParameters];
    }
}
