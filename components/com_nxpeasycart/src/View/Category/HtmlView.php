<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Site\View\Category;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasycart\Site\Helper\SiteAssetHelper;
use Joomla\Component\Nxpeasycart\Site\Service\TemplateAdapter;

/**
 * Category listing view.
 *
 * @since 0.1.5
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Template styling tokens.
     *
     * @var array<string, mixed>
     *
     * @since 0.1.5
     */
    protected array $theme = [];

    /**
     * @var array<string, mixed>|null
     *
     * @since 0.1.5
     */
    protected ?array $category = null;

    /**
     * @var array<int, array<string, mixed>>
     *
     * @since 0.1.5
     */
    protected array $products = [];

    /**
     * @var array<int, array<string, mixed>>
     *
     * @since 0.1.5
     */
    protected array $categories = [];

    /**
     * @var array<string, int>
     *
     * @since 0.1.5
     */
    protected array $pagination = [];

    /**
     * @var string
     *
     * @since 0.1.5
     */
    protected string $paginationMode = 'paged';

    /**
     * @var string
     *
     * @since 0.1.5
     */
    protected string $searchTerm = '';

    public function display($tpl = null): void
    {
        $app      = Factory::getApplication();
        $document = $this->getDocument();
        $format   = strtolower((string) $app->input->getCmd('format', 'html'));

        $model            = $this->getModel();
        $this->category   = $model ? $model->getItem() : null;
        $this->products   = $model ? $model->getProducts() : [];
        $this->categories = $model ? $model->getCategories() : [];
        $this->theme      = TemplateAdapter::resolve();
        $search           = $model ? (string) $model->getState('filter.search', '') : '';
        $this->searchTerm = trim($search);
        $this->pagination = $model ? $model->getPagination() : [];
        $this->paginationMode = $model ? (string) $model->getState('category.pagination_mode', 'paged') : 'paged';

        if ($format === 'json') {
            echo new JsonResponse([
                'products'   => $this->products,
                'pagination' => array_merge($this->pagination, ['mode' => $this->paginationMode]),
                'category'   => $this->category,
                'search'     => $this->searchTerm,
            ]);

            $app->close();
            return;
        }

        SiteAssetHelper::useSiteAssets($document);

        if (!$this->category) {
            $document->setTitle(Text::_('COM_NXPEASYCART_CATEGORY_NOT_FOUND'));
            $this->products = [];

            parent::display($tpl);
            return;
        }

        $sitename  = (string) $app->get('sitename', '');
        $title     = (string) $this->category['title'];
        $fullTitle = $sitename !== '' ? trim($title . ' | ' . $sitename, ' |') : $title;
        $document->setTitle($fullTitle);

        $uri       = Uri::getInstance();
        $canonical = $uri->toString(['scheme', 'host', 'port', 'path', 'query']);
        $document->addHeadLink($canonical, 'canonical');

        parent::display($tpl);
    }
}
