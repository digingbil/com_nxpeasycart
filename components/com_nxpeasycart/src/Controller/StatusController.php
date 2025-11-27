<?php

namespace Joomla\Component\Nxpeasycart\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Component\Nxpeasycart\Site\Model\OrderModel;
use Joomla\Component\Nxpeasycart\Site\Model\OrdersModel;

/**
 * Read-only order status endpoints for storefront consumers.
 */
class StatusController extends BaseController
{
    /**
     * Public, tokenised order status endpoint.
     */
    public function tracking(): void
    {
        $app   = Factory::getApplication();
        $token = trim((string) $app->input->getString('ref', $app->input->getString('token', '')));

        if ($token === '') {
            $this->respond(['message' => Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND')], 400);
            return;
        }

        /** @var OrderModel|null $model */
        $model = $this->getModel('Order');

        if ($model) {
            $model->setState('order.token', $token);
            $model->setState('order.id', 0);
            $model->setState('order.number', '');
        }

        $order = $model ? $model->getItem() : null;

        if (!$order) {
            $this->respond(['message' => Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND')], 404);
            return;
        }

        $this->respond([
            'order'  => $order,
            'public' => $model ? (bool) $model->isPublicView() : true,
            'owner'  => $model ? (bool) $model->isOwnerView() : false,
        ]);
    }

    /**
     * Authenticated order detail endpoint (owner-only).
     */
    public function show(): void
    {
        $identity = $this->getIdentity();

        if (!$identity || $identity->guest) {
            $this->respond(['message' => Text::_('JGLOBAL_AUTH_ACCESS_DENIED')], 401);
            return;
        }

        $app     = Factory::getApplication();
        $orderId = $app->input->getInt('id', 0);
        $orderNo = $app->input->getCmd('no', '');

        if ($orderId <= 0 && $orderNo === '') {
            $this->respond(['message' => Text::_('COM_NXPEASYCART_ERROR_INVALID_ID')], 400);
            return;
        }

        /** @var OrderModel|null $model */
        $model = $this->getModel('Order');

        if ($model) {
            $model->setState('order.id', $orderId);
            $model->setState('order.number', $orderNo);
            $model->setState('order.token', '');
        }

        $order = $model ? $model->getItem() : null;

        if (!$order) {
            $this->respond(['message' => Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND')], 404);
            return;
        }

        $this->respond(['order' => $order]);
    }

    /**
     * Authenticated list endpoint for the current user's orders.
     */
    public function mine(): void
    {
        $identity = $this->getIdentity();

        if (!$identity || $identity->guest) {
            $this->respond(['message' => Text::_('JGLOBAL_AUTH_ACCESS_DENIED')], 401);
            return;
        }

        /** @var OrdersModel|null $model */
        $model = $this->getModel('Orders');

        if ($model) {
            $app = Factory::getApplication();
            $model->setState('list.limit', max(1, (int) $app->input->getInt('limit', (int) $app->get('list_limit', 20))));
            $model->setState('list.start', max(0, (int) $app->input->getInt('start', 0)));
        }

        $items      = $model ? $model->getItems() : [];
        $pagination = $model ? $model->getPagination() : [];

        $this->respond([
            'items'      => $items,
            'pagination' => $pagination,
        ]);
    }

    /**
     * Resolve the current user identity safely.
     */
    private function getIdentity()
    {
        try {
            return Factory::getApplication()->getIdentity();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function respond(array $payload, int $code = 200): void
    {
        $app      = Factory::getApplication();
        $hasError = $code >= 400;

        if (\function_exists('http_response_code')) {
            http_response_code($code);
        }

        $app->setHeader('Content-Type', 'application/json', true);
        $app->setHeader('Status', (string) $code, true);
        $app->setBody((new JsonResponse($payload, '', $hasError))->toString());
        $app->sendResponse();
        $app->close();
    }
}
