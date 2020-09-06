<?php

declare(strict_types = 1);

namespace App\Service\Form\Handler;

use App\Domain\Entity\Trick;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\CommentManager;
use App\Service\Form\Type\Admin\CreateCommentType;
use App\Utils\Traits\CSRFTokenHelperTrait;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class CreateCommentHandler.
 *
 * Handle the form request when a user tries to create a trick comment.
 * Call any additional validations and actions.
 */
final class CreateCommentHandler extends AbstractFormHandler
{
    use CSRFTokenHelperTrait;

    /**
     * @var csrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * CreateCommentHandler constructor.
     *
     * @param CsrfTokenManagerInterface $csrfTokenManager
     * @param FlashBagInterface         $flashBag
     * @param FormFactoryInterface      $formFactory
     * @param RequestStack              $requestStack
     */
    public function __construct(
        csrfTokenManagerInterface $csrfTokenManager,
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        RequestStack $requestStack
    ) {
        parent::__construct($flashBag, $formFactory, CreateCommentType::class, $requestStack);
        $this->csrfTokenManager = $csrfTokenManager;
        $this->customError = null;
    }

    /**
     * Add custom validation to check once form constraints are validated.
     *
     * @param array $actionData some data to handle
     *
     * @return bool
     *
     * @throws \Exception
     *
     * @see AbstractFormHandler::processFormRequest()
     */
    protected function addCustomValidation(array $actionData) : bool
    {
        $csrfToken = $this->request->request->get('create_comment')['token'];
        // CSRF token is not valid.
        if (false === $this->isCSRFTokenValid('create_comment_token', $csrfToken)) {
            throw new \Exception('Security error: CSRF form token is invalid!');
        }
        // DTO is in valid state.
        // Check CommentManager, current Trick and authenticated User instances in passed data
        $this->checkNecessaryData($actionData);
        // IMPORTANT! No custom error is set at this time, but property is kept in case of application needs!
        return true;
    }

    /**
     * Add custom action once form is validated.
     *
     * @param array $actionData some data to handle
     *
     * @return void
     *
     * @throws \Exception
     *
     * @see AbstractFormHandler::processFormRequest()
     */
    protected function addCustomAction(array $actionData) : void
    {
        // Check CommentManager, current Trick and authenticated User instances in passed data
        $this->checkNecessaryData($actionData);
        /** @var CommentManager $commentService */
        $commentService = $actionData['commentService'];
        /** @var Trick $currentTrick */
        $currentTrick = $actionData['trickToUpdate'];
        /** @var User|UserInterface $authenticatedUser */
        $authenticatedUser = $actionData['userToUpdate'];
        // Get comment form data
        $createCommentDTO = $this->form->getData();
        $newTrickComment = $commentService->createTrickComment(
            $createCommentDTO,
            $currentTrick,
            $authenticatedUser,
            true,
            true
        );
        // Create success notification message
        if (!\is_null($newTrickComment)) {
            $message = 'A new trick comment' . "\n" . 'was created successfully!' . "\n" .
                       'Please check complete list below to look at content.';
            $this->flashBag->add(
                'success',
                $message
            );
        // Create error notification message
        } else {
            $message = 'Sorry, comment creation failed' . "\n" .
                       'due a technical error!' . "\n" .
                       'Please try again with new data' . "\n" .
                       'or contact us if necessary.';
            $this->flashBag->add(
                'error',
                $message
            );
        }
    }

    /**
     * Get the comment creation error.
     *
     * @return string|null
     */
    public function getCommentCreationError() : ?string
    {
        return $this->customError;
    }
}
