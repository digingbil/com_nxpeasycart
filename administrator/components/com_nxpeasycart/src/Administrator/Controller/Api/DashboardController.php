<?php

namespace Nxp\EasyCart\Admin\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use Nxp\EasyCart\Admin\Administrator\Service\DashboardService;
use Nxp\EasyCart\Admin\Administrator\Service\SettingsService;

/**
 * Dashboard JSON controller delivering summary metrics.
 */
class DashboardController extends AbstractJsonController
{
    public function __construct($config = [], MVCFactoryInterface $factory = null, CMSApplicationInterface $app = null)
    {
        parent::__construct($config, $factory, $app);
    }

    /** @inheritDoc */
    public function execute($task)
    {
        $task = strtolower((string) $task ?: 'summary');

        return match ($task) {
            'summary' => $this->summary(),
            default => $this->respond([
                'message' => Text::_('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND'),
            ], 404),
        };
    }

    private function summary(): JsonResponse
    {
        $this->assertCan('core.manage');

        $container = Factory::getContainer();

        if (!$container->has(SettingsService::class)) {
            $container->set(
                SettingsService::class,
                static fn ($container) => new SettingsService($container->get(DatabaseInterface::class))
            );
        }

        if (!$container->has(DashboardService::class)) {
            $container->set(
                DashboardService::class,
                static fn ($container) => new DashboardService(
                    $container->get(DatabaseInterface::class),
                    $container->get(SettingsService::class)
                )
            );
        }

        /** @var DashboardService $service */
        $service = $container->get(DashboardService::class);

        return $this->respond([
            'summary' => $service->getSummary(),
            'checklist' => $service->getChecklist(),
        ]);
    }
}
