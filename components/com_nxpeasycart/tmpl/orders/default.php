<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasycart\Site\Helper\RouteHelper;

/** @var array<int, array<string, mixed>> $this->orders */
/** @var array<string, int> $this->pagination */

$user = null;

try {
    $user = Factory::getApplication()->getIdentity();
} catch (\Throwable $exception) {
    $user = null;
}

$orders     = $this->orders ?? [];
$pagination = $this->pagination ?? [];
$formatTimestamp = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '';
    }

    try {
        return Factory::getDate($value)->format(Text::_('DATE_FORMAT_LC2'));
    } catch (\Throwable $exception) {
        return (string) $value;
    }
};

if (!$user || $user->guest) : ?>
    <section class="nxp-ec-orders">
        <h1><?php echo Text::_('COM_NXPEASYCART_MY_ORDERS_TITLE'); ?></h1>
        <p><?php echo Text::_('COM_NXPEASYCART_MY_ORDERS_LOGIN_REQUIRED'); ?></p>
    </section>
    <?php return; ?>
<?php endif; ?>

<section class="nxp-ec-orders">
    <header class="nxp-ec-orders__header">
        <h1><?php echo Text::_('COM_NXPEASYCART_MY_ORDERS_TITLE'); ?></h1>
        <p class="nxp-ec-orders__lead"><?php echo Text::_('COM_NXPEASYCART_MY_ORDERS_LEAD'); ?></p>
    </header>

    <?php if (empty($orders)) : ?>
        <p class="nxp-ec-orders__empty"><?php echo Text::_('COM_NXPEASYCART_MY_ORDERS_EMPTY'); ?></p>
    <?php else : ?>
        <ul class="nxp-ec-orders__list">
            <?php foreach ($orders as $order) : ?>
                <?php
                    $orderNo   = (string) ($order['order_no'] ?? '');
                    $state     = isset($order['state']) ? strtolower((string) $order['state']) : '';
                    $updatedAt = $order['status_updated_at'] ?? ($order['modified'] ?? $order['created'] ?? '');
                    $total     = isset($order['total_cents']) ? (int) $order['total_cents'] : 0;
                    $currency  = (string) ($order['currency'] ?? '');
                    $items     = isset($order['items_count']) ? (int) $order['items_count'] : (int) (count($order['items'] ?? []));
                    $updatedDisplay = $formatTimestamp($updatedAt);

                    $stateLabelMap = [
                        'cart'      => Text::_('COM_NXPEASYCART_ORDER_STATE_CART'),
                        'pending'   => Text::_('COM_NXPEASYCART_ORDER_STATE_PENDING'),
                        'paid'      => Text::_('COM_NXPEASYCART_ORDER_STATE_PAID'),
                        'fulfilled' => Text::_('COM_NXPEASYCART_ORDER_STATE_FULFILLED'),
                        'refunded'  => Text::_('COM_NXPEASYCART_ORDER_STATE_REFUNDED'),
                        'canceled'  => Text::_('COM_NXPEASYCART_ORDER_STATE_CANCELED'),
                    ];

                    $stateLabel = $stateLabelMap[$state] ?? ucfirst($state);
                    $orderUrl   = RouteHelper::getOrderRoute($orderNo);
                ?>
                <li class="nxp-ec-orders__item">
                    <div class="nxp-ec-orders__item-main">
                        <h2>
                            <a href="<?php echo htmlspecialchars($orderUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($orderNo, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </h2>
                        <p class="nxp-ec-orders__meta">
                            <span class="nxp-ec-orders__badge">
                                <?php echo htmlspecialchars($stateLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <?php if ($updatedDisplay) : ?>
                                <span class="nxp-ec-orders__timestamp">
                                    <?php echo Text::sprintf(
                                        'COM_NXPEASYCART_ORDER_LAST_UPDATED_AT',
                                        htmlspecialchars($updatedDisplay, ENT_QUOTES, 'UTF-8')
                                    ); ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="nxp-ec-orders__item-stats">
                        <div>
                            <span class="nxp-ec-orders__stat-label"><?php echo Text::_('COM_NXPEASYCART_ORDER_TOTAL'); ?></span>
                            <strong><?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?> <?php echo number_format($total / 100, 2); ?></strong>
                        </div>
                        <div>
                            <span class="nxp-ec-orders__stat-label"><?php echo Text::_('COM_NXPEASYCART_ORDER_ITEMS_COUNT'); ?></span>
                            <strong><?php echo (int) $items; ?></strong>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php
            $currentPage = isset($pagination['current']) ? (int) $pagination['current'] : 1;
            $totalPages  = isset($pagination['pages']) ? (int) $pagination['pages'] : 1;
            $limit       = isset($pagination['limit']) ? (int) $pagination['limit'] : 20;
        ?>
        <?php if ($totalPages > 1) : ?>
            <nav class="nxp-ec-orders__pagination" aria-label="<?php echo Text::_('JGLOBAL_PAGINATION_LABEL'); ?>">
                <span><?php echo Text::sprintf('COM_NXPEASYCART_ORDER_PAGE_X_OF_Y', $currentPage, $totalPages); ?></span>
                <div class="nxp-ec-orders__pagination-links">
                    <?php if ($currentPage > 1) :
                        $prevStart = max(0, ($currentPage - 2) * $limit);
                    ?>
                        <a href="<?php echo htmlspecialchars(RouteHelper::getOrdersRoute(false) . '&start=' . $prevStart, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo Text::_('JPREV'); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($currentPage < $totalPages) :
                        $nextStart = $currentPage * $limit;
                    ?>
                        <a href="<?php echo htmlspecialchars(RouteHelper::getOrdersRoute(false) . '&start=' . $nextStart, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo Text::_('JNEXT'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
