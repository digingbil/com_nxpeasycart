<?php

namespace Joomla\Component\Nxpeasycart\Site\View\Category;

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

    /**
     * @var string
     */
    protected string $searchTerm = '';

    public function display($tpl = null): void
    {
        $app      = Factory::getApplication();
        $document = $this->document;

        $model            = $this->getModel();
        $this->category   = $model ? $model->getItem() : null;
        $this->products   = $model ? $model->getProducts() : [];
        $this->categories = $model ? $model->getCategories() : [];

        $wa = $document->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_nxpeasycart.site.css',
            'media/com_nxpeasycart/css/site.css',
            ['version' => 'auto', 'relative' => true]
        );

        $siteBundleAsset = 'com_nxpeasycart.site.bundle';
        $siteScriptUri   = rtrim(Uri::root(), '/') . '/media/com_nxpeasycart/js/site.iife.js';

        if (!$wa->assetExists('script', $siteBundleAsset)) {
            $wa->registerScript(
                $siteBundleAsset,
                $siteScriptUri,
                [],
                ['defer' => true]
            );
        }

        $wa->useScript($siteBundleAsset);

        if (!$this->category) {
            $document->setTitle(Text::_('COM_NXPEASYCART_CATEGORY_NOT_FOUND'));
            $this->products = [];
            $this->searchTerm = trim($app->input->getString('q', ''));

            ob_start();
            parent::display($tpl);
            TemplateAdapter::injectIntoT4($document, ob_get_clean() ?: '');

            return;
        }

        $sitename  = (string) $app->get('sitename', '');
        $title     = (string) $this->category['title'];
        $fullTitle = $sitename !== '' ? trim($title . ' | ' . $sitename, ' |') : $title;
        $document->setTitle($fullTitle);

        $this->searchTerm = trim($app->input->getString('q', ''));

        $uri       = Uri::getInstance();
        $canonical = $uri->toString(['scheme', 'host', 'port', 'path', 'query']);
        $document->addHeadLink($canonical, 'canonical');

        ob_start();
        parent::display($tpl);
        TemplateAdapter::injectIntoT4($document, ob_get_clean() ?: '');
    }
}
