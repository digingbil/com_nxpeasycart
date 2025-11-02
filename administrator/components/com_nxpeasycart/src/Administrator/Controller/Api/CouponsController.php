<?php

namespace Nxp\EasyCart\Admin\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use Nxp\EasyCart\Admin\Administrator\Service\CouponService;
use RuntimeException;

/**
 * Coupons API controller.
 */
class CouponsController extends AbstractJsonController
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
            'store', 'create' => $this->store(),
            'update', 'patch' => $this->update(),
            'delete', 'destroy' => $this->destroy(),
            default => $this->respond(['message' => Text::_('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND')], 404),
        };
    }

    protected function list(): JsonResponse
    {
        $this->assertCan('core.manage');

        $limit  = $this->input->getInt('limit', 20);
        $start  = $this->input->getInt('start', 0);
        $search = $this->input->getString('search', '');

        $service = $this->getCouponService();
        $result  = $service->paginate([
            'search' => $search,
        ], $limit, $start);

        return $this->respond($result);
    }

    protected function store(): JsonResponse
    {
        $this->assertCan('core.create');
        $this->assertToken();

        $payload = $this->decodePayload();
        $service = $this->getCouponService();
        $coupon  = $service->create($payload);

        return $this->respond(['coupon' => $coupon], 201);
    }

    protected function update(): JsonResponse
    {
        $this->assertCan('core.edit');
        $this->assertToken();

        $id      = $this->requireId();
        $payload = $this->decodePayload();

        $service = $this->getCouponService();
        $coupon  = $service->update($id, $payload);

        return $this->respond(['coupon' => $coupon]);
    }

    protected function destroy(): JsonResponse
    {
        $this->assertCan('core.delete');
        $this->assertToken();

        $payload = $this->decodePayload();
        $ids     = isset($payload['ids']) && is_array($payload['ids'])
            ? array_map('intval', $payload['ids'])
            : [];

        if (!$ids) {
            $id = $this->input->getInt('id');

            if ($id > 0) {
                $ids = [$id];
            }
        }

        if (!$ids) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_COUPON_ID_REQUIRED'), 400);
        }

        $service = $this->getCouponService();
        $deleted = $service->delete($ids);

        return $this->respond(['deleted' => $deleted]);
    }

    private function decodePayload(): array
    {
        $raw = $this->input->json->getRaw();

        if ($raw === null || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_INVALID_JSON'), 400);
        }

        return (array) $data;
    }

    private function getCouponService(): CouponService
    {
        $container = Factory::getContainer();

        if (!$container->has(CouponService::class)) {
            $container->set(
                CouponService::class,
                static fn ($container) => new CouponService($container->get(DatabaseInterface::class))
            );
        }

        return $container->get(CouponService::class);
    }
}
