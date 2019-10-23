<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use App\Action\Admin\RequestNewPasswordAction;
use App\Domain\Entity\User;
use App\Form\Type\Admin\RequestNewPasswordType;
use App\Service\Mailer\SwiftMailerManager;
use App\Utils\Traits\CSRFTokenHelperTrait;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class RequestNewPasswordHandler.
 *
 * Handle the form request when a user asks for reset his password.
 * Call any additional actions.
 */
final class RequestNewPasswordHandler extends AbstractFormHandler
{
    use CSRFTokenHelperTrait;

    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var csrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * @var FormInterface
     */
    protected $form;

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    /**
     * @var SwiftMailerManager
     */
    private $mailer;

    /**
     * RequestNewPasswordHandler constructor.
     *
     * @param FormFactoryInterface      $formFactory
     * @param CsrfTokenManagerInterface $csrfTokenManager
     * @param ParameterBagInterface     $parameterBag
     * @param SwiftMailerManager        $mailer
     */
    public function __construct(
        FormFactoryInterface $formFactory,
        csrfTokenManagerInterface $csrfTokenManager,
        ParameterBagInterface $parameterBag,
        SwiftMailerManager $mailer
    ) {
        $this->formFactory = $formFactory;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->form = $this->initForm(requestNewPasswordType::class);
        $this->parameterBag = $parameterBag;
        $this->mailer = $mailer;
    }

    /**
     * Get mailer service.
     *
     * @return SwiftMailerManager
     */
    public function getMailer() : SwiftMailerManager
    {
        return $this->mailer;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Exception
     */
    public function processFormRequestOnSubmit(Request $request) : bool
    {
        $csrfToken = $request->request->get('request_new_password')['token'];
        // CSRF token is not valid.
        if (false === $this->isCSRFTokenValid('request_new_password_token', $csrfToken)) {
            throw new \Exception('Security error: CSRF form token is invalid!');
        }
        $validProcess = $this->getForm()->isValid() ? true : false;
        return $validProcess;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     */
    public function executeFormRequestActionOnSuccess(array $actionData = null, Request $request = null) : bool
    {
        /** @var User $user */
        $user = $actionData['userToUpdate'] ?? null;
        if (!$user instanceof User || \is_null($user)) {
            throw new \InvalidArgumentException('A instance of User must be set first!');
        }
        $sender = [$this->parameterBag->get('app_swiftmailer_website_email') => 'SnowTricks - Member service'];
        $receiver = [$user->getEmail() => $user->getFirstName() . ' ' . $user->getFamilyName()];
        $emailHtmlBody = $this->mailer->createEmailBody(RequestNewPasswordAction::class, ['_locale' => $request->get('_locale'), 'user' => $user]);
        $isEmailSent = $this->mailer->sendEmail($sender, $receiver, 'Password renewal request', $emailHtmlBody);
        return $isEmailSent ? true : false;
    }
}
