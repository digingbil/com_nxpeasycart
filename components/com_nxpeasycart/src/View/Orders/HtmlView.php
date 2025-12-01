<?php

namespace Joomla\Component\Nxpeasycart\Site\View\Orders;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\Nxpeasycart\Site\Helper\SessionSecurityHelper;
use Joomla\Component\Nxpeasycart\Site\Helper\SiteAssetHelper;

/**
 * Orders list view for authenticated users.
 *
 * @since 0.1.5
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<int, array<string, mixed>>
     *
     * @since 0.1.5
     */
    protected array $orders = [];

    /**
     * @var array<string, int>
     *
     * @since 0.1.5
     */
    protected array $pagination = [];

    public function display($tpl = null): void
    {
        SessionSecurityHelper::regenerateIfNeeded();

        $document = $this->getDocument();
        $app       = \Joomla\CMS\Factory::getApplication();
        $document->setMetaData('robots', 'noindex, nofollow');
        $app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
        SiteAssetHelper::useSiteAssets($document);

        $model = $this->getModel();

        if ($model) {
            $this->orders      = $model->getItems();
            $this->pagination  = $model->getPagination();
        }

        $document->setTitle(Text::_('COM_NXPEASYCART_MY_ORDERS_TITLE'));

        parent::display($tpl);
    }
}
