<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

$wa = $this->document->getWebAssetManager();
$wa->useScript('com_nxpeasycart.admin');

$token = Session::getFormToken();
$productsEndpointList = 'index.php?option=com_nxpeasycart&task=api.products.list&format=json';
$productsEndpointStore = 'index.php?option=com_nxpeasycart&task=api.products.store&format=json';
$productsEndpointUpdate = 'index.php?option=com_nxpeasycart&task=api.products.update&format=json';
$productsEndpointDelete = 'index.php?option=com_nxpeasycart&task=api.products.delete&format=json';

$dataAttributes = [
    'csrf-token' => $token,
    'products-endpoint' => $productsEndpointList,
    'products-endpoint-create' => $productsEndpointStore,
    'products-endpoint-update' => $productsEndpointUpdate,
    'products-endpoint-delete' => $productsEndpointDelete,
    'app-title' => Text::_('COM_NXPEASYCART'),
    'app-lead' => Text::_('COM_NXPEASYCART_ADMIN_PLACEHOLDER'),
    'products-panel-title' => Text::_('COM_NXPEASYCART_MENU_PRODUCTS'),
    'products-panel-lead' => Text::_('COM_NXPEASYCART_PRODUCTS_LEAD'),
    'products-refresh' => Text::_('COM_NXPEASYCART_PRODUCTS_REFRESH'),
    'products-search-placeholder' => Text::_('COM_NXPEASYCART_PRODUCTS_SEARCH_PLACEHOLDER'),
    'products-loading' => Text::_('COM_NXPEASYCART_PRODUCTS_LOADING'),
    'products-empty' => Text::_('COM_NXPEASYCART_PRODUCTS_EMPTY'),
    'status-active' => Text::_('COM_NXPEASYCART_STATUS_ACTIVE'),
    'status-inactive' => Text::_('COM_NXPEASYCART_STATUS_INACTIVE'),
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
            <?php echo Text::_('COM_NXPEASYCART'); ?>
        </h1>
        <p class="nxp-admin-app__lead">
            <?php echo Text::_('COM_NXPEASYCART_ADMIN_PLACEHOLDER'); ?>
        </p>
    </div>
</div>
