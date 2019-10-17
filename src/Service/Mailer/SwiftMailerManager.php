<?php

declare(strict_types = 1);

namespace App\Service\Mailer;

use App\Service\Templating\TemplateRendererInterface;

/**
 * Class SwiftMailerManager.
 *
 * Send an email with \Swift_Mailer service.
 */
class SwiftMailerManager
{
    /**
     * @var TemplateRendererInterface
     */
    private $renderer;

    /**
     * @var \Swift_Mailer
     */
    private $mailer;

    /**
     * @var \Swift_Plugins_Loggers_ArrayLogger
     */
    private $mailerLogger;

    /**
     * SwiftMailerManager constructor.
     *
     * @param \Swift_Mailer             $mailer
     * @param TemplateRendererInterface $renderer
     *
     * @return void
     */
    public function __construct(\Swift_Mailer $mailer, TemplateRendererInterface $renderer)
    {
        $this->mailer = $mailer;
        $this->renderer = $renderer;
        $this->mailerLogger = new \Swift_Plugins_Loggers_ArrayLogger();
        $this->mailer->registerPlugin(new \Swift_Plugins_LoggerPlugin($this->mailerLogger));
    }

    /**
     * Get SwiftMailer own "array" logger.
     *
     * @return \Swift_Plugins_Loggers_ArrayLogger
     */
    public function getLoggerPlugin() : \Swift_Plugins_Loggers_ArrayLogger
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
    public function sendEmail(
        array $from,
        array $to,
        string $subject,
        string $body
    ) : bool {
        $mail = (new \Swift_Message($subject))
            ->setFrom($from)
            ->setTo($to)
            ->setSubject($subject)
            ->setBody($body)
            ->setReplyTo($from)
            ->setContentType('text/html');
        if (!$this->mailer->send($mail)) {
            return false;
        }
        return true;
    }

    /**
     * Create an email content with a template and data.
     *
     * @param string $className
     * @param array  $data
     *
     * @return string
     */
    public function createEmailBody(string $className, array $data) : string
    {
        $template = $this->renderer->getTemplate($className);
        return $this->renderer->renderTemplate($template, $data);
    }
}