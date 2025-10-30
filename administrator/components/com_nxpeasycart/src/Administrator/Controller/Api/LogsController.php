<?php

namespace Nxp\EasyCart\Admin\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use Nxp\EasyCart\Admin\Administrator\Service\AuditService;

class LogsController extends AbstractJsonController
{
    public function __construct($config = [], MVCFactoryInterface $factory = null, CMSApplicationInterface $app = null)
    {
        parent::__construct($config, $factory, $app);
    }

    public function execute($task)
    {
        $task = strtolower((string) $task ?: 'list');

        return match ($task) {
            'list', 'browse' => $this->list(),
            default          => $this->respond(['message' => Text::_('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND')], 404),
        };
    }

    protected function list(): JsonResponse
    {
        $this->assertCan('core.manage');

        $limit = $this->input->getInt('limit', 20);
        $start = $this->input->getInt('start', 0);
        $entity = $this->input->getCmd('entity', '');
        $search = $this->input->getString('search', '');

        $result = $this->getService()->paginate(
            [
                'entity' => $entity,
                'search' => $search,
            ],
            $limit,
            $start
        );

        return $this->respond($result);
    }

    private function getService(): AuditService
    {
        $container = Factory::getContainer();

        if (!$container->has(AuditService::class)) {
            $container->set(
                AuditService::class,
                static fn ($container) => new AuditService($container->get(DatabaseInterface::class))
            );
        }

        return $container->get(AuditService::class);
    }
}
