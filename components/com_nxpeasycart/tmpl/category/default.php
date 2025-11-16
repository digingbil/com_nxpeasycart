<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$theme   = $this->theme ?? [];
$cssVars = '';
foreach (($theme['css_vars'] ?? []) as $var => $value) {
    $cssVars .= $var . ':' . $value . ';';
}

/** @var array<string, mixed>|null $this->category */
/** @var array<int, array<string, mixed>> $this->products */
/** @var array<int, array<string, mixed>> $this->categories */

$category      = $this->category    ?? null;
$products      = $this->products    ?? [];
$categories    = $this->categories  ?? [];
$activeSlug    = $category['slug']  ?? '';
$categoryTitle = $category['title'] ?? Text::_('COM_NXPEASYCART_CATEGORY_ALL');
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
$searchTerm  = $this->searchTerm ?? '';
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
];
$labelsJson = htmlspecialchars(
    json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$links = [
    'all'    => Route::_('index.php?option=com_nxpeasycart&view=category'),
    'search' => Route::_('index.php?option=com_nxpeasycart&view=category'),
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
        'cart'     => Route::_('index.php?option=com_nxpeasycart&view=cart'),
        'checkout' => Route::_('index.php?option=com_nxpeasycart&view=checkout'),
    ],
];
$cartJson = htmlspecialchars(
    json_encode($cart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$locale   = $this->locale ?? \Joomla\CMS\Factory::getApplication()->getLanguage()->getTag();
$currency = $this->currency ?? 'USD';
?>

<section
    class="nxp-ec-category"
    data-nxp-island="category"
    data-nxp-category="<?php echo $categoryJson; ?>"
    data-nxp-products="<?php echo $productsJson; ?>"
    data-nxp-categories="<?php echo $categoriesJson; ?>"
    data-nxp-labels="<?php echo $labelsJson; ?>"
    data-nxp-links="<?php echo $linksJson; ?>"
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
                        <?php if (!empty($product['price_label'])) : ?>
                            <p class="nxp-ec-product-card__price">
                                <?php echo htmlspecialchars($product['price_label'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                        <a class="nxp-ec-btn nxp-ec-btn--ghost" href="<?php echo htmlspecialchars($product['link'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo Text::_('COM_NXPEASYCART_CATEGORY_VIEW_PRODUCT'); ?>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
