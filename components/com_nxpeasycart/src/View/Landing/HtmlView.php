<?php

namespace Joomla\Component\Nxpeasycart\Site\View\Landing;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Uri\Uri;

/**
 * Landing page view.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>
     */
    protected array $hero = [];

    /**
     * @var array<string, mixed>
     */
    protected array $search = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $categoryTiles = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $featured = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $arrivals = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $deals = [];

    /**
     * @var array<string, string>
     */
    protected array $sectionTitles = [];

    /**
     * @var string
     */
    protected string $trustBadge = '';

    /**
     * {@inheritDoc}
     */
    public function display($tpl = null): void
    {
        $app      = Factory::getApplication();
        $document = $this->document;

        $document->addStyleSheet(Uri::root(true) . '/media/com_nxpeasycart/css/site.css');

        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addRegistryFile('media/com_nxpeasycart/joomla.asset.json');
        $wa->useScript('com_nxpeasycart.site');

        $model = $this->getModel();

        if ($model) {
            $this->hero          = $model->getHero();
            $this->search        = $model->getSearch();
            $this->categoryTiles = $model->getCategoryTiles();
            $this->sectionTitles = $model->getSectionTitles();

            $collections   = $model->getCollections();
            $this->featured = $collections['featured'] ?? [];
            $this->arrivals = $collections['arrivals'] ?? [];
            $this->deals    = $collections['deals'] ?? [];

            $this->trustBadge = $model->getTrustBadge();
        }

        $pageTitle = $this->hero['title'] ?? Text::_('COM_NXPEASYCART_LANDING_PAGE_TITLE');
        $sitename  = (string) $app->get('sitename', '');
        $fullTitle = $sitename !== '' ? trim($pageTitle . ' | ' . $sitename, ' |') : $pageTitle;

        $document->setTitle($fullTitle);

        $subtitle = $this->hero['subtitle'] ?? '';

        if (\is_string($subtitle) && $subtitle !== '') {
            $document->setDescription(strip_tags($subtitle));
        }

        parent::display($tpl);
    }

    /**
     * Dataset passed to the Vue island.
     *
     * @return array<string, mixed>
     */
    public function getLandingPayload(): array
    {
        return [
            'hero' => $this->hero,
            'search' => $this->search,
            'categories' => $this->categoryTiles,
            'sections' => [
                [
                    'key'   => 'featured',
                    'title' => $this->sectionTitles['featured'] ?? Text::_('COM_NXPEASYCART_LANDING_FEATURED_TITLE_DEFAULT'),
                    'items' => $this->featured,
                ],
                [
                    'key'   => 'arrivals',
                    'title' => $this->sectionTitles['arrivals'] ?? Text::_('COM_NXPEASYCART_LANDING_NEW_ARRIVALS_TITLE_DEFAULT'),
                    'items' => $this->arrivals,
                ],
                [
                    'key'   => 'deals',
                    'title' => $this->sectionTitles['deals'] ?? Text::_('COM_NXPEASYCART_LANDING_DEALS_TITLE_DEFAULT'),
                    'items' => $this->deals,
                ],
            ],
            'labels' => [
                'search_label'    => Text::_('COM_NXPEASYCART_LANDING_SEARCH_LABEL'),
                'search_button'   => Text::_('COM_NXPEASYCART_LANDING_SEARCH_SUBMIT'),
                'view_all'        => Text::_('COM_NXPEASYCART_LANDING_VIEW_ALL'),
                'view_product'    => Text::_('COM_NXPEASYCART_LANDING_CARD_VIEW'),
                'categories_aria' => Text::_('COM_NXPEASYCART_LANDING_CATEGORIES_ARIA'),
            ],
            'trust' => [
                'text' => $this->trustBadge,
            ],
        ];
    }
}
