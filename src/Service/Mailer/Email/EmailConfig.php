<?php

declare(strict_types = 1);

namespace App\Service\Mailer\Email;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class EmailConfig.
 *
 * Create an email configuration to pass to a mailer
 * and guaranty a successful sending thanks to an option resolver.
 *
 * Please note this configuration accepts only 1 sender and 1 receiver.
 * Class fit application needs and must be adapted for more flexibility if necessary.
 * You can also use a different email configuration Class for other cases.
 */
class EmailConfig implements EmailConfigInterface
{
    /**
     * @var string
     */
    private $actionClassName;

    /**
     * @var string
     */
    private $actionContext;

    /**
     * @var OptionsResolver
     */
    private $optionsResolver;

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    /**
     * @var string
     */
    private $subject;

    /**
     * @var array
     */
    private $templateData;

    /**
     * @var array
     */
    private $receiver;

    /**
     * @var array
     */
    private $sender;

    /**
     * EmailConfig constructor.
     *
     * @param OptionsResolver       $optionsResolver
     * @param ParameterBagInterface $parameterBag
     * @param string                $actionClassName a F.Q.C.N to retrieve an expected email template
     * @param string                $actionContext a label to describe email configuration purpose
     * @param array                 $emailParameters
     */
    public function __construct(
        OptionsResolver $optionsResolver,
        ParameterBagInterface $parameterBag,
        string $actionClassName,
        string $actionContext,
        array $emailParameters
    ) {
        $this->optionsResolver =  $optionsResolver;
        $this->actionClassName = $actionClassName;
        $this->actionContext = $actionContext;
        $this->parameterBag = $parameterBag;
        $this->buildConfiguration($emailParameters);
    }

    /**
     * {@inheritDoc}
     */
    public function buildConfiguration(array $parameters) : void
    {
        // Set initial options with resolver
        $this->initOptions();
        // Compile initialized options and dynamic options passed to current configuration email
        // Feed automatically current configuration email object properties
        $options = $this->optionsResolver->resolve($parameters);
        foreach ($options as $option => $value) {
            if (property_exists($this, $option)) {
                $this->{$option} = $value;
            }
        }
    }

    /**
     * Check if an array respects the expected format:
     * it has a unique value.
     * It has a key formatted with an email address.
     * It has a string value associated to email key.
     *
     * @param array $value
     *
     * @return bool
     */
    private function checkArrayFormat(array $value) : bool
    {
        return 1 == \count($value) && false === !filter_var(array_keys($value)[0],FILTER_VALIDATE_EMAIL) && \is_string(array_values($value)[0]);
    }

    /**
     * {@inheritDoc}
     */
    public function initOptions() : void
    {
        // Avoid potential issue when resolving: if any options were previously configured or resolved, clean them?
        $this->optionsResolver->clear();
        // Set configuration
        $this->optionsResolver->setRequired(['sender', 'receiver', 'subject', 'templateData'])
            ->setDefault('sender', [$this->parameterBag->get('app_swift_mailer_sender_email') => $this->parameterBag->get('app_swift_mailer_sender_name')])
            ->setAllowedTypes('sender','array')
            ->setAllowedValues('sender', function ($value) {
                return $this->checkArrayFormat($value);
            })
            ->setDefined('receiver')
            ->setAllowedTypes('receiver','array')
            ->setAllowedValues('receiver', function ($value) {
                return $this->checkArrayFormat($value);
            })
            ->setDefault('subject', EmailConfigFactory::CUSTOM_PARAMETERS_CONFIG[$this->actionClassName][$this->actionContext]['subject'])
            ->setAllowedTypes('subject','string');
        // Configure template data separately with defined configuration constant CUSTOM_PARAMETERS which is an array.
        $this->initTemplateDataOptions();
    }

    /**
     * Initialize particular template data options.
     *
     * @return void
     */
    private function initTemplateDataOptions() : void
    {
        $this->optionsResolver->setAllowedTypes('templateData', 'array');
        $this->optionsResolver->setDefault('templateData', function (OptionsResolver $templateDataResolver) {
            $definedTemplateData = EmailConfigFactory::CUSTOM_PARAMETERS_CONFIG[$this->actionClassName][$this->actionContext]['templateData'];
            array_filter($definedTemplateData, function ($parameterValue, $parameterKey) use ($templateDataResolver) {
                $templateDataResolver->setRequired($parameterKey);
                // $parameterValue is a nested array.
                if (isset($parameterValue['value']) && !\is_null($parameterValue['value'])) {
                    $templateDataResolver->setDefault($parameterKey, $parameterValue['value']);
                } else {
                    $templateDataResolver->setDefined($parameterKey);
                }
                $templateDataResolver->setAllowedTypes($parameterKey, $parameterValue['type']);
            },ARRAY_FILTER_USE_BOTH);
        });

    }

    /**
     * @return string
     */
    public function getActionClassName() : string
    {
        return $this->actionClassName;
    }

    /**
     * @return string
     */
    public function getActionContext() : string
    {
        return $this->actionContext;
    }

    /**
     * @return array
     */
    public function getReceiver() : array
    {
        return $this->receiver;
    }

    /**
     * @return array
     */
    public function getSender() : array
    {
        return $this->sender;
    }

    /**
     * @return string
     */
    public function getSubject() : string
    {
        return $this->subject;
    }

    /**
     * @return array
     */
    public function getTemplateData() : array
    {
        return $this->templateData;
    }
}
