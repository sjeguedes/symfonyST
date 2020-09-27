<?php

declare(strict_types=1);

namespace App\Service\Mailer;

use App\Service\Mailer\Email\EmailConfigInterface;
use App\Service\Templating\TemplateRendererInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Class SwiftMailerManager.
 *
 * Send an email with \Swift_Mailer service.
 */
class SwiftMailerManager
{
    use LoggerAwareTrait;

    /**
     * @var array
     */
    private $emailParameters;

    /**
     * @var \Swift_Mailer
     */
    private $mailer;

    /**
     * @var \Swift_Plugins_Loggers_ArrayLogger
     */
    private $mailerLogger;

    /**
     * @var TemplateRendererInterface
     */
    private $renderer;

    /**
     * SwiftMailerManager constructor.
     *
     * @param \Swift_Mailer             $mailer
     * @param TemplateRendererInterface $renderer
     * @param LoggerInterface           $logger
     */
    public function __construct(
        \Swift_Mailer $mailer,
        TemplateRendererInterface $renderer,
        LoggerInterface $logger
    ) {
        $this->mailer = $mailer;
        $this->mailerLogger = new \Swift_Plugins_Loggers_ArrayLogger();
        $this->mailer->registerPlugin(new \Swift_Plugins_LoggerPlugin($this->mailerLogger));
        $this->renderer = $renderer;
        $this->setLogger($logger);
     }

    /**
     * Create an email content with a template and data.
     *
     * @param string $className
     * @param array  $data
     *
     * @return string the email HTML content with data
     *
     * @throws \Exception
     */
    public function createEmailBody(string $className, array $data): string
    {
        $template = $this->renderer->getTemplate($className, true);
        return $this->renderer->renderTemplate($template, $data);
    }

    /**
     * Get SwiftMailer own "array" logger.
     *
     * @return \Swift_Plugins_Loggers_ArrayLogger
     */
    public function getLoggerPlugin(): \Swift_Plugins_Loggers_ArrayLogger
    {
        return $this->mailerLogger;
    }

    /**
     * Send an email with a \Swift_Message instance.
     *
     * @param array  $from
     * @param array  $to
     * @param string $subject
     * @param string $body
     *
     * @return bool
     */
    public function sendEmail(array $from, array $to, string $subject, string $body): bool
    {
        $email = (new \Swift_Message($subject))
            ->setFrom($from)
            ->setTo($to)
            ->setSubject($subject)
            ->setBody($body)
            ->setReplyTo($from)
            ->setContentType('text/html');
        if (!$this->mailer->send($email)) {
            return false;
        }
        return true;
    }

    /**
     * Notify by sending information.
     *
     * @param EmailConfigInterface $emailConfig
     *
     * @return bool
     *
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function notify(EmailConfigInterface $emailConfig): bool
    {
        $sender = $emailConfig->getSender();
        $receiver =  $emailConfig->getReceiver();
        $emailHtmlBody = $this->createEmailBody($emailConfig->getActionClassName(), $emailConfig->getTemplateData());
        $actionClassShortName = (new \ReflectionClass($emailConfig->getActionClassName()))->getShortName();
        // Technical error when trying to send
        if (!$isEmailSent = $this->sendEmail($sender, $receiver, $emailConfig->getSubject(), $emailHtmlBody)) {
            $this->logger->error(
                sprintf('[trace app SnowTricks] action: %s/__invoke and subject: %s  => email not sent to %s: %s',
                    $actionClassShortName, $emailConfig->getSubject(), $emailConfig->getReceiver(), $this->getLoggerPlugin()->dump()
                )
            );
        }
        return $isEmailSent;
    }
}
