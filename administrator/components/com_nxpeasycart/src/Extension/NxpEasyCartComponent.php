<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;

/**
 * Component entry point for the administrator application.
 *
 * @since 0.1.5
 */
class NxpEasyCartComponent extends MVCComponent implements RouterServiceInterface
{
    use RouterServiceTrait;

    /**
     * Constructor.
     *
     * @param ComponentDispatcherFactoryInterface $dispatcherFactory Dispatcher factory
     * @param MVCFactoryInterface                 $mvcFactory        MVC factory
     *
     * @since 0.1.5
     */
    public function __construct(
        ComponentDispatcherFactoryInterface $dispatcherFactory,
        MVCFactoryInterface $mvcFactory
    ) {
        parent::__construct($dispatcherFactory, $mvcFactory);
    }
}
