<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

$document = $this->getDocument();
$wa        = $document->getWebAssetManager();
$wa->getRegistry()->addRegistryFile('media/com_nxpeasycart/joomla.asset.json');
$wa->getRegistry()->addRegistryFile('media/com_media/joomla.asset.json');
$wa->useScript('com_nxpeasycart.admin');
$wa->registerAndUseStyle(
    'com_nxpeasycart.admin.css',
    'media/com_nxpeasycart/css/admin.css',
    ['version' => 'auto', 'relative' => true]
);

// Explicitly queue the bundle in case the registry file is not picked up (symlinked dev installs).
$document->addScript(Uri::root(true) . '/media/com_nxpeasycart/js/admin.iife.js', [], ['defer' => true]);

// Ensure the Joomla media picker assets are available for the product editor.
$wa->useStyle('webcomponent.field-media')
    ->useStyle('webcomponent.media-select')
    ->useScript('webcomponent.field-media')
    ->useScript('webcomponent.media-select')
    ->useScript('joomla.dialog')
    ->useScript('joomla.dialog-autocreate')
    ->useStyle('com_media.mediamanager')
    ->useScript('com_media.mediamanager');

$mediaParams    = ComponentHelper::getParams('com_media');
$imagesExt      = array_map('trim', explode(',', $mediaParams->get('image_extensions', 'bmp,gif,jpg,jpeg,png,webp,svg,avif')));
$audiosExt      = array_map('trim', explode(',', $mediaParams->get('audio_extensions', 'mp3,m4a,mp4a,ogg')));
$videosExt      = array_map('trim', explode(',', $mediaParams->get('video_extensions', 'mp4,mp4v,mpeg,mov,webm')));
$documentsExt   = array_map('trim', explode(',', $mediaParams->get('doc_extensions', 'doc,odg,odp,ods,odt,pdf,ppt,txt,xcf,xls,csv')));
$mediaScriptSet = $document->getScriptOptions('media-picker');

$document->addScriptOptions(
    'media-picker-api',
    ['apiBaseUrl' => Uri::base(true) . '/index.php?option=com_media&format=json']
);

if (!$mediaScriptSet) {
    $document->addScriptOptions(
        'media-picker',
        [
            'images'    => $imagesExt,
            'audios'    => $audiosExt,
            'videos'    => $videosExt,
            'documents' => $documentsExt,
        ]
    );
}

$user           = Factory::getApplication()->getIdentity();
$token                        = Session::getFormToken();
$tokenQuery                   = $token . '=1';
$adminBase                    = rtrim(Uri::base(), '/');
$mediaModalUrl                = $adminBase . '/index.php?option=com_media&view=media&layout=modal&tmpl=component&mediatypes=0,1,2,3&asset=com_nxpeasycart&author=' . (int) $user->id;
$productsEndpointList         = $adminBase . '/index.php?option=com_nxpeasycart&task=api.products.list&format=json';
$productsEndpointStore        = $adminBase . '/index.php?option=com_nxpeasycart&task=api.products.store&format=json&' . $tokenQuery;
$productsEndpointUpdate       = $adminBase . '/index.php?option=com_nxpeasycart&task=api.products.update&format=json&' . $tokenQuery;
$productsEndpointDelete       = $adminBase . '/index.php?option=com_nxpeasycart&task=api.products.delete&format=json&' . $tokenQuery;
$categoriesEndpointList       = $adminBase . '/index.php?option=com_nxpeasycart&task=api.categories.list&format=json';
$categoriesEndpointStore      = $adminBase . '/index.php?option=com_nxpeasycart&task=api.categories.store&format=json&' . $tokenQuery;
$categoriesEndpointUpdate     = $adminBase . '/index.php?option=com_nxpeasycart&task=api.categories.update&format=json&' . $tokenQuery;
$categoriesEndpointDelete     = $adminBase . '/index.php?option=com_nxpeasycart&task=api.categories.delete&format=json&' . $tokenQuery;
$ordersEndpointList           = $adminBase . '/index.php?option=com_nxpeasycart&task=api.orders.list&format=json';
$ordersEndpointShow           = $adminBase . '/index.php?option=com_nxpeasycart&task=api.orders.show&format=json';
$ordersEndpointTransition     = $adminBase . '/index.php?option=com_nxpeasycart&task=api.orders.transition&format=json&' . $tokenQuery;
$ordersEndpointBulkTransition = $adminBase . '/index.php?option=com_nxpeasycart&task=api.orders.bulkTransition&format=json&' . $tokenQuery;
$ordersEndpointNote           = $adminBase . '/index.php?option=com_nxpeasycart&task=api.orders.note&format=json&' . $tokenQuery;
$dashboardEndpoint            = $adminBase . '/index.php?option=com_nxpeasycart&task=api.dashboard.summary&format=json';
$customersEndpointList        = $adminBase . '/index.php?option=com_nxpeasycart&task=api.customers.list&format=json';
$customersEndpointShow        = $adminBase . '/index.php?option=com_nxpeasycart&task=api.customers.show&format=json';
$couponsEndpointList          = $adminBase . '/index.php?option=com_nxpeasycart&task=api.coupons.list&format=json';
$couponsEndpointStore         = $adminBase . '/index.php?option=com_nxpeasycart&task=api.coupons.store&format=json&' . $tokenQuery;
$couponsEndpointUpdate        = $adminBase . '/index.php?option=com_nxpeasycart&task=api.coupons.update&format=json&' . $tokenQuery;
$couponsEndpointDelete        = $adminBase . '/index.php?option=com_nxpeasycart&task=api.coupons.delete&format=json&' . $tokenQuery;
$taxEndpointList              = $adminBase . '/index.php?option=com_nxpeasycart&task=api.tax.list&format=json';
$taxEndpointStore             = $adminBase . '/index.php?option=com_nxpeasycart&task=api.tax.store&format=json&' . $tokenQuery;
$taxEndpointUpdate            = $adminBase . '/index.php?option=com_nxpeasycart&task=api.tax.update&format=json&' . $tokenQuery;
$taxEndpointDelete            = $adminBase . '/index.php?option=com_nxpeasycart&task=api.tax.delete&format=json&' . $tokenQuery;
$shippingEndpointList         = $adminBase . '/index.php?option=com_nxpeasycart&task=api.shipping.list&format=json';
$shippingEndpointStore        = $adminBase . '/index.php?option=com_nxpeasycart&task=api.shipping.store&format=json&' . $tokenQuery;
$shippingEndpointUpdate       = $adminBase . '/index.php?option=com_nxpeasycart&task=api.shipping.update&format=json&' . $tokenQuery;
$shippingEndpointDelete       = $adminBase . '/index.php?option=com_nxpeasycart&task=api.shipping.delete&format=json&' . $tokenQuery;
$settingsEndpointGet          = $adminBase . '/index.php?option=com_nxpeasycart&task=api.settings.show&format=json';
$settingsEndpointSave         = $adminBase . '/index.php?option=com_nxpeasycart&task=api.settings.update&format=json&' . $tokenQuery;
$logsEndpointList             = $adminBase . '/index.php?option=com_nxpeasycart&task=api.logs.list&format=json';
$params                       = ComponentHelper::getParams('com_nxpeasycart');
$baseCurrency                 = strtoupper($params->get('base_currency', 'USD'));
$section                      = Factory::getApplication()->input->getCmd('appSection', 'dashboard');
$ordersPreload                = property_exists($this, 'orders')             && \is_array($this->orders) ? $this->orders : ['items' => [], 'pagination' => []];
$dashboardSummary             = property_exists($this, 'dashboardSummary')   && \is_array($this->dashboardSummary) ? $this->dashboardSummary : [];
$dashboardChecklist           = property_exists($this, 'dashboardChecklist') && \is_array($this->dashboardChecklist) ? $this->dashboardChecklist : [];
$customersPreload             = property_exists($this, 'customers')          && \is_array($this->customers) ? $this->customers : ['items' => [], 'pagination' => []];
$categoriesPreload            = property_exists($this, 'categories')         && \is_array($this->categories) ? $this->categories : ['items' => [], 'pagination' => []];
$couponsPreload               = property_exists($this, 'coupons')            && \is_array($this->coupons) ? $this->coupons : ['items' => [], 'pagination' => []];
$taxPreload                   = property_exists($this, 'taxRates')           && \is_array($this->taxRates) ? $this->taxRates : ['items' => []];
$shippingPreload              = property_exists($this, 'shippingRules')      && \is_array($this->shippingRules) ? $this->shippingRules : ['items' => []];
$settingsPreload              = property_exists($this, 'settingsData')       && \is_array($this->settingsData) ? $this->settingsData : [];
$logsPreload                  = property_exists($this, 'logsData')           && \is_array($this->logsData) ? $this->logsData : ['items' => [], 'pagination' => []];
$navItems                     = [
    [
        'id'    => 'dashboard',
        'title' => Text::_('COM_NXPEASYCART_MENU_DASHBOARD'),
        'link'  => 'index.php?option=com_nxpeasycart&view=app',
    ],
    [
        'id'    => 'products',
        'title' => Text::_('COM_NXPEASYCART_MENU_PRODUCTS'),
        'link'  => 'index.php?option=com_nxpeasycart&view=products',
    ],
    [
        'id'    => 'categories',
        'title' => Text::_('COM_NXPEASYCART_MENU_CATEGORIES'),
        'link'  => 'index.php?option=com_nxpeasycart&view=categories',
    ],
    [
        'id'    => 'orders',
        'title' => Text::_('COM_NXPEASYCART_MENU_ORDERS'),
        'link'  => 'index.php?option=com_nxpeasycart&view=orders',
    ],
    [
        'id'    => 'customers',
        'title' => Text::_('COM_NXPEASYCART_MENU_CUSTOMERS'),
        'link'  => 'index.php?option=com_nxpeasycart&view=customers',
    ],
    [
        'id'    => 'coupons',
        'title' => Text::_('COM_NXPEASYCART_MENU_COUPONS'),
        'link'  => 'index.php?option=com_nxpeasycart&view=coupons',
    ],
    [
        'id'    => 'settings',
        'title' => Text::_('COM_NXPEASYCART_MENU_SETTINGS'),
        'link'  => 'index.php?option=com_nxpeasycart&view=settings',
    ],
    [
        'id'    => 'logs',
        'title' => Text::_('COM_NXPEASYCART_MENU_LOGS'),
        'link'  => 'index.php?option=com_nxpeasycart&view=logs',
    ],
];
$orderStates = ['cart', 'pending', 'paid', 'fulfilled', 'refunded', 'canceled'];
$jsonOptions = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;

$appConfig = [
    'activeSection' => $section,
    'baseCurrency'  => $baseCurrency,
    'navItems'      => $navItems,
    'orderStates'   => $orderStates,
    'preload'       => [
        'orders'    => $ordersPreload,
        'dashboard' => [
            'summary'   => $dashboardSummary,
            'checklist' => $dashboardChecklist,
        ],
        'customers'  => $customersPreload,
        'categories' => $categoriesPreload,
        'coupons'    => $couponsPreload,
        'tax'        => $taxPreload,
        'shipping'   => $shippingPreload,
        'settings'   => $settingsPreload,
        'logs'       => $logsPreload,
    ],
    'endpoints' => [
        'dashboard' => $dashboardEndpoint,
        'products'  => [
            'list'   => $productsEndpointList,
            'create' => $productsEndpointStore,
            'update' => $productsEndpointUpdate,
            'delete' => $productsEndpointDelete,
        ],
        'categories' => [
            'list'   => $categoriesEndpointList,
            'create' => $categoriesEndpointStore,
            'update' => $categoriesEndpointUpdate,
            'delete' => $categoriesEndpointDelete,
        ],
        'orders' => [
            'list'           => $ordersEndpointList,
            'show'           => $ordersEndpointShow,
            'transition'     => $ordersEndpointTransition,
            'bulkTransition' => $ordersEndpointBulkTransition,
            'note'           => $ordersEndpointNote,
        ],
        'customers' => [
            'list' => $customersEndpointList,
            'show' => $customersEndpointShow,
        ],
        'coupons' => [
            'list'   => $couponsEndpointList,
            'create' => $couponsEndpointStore,
            'update' => $couponsEndpointUpdate,
            'delete' => $couponsEndpointDelete,
        ],
        'tax' => [
            'list'   => $taxEndpointList,
            'create' => $taxEndpointStore,
            'update' => $taxEndpointUpdate,
            'delete' => $taxEndpointDelete,
        ],
        'shipping' => [
            'list'   => $shippingEndpointList,
            'create' => $shippingEndpointStore,
            'update' => $shippingEndpointUpdate,
            'delete' => $shippingEndpointDelete,
        ],
        'settings' => [
            'show'   => $settingsEndpointGet,
            'update' => $settingsEndpointSave,
        ],
        'payments' => [
            'show'   => $adminBase . '/index.php?option=com_nxpeasycart&task=api.payments.show&format=json',
            'update' => $adminBase . '/index.php?option=com_nxpeasycart&task=api.payments.update&format=json&' . $tokenQuery,
        ],
        'logs' => [
            'list' => $logsEndpointList,
        ],
    ],
];

switch ($section) {
    case 'products':
        $appTitleKey = 'COM_NXPEASYCART_MENU_PRODUCTS';
        $appLeadKey  = 'COM_NXPEASYCART_PRODUCTS_LEAD';
        break;
    case 'orders':
        $appTitleKey = 'COM_NXPEASYCART_MENU_ORDERS';
        $appLeadKey  = 'COM_NXPEASYCART_VIEW_ORDERS_PLACEHOLDER';
        break;
    case 'customers':
        $appTitleKey = 'COM_NXPEASYCART_MENU_CUSTOMERS';
        $appLeadKey  = 'COM_NXPEASYCART_VIEW_CUSTOMERS_PLACEHOLDER';
        break;
    case 'coupons':
        $appTitleKey = 'COM_NXPEASYCART_MENU_COUPONS';
        $appLeadKey  = 'COM_NXPEASYCART_VIEW_COUPONS_PLACEHOLDER';
        break;
    case 'settings':
        $appTitleKey = 'COM_NXPEASYCART_MENU_SETTINGS';
        $appLeadKey  = 'COM_NXPEASYCART_VIEW_SETTINGS_PLACEHOLDER';
        break;
    case 'logs':
        $appTitleKey = 'COM_NXPEASYCART_MENU_LOGS';
        $appLeadKey  = 'COM_NXPEASYCART_VIEW_LOGS_PLACEHOLDER';
        break;
    default:
        $appTitleKey = 'COM_NXPEASYCART_MENU_DASHBOARD';
        $appLeadKey  = 'COM_NXPEASYCART_ADMIN_PLACEHOLDER';
        break;
}

$appTitle = Text::_($appTitleKey);
$appLead  = Text::_($appLeadKey);

$dataAttributes = [
    'csrf-token'                         => $token,
    'products-endpoint'                  => $productsEndpointList,
    'products-endpoint-create'           => $productsEndpointStore,
    'products-endpoint-update'           => $productsEndpointUpdate,
    'products-endpoint-delete'           => $productsEndpointDelete,
    'categories-endpoint'                => $categoriesEndpointList,
    'categories-endpoint-create'         => $categoriesEndpointStore,
    'categories-endpoint-update'         => $categoriesEndpointUpdate,
    'categories-endpoint-delete'         => $categoriesEndpointDelete,
    'orders-endpoint'                    => $ordersEndpointList,
    'orders-endpoint-show'               => $ordersEndpointShow,
    'orders-endpoint-transition'         => $ordersEndpointTransition,
    'app-title'                          => $appTitle,
    'app-lead'                           => $appLead,
    'app-title-key'                      => $appTitleKey,
    'app-lead-key'                       => $appLeadKey,
    'active-section'                     => $section,
    'products-panel-title'               => Text::_('COM_NXPEASYCART_MENU_PRODUCTS'),
    'products-panel-lead'                => Text::_('COM_NXPEASYCART_PRODUCTS_LEAD'),
    'products-refresh'                   => Text::_('COM_NXPEASYCART_PRODUCTS_REFRESH'),
    'products-search-placeholder'        => Text::_('COM_NXPEASYCART_PRODUCTS_SEARCH_PLACEHOLDER'),
    'products-loading'                   => Text::_('COM_NXPEASYCART_PRODUCTS_LOADING'),
    'products-empty'                     => Text::_('COM_NXPEASYCART_PRODUCTS_EMPTY'),
    'categories-panel-title'             => Text::_('COM_NXPEASYCART_MENU_CATEGORIES'),
    'categories-panel-lead'              => Text::_('COM_NXPEASYCART_CATEGORIES_LEAD'),
    'categories-refresh'                 => Text::_('COM_NXPEASYCART_CATEGORIES_REFRESH'),
    'categories-search-placeholder'      => Text::_('COM_NXPEASYCART_CATEGORIES_SEARCH_PLACEHOLDER'),
    'categories-loading'                 => Text::_('COM_NXPEASYCART_CATEGORIES_LOADING'),
    'categories-empty'                   => Text::_('COM_NXPEASYCART_CATEGORIES_EMPTY'),
    'categories-add'                     => Text::_('COM_NXPEASYCART_CATEGORIES_ADD'),
    'categories-table-title'             => Text::_('COM_NXPEASYCART_CATEGORIES_TABLE_TITLE'),
    'categories-table-slug'              => Text::_('COM_NXPEASYCART_CATEGORIES_TABLE_SLUG'),
    'categories-table-parent'            => Text::_('COM_NXPEASYCART_CATEGORIES_TABLE_PARENT'),
    'categories-table-sort'              => Text::_('COM_NXPEASYCART_CATEGORIES_TABLE_SORT'),
    'categories-table-usage'             => Text::_('COM_NXPEASYCART_CATEGORIES_TABLE_USAGE'),
    'categories-form-title'              => Text::_('COM_NXPEASYCART_CATEGORIES_FORM_TITLE'),
    'categories-form-slug'               => Text::_('COM_NXPEASYCART_CATEGORIES_FORM_SLUG'),
    'categories-form-parent'             => Text::_('COM_NXPEASYCART_CATEGORIES_FORM_PARENT'),
    'categories-form-parent-placeholder' => Text::_('COM_NXPEASYCART_CATEGORIES_FORM_PARENT_PLACEHOLDER'),
    'categories-form-sort'               => Text::_('COM_NXPEASYCART_CATEGORIES_FORM_SORT'),
    'categories-save'                    => Text::_('COM_NXPEASYCART_CATEGORIES_SAVE'),
    'categories-cancel'                  => Text::_('COM_NXPEASYCART_CATEGORIES_CANCEL'),
    'categories-delete-confirm'          => Text::_('COM_NXPEASYCART_CATEGORIES_DELETE_CONFIRM'),
    'categories-parent-none'             => Text::_('COM_NXPEASYCART_CATEGORIES_PARENT_NONE'),
    'categories-details-close'           => Text::_('COM_NXPEASYCART_CATEGORIES_DETAILS_CLOSE'),
    'status-active'                      => Text::_('COM_NXPEASYCART_STATUS_ACTIVE'),
    'status-inactive'                    => Text::_('COM_NXPEASYCART_STATUS_INACTIVE'),
    'base-currency'                      => $baseCurrency,
    'orders-panel-title'                 => Text::_('COM_NXPEASYCART_MENU_ORDERS'),
    'orders-panel-lead'                  => Text::_('COM_NXPEASYCART_ORDERS_LEAD'),
    'orders-refresh'                     => Text::_('COM_NXPEASYCART_ORDERS_REFRESH'),
    'orders-search-placeholder'          => Text::_('COM_NXPEASYCART_ORDERS_SEARCH_PLACEHOLDER'),
    'orders-filter-state'                => Text::_('COM_NXPEASYCART_ORDERS_FILTER_STATE'),
    'orders-loading'                     => Text::_('COM_NXPEASYCART_ORDERS_LOADING'),
    'orders-empty'                       => Text::_('COM_NXPEASYCART_ORDERS_EMPTY'),
    'orders-state-transitions'           => Text::_('COM_NXPEASYCART_ORDERS_TRANSITIONS'),
    'orders-details-title'               => Text::_('COM_NXPEASYCART_ORDERS_DETAILS_TITLE'),
    'orders-details-close'               => Text::_('COM_NXPEASYCART_ORDERS_DETAILS_CLOSE'),
    'orders-state-label'                 => Text::_('COM_NXPEASYCART_ORDERS_STATE_LABEL'),
    'orders-total-label'                 => Text::_('COM_NXPEASYCART_ORDERS_TOTAL_LABEL'),
    'orders-currency-label'              => Text::_('COM_NXPEASYCART_ORDERS_CURRENCY_LABEL'),
    'orders-items-label'                 => Text::_('COM_NXPEASYCART_ORDERS_ITEMS_LABEL'),
    'orders-billing-label'               => Text::_('COM_NXPEASYCART_ORDERS_BILLING_LABEL'),
    'orders-shipping-label'              => Text::_('COM_NXPEASYCART_ORDERS_SHIPPING_LABEL'),
    'orders-transition-success'          => Text::_('COM_NXPEASYCART_ORDERS_TRANSITION_SUCCESS'),
    'orders-transition-error'            => Text::_('COM_NXPEASYCART_ORDERS_TRANSITION_ERROR'),
    'orders-no-shipping'                 => Text::_('COM_NXPEASYCART_ORDERS_NO_SHIPPING'),
    'orders-table-select'                => Text::_('COM_NXPEASYCART_ORDERS_TABLE_SELECT'),
    'orders-select-order'                => Text::_('COM_NXPEASYCART_ORDERS_SELECT_ORDER'),
    'orders-selected-count'              => Text::_('COM_NXPEASYCART_ORDERS_SELECTED_COUNT'),
    'orders-bulk-state'                  => Text::_('COM_NXPEASYCART_ORDERS_BULK_STATE'),
    'orders-bulk-state-placeholder'      => Text::_('COM_NXPEASYCART_ORDERS_BULK_STATE_PLACEHOLDER'),
    'orders-bulk-apply'                  => Text::_('COM_NXPEASYCART_ORDERS_BULK_APPLY'),
    'orders-clear-selection'             => Text::_('COM_NXPEASYCART_ORDERS_CLEAR_SELECTION'),
    'orders-transactions-label'          => Text::_('COM_NXPEASYCART_ORDERS_TRANSACTIONS_LABEL'),
    'orders-timeline-label'              => Text::_('COM_NXPEASYCART_ORDERS_TIMELINE_LABEL'),
    'orders-timeline-empty'              => Text::_('COM_NXPEASYCART_ORDERS_TIMELINE_EMPTY'),
    'orders-timeline-created'            => Text::_('COM_NXPEASYCART_ORDERS_TIMELINE_CREATED'),
    'orders-timeline-state'              => Text::_('COM_NXPEASYCART_ORDERS_TIMELINE_STATE'),
    'orders-timeline-note'               => Text::_('COM_NXPEASYCART_ORDERS_TIMELINE_NOTE'),
    'orders-note-label'                  => Text::_('COM_NXPEASYCART_ORDERS_NOTE_LABEL'),
    'orders-note-placeholder'            => Text::_('COM_NXPEASYCART_ORDERS_NOTE_PLACEHOLDER'),
    'orders-note-submit'                 => Text::_('COM_NXPEASYCART_ORDERS_NOTE_SUBMIT'),
    'product-images-placeholder'         => Text::_('COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_PLACEHOLDER'),
    'product-images-select'              => Text::_('COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_SELECT'),
    'product-images-move-up'             => Text::_('COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_MOVE_UP'),
    'product-images-move-down'           => Text::_('COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_MOVE_DOWN'),
    'product-images-remove'              => Text::_('COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_REMOVE'),
    'product-images-add'                 => Text::_('COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_ADD'),
    'product-images-prompt'              => Text::_('COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_PROMPT'),
    'customers-panel-title'              => Text::_('COM_NXPEASYCART_MENU_CUSTOMERS'),
    'customers-panel-lead'               => Text::_('COM_NXPEASYCART_CUSTOMERS_LEAD'),
    'customers-refresh'                  => Text::_('COM_NXPEASYCART_CUSTOMERS_REFRESH'),
    'customers-search-placeholder'       => Text::_('COM_NXPEASYCART_CUSTOMERS_SEARCH_PLACEHOLDER'),
    'customers-loading'                  => Text::_('COM_NXPEASYCART_CUSTOMERS_LOADING'),
    'customers-empty'                    => Text::_('COM_NXPEASYCART_CUSTOMERS_EMPTY'),
    'customers-table-email'              => Text::_('COM_NXPEASYCART_CUSTOMERS_TABLE_EMAIL'),
    'customers-table-name'               => Text::_('COM_NXPEASYCART_CUSTOMERS_TABLE_NAME'),
    'customers-table-orders'             => Text::_('COM_NXPEASYCART_CUSTOMERS_TABLE_ORDERS'),
    'customers-table-total'              => Text::_('COM_NXPEASYCART_CUSTOMERS_TABLE_TOTAL'),
    'customers-table-last'               => Text::_('COM_NXPEASYCART_CUSTOMERS_TABLE_LAST'),
    'customers-details-close'            => Text::_('COM_NXPEASYCART_CUSTOMERS_DETAILS_CLOSE'),
    'customers-details-summary'          => Text::_('COM_NXPEASYCART_CUSTOMERS_DETAILS_SUMMARY'),
    'customers-details-name'             => Text::_('COM_NXPEASYCART_CUSTOMERS_DETAILS_NAME'),
    'customers-details-total'            => Text::_('COM_NXPEASYCART_CUSTOMERS_DETAILS_TOTAL'),
    'customers-details-orders'           => Text::_('COM_NXPEASYCART_CUSTOMERS_DETAILS_ORDERS'),
    'customers-details-last'             => Text::_('COM_NXPEASYCART_CUSTOMERS_DETAILS_LAST'),
    'customers-details-billing'          => Text::_('COM_NXPEASYCART_CUSTOMERS_DETAILS_BILLING'),
    'customers-details-shipping'         => Text::_('COM_NXPEASYCART_CUSTOMERS_DETAILS_SHIPPING'),
    'customers-details-no-shipping'      => Text::_('COM_NXPEASYCART_CUSTOMERS_DETAILS_NO_SHIPPING'),
    'customers-details-orders-list'      => Text::_('COM_NXPEASYCART_CUSTOMERS_DETAILS_ORDERS_LIST'),
    'customers-details-no-orders'        => Text::_('COM_NXPEASYCART_CUSTOMERS_DETAILS_NO_ORDERS'),
    'coupons-panel-title'                => Text::_('COM_NXPEASYCART_MENU_COUPONS'),
    'coupons-panel-lead'                 => Text::_('COM_NXPEASYCART_COUPONS_LEAD'),
    'coupons-refresh'                    => Text::_('COM_NXPEASYCART_COUPONS_REFRESH'),
    'coupons-add'                        => Text::_('COM_NXPEASYCART_COUPONS_ADD'),
    'coupons-search-placeholder'         => Text::_('COM_NXPEASYCART_COUPONS_SEARCH_PLACEHOLDER'),
    'coupons-loading'                    => Text::_('COM_NXPEASYCART_COUPONS_LOADING'),
    'coupons-empty'                      => Text::_('COM_NXPEASYCART_COUPONS_EMPTY'),
    'coupons-table-code'                 => Text::_('COM_NXPEASYCART_COUPONS_TABLE_CODE'),
    'coupons-table-type'                 => Text::_('COM_NXPEASYCART_COUPONS_TABLE_TYPE'),
    'coupons-table-value'                => Text::_('COM_NXPEASYCART_COUPONS_TABLE_VALUE'),
    'coupons-table-min-total'            => Text::_('COM_NXPEASYCART_COUPONS_TABLE_MIN_TOTAL'),
    'coupons-table-active'               => Text::_('COM_NXPEASYCART_COUPONS_TABLE_ACTIVE'),
    'coupons-table-usage'                => Text::_('COM_NXPEASYCART_COUPONS_TABLE_USAGE'),
    'coupons-details-close'              => Text::_('COM_NXPEASYCART_COUPONS_DETAILS_CLOSE'),
    'coupons-form-code'                  => Text::_('COM_NXPEASYCART_COUPONS_FORM_CODE'),
    'coupons-form-type'                  => Text::_('COM_NXPEASYCART_COUPONS_FORM_TYPE'),
    'coupons-form-value'                 => Text::_('COM_NXPEASYCART_COUPONS_FORM_VALUE'),
    'coupons-form-min-total'             => Text::_('COM_NXPEASYCART_COUPONS_FORM_MIN_TOTAL'),
    'coupons-form-start'                 => Text::_('COM_NXPEASYCART_COUPONS_FORM_START'),
    'coupons-form-end'                   => Text::_('COM_NXPEASYCART_COUPONS_FORM_END'),
    'coupons-form-max-uses'              => Text::_('COM_NXPEASYCART_COUPONS_FORM_MAX_USES'),
    'coupons-form-active'                => Text::_('COM_NXPEASYCART_COUPONS_FORM_ACTIVE'),
    'coupons-form-save'                  => Text::_('COM_NXPEASYCART_COUPONS_FORM_SAVE'),
    'coupons-form-cancel'                => Text::_('COM_NXPEASYCART_COUPONS_FORM_CANCEL'),
    'coupons-form-type-percent'          => Text::_('COM_NXPEASYCART_COUPONS_FORM_TYPE_PERCENT'),
    'coupons-form-type-fixed'            => Text::_('COM_NXPEASYCART_COUPONS_FORM_TYPE_FIXED'),
    'coupons-delete-confirm'             => Text::_('COM_NXPEASYCART_COUPONS_DELETE_CONFIRM'),
    'orders-preload'                     => json_encode($ordersPreload['items'] ?? [], $jsonOptions),
    'orders-preload-pagination'          => json_encode($ordersPreload['pagination'] ?? [], $jsonOptions),
    'dashboard-summary'                  => json_encode($dashboardSummary, $jsonOptions),
    'dashboard-checklist'                => json_encode($dashboardChecklist, $jsonOptions),
    'customers-preload'                  => json_encode($customersPreload['items'] ?? [], $jsonOptions),
    'customers-preload-pagination'       => json_encode($customersPreload['pagination'] ?? [], $jsonOptions),
    'categories-preload'                 => json_encode($categoriesPreload['items'] ?? [], $jsonOptions),
    'categories-preload-pagination'      => json_encode($categoriesPreload['pagination'] ?? [], $jsonOptions),
    'coupons-preload'                    => json_encode($couponsPreload['items'] ?? [], $jsonOptions),
    'tax-preload'                        => json_encode($taxPreload['items'] ?? [], $jsonOptions),
    'tax-preload-pagination'             => json_encode($taxPreload['pagination'] ?? [], $jsonOptions),
    'shipping-preload'                   => json_encode($shippingPreload['items'] ?? [], $jsonOptions),
    'shipping-preload-pagination'        => json_encode($shippingPreload['pagination'] ?? [], $jsonOptions),
    'settings-preload'                   => json_encode($settingsPreload, $jsonOptions),
    'logs-preload'                       => json_encode($logsPreload['items'] ?? [], $jsonOptions),
    'logs-preload-pagination'            => json_encode($logsPreload['pagination'] ?? [], $jsonOptions),
    'config'                             => json_encode($appConfig, $jsonOptions),
    'nav-items'                          => json_encode($navItems, $jsonOptions),
    'order-states'                       => json_encode($orderStates, $jsonOptions),
    'dashboard-endpoint'                 => $dashboardEndpoint,
    'orders-endpoint-bulk'               => $ordersEndpointBulkTransition,
    'orders-endpoint-note'               => $ordersEndpointNote,
    'customers-endpoint'                 => $customersEndpointList,
    'customers-endpoint-show'            => $customersEndpointShow,
    'coupons-endpoint'                   => $couponsEndpointList,
    'coupons-endpoint-create'            => $couponsEndpointStore,
    'coupons-endpoint-update'            => $couponsEndpointUpdate,
    'coupons-endpoint-delete'            => $couponsEndpointDelete,
    'tax-endpoint'                       => $taxEndpointList,
    'tax-endpoint-create'                => $taxEndpointStore,
    'tax-endpoint-update'                => $taxEndpointUpdate,
    'tax-endpoint-delete'                => $taxEndpointDelete,
    'shipping-endpoint'                  => $shippingEndpointList,
    'shipping-endpoint-create'           => $shippingEndpointStore,
    'shipping-endpoint-update'           => $shippingEndpointUpdate,
    'shipping-endpoint-delete'           => $shippingEndpointDelete,
    'settings-endpoint-show'             => $settingsEndpointGet,
    'settings-endpoint-update'           => $settingsEndpointSave,
    'payments-endpoint-show'             => $adminBase . '/index.php?option=com_nxpeasycart&task=api.payments.show&format=json',
    'payments-endpoint-update'           => $adminBase . '/index.php?option=com_nxpeasycart&task=api.payments.update&format=json&' . $tokenQuery,
    'logs-endpoint'                      => $logsEndpointList,
    'dashboard-title'                    => Text::_('COM_NXPEASYCART_MENU_DASHBOARD'),
    'dashboard-lead'                     => Text::_('COM_NXPEASYCART_DASHBOARD_LEAD'),
    'dashboard-refresh'                  => Text::_('COM_NXPEASYCART_DASHBOARD_REFRESH'),
    'dashboard-metric-products'          => Text::_('COM_NXPEASYCART_DASHBOARD_METRIC_PRODUCTS'),
    'dashboard-metric-products-total'    => Text::_('COM_NXPEASYCART_DASHBOARD_METRIC_PRODUCTS_TOTAL'),
    'dashboard-metric-orders'            => Text::_('COM_NXPEASYCART_DASHBOARD_METRIC_ORDERS'),
    'dashboard-metric-orders-count'      => Text::_('COM_NXPEASYCART_DASHBOARD_METRIC_ORDERS_COUNT'),
    'dashboard-metric-customers'         => Text::_('COM_NXPEASYCART_DASHBOARD_METRIC_CUSTOMERS'),
    'dashboard-metric-customers-hint'    => Text::_('COM_NXPEASYCART_DASHBOARD_METRIC_CUSTOMERS_HINT'),
    'dashboard-metric-month'             => Text::_('COM_NXPEASYCART_DASHBOARD_METRIC_MONTH'),
    'dashboard-metric-currency'          => Text::_('COM_NXPEASYCART_DASHBOARD_METRIC_CURRENCY'),
    'dashboard-checklist-title'          => Text::_('COM_NXPEASYCART_DASHBOARD_CHECKLIST'),
    'dashboard-checklist-action'         => Text::_('COM_NXPEASYCART_DASHBOARD_CHECKLIST_ACTION'),
    'media-modal-url'                    => $mediaModalUrl,
];
?>

<div
    id="nxp-ec-admin-app"
    class="nxp-ec-admin-app"
    <?php foreach ($dataAttributes as $key => $value) : ?>
        data-<?php echo $key; ?>="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"
    <?php endforeach; ?>
>
    <section class="nxp-admin-app__shell nxp-admin-app__shell--loading">
        <header class="nxp-admin-app__header">
            <h1 class="nxp-admin-app__title">
                <?php echo htmlspecialchars($appTitle, ENT_QUOTES, 'UTF-8'); ?>
            </h1>
            <p class="nxp-admin-app__lead">
                <?php echo htmlspecialchars($appLead, ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </header>

        <?php if (!empty($navItems)) : ?>
            <nav class="nxp-ec-admin-nav nxp-ec-admin-nav--loading" aria-label="<?php echo Text::_('JGLOBAL_NAVIGATION'); ?>">
                <?php foreach ($navItems as $item) : ?>
                    <?php
                        $link = htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8');
                    $title    = htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8');
                    $isActive = $item['id'] === $section;
                    ?>
                    <a
                        href="<?php echo $link; ?>"
                        class="<?php echo $isActive ? 'is-active' : ''; ?>"
                        <?php echo $isActive ? 'aria-current="page"' : ''; ?>
                    >
                        <?php echo $title; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <div class="nxp-ec-admin-panel nxp-ec-admin-panel--placeholder">
            <div class="nxp-ec-admin-panel__body">
                <p class="nxp-ec-admin-panel__lead">
                    <?php echo htmlspecialchars($appLead, ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>
        </div>
    </section>
</div>
