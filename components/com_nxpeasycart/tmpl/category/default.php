<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

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
$searchTerm  = $this->searchTerm ?? '';
$searchValue = htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8');
?>

<section
    class="nxp-category"
    data-nxp-island="category"
    data-nxp-category="<?php echo $categoryJson; ?>"
    data-nxp-products="<?php echo $productsJson; ?>"
    data-nxp-search="<?php echo $searchValue; ?>"
>
    <noscript>
    <header class="nxp-category__header">
        <div>
            <h1 class="nxp-category__title">
                <?php echo htmlspecialchars($categoryTitle, ENT_QUOTES, 'UTF-8'); ?>
            </h1>
        </div>
        <form class="nxp-category__search" action="<?php echo htmlspecialchars(Route::_('index.php?option=com_nxpeasycart&view=category'), ENT_QUOTES, 'UTF-8'); ?>" method="get">
            <input
                type="search"
                name="q"
                value="<?php echo $searchValue; ?>"
                placeholder="<?php echo Text::_('COM_NXPEASYCART_PRODUCTS_SEARCH_PLACEHOLDER'); ?>"
            />
        </form>
        <nav class="nxp-category__filters" aria-label="<?php echo Text::_('COM_NXPEASYCART_CATEGORY_FILTERS'); ?>">
            <a
                class="nxp-category__filter<?php echo $activeSlug === '' ? ' is-active' : ''; ?>"
                href="<?php echo htmlspecialchars(Route::_('index.php?option=com_nxpeasycart&view=category'), ENT_QUOTES, 'UTF-8'); ?>"
            >
                <?php echo Text::_('COM_NXPEASYCART_CATEGORY_FILTER_ALL'); ?>
            </a>
            <?php foreach ($categories as $cat) : ?>
                <a
                    class="nxp-category__filter<?php echo $activeSlug === ($cat['slug'] ?? '') ? ' is-active' : ''; ?>"
                    href="<?php echo htmlspecialchars($cat['link'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                >
                    <?php echo htmlspecialchars($cat['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

        <?php if (empty($products)) : ?>
            <p class="nxp-category__empty">
                <?php echo Text::_('COM_NXPEASYCART_CATEGORY_EMPTY'); ?>
            </p>
        <?php else : ?>
            <div class="nxp-category__grid">
                <?php foreach ($products as $product) : ?>
                    <article class="nxp-product-card">
                        <?php if (!empty($product['images'][0])) : ?>
                            <figure class="nxp-product-card__media">
                                <img
                                    src="<?php echo htmlspecialchars($product['images'][0], ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                    loading="lazy"
                                />
                            </figure>
                        <?php endif; ?>
                        <div class="nxp-product-card__body">
                            <h2 class="nxp-product-card__title">
                                <a href="<?php echo htmlspecialchars($product['link'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </h2>
                            <?php if (!empty($product['short_desc'])) : ?>
                                <p class="nxp-product-card__intro">
                                    <?php echo htmlspecialchars($product['short_desc'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>
                            <a class="nxp-btn nxp-btn--ghost" href="<?php echo htmlspecialchars($product['link'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo Text::_('COM_NXPEASYCART_CATEGORY_VIEW_PRODUCT'); ?>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </noscript>
</section>
