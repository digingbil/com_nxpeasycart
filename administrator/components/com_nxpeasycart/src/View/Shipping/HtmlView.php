<?php

namespace Joomla\Component\Nxpeasycart\Administrator\View\Shipping;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Administrator\Service\ShippingRuleService;

/**
 * Shipping rules listing view.
 *
 * @since 0.1.11
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>
     *
     * @since 0.1.11
     */
    protected array $shippingRules = [
        'items'      => [],
        'pagination' => [],
    ];

    /**
     * {@inheritDoc}
     *
     * @since 0.1.11
     */
    public function display($tpl = null): void
    {
        $this->getDocument()->setTitle(Text::_('COM_NXPEASYCART_MENU_SHIPPING'));
        $this->shippingRules = $this->fetchShippingRules();

        parent::display($tpl);
    }

    private function fetchShippingRules(): array
    {
        $container = Factory::getContainer();

        if (!$container->has(ShippingRuleService::class)) {
            $container->set(
                ShippingRuleService::class,
                static fn ($container) => new ShippingRuleService($container->get(DatabaseInterface::class))
            );
        }

        /** @var ShippingRuleService $service */
        $service = $container->get(ShippingRuleService::class);

        return $service->paginate([], 50, 0);
    }
}
