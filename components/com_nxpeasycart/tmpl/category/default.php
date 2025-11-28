<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Site\Helper\RouteHelper;

$theme   = $this->theme ?? [];
$cssVars = '';
foreach (($theme['css_vars'] ?? []) as $var => $value) {
    $cssVars .= $var . ':' . $value . ';';
}

/** @var array<string, mixed>|null $this->category */
/** @var array<int, array<string, mixed>> $this->products */
/** @var array<int, array<string, mixed>> $this->categories */

$category        = $this->category     ?? null;
$products        = $this->products     ?? [];
$categories      = $this->categories   ?? [];
$pagination      = $this->pagination   ?? [];
$paginationMode  = $this->paginationMode ?? 'paged';
$activeSlug      = $category['slug']   ?? '';
$categoryTitle   = $category['title']  ?? Text::_('COM_NXPEASYCART_CATEGORY_ALL');
$baseRoute       = RouteHelper::getCategoryRoute($category['slug'] ?? null, $category['id'] ?? null, false);
$categoryRoute   = RouteHelper::getCategoryRoute($category['slug'] ?? null, $category['id'] ?? null);
$searchTerm      = $this->searchTerm ?? '';
$limitDefault    = isset($pagination['limit']) ? (int) $pagination['limit'] : (int) \count($products);
$limit           = $limitDefault > 0 ? $limitDefault : 12;
$start           = isset($pagination['start']) ? max(0, (int) $pagination['start']) : 0;
$total           = isset($pagination['total']) ? (int) $pagination['total'] : (int) \count($products);
$pages           = isset($pagination['pages']) ? (int) $pagination['pages'] : ($limit > 0 ? (int) ceil($total / $limit) : 1);
$currentPage     = isset($pagination['current']) ? (int) $pagination['current'] : 1;
$pages           = max(1, $pages);
$currentPage     = max(1, $currentPage);
$hasMore         = ($start + $limit) < $total;
$nextStart       = $start + $limit;
$prevStart       = max(0, $start - $limit);
$buildPageLink   = static function (int $startValue, bool $asJson) use ($baseRoute, $limit, $searchTerm): string {
    $uri = Uri::getInstance($baseRoute);

    if ($limit > 0) {
        $uri->setVar('limit', $limit);
    } else {
        $uri->delVar('limit');
    }

    if ($startValue > 0) {
        $uri->setVar('start', $startValue);
    } else {
        $uri->delVar('start');
    }

    if ($searchTerm !== '') {
        $uri->setVar('q', $searchTerm);
    } else {
        $uri->delVar('q');
    }

    if ($asJson) {
        $uri->setVar('format', 'json');
    } else {
        $uri->delVar('format');
    }

    return $uri->toString();
};
$paginationPayload = [
    'total'     => $total,
    'limit'     => $limit,
    'start'     => $start,
    'pages'     => $pages,
    'current'   => $currentPage,
    'mode'      => $paginationMode,
    'has_more'  => $hasMore,
    'base'      => $baseRoute,
    'search'    => $searchTerm,
    'next'      => $hasMore ? $buildPageLink($nextStart, false) : '',
    'prev'      => $currentPage > 1 ? $buildPageLink($prevStart, false) : '',
    'next_json' => $hasMore ? $buildPageLink($nextStart, true) : '',
    'prev_json' => $currentPage > 1 ? $buildPageLink($prevStart, true) : '',
];
$prevLink = $paginationPayload['prev'];
$nextLink = $paginationPayload['next'];
$categoryJson  = htmlspecialchars(
    json_encode($category ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$productsJson = htmlspecialchars(
    json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$categoriesJson = htmlspecialchars(
    json_encode($categories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$searchValue = htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8');
$labels      = [
    'filters'            => Text::_('COM_NXPEASYCART_CATEGORY_FILTERS'),
    'filter_all'         => Text::_('COM_NXPEASYCART_CATEGORY_FILTER_ALL'),
    'empty'              => Text::_('COM_NXPEASYCART_CATEGORY_EMPTY'),
    'view_product'       => Text::_('COM_NXPEASYCART_CATEGORY_VIEW_PRODUCT'),
    'search_placeholder' => Text::_('COM_NXPEASYCART_PRODUCTS_SEARCH_PLACEHOLDER'),
    'search_label'       => Text::_('COM_NXPEASYCART_PRODUCTS_SEARCH_PLACEHOLDER'),
    'add_to_cart'        => Text::_('COM_NXPEASYCART_PRODUCT_ADD_TO_CART'),
    'added'              => Text::_('COM_NXPEASYCART_PRODUCT_ADDED_TO_CART'),
    'view_cart'          => Text::_('COM_NXPEASYCART_PRODUCT_VIEW_CART'),
    'out_of_stock'       => Text::_('COM_NXPEASYCART_PRODUCT_OUT_OF_STOCK'),
    'error_generic'      => Text::_('COM_NXPEASYCART_PRODUCT_ADD_TO_CART_ERROR'),
    'select_variant'     => Text::_('COM_NXPEASYCART_PRODUCT_SELECT_VARIANT'),
    'prev'               => Text::_('JPREV'),
    'next'               => Text::_('JNEXT'),
    'pagination_label'   => Text::_('JGLOBAL_PAGINATION_LABEL'),
    'page_of'            => Text::_('COM_NXPEASYCART_ORDER_PAGE_X_OF_Y'),
    'load_more'          => Text::_('COM_NXPEASYCART_CATEGORY_LOAD_MORE'),
    'loading_more'       => Text::_('COM_NXPEASYCART_CATEGORY_LOADING_MORE'),
    'no_more'            => Text::_('COM_NXPEASYCART_CATEGORY_NO_MORE'),
    'load_error'         => Text::_('COM_NXPEASYCART_CATEGORY_LOAD_ERROR'),
];
$labelsJson = htmlspecialchars(
    json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$links = [
    'all'    => RouteHelper::getCategoryRoute(),
    'search' => $categoryRoute,
];
$linksJson = htmlspecialchars(
    json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);

$cart = [
    'token' => Session::getFormToken(),
    'endpoints' => [
        'add'     => Route::_('index.php?option=com_nxpeasycart&task=cart.add&format=json', false),
        'summary' => Route::_('index.php?option=com_nxpeasycart&task=cart.summary&format=json', false),
    ],
    'links' => [
        'cart'     => RouteHelper::getCartRoute(),
        'checkout' => RouteHelper::getCheckoutRoute(),
    ],
];
$cartJson = htmlspecialchars(
    json_encode($cart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$paginationJson = htmlspecialchars(
    json_encode($paginationPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$locale   = $this->locale ?? \Joomla\CMS\Factory::getApplication()->getLanguage()->getTag();
$currency = ConfigHelper::getBaseCurrency();
?>

<section
    class="nxp-ec-category"
    data-nxp-island="category"
    data-nxp-category="<?php echo $categoryJson; ?>"
    data-nxp-products="<?php echo $productsJson; ?>"
    data-nxp-categories="<?php echo $categoriesJson; ?>"
    data-nxp-labels="<?php echo $labelsJson; ?>"
    data-nxp-links="<?php echo $linksJson; ?>"
    data-nxp-pagination="<?php echo $paginationJson; ?>"
    data-nxp-search="<?php echo $searchValue; ?>"
    data-nxp-cart="<?php echo $cartJson; ?>"
    data-nxp-locale="<?php echo htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'); ?>"
    data-nxp-currency="<?php echo htmlspecialchars(strtoupper($currency), ENT_QUOTES, 'UTF-8'); ?>"
    <?php if ($cssVars !== '') : ?>style="<?php echo htmlspecialchars($cssVars, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
>
    <header class="nxp-ec-category__header">
        <h1 class="nxp-ec-category__title">
            <?php echo htmlspecialchars($categoryTitle, ENT_QUOTES, 'UTF-8'); ?>
        </h1>
        <form
            class="nxp-ec-category__search"
            action="<?php echo htmlspecialchars($links['search'], ENT_QUOTES, 'UTF-8'); ?>"
            method="get"
        >
            <input
                type="search"
                name="q"
                value="<?php echo $searchValue; ?>"
                placeholder="<?php echo Text::_('COM_NXPEASYCART_PRODUCTS_SEARCH_PLACEHOLDER'); ?>"
            />
        </form>

        <nav
            class="nxp-ec-category__filters"
            aria-label="<?php echo htmlspecialchars($labels['filters'], ENT_QUOTES, 'UTF-8'); ?>"
        >
            <?php foreach ($categories as $cat) : ?>
                <a
                    class="nxp-ec-category__filter<?php echo $activeSlug === ($cat['slug'] ?? '') ? ' is-active' : ''; ?>"
                    href="<?php echo htmlspecialchars($cat['link'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                >
                    <?php echo htmlspecialchars($cat['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <?php if (empty($products)) : ?>
        <p class="nxp-ec-category__empty">
            <?php echo Text::_('COM_NXPEASYCART_CATEGORY_EMPTY'); ?>
        </p>
    <?php else : ?>
        <div class="nxp-ec-category__grid">
            <?php foreach ($products as $product) : ?>
                <article class="nxp-ec-product-card">
                    <?php if (!empty($product['images'][0])) : ?>
                        <a
                            class="nxp-ec-product-card__media"
                            href="<?php echo htmlspecialchars($product['link'], ENT_QUOTES, 'UTF-8'); ?>"
                            aria-label="<?php echo htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <img
                                src="<?php echo htmlspecialchars($product['images'][0], ENT_QUOTES, 'UTF-8'); ?>"
                                alt="<?php echo htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                loading="lazy"
                            />
                        </a>
                    <?php endif; ?>
                    <div class="nxp-ec-product-card__body">
                        <h2 class="nxp-ec-product-card__title">
                            <a href="<?php echo htmlspecialchars($product['link'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </h2>
                        <?php if (!empty($product['short_desc'])) : ?>
                            <p class="nxp-ec-product-card__intro">
                                <?php echo htmlspecialchars($product['short_desc'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($product['out_of_stock'])) : ?>
                            <p class="nxp-ec-product-card__badge">
                                <?php echo Text::_('COM_NXPEASYCART_PRODUCT_OUT_OF_STOCK'); ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($product['price_label'])) : ?>
                            <p class="nxp-ec-product-card__price">
                                <?php echo htmlspecialchars($product['price_label'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                        <a class="nxp-ec-btn nxp-ec-btn--ghost" href="<?php echo htmlspecialchars($product['link'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo Text::_('COM_NXPEASYCART_CATEGORY_VIEW_PRODUCT'); ?>
                        </a>
                        <?php if (!empty($product['out_of_stock']) && !empty($labels['out_of_stock'])) : ?>
                            <p class="nxp-ec-product-card__hint nxp-ec-product-card__hint--alert">
                                <?php echo htmlspecialchars($labels['out_of_stock'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="nxp-ec-category__pagination-shell">
            <?php if ($paginationMode === 'paged') : ?>
                <?php if ($pages > 1) : ?>
                    <nav class="nxp-ec-category__pagination" aria-label="<?php echo Text::_('JGLOBAL_PAGINATION_LABEL'); ?>">
                        <span class="nxp-ec-category__pagination-meta">
                            <?php echo Text::sprintf('COM_NXPEASYCART_ORDER_PAGE_X_OF_Y', $currentPage, $pages); ?>
                        </span>
                        <div class="nxp-ec-category__pagination-links">
                            <?php if ($prevLink !== '') : ?>
                                <a class="nxp-ec-category__pagination-link" href="<?php echo htmlspecialchars($prevLink, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo Text::_('JPREV'); ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($nextLink !== '') : ?>
                                <a class="nxp-ec-category__pagination-link" href="<?php echo htmlspecialchars($nextLink, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo Text::_('JNEXT'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </nav>
                <?php endif; ?>
            <?php else : ?>
                <div class="nxp-ec-category__load-more">
                    <?php if ($nextLink !== '') : ?>
                        <a class="nxp-ec-btn nxp-ec-btn--ghost nxp-ec-category__load-more-button" href="<?php echo htmlspecialchars($nextLink, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo Text::_('COM_NXPEASYCART_CATEGORY_LOAD_MORE'); ?>
                        </a>
                    <?php else : ?>
                        <span class="nxp-ec-category__load-more-label">
                            <?php echo Text::_('COM_NXPEASYCART_CATEGORY_NO_MORE'); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="nxp-ec-category__sentinel" aria-hidden="true"></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
