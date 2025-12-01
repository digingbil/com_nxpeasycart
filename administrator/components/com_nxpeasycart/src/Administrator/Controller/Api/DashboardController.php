<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Administrator\Service\DashboardService;
use Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService;

/**
 * Dashboard JSON controller delivering summary metrics.
 *
 * @since 0.1.5
 */
class DashboardController extends AbstractJsonController
{
    public function __construct($config = [], MVCFactoryInterface $factory = null, CMSApplicationInterface $app = null)
    {
        parent::__construct($config, $factory, $app);
    }

    /**
     * {@inheritDoc}
     *
     * @param string $task The task name
     * @return JsonResponse
     *
     * @since 0.1.5
     */
    public function execute($task)
    {
        $task = strtolower((string) $task);

        if (str_contains($task, '.')) {
            $segments = array_filter(explode('.', $task));
            $task     = trim((string) array_pop($segments));
        }

        $task = $task !== '' ? $task : 'summary';

        return match ($task) {
            'summary', 'browse', 'list' => $this->summary(),
            default => $this->respond([
                'message' => Text::sprintf('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND', $task),
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
            'summary'   => $service->getSummary(),
            'checklist' => $service->getChecklist(),
        ]);
    }
}
