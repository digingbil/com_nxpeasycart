<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var array<string, mixed>|null $this->order */
$order = $this->order ?? null;
?>

<section class="nxp-order-confirmation">
    <?php if (!$order) : ?>
        <header>
            <h1><?php echo Text::_('COM_NXPEASYCART_ORDER_NOT_FOUND'); ?></h1>
            <p>
                <a href="<?php echo htmlspecialchars(Route::_('index.php?option=com_nxpeasycart&view=cart'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('COM_NXPEASYCART_ORDER_RETURN_TO_CART'); ?>
                </a>
            </p>
        </header>

        <?php return; ?>
    <?php endif; ?>

    <header class="nxp-order-confirmation__header">
        <h1>
            <?php echo Text::sprintf('COM_NXPEASYCART_ORDER_CONFIRMED_TITLE', htmlspecialchars($order['order_no'] ?? '', ENT_QUOTES, 'UTF-8')); ?>
        </h1>
        <p><?php echo Text::_('COM_NXPEASYCART_ORDER_CONFIRMED_LEAD'); ?></p>
    </header>

    <div class="nxp-order-confirmation__grid">
        <section class="nxp-order-confirmation__summary">
            <h2><?php echo Text::_('COM_NXPEASYCART_ORDER_SUMMARY'); ?></h2>
            <ul>
                <?php foreach ($order['items'] ?? [] as $item) : ?>
                    <li>
                        <div>
                            <strong><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span>× <?php echo (int) $item['qty']; ?></span>
                        </div>
                        <div>
                            <?php echo htmlspecialchars($order['currency'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php echo number_format(((int) $item['total_cents']) / 100, 2); ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="nxp-order-confirmation__totals">
                <div>
                    <span><?php echo Text::_('COM_NXPEASYCART_ORDER_SUBTOTAL'); ?></span>
                    <strong>
                        <?php echo htmlspecialchars($order['currency'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php echo number_format(((int) ($order['subtotal_cents'] ?? 0)) / 100, 2); ?>
                    </strong>
                </div>
                <div>
                    <span><?php echo Text::_('COM_NXPEASYCART_ORDER_TOTAL'); ?></span>
                    <strong>
                        <?php echo htmlspecialchars($order['currency'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php echo number_format(((int) ($order['total_cents'] ?? 0)) / 100, 2); ?>
                    </strong>
                </div>
            </div>
        </section>

        <section class="nxp-order-confirmation__details">
            <h2><?php echo Text::_('COM_NXPEASYCART_ORDER_CUSTOMER'); ?></h2>
            <p><?php echo htmlspecialchars($order['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>

            <h3><?php echo Text::_('COM_NXPEASYCART_ORDER_BILLING'); ?></h3>
            <pre class="nxp-order-confirmation__address"><?php echo htmlspecialchars(json_encode($order['billing'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></pre>

            <?php if (!empty($order['shipping'])) : ?>
                <h3><?php echo Text::_('COM_NXPEASYCART_ORDER_SHIPPING'); ?></h3>
                <pre class="nxp-order-confirmation__address"><?php echo htmlspecialchars(json_encode($order['shipping'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></pre>
            <?php endif; ?>
        </section>
    </div>
</section>
