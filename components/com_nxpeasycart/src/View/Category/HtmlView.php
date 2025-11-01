<?php

namespace Nxp\EasyCart\Site\View\Category;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Uri\Uri;

/**
 * Category listing view.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $category = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $products = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $categories = [];

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $document = $this->document;

        $model = $this->getModel();
        $this->category = $model ? $model->getItem() : null;
        $this->products = $model ? $model->getProducts() : [];
        $this->categories = $model ? $model->getCategories() : [];

        $document->addStyleSheet(Uri::root(true) . '/media/com_nxpeasycart/css/site.css');

        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addRegistryFile('media/com_nxpeasycart/joomla.asset.json');
        $wa->useScript('com_nxpeasycart.site');

        if (!$this->category) {
            $document->setTitle(Text::_('COM_NXPEASYCART_CATEGORY_NOT_FOUND'));
            $this->products = [];

            parent::display($tpl);

            return;
        }

        $sitename = (string) $app->get('sitename', '');
        $title = (string) $this->category['title'];
        $fullTitle = $sitename !== '' ? trim($title . ' | ' . $sitename, ' |') : $title;
        $document->setTitle($fullTitle);

        $uri = Uri::getInstance();
        $canonical = $uri->toString(['scheme', 'host', 'port', 'path', 'query']);
        $document->addHeadLink($canonical, 'canonical');

        parent::display($tpl);
    }
}
