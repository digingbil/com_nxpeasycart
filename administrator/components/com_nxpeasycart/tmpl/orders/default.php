<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

$app = Factory::getApplication();
$app->input->set('appSection', 'orders');

require __DIR__ . '/../app/default.php';

$orders = property_exists($this, 'orders') && \is_array($this->orders) ? ($this->orders['items'] ?? []) : [];

if (!empty($orders)) : ?>
    <div id="nxp-orders-fallback" class="nxp-admin-panel nxp-admin-panel--fallback">
        <header class="nxp-admin-panel__header">
            <div>
                <h2 class="nxp-admin-panel__title">
                    <?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_('COM_NXPEASYCART_MENU_ORDERS'), ENT_QUOTES, 'UTF-8'); ?>
                </h2>
                <p class="nxp-admin-panel__lead">
                    <?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_('COM_NXPEASYCART_ORDERS_LEAD'), ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>
        </header>
        <div class="nxp-admin-panel__body">
            <table class="nxp-admin-table">
                <thead>
                    <tr>
                        <th><?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_('COM_NXPEASYCART_ORDERS_TABLE_ORDER'), ENT_QUOTES, 'UTF-8'); ?></th>
                        <th><?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_('COM_NXPEASYCART_ORDERS_TABLE_CUSTOMER'), ENT_QUOTES, 'UTF-8'); ?></th>
                        <th><?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_('COM_NXPEASYCART_ORDERS_TABLE_TOTAL'), ENT_QUOTES, 'UTF-8'); ?></th>
                        <th><?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_('COM_NXPEASYCART_ORDERS_TABLE_STATE'), ENT_QUOTES, 'UTF-8'); ?></th>
                        <th><?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_('COM_NXPEASYCART_ORDERS_TABLE_UPDATED'), ENT_QUOTES, 'UTF-8'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order) : ?>
                        <tr>
                            <th scope="row">
                                <?php echo htmlspecialchars($order['order_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </th>
                            <td>
                                <div><?php echo htmlspecialchars($order['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            </td>
                            <td>
                                <?php
                                $total = (int) ($order['total_cents'] ?? 0);
                                $currency = strtoupper((string) ($order['currency'] ?? 'USD'));
                                echo htmlspecialchars(sprintf('%s %.2f', $currency, $total / 100), ENT_QUOTES, 'UTF-8');
                                ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars(ucfirst((string) ($order['state'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($order['modified'] ?? $order['created'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        (function () {
            var removeFallback = function () {
                if (window.__NXP_EASYCART__ && window.__NXP_EASYCART__.adminMounted) {
                    var fallback = document.getElementById('nxp-orders-fallback');
                    if (fallback && fallback.parentNode) {
                        fallback.parentNode.removeChild(fallback);
                    }
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', removeFallback);
            } else {
                removeFallback();
            }

            setTimeout(removeFallback, 1500);
        }());
    </script>
<?php endif; ?>
