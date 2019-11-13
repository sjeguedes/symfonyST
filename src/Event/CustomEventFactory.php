<?php
declare(strict_types = 1);

namespace App\Event;

use App\Domain\Entity\User;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class CustomEventFactory.
 *
 * Create custom events instances with a factory method.
 */
class CustomEventFactory implements CustomEventFactoryInterface
{
    /**
     * Define a context label each time a user is allowed to renew his password.
     */
    public const USER_ALLOWED_TO_RENEW_PASSWORD = 'user.allowedTo.renewPassword';

    /**
     * Define all the custom events configuration.
     */
    private const CUSTOM_EVENT_LIST = [
        self::USER_ALLOWED_TO_RENEW_PASSWORD => [
            'event'        => UserRetrievedEvent::class,
            'eventName'    => UserRetrievedEvent::NAME,
            'data'         => [ // Key order and value type must be checked for each entry.
                'user'  => ['type' => User::class, 'value' => null]
            ]
        ]
    ];

    /**
     * @var OptionsResolver
     */
    private $optionsResolver;

    /**
     * CustomEventFactory constructor.
     *
     * @param OptionsResolver $optionsResolver
     */
    public function __construct(OptionsResolver $optionsResolver)
    {
        $this->optionsResolver = $optionsResolver;
    }

    /**
     * {@inheritDoc}
     */
        public function createFromContext(string $eventContext, array $eventParameters) : CustomEventInterface
    {
        // Event context is unknown.
        if (!\array_key_exists($eventContext,self::CUSTOM_EVENT_LIST)) {
            throw new \InvalidArgumentException('Event context argument does not exist in list!');
        }
        $event = self::CUSTOM_EVENT_LIST[$eventContext]['event'];
        // Prepare data to be injected as arguments with splat operator
        $eventData = $this->buildEventDataConfiguration($eventContext, $eventParameters);
        // Add event context as the first argument in parameters array.
        array_unshift($eventData, $eventContext);
        // Return an expected instance
        return new $event(...$eventData);
    }

    /**
     * Initialize event data with resolved configuration.
     *
     * @param string $eventContext
     * @param array $eventParameters
     *
     * @return array
     */
    private function buildEventDataConfiguration(string $eventContext, array $eventParameters) : array
    {
        // Check event parameters with expected data configuration
        $dataConfiguration = self::CUSTOM_EVENT_LIST[$eventContext]['data'];
        // Configure event data options
        $this->initEventDataOptions($dataConfiguration);
        // Validate event parameters by resolving configuration
        $this->optionsResolver->resolve($eventParameters);
        // Check if event parameters keys respect the keys correct order defined in $dataConfiguration
        $keysFromConfiguration = array_keys($dataConfiguration);
        $keysFromParameters = array_keys($eventParameters);
        array_walk($keysFromParameters, function ($v, $k) use ($keysFromConfiguration) {
            // Compare keys order provided by each array by testing values created in $keysFromParameters and $keysFromConfiguration
            if ($v !== $keysFromConfiguration[$k]) {
                throw new \InvalidArgumentException('Event parameters keys do not respect expected order set in configuration!');
            }
        });
        // Keep only a simple array with event parameters values: the next step will use this array with "splat operator".
        return array_values($eventParameters);
    }

    /**
     * Initialize particular event data options.
     *
     * @param array $dataConfiguration
     *
     * @return void
     */
    private function initEventDataOptions(array $dataConfiguration) : void
    {
        // Avoid potential issue when resolving: if any options were previously configured or resolved, clean them?
        $this->optionsResolver->clear();
        // Set configuration
        array_filter($dataConfiguration, function ($dataValue, $dataKey) {
            // Configure expected data: each configuration data value is a nested array with "type" and "value" entries.
            $this->optionsResolver->setRequired($dataKey);
            if (isset($dataValue['value']) && !\is_null($dataValue['value'])) {
                $this->optionsResolver->setDefault($dataKey, $dataValue['value']);
            } else {
                $this->optionsResolver->setDefined($dataKey);
            }
            $this->optionsResolver->setAllowedTypes($dataKey, $dataValue['type']);
        },ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Return defined event name from configuration.
     *
     * @param string $eventContext
     *
     * @return string
     */
    public function getEventNameByContext(string $eventContext) : string
    {
        return  self::CUSTOM_EVENT_LIST[$eventContext]['eventName'];
    }
}
