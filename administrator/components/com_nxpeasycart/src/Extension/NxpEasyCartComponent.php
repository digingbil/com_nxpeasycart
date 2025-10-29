<?php

namespace Nxp\EasyCart\Admin\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;

/**
 * Component entry point for the administrator application.
 */
class NxpEasyCartComponent extends MVCComponent
{
    /**
     * Constructor.
     *
     * @param ComponentDispatcherFactoryInterface $dispatcherFactory Dispatcher factory
     * @param MVCFactoryInterface                 $mvcFactory        MVC factory
     */
    public function __construct(
        ComponentDispatcherFactoryInterface $dispatcherFactory,
        MVCFactoryInterface $mvcFactory
    ) {
        parent::__construct($dispatcherFactory, $mvcFactory);
    }
}
