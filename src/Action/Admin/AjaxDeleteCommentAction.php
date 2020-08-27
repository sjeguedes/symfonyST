<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\Entity\Comment;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\CommentManager;
use App\Responder\Json\JsonResponder;
use App\Utils\Traits\UuidHelperTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class AjaxDeleteCommentAction.
 *
 * Manage comment deletion process.
 */
class AjaxDeleteCommentAction
{
    use UuidHelperTrait;

    /**
     * Define use of HTTP referer.
     */
    const ALLOWED_HTTP_REFERER = true;

    /**
     * @var CommentManager
     */
    private $commentService;

    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * @var Security
     */
    private $security;

    /**
     * AjaxDeleteTrickAction constructor.
     *
     * @param CommentManager    $commentService
     * @param FlashBagInterface $flashBag
     * @param Security          $security
     */
    public function __construct(
        CommentManager $commentService,
        FlashBagInterface $flashBag,
        Security $security
    ) {
        $this->commentService = $commentService;
        $this->flashBag = $flashBag;
        $this->security = $security;
    }

    /**
     *  Manage comment deletion with CSRF token process validation.
     *
     * @Route({
     *     "en": "/{_locale<en>}/{mainRoleLabel<admin|member>}/delete-comment/{encodedUuid<\w+>}/{csrfToken<[\w-]+>}"
     * }, name="delete_comment", methods={"DELETE"})
     *
     * @param CsrfTokenManagerInterface $csrfTokenManager
     * @param JsonResponder             $jsonResponder
     * @param Request                   $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function __invoke(CsrfTokenManagerInterface $csrfTokenManager, JsonResponder $jsonResponder, Request $request) : Response
    {
        // Filter AJAX request
        if (!$request->isXmlHttpRequest()) {
            throw new AccessDeniedException('Access is not allowed without AJAX request!');
        }
        // "delete_comment" must be a unique token id for session storage inside application!
        $token = new CsrfToken('delete_comment', $request->attributes->get('csrfToken'));
        // Action is stopped since token is not allowed!
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException('CSRF Token used for comment deletion is not valid!');
        }
        // Decode comment uuid
        $commentUuid = $this->decode($request->attributes->get('encodedUuid'));
        // Get comment to delete
        /** @var Comment|null $commentToDelete */
        $commentToDelete = $this->commentService->getRepository()->findOneBy(['uuid' => $commentUuid]);
        // Adapt process response parameters with success or error message
        $parameters = $this->manageCommentDeletionResult($commentToDelete, $request);
        // Return JSON response
        return $jsonResponder($parameters['data'], $parameters['statusCode']);
    }

    /**
     * Check comment creation form access.
     *
     * @param Comment|null $commentToDelete
     *
     * @return void
     */
    private function checkAccessToDeletionAction(?Comment $commentToDelete) : void
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }
        // Check access permissions to trick creation page
        $authenticatedUser = $this->security->getUser();
        /** @var User|UserInterface $authenticatedUser */
        $isUserAuthor = $authenticatedUser->getUuid()->toString() === $commentToDelete->getUser()->getUuid()->toString();

        if ($this->security->isGranted('ROLE_USER') && !$isUserAuthor) {
            throw new AccessDeniedException("Current user can not delete this comment!");
        }
    }

    /**
     * Manage comment deletion result parameters.
     *
     * @param Comment|null $commentToDelete
     * @param Request      $request
     *
     * @return array
     *
     */
    private function manageCommentDeletionResult(?Comment $commentToDelete, Request $request) : array
    {
        // Filter authenticated user permissions
        $this->checkAccessToDeletionAction($commentToDelete);
        // Error parameters
        $parameters = [
            'data'       => ['status' => 0],
            'statusCode' => 404
        ];
        // Success actions and parameters
        if (!\is_null($commentToDelete)) {
            $data = ['status' => 0];
            // Delete comment
            $isCommentRemoved = $this->commentService->removeComment($commentToDelete, true);
            if ($isCommentRemoved) {
                // Comment removal success flash notification
                $message = 'Your selected comment' . "\n" . 'was successfully deleted!' . "\n" .
                           'Please also note' . "\n" . 'all its possible associated data do not exist anymore.';
                $this->flashBag->add('success', $message);
                // Success JSON response with redirection to the HTTP referer and flash bag notification
                $httpReferer = $request->server->get('HTTP_REFERER');
                // Update data
                $data = ['status' => 1, 'notification' => $message];
                // Make redirection or not depending on configuration
                !self::ALLOWED_HTTP_REFERER ?: $data['redirection'] = $httpReferer;
            }
            // Update parameters
            $parameters = [
                'data'       => $data,
                'statusCode' => 200
            ];
        }
        return $parameters;
    }
}
