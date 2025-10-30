<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

$wa = $this->document->getWebAssetManager();
$wa->getRegistry()->addRegistryFile('media/com_nxpeasycart/joomla.asset.json');
$wa->useScript('com_nxpeasycart.admin');

// Explicitly queue the bundle in case the registry file is not picked up (symlinked dev installs).
$this->document->addScript(Uri::root(true) . '/media/com_nxpeasycart/js/admin.iife.js', [], ['defer' => true]);
$this->document->addStyleSheet(Uri::root(true) . '/media/com_nxpeasycart/css/admin.css');

$token = Session::getFormToken();
$adminBase = rtrim(Uri::base(), '/');
$productsEndpointList = $adminBase . '/index.php?option=com_nxpeasycart&task=api.products.list&format=json';
$productsEndpointStore = $adminBase . '/index.php?option=com_nxpeasycart&task=api.products.store&format=json';
$productsEndpointUpdate = $adminBase . '/index.php?option=com_nxpeasycart&task=api.products.update&format=json';
$productsEndpointDelete = $adminBase . '/index.php?option=com_nxpeasycart&task=api.products.delete&format=json';
$ordersEndpointList = $adminBase . '/index.php?option=com_nxpeasycart&task=api.orders.list&format=json';
$ordersEndpointShow = $adminBase . '/index.php?option=com_nxpeasycart&task=api.orders.show&format=json';
$ordersEndpointTransition = $adminBase . '/index.php?option=com_nxpeasycart&task=api.orders.transition&format=json';
$params = ComponentHelper::getParams('com_nxpeasycart');
$baseCurrency = strtoupper($params->get('base_currency', 'USD'));
$section = Factory::getApplication()->input->getCmd('appSection', 'dashboard');
$ordersPreload = property_exists($this, 'orders') && \is_array($this->orders) ? $this->orders : ['items' => [], 'pagination' => []];
$navItems = [
    [
        'id' => 'dashboard',
        'title' => Text::_('COM_NXPEASYCART_MENU_DASHBOARD'),
        'link' => 'index.php?option=com_nxpeasycart&view=app',
    ],
    [
        'id' => 'products',
        'title' => Text::_('COM_NXPEASYCART_MENU_PRODUCTS'),
        'link' => 'index.php?option=com_nxpeasycart&view=products',
    ],
    [
        'id' => 'orders',
        'title' => Text::_('COM_NXPEASYCART_MENU_ORDERS'),
        'link' => 'index.php?option=com_nxpeasycart&view=orders',
    ],
    [
        'id' => 'customers',
        'title' => Text::_('COM_NXPEASYCART_MENU_CUSTOMERS'),
        'link' => 'index.php?option=com_nxpeasycart&view=customers',
    ],
    [
        'id' => 'coupons',
        'title' => Text::_('COM_NXPEASYCART_MENU_COUPONS'),
        'link' => 'index.php?option=com_nxpeasycart&view=coupons',
    ],
    [
        'id' => 'settings',
        'title' => Text::_('COM_NXPEASYCART_MENU_SETTINGS'),
        'link' => 'index.php?option=com_nxpeasycart&view=settings',
    ],
    [
        'id' => 'logs',
        'title' => Text::_('COM_NXPEASYCART_MENU_LOGS'),
        'link' => 'index.php?option=com_nxpeasycart&view=logs',
    ],
];
$orderStates = ['cart', 'pending', 'paid', 'fulfilled', 'refunded', 'canceled'];

switch ($section) {
    case 'products':
        $appTitleKey = 'COM_NXPEASYCART_MENU_PRODUCTS';
        $appLeadKey = 'COM_NXPEASYCART_PRODUCTS_LEAD';
        break;
    case 'orders':
        $appTitleKey = 'COM_NXPEASYCART_MENU_ORDERS';
        $appLeadKey = 'COM_NXPEASYCART_VIEW_ORDERS_PLACEHOLDER';
        break;
    case 'customers':
        $appTitleKey = 'COM_NXPEASYCART_MENU_CUSTOMERS';
        $appLeadKey = 'COM_NXPEASYCART_VIEW_CUSTOMERS_PLACEHOLDER';
        break;
    case 'coupons':
        $appTitleKey = 'COM_NXPEASYCART_MENU_COUPONS';
        $appLeadKey = 'COM_NXPEASYCART_VIEW_COUPONS_PLACEHOLDER';
        break;
    case 'settings':
        $appTitleKey = 'COM_NXPEASYCART_MENU_SETTINGS';
        $appLeadKey = 'COM_NXPEASYCART_VIEW_SETTINGS_PLACEHOLDER';
        break;
    case 'logs':
        $appTitleKey = 'COM_NXPEASYCART_MENU_LOGS';
        $appLeadKey = 'COM_NXPEASYCART_VIEW_LOGS_PLACEHOLDER';
        break;
    default:
        $appTitleKey = 'COM_NXPEASYCART_MENU_DASHBOARD';
        $appLeadKey = 'COM_NXPEASYCART_ADMIN_PLACEHOLDER';
        break;
}

$appTitle = Text::_($appTitleKey);
$appLead = Text::_($appLeadKey);

$dataAttributes = [
    'csrf-token' => $token,
    'products-endpoint' => $productsEndpointList,
    'products-endpoint-create' => $productsEndpointStore,
    'products-endpoint-update' => $productsEndpointUpdate,
    'products-endpoint-delete' => $productsEndpointDelete,
    'orders-endpoint' => $ordersEndpointList,
    'orders-endpoint-show' => $ordersEndpointShow,
    'orders-endpoint-transition' => $ordersEndpointTransition,
    'app-title' => $appTitle,
    'app-lead' => $appLead,
    'app-title-key' => $appTitleKey,
    'app-lead-key' => $appLeadKey,
    'active-section' => $section,
    'products-panel-title' => Text::_('COM_NXPEASYCART_MENU_PRODUCTS'),
    'products-panel-lead' => Text::_('COM_NXPEASYCART_PRODUCTS_LEAD'),
    'products-refresh' => Text::_('COM_NXPEASYCART_PRODUCTS_REFRESH'),
    'products-search-placeholder' => Text::_('COM_NXPEASYCART_PRODUCTS_SEARCH_PLACEHOLDER'),
    'products-loading' => Text::_('COM_NXPEASYCART_PRODUCTS_LOADING'),
    'products-empty' => Text::_('COM_NXPEASYCART_PRODUCTS_EMPTY'),
    'status-active' => Text::_('COM_NXPEASYCART_STATUS_ACTIVE'),
    'status-inactive' => Text::_('COM_NXPEASYCART_STATUS_INACTIVE'),
    'base-currency' => $baseCurrency,
    'orders-panel-title' => Text::_('COM_NXPEASYCART_MENU_ORDERS'),
    'orders-panel-lead' => Text::_('COM_NXPEASYCART_ORDERS_LEAD'),
    'orders-refresh' => Text::_('COM_NXPEASYCART_ORDERS_REFRESH'),
    'orders-search-placeholder' => Text::_('COM_NXPEASYCART_ORDERS_SEARCH_PLACEHOLDER'),
    'orders-filter-state' => Text::_('COM_NXPEASYCART_ORDERS_FILTER_STATE'),
    'orders-loading' => Text::_('COM_NXPEASYCART_ORDERS_LOADING'),
    'orders-empty' => Text::_('COM_NXPEASYCART_ORDERS_EMPTY'),
    'orders-state-transitions' => Text::_('COM_NXPEASYCART_ORDERS_TRANSITIONS'),
    'orders-details-title' => Text::_('COM_NXPEASYCART_ORDERS_DETAILS_TITLE'),
    'orders-details-close' => Text::_('COM_NXPEASYCART_ORDERS_DETAILS_CLOSE'),
    'orders-state-label' => Text::_('COM_NXPEASYCART_ORDERS_STATE_LABEL'),
    'orders-total-label' => Text::_('COM_NXPEASYCART_ORDERS_TOTAL_LABEL'),
    'orders-currency-label' => Text::_('COM_NXPEASYCART_ORDERS_CURRENCY_LABEL'),
    'orders-items-label' => Text::_('COM_NXPEASYCART_ORDERS_ITEMS_LABEL'),
    'orders-billing-label' => Text::_('COM_NXPEASYCART_ORDERS_BILLING_LABEL'),
    'orders-shipping-label' => Text::_('COM_NXPEASYCART_ORDERS_SHIPPING_LABEL'),
    'orders-transition-success' => Text::_('COM_NXPEASYCART_ORDERS_TRANSITION_SUCCESS'),
    'orders-transition-error' => Text::_('COM_NXPEASYCART_ORDERS_TRANSITION_ERROR'),
    'orders-no-shipping' => Text::_('COM_NXPEASYCART_ORDERS_NO_SHIPPING'),
    'orders-preload' => json_encode($ordersPreload['items'] ?? [], JSON_UNESCAPED_SLASHES),
    'orders-preload-pagination' => json_encode($ordersPreload['pagination'] ?? [], JSON_UNESCAPED_SLASHES),
    'nav-items' => json_encode($navItems, JSON_UNESCAPED_SLASHES),
    'order-states' => json_encode($orderStates, JSON_UNESCAPED_SLASHES),
];
?>

<div
    id="nxp-admin-app"
    class="nxp-admin-app"
    <?php foreach ($dataAttributes as $key => $value) : ?>
        data-<?php echo $key; ?>="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"
    <?php endforeach; ?>
>
    <div class="nxp-admin-app__placeholder">
        <h1 class="nxp-admin-app__title">
            <?php echo htmlspecialchars($appTitle, ENT_QUOTES, 'UTF-8'); ?>
        </h1>
        <p class="nxp-admin-app__lead">
            <?php echo htmlspecialchars($appLead, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    </div>
</div>
