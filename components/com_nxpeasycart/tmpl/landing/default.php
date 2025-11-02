<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var Nxp\EasyCart\Site\View\Landing\HtmlView $this */

$payload = $this->getLandingPayload();

$payloadJson = htmlspecialchars(
    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);

$hero        = $payload['hero'] ?? [];
$search      = $payload['search'] ?? [];
$categories  = $payload['categories'] ?? [];
$sections    = $payload['sections'] ?? [];
$trustBadge  = $payload['trust']['text'] ?? '';
$searchRoute = isset($search['action']) ? Route::_($search['action']) : Route::_('index.php?option=com_nxpeasycart&view=category');
$searchPlaceholder = $search['placeholder'] ?? Text::_('COM_NXPEASYCART_LANDING_SEARCH_PLACEHOLDER_DEFAULT');
$ctaLink     = Route::_($hero['cta']['link'] ?? 'index.php?option=com_nxpeasycart&view=category');
$ctaLabel    = $hero['cta']['label'] ?? Text::_('COM_NXPEASYCART_LANDING_HERO_CTA_LABEL_DEFAULT');
?>

<section
    class="nxp-landing"
    data-nxp-island="landing"
    data-nxp-landing="<?php echo $payloadJson; ?>"
>
    <noscript>
        <div class="nxp-landing__inner">
            <header class="nxp-landing__hero">
                <div class="nxp-landing__hero-copy">
                    <?php if (!empty($hero['eyebrow'])) : ?>
                        <p class="nxp-landing__eyebrow">
                            <?php echo htmlspecialchars($hero['eyebrow'], ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    <?php endif; ?>
                    <h1 class="nxp-landing__title">
                        <?php echo htmlspecialchars($hero['title'] ?? Text::_('COM_NXPEASYCART_LANDING_PAGE_TITLE'), ENT_QUOTES, 'UTF-8'); ?>
                    </h1>
                    <?php if (!empty($hero['subtitle'])) : ?>
                        <p class="nxp-landing__subtitle">
                            <?php echo htmlspecialchars($hero['subtitle'], ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    <?php endif; ?>
                    <div class="nxp-landing__actions">
                        <a class="nxp-btn nxp-btn--primary" href="<?php echo htmlspecialchars($ctaLink, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </div>
                </div>
                <form class="nxp-landing__search" action="<?php echo htmlspecialchars($searchRoute, ENT_QUOTES, 'UTF-8'); ?>" method="get">
                    <label class="sr-only" for="nxp-landing-search">
                        <?php echo Text::_('COM_NXPEASYCART_LANDING_SEARCH_LABEL'); ?>
                    </label>
                    <input
                        id="nxp-landing-search"
                        name="q"
                        type="search"
                        placeholder="<?php echo htmlspecialchars($searchPlaceholder, ENT_QUOTES, 'UTF-8'); ?>"
                    />
                    <button type="submit" class="nxp-btn nxp-btn--ghost">
                        <?php echo Text::_('COM_NXPEASYCART_LANDING_SEARCH_SUBMIT'); ?>
                    </button>
                </form>
            </header>

            <?php if (!empty($categories)) : ?>
                <section class="nxp-landing__categories" aria-label="<?php echo Text::_('COM_NXPEASYCART_LANDING_CATEGORIES_ARIA'); ?>">
                    <?php foreach ($categories as $category) : ?>
                        <a class="nxp-landing__category" href="<?php echo htmlspecialchars($category['link'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="nxp-landing__category-title">
                                <?php echo htmlspecialchars($category['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <?php foreach ($sections as $section) : ?>
                <?php $items = $section['items'] ?? []; ?>
                <?php if (empty($items)) : ?>
                    <?php continue; ?>
                <?php endif; ?>
                <section class="nxp-landing__section">
                    <header class="nxp-landing__section-header">
                        <h2 class="nxp-landing__section-title">
                            <?php echo htmlspecialchars($section['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </h2>
                        <a class="nxp-landing__section-link" href="<?php echo htmlspecialchars($searchRoute, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo Text::_('COM_NXPEASYCART_LANDING_VIEW_ALL'); ?>
                        </a>
                    </header>
                    <div class="nxp-landing__grid">
                        <?php foreach ($items as $item) : ?>
                            <article class="nxp-landing__card">
                                <?php if (!empty($item['images'][0])) : ?>
                                    <figure class="nxp-landing__card-media">
                                        <img
                                            src="<?php echo htmlspecialchars($item['images'][0], ENT_QUOTES, 'UTF-8'); ?>"
                                            alt="<?php echo htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            loading="lazy"
                                        />
                                    </figure>
                                <?php endif; ?>
                                <div class="nxp-landing__card-body">
                                    <h3 class="nxp-landing__card-title">
                                        <a href="<?php echo htmlspecialchars($item['link'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </h3>
                                    <?php if (!empty($item['short_desc'])) : ?>
                                        <p class="nxp-landing__card-intro">
                                            <?php echo htmlspecialchars($item['short_desc'], ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($item['price_label'])) : ?>
                                        <p class="nxp-landing__card-price">
                                            <?php echo htmlspecialchars($item['price_label'], ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                    <?php endif; ?>
                                    <a class="nxp-btn nxp-btn--ghost" href="<?php echo htmlspecialchars($item['link'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo Text::_('COM_NXPEASYCART_LANDING_CARD_VIEW'); ?>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <?php if (!empty($trustBadge)) : ?>
                <aside class="nxp-landing__trust">
                    <p class="nxp-landing__trust-text">
                        <?php echo htmlspecialchars($trustBadge, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </aside>
            <?php endif; ?>
        </div>
    </noscript>
</section>
