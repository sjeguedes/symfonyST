<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\Entity\User;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\MediaManager;
use App\Domain\ServiceLayer\TrickManager;
use App\Domain\ServiceLayer\UserManager;
use App\Responder\Admin\UpdateProfileResponder;
use App\Responder\Redirection\RedirectionResponder;
use App\Service\Form\Handler\FormHandlerInterface;
use App\Service\Form\Type\Admin\UpdateProfileAvatarType;
use App\Utils\Traits\RouterHelperTrait;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class UpdateProfileAction.
 *
 * Manage user profile infos update form.
 */
class UpdateProfileAction
{
    use RouterHelperTrait;

    /**
     * @var ImageManager $imageService
     */
    private $imageService;

    /**
     * @var MediaManager
     */
    private $mediaService;

    /**
     * @var TrickManager
     */
    private $trickService;

    /**
     * @var UserManager $userService
     */
    private $userService;

    /**
     * @var array|FormHandlerInterface[]
     */
    private $formHandlers;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * UpdateProfileAction constructor.
     *
     * @param ImageManager                 $imageService
     * @param MediaManager                 $mediaService
     * @param TrickManager                 $trickService
     * @param UserManager                  $userService
     * @param array|FormHandlerInterface[] $formHandlers
     * @param RouterInterface              $router
     */
    public function __construct(
        ImageManager $imageService,
        MediaManager $mediaService,
        TrickManager $trickService,
        UserManager $userService,
        array $formHandlers,
        RouterInterface $router
    ) {
        $this->imageService = $imageService;
        $this->mediaService = $mediaService;
        $this->trickService = $trickService;
        $this->userService = $userService;
        $this->formHandlers = $formHandlers;
        $this->setRouter($router);
    }

    /**
     *  Show profile update forms (user avatar, account) and validation errors.
     *
     * Please not this action is used for both non AJAX/AJAX mode!
     * If this action is a simple AJAX request, this url is always the same even if language changed: "locale" parameter can be null.
     *
     * @Route({
     *     "en": "/{_locale?<en>}/{mainRoleLabel<admin|member>}/update-profile"
     * }, name="update_profile", methods={"GET", "POST"})
     *
     * @param RedirectionResponder   $redirectionResponder
     * @param UpdateProfileResponder $responder
     * @param Request                $request
     *
     * @return Response|null
     *
     * @throws \Exception
     */
    public function __invoke(RedirectionResponder $redirectionResponder, UpdateProfileResponder $responder, Request $request) : ?Response
    {
        // Get user from symfony security context: access is controlled by ACL.
        /** @var UserInterface|User $identifiedUser */
        $identifiedUser = $this->userService->getAuthenticatedMember();
        // Set form without initial model data or data, and set the request by binding it
        $updateProfileAvatarForm = $this->formHandlers[0]->initForm()->bindRequest($request);
        // Set form without initial model data and set the request by binding it
        $updateProfileInfosForm = $this->formHandlers[1]->initForm(['userToUpdate' => $identifiedUser])->bindRequest($request);
        // Process only on submit with POST request for both forms
        if ('POST' === $request->getMethod()) {
            // Return the appropriate response by handling the forms
            $redirectionResponse = $this->manageRedirectionResponseWithFormsHandling(
                $redirectionResponder,
                $request,
                $updateProfileAvatarForm,
                $updateProfileInfosForm,
                $identifiedUser
            );
            if (!\is_null($redirectionResponse)) {
                return $redirectionResponse;
            }
        }
        $data = [
            'avatarUploadAjaxMode'    => UpdateProfileAvatarType::IS_AVATAR_UPLOAD_AJAX_MODE,
            'avatarUploadError'       => $this->formHandlers[0]->getUserAvatarError() ?? null,
            'uniqueUserError'         => $this->formHandlers[1]->getUniqueUserError() ?? null,
            'updateProfileAvatarForm' => $updateProfileAvatarForm->createView(),
            'updateProfileInfosForm'  => $updateProfileInfosForm->createView(),
            'userAvatarImage'         => $this->imageService->getUserAvatarImage($identifiedUser),
            'userCreatedTricks'       => $this->trickService->findOnesByAuthor($identifiedUser->getUuid())
        ];
        return $responder($data);
    }

    /**
     * Get a response relative to avatar upload ajax request de/activation.
     *
     * @param bool                 $isFormRequestValid
     * @param RedirectionResponder $redirectionResponder
     * @param Request              $request
     *
     * @return Response|null
     */
    private function getAvatarUploadResponse(bool $isFormRequestValid, RedirectionResponder $redirectionResponder, Request $request) : ?Response
    {
        $routeName = 'update_profile';
        $routeParameters = ['mainRoleLabel' => $request->attributes->get('mainRoleLabel')];
        if ($request->isXmlHttpRequest()) {
            // Return a JSON response to perform a JS redirection in case of success
            if ($isFormRequestValid) {
                $redirectionURL = $this->generateURLFromRoute($routeName, $routeParameters);
                $response = new JsonResponse(['redirectionURL' => $redirectionURL]);
            // Return a JSON response to show an invalid form or a custom check error notification
            } else {
                $avatarUploadError = $this->formHandlers[0]->getUserAvatarError();
                $response = new JsonResponse($avatarUploadError);
            }
        } else {
            $response = $isFormRequestValid ? $redirectionResponder($routeName, $routeParameters) : null;
        }
        return $response;
    }

    /**
     * Manage and handle the two forms to get the corresponding response.
     *
     * @param RedirectionResponder $redirectionResponder
     * @param Request              $request
     * @param FormInterface        $updateProfileAvatarForm
     * @param FormInterface        $updateProfileInfosForm
     * @param UserInterface        $identifiedUser
     *
     * @return Response|null
     */
    private function manageRedirectionResponseWithFormsHandling(
        RedirectionResponder $redirectionResponder,
        Request $request,
        FormInterface $updateProfileAvatarForm,
        FormInterface $updateProfileInfosForm,
        UserInterface $identifiedUser
    ) : ?Response
    {
        $actionData = ['userService' => $this->userService, 'userToUpdate' => $identifiedUser];
        // Manage the forms
        switch ($submittedRequest = $request->request) {
            // Update profile avatar image
            case $submittedRequest->has($updateProfileAvatarForm->getName()): // 'update_profile_avatar'
                $actionData['imageService'] = $this->imageService;
                $actionData['mediaService'] = $this->mediaService;
                // Constraints and custom validation: call actions to perform if necessary on success
                $isFormRequestValid = $this->formHandlers[0]->processFormRequest($actionData);
                // Adapt the response depending on ajax mode de/activation
                $response = $this->getAvatarUploadResponse($isFormRequestValid, $redirectionResponder, $request);
                break;
            // Update other profile infos
            case $submittedRequest->has($updateProfileInfosForm->getName()): // 'update_profile_infos'
                // Constraints and custom validation: call actions to perform if necessary on success
                $isFormRequestValid =$this->formHandlers[1]->processFormRequest($actionData);
                // Redirect to the right page if form request is a success
                $response = $isFormRequestValid ? $redirectionResponder('home') : null;
                break;
            default:
                $response = null;
        }
        return $response;
    }
}
