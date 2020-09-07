<?php

declare(strict_types=1);

namespace App\Service\Mailer\Email;

use App\Action\Admin\RegisterAction;
use App\Action\Admin\RenewPasswordAction;
use App\Action\Admin\RequestNewPasswordAction;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\UserManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class EmailConfigFactory.
 *
 * Create EmailConfig instances depending on action.
 */
class EmailConfigFactory implements EmailConfigFactoryInterface
{
    /**
     * Define a context label each time a user ask for renew his password.
     */
    public const USER_ASK_FOR_RENEW_PASSWORD = 'user.askFor.renewPassword';

    /**
     * Define a context label each time a user renew his password.
     */
    public const USER_RENEW_PASSWORD = 'user.renewPassword';

    /**
     * Define a context label each time a user is created.
     */
    public const USER_REGISTER = 'user.register';

    /**
     * Define all the emails custom parameters configuration.
     */
    public const CUSTOM_PARAMETERS_CONFIG = [
        RequestNewPasswordAction::class  => [
            self::USER_ASK_FOR_RENEW_PASSWORD => [
                'subject'      => 'Password renewal request',
                'templateData' => [
                    'user'      => ['type' => User::class, 'value' => null],
                    'timeLimit' => ['type' => 'integer', 'value'   => UserManager::PASSWORD_RENEWAL_TIME_LIMIT / 60]
                ]
            ]
        ],
        RenewPasswordAction::class  => [
            self::USER_RENEW_PASSWORD => [
                'subject'      => 'Password renewal confirmation',
                'templateData' => [
                    'user' => ['type' => User::class, 'value' => null]
                ]
            ]
        ],
        RegisterAction::class  => [
            self::USER_REGISTER => [
                'subject'      => 'Account registration confirmation',
                'templateData' => [
                    'user' => ['type' => User::class, 'value' => null]
                ]
            ]
        ]
    ];

    /**
     * @var OptionsResolver
     */
    private $optionResolver;

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    /**
     * EmailConfigFactory constructor.
     *
     * @param OptionsResolver       $optionResolver
     * @param ParameterBagInterface $parameterBag
     */
    public function __construct(OptionsResolver $optionResolver, ParameterBagInterface $parameterBag)
    {
        $this->optionResolver = $optionResolver;
        $this->parameterBag = $parameterBag;
    }

    /**
     * {@inheritDoc}
     */
    public function createFromActionContext(string $actionClassName, string $actionContext, array $emailParameters): EmailConfigInterface
    {
        if (!\array_key_exists($actionContext, self::CUSTOM_PARAMETERS_CONFIG[$actionClassName])) {
            throw new \InvalidArgumentException('Action context argument does not exist in list!');
        }
        return new EmailConfig($this->optionResolver, $this->parameterBag, $actionClassName, $actionContext, $emailParameters);
    }
}
