<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;

/**
 * Component entry point for the administrator application.
 */
class NxpEasyCartComponent extends MVCComponent implements RouterServiceInterface
{
    use RouterServiceTrait;

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
