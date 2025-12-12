<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Factory as JoomlaFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasycart\Administrator\Helper\MoneyHelper;

/** @var array<string, mixed> $order */
/** @var array<string, mixed> $store */

$items    = $order['items']    ?? [];
$currency = $order['currency'] ?? 'USD';
$publicToken = isset($order['public_token']) ? (string) $order['public_token'] : '';
$trackUrl    = '';

if ($publicToken !== '') {
    // Build SEF URL by looking up the menu item alias
    $siteRoot = rtrim(Uri::root(), '/');
    $menuAlias = '';

    try {
        // Get site menu to find the landing/order menu item
        $app = JoomlaFactory::getApplication('site');
        $menu = $app->getMenu();
        $menuItems = $menu->getItems('component', 'com_nxpeasycart') ?: [];

        foreach ($menuItems as $item) {
            $view = $item->query['view'] ?? '';
            if ($view === 'landing' || $view === 'order') {
                $menuAlias = $item->route ?? $item->alias ?? '';
                break;
            }
        }
    } catch (\Throwable $e) {
        // Fallback if menu lookup fails
    }

    if ($menuAlias !== '') {
        $trackUrl = $siteRoot . '/' . $menuAlias . '/order?ref=' . rawurlencode($publicToken);
    } else {
        $trackUrl = $siteRoot . '/index.php?option=com_nxpeasycart&view=order&ref=' . rawurlencode($publicToken);
    }
}

$formatMoney = static function (int $cents) use ($currency): string {
    return MoneyHelper::format($cents, $currency);
};

$downloads = isset($order['downloads']) && \is_array($order['downloads']) ? $order['downloads'] : [];

$downloadRemaining = static function (array $download): string {
    $used = (int) ($download['download_count'] ?? 0);
    $max = isset($download['max_downloads']) ? (int) $download['max_downloads'] : null;

    if ($max === null || $max <= 0) {
        return Text::_('COM_NXPEASYCART_ORDER_DOWNLOADS_UNLIMITED');
    }

    $remaining = max(0, $max - $used);

    return Text::sprintf('COM_NXPEASYCART_ORDER_DOWNLOADS_REMAINING', $remaining);
};

$downloadExpiry = static function (array $download): string {
    if (empty($download['expires_at'])) {
        return '';
    }

    return Text::sprintf('COM_NXPEASYCART_ORDER_DOWNLOADS_EXPIRES', (string) $download['expires_at']);
};
?>

<div style="font-family: Arial, sans-serif; color: #111827;">
    <h1 style="font-size: 20px; margin-bottom: 16px;">
        <?php echo htmlspecialchars($store['name'] ?? Text::_('COM_NXPEASYCART_EMAIL_STORE_FALLBACK'), ENT_QUOTES, 'UTF-8'); ?>
    </h1>

    <p style="margin: 0 0 24px; font-size: 15px;">
        <?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_EMAIL_GREETING', $order['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <p style="margin: 0 0 24px; font-size: 15px;">
        <?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_EMAIL_DOWNLOADS_READY', $order['order_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <?php if (!empty($downloads)) : ?>
        <h2 style="font-size: 16px; margin: 0 0 12px;">
            <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_DOWNLOADS_TITLE'), ENT_QUOTES, 'UTF-8'); ?>
        </h2>
        <p style="margin: 0 0 12px; font-size: 14px;">
            <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_DOWNLOADS_INTRO'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-bottom: 24px;">
            <thead>
                <tr>
                    <th align="left" style="border-bottom: 1px solid #e2e8f0; padding: 8px 0; font-size: 13px; text-transform: uppercase;">
                        <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_DOWNLOADS_FILE'), ENT_QUOTES, 'UTF-8'); ?>
                    </th>
                    <th align="left" style="border-bottom: 1px solid #e2e8f0; padding: 8px 0; font-size: 13px; text-transform: uppercase;">
                        <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_DOWNLOADS_LINK'), ENT_QUOTES, 'UTF-8'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($downloads as $download) : ?>
                    <tr>
                        <td style="padding: 8px 0; font-size: 14px;">
                            <?php echo htmlspecialchars((string) ($download['filename'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            <?php if (!empty($download['version'])) : ?>
                                <br>
                                <small style="color: #64748b;">
                                    <?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_EMAIL_DOWNLOADS_VERSION', (string) $download['version']), ENT_QUOTES, 'UTF-8'); ?>
                                </small>
                            <?php endif; ?>
                            <br>
                            <small style="color: #64748b;">
                                <?php echo htmlspecialchars($downloadRemaining($download), ENT_QUOTES, 'UTF-8'); ?>
                                <?php $expiresLabel = $downloadExpiry($download); ?>
                                <?php if ($expiresLabel !== '') : ?>
                                    · <?php echo htmlspecialchars($expiresLabel, ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td style="padding: 8px 0; font-size: 14px;">
                            <?php if (!empty($download['url'])) : ?>
                                <a
                                    href="<?php echo htmlspecialchars((string) $download['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                    style="display: inline-block; padding: 10px 14px; background: #0f172a; color: #fff; text-decoration: none; border-radius: 6px; font-size: 13px;"
                                >
                                    <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_DOWNLOADS_BUTTON'), ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($trackUrl !== '') : ?>
        <p style="margin: 0 0 12px; font-size: 14px;">
            <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_DOWNLOADS_ORDER_PAGE'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <p style="margin: 0 0 24px;">
            <a href="<?php echo htmlspecialchars($trackUrl, ENT_QUOTES, 'UTF-8'); ?>"
                style="display: inline-block; padding: 12px 18px; background: #4f6d7a; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 14px;">
                <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_ORDER_TRACK'), ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </p>
    <?php endif; ?>

    <p style="margin: 0 0 16px; font-size: 14px;">
        <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_ORDER_FOOTER_DIGITAL'), ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <p style="margin: 0; font-size: 13px; color: #64748b;">
        <?php echo htmlspecialchars($store['url'] ?? Uri::root(), ENT_QUOTES, 'UTF-8'); ?>
    </p>
</div>
