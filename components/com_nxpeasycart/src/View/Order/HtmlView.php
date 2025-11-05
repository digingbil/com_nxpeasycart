<?php

namespace Joomla\Component\Nxpeasycart\Site\View\Order;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Order confirmation view.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $order = null;

    public function display($tpl = null): void
    {
        $document = $this->document;

        $wa = $document->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_nxpeasycart.site.css',
            'media/com_nxpeasycart/css/site.css',
            ['version' => 'auto', 'relative' => true]
        );
        $wa->getRegistry()->addRegistryFile('media/com_nxpeasycart/joomla.asset.json');
        $wa->useScript('com_nxpeasycart.site');

        $model       = $this->getModel();
        $this->order = $model ? $model->getItem() : null;

        if ($this->order) {
            $document->setTitle(Text::sprintf('COM_NXPEASYCART_ORDER_CONFIRMED_TITLE', $this->order['order_no'] ?? ''));
        } else {
            $document->setTitle(Text::_('COM_NXPEASYCART_ORDER_NOT_FOUND'));
        }

        parent::display($tpl);
    }
}
