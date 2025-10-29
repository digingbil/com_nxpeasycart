<?php

namespace Nxp\EasyCart\Admin\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;

/**
 * Products API controller placeholder.
 */
class ProductsController extends AbstractJsonController
{
    /**
     * Constructor.
     *
     * @param array                     $config  Controller configuration
     * @param MVCFactoryInterface|null  $factory MVC factory
     * @param CMSApplicationInterface|null $app  Application instance
     */
    public function __construct($config = [], MVCFactoryInterface $factory = null, CMSApplicationInterface $app = null)
    {
        parent::__construct($config, $factory, $app);
    }

    /**
     * {@inheritDoc}
     */
    public function execute($task)
    {
        return new JsonResponse(
            [
                'data' => [],
            ]
        );
    }
}
