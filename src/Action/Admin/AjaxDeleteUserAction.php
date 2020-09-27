<?php

declare(strict_types=1);

namespace App\Action\Admin;

use App\Domain\Entity\User;
use App\Domain\ServiceLayer\UserManager;
use App\Responder\Json\JsonResponder;
use App\Utils\Traits\RouterHelperTrait;
use App\Utils\Traits\UuidHelperTrait;
use Doctrine\Common\Collections\Collection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class AjaxDeleteUserAction.
 *
 * Manage user (account) deletion process.
 */
class AjaxDeleteUserAction
{
    use RouterHelperTrait;
    use UuidHelperTrait;

    /**
     * @var UserManager
     */
    private $userService;

    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var Security
     */
    private $security;

    /**
     * AjaxDeleteUserAction constructor.
     *
     * @param UserManager       $userService
     * @param FlashBagInterface $flashBag
     * @param RouterInterface   $router
     * @param Security          $security
     */
    public function __construct(
        UserManager $userService,
        FlashBagInterface $flashBag,
        RouterInterface $router,
        Security $security
    ) {
        $this->userService = $userService;
        $this->flashBag = $flashBag;
        $this->setRouter($router);
        $this->security = $security;
    }

    /**
     *  Manage user (account) deletion with CSRF token process validation.
     *
     * @Route({
     *     "en": "/{_locale<en>}/{mainRoleLabel<admin|member>}/delete-user/{encodedUuid<\w+>}/{csrfToken<[\w-]+>}"
     * }, name="delete_user", methods={"DELETE"})
     *
     * @param CsrfTokenManagerInterface $csrfTokenManager
     * @param JsonResponder             $jsonResponder
     * @param Request                   $request
     *
     * @return Response
     *
     * CAUTION! Update any URI change in:
     * @see LoginFormAuthenticationManager::onAuthenticationSuccess()
     *
     * @throws \Exception
     */
    public function __invoke(CsrfTokenManagerInterface $csrfTokenManager, JsonResponder $jsonResponder, Request $request): Response
    {
        // Filter AJAX request
        if (!$request->isXmlHttpRequest()) {
            throw new AccessDeniedException('Access is not allowed without AJAX request!');
        }
        // "delete_user" must be a unique token id for session storage inside application!
        $token = new CsrfToken('delete_user', $request->attributes->get('csrfToken'));
        // Action is stopped since token is not allowed!
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException('CSRF Token used for user deletion is not valid!');
        }
        // Decode user uuid
        $userUuid = $this->decode($request->attributes->get('encodedUuid'));
        /** @var UuidInterface $authenticatedUserUuid */
        $authenticatedUserUuid = $this->security->getUser()->getUuid();
        // Check if authenticated user is the same as user to delete!
        if ($authenticatedUserUuid->toString() !== $userUuid->toString()) {
            throw new AccessDeniedException('Your are not allowed to delete this account!');
        }
        // Get user to delete
        /** @var User|null $userToDelete */
        $userToDelete = $this->userService->getRepository()->findOneBy(['uuid' => $userUuid]);
        // Adapt process response parameters with success or error message
        $parameters = $this->manageUserDeletionResult($userToDelete);
        // Return JSON response
        return $jsonResponder($parameters['data'], $parameters['statusCode']);
    }

    /**
     * Manage user deletion result parameters.
     *
     * @param User|null $userToDelete
     *
     * @return array
     *
     * @throws \Exception
     */
    private function manageUserDeletionResult(?User $userToDelete): array
    {
        // Error parameters
        $parameters = [
            'data'       => ['status' => 0],
            'statusCode' => 404
        ];
        // Success actions and parameters
        if (!\is_null($userToDelete)) {
            $data = ['status' => 0];
            // Retrieve default user to transfer data
            $mediasToTransfer = $userToDelete->getMedias();
            $tricksToTransfer = $userToDelete->getTricks();
            $defaultUser = $this->transferDataToDefaultSuperAdminUser($mediasToTransfer, $tricksToTransfer);
            // Delete user (account), his corresponding media owner entity,
            // and his tricks comments entities, thanks to cascade option
            $isUserRemoved = $this->userService->removeUser($userToDelete, true);
            if ($isUserRemoved) {
                // Update default user entity when old user is correctly deleted.
                $this->userService->addAndSaveNewEntity($defaultUser, false, true);
                // User removal success flash notification
                $message = 'Your user account' . "\n" . 'was successfully deleted!' . "\n" .
                           'Please also note' . "\n" . 'all your tricks comments do not exist anymore,' . "\n" .
                           'and your created tricks will remain available!' ;
                $this->flashBag->add('success', $message);
                // Success JSON response with redirection and flash bag notification
                $redirectionURI = $this->router->generate('home');
                // Update data
                $data = ['status' => 1, 'notification' => $message, 'redirection' => $redirectionURI];
            }
            // Update parameters
            $parameters = [
                'data'       => $data,
                'statusCode' => 200
            ];
        }
        return $parameters;
    }

    /**
     * Transfer data to conserve from user to delete to default super admin user.
     * At this time, it is essentially medias and tricks collections!
     *
     * @param Collection $mediasToTransfer
     * @param Collection $tricksToTransfer
     *
     * @return User
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    private function transferDataToDefaultSuperAdminUser(Collection $mediasToTransfer, Collection $tricksToTransfer): User
    {
        // Retrieve default user to transfer data
        $defaultUser = $this->userService->findSingleByEmail(UserManager::DEFAULT_SUPER_ADMIN_USER_EMAIL);
        if (\is_null($defaultUser)) {
            throw new \RuntimeException(
                'Default super admin user email must not be changed! Please add this feature if necessary.'
            );
        }
        // Transfer medias from user to delete to default super admin user as new author
        foreach ($mediasToTransfer as $media) {
            $media->modifyUser($defaultUser);
            $defaultUser->addMedia($media);
        }
        // Transfer tricks from user to delete to default super admin user as new author
        foreach ($tricksToTransfer as $trick) {
            $trick->modifyUser($defaultUser);
            $defaultUser->addTrick($trick);
        }
        // Trace update
        $defaultUser->modifyUpdateDate(new \DateTime('now'));
        return $defaultUser;
    }
}
