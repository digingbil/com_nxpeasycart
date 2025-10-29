<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

$wa = $this->document->getWebAssetManager();
$wa->useStyle('com_nxpeasycart.admin');
$wa->useScript('com_nxpeasycart.admin');

$token = Session::getFormToken();
$productsEndpoint = 'index.php?option=com_nxpeasycart&task=api.products.list&format=json';
?>

<div
    id="nxp-admin-app"
    class="nxp-admin-app"
    data-csrf-token="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>"
    data-products-endpoint="<?php echo htmlspecialchars($productsEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
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
