<?php

namespace Joomla\Component\Nxpeasycart\Administrator\View\Logs;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Administrator\Service\AuditService;

/**
 * Logs view placeholder.
 *
 * @since 0.1.5
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>
     *
     * @since 0.1.5
     */
    protected array $logsData = [
        'items'      => [],
        'pagination' => [],
    ];

    /**
     * {@inheritDoc}
     *
     * @since 0.1.5
     */
    public function display($tpl = null): void
    {
        $this->getDocument()->setTitle(Text::_('COM_NXPEASYCART_MENU_LOGS'));
        $this->logsData = $this->fetchLogs();

        parent::display($tpl);
    }

    private function fetchLogs(): array
    {
        $container = Factory::getContainer();

        if (!$container->has(AuditService::class)) {
            $container->set(
                AuditService::class,
                static fn ($container) => new AuditService($container->get(DatabaseInterface::class))
            );
        }

        /** @var AuditService $audit */
        $audit = $container->get(AuditService::class);

        return $audit->paginate([], 20, 0);
    }
}
