<?php

namespace Nxp\EasyCart\Admin\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mailer\MailerInterface;
use Joomla\CMS\Uri\Uri;
use RuntimeException;

/**
 * Outgoing email helper for transactional mail.
 */
class MailService
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Send an order confirmation email.
     *
     * @param array<string, mixed> $order
     */
    public function sendOrderConfirmation(array $order): void
    {
        $recipient = $order['email'] ?? '';

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $subject = Text::sprintf('COM_NXPEASYCART_EMAIL_ORDER_SUBJECT', $order['order_no'] ?? '');
        $body    = $this->renderTemplate('order_confirmation', [
            'order' => $order,
            'store' => [
                'name' => Factory::getConfig()->get('sitename'),
                'url'  => Uri::root(),
            ],
        ]);

        $mailer = clone $this->mailer;
        $mailer->setSubject($subject);
        $mailer->isHtml(true);
        $mailer->setBody($body);
        $mailer->addRecipient($recipient);

        $mailer->Send();
    }

    /**
     * Render a blade-style PHP template.
     *
     * @param array<string, mixed> $context
     */
    private function renderTemplate(string $name, array $context = []): string
    {
        $path = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/templates/email/' . $name . '.php';

        if (!is_file($path)) {
            throw new RuntimeException('Email template not found: ' . $name);
        }

        extract($context, EXTR_SKIP);

        ob_start();
        include $path;

        return (string) ob_get_clean();
    }
}
