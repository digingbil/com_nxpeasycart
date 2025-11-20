<?php

namespace Joomla\Component\Nxpeasycart\Site\View\Category;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasycart\Site\Helper\SiteAssetHelper;
use Joomla\Component\Nxpeasycart\Site\Service\TemplateAdapter;

/**
 * Category listing view.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Template styling tokens.
     *
     * @var array<string, mixed>
     */
    protected array $theme = [];

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
        $document = $this->getDocument();

        $model            = $this->getModel();
        $this->category   = $model ? $model->getItem() : null;
        $this->products   = $model ? $model->getProducts() : [];
        $this->categories = $model ? $model->getCategories() : [];
        $this->theme      = TemplateAdapter::resolve();

        SiteAssetHelper::useSiteAssets($document);

        if (!$this->category) {
            $document->setTitle(Text::_('COM_NXPEASYCART_CATEGORY_NOT_FOUND'));
            $this->products = [];
            $this->searchTerm = trim($app->input->getString('q', ''));

            parent::display($tpl);
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

        parent::display($tpl);
    }
}
