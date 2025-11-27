<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * GDPR helper utilities to export or anonymise customer data.
 */
class GdprService
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    public function exportByEmail(string $email): array
    {
        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_GDPR_EMAIL_INVALID'), 400);
        }

        $orders = $this->loadOrders($email);

        return [
            'email'  => $email,
            'orders' => $orders,
        ];
    }

    public function anonymiseByEmail(string $email): int
    {
        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_GDPR_EMAIL_INVALID'), 400);
        }

        $hash            = substr(sha1($email . microtime()), 0, 12);
        $anonymisedEmail = sprintf('gdpr+%s@example.invalid', $hash);

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_orders'))
            ->set($this->db->quoteName('email') . ' = :anonEmail')
            ->set($this->db->quoteName('billing') . ' = NULL')
            ->set($this->db->quoteName('shipping') . ' = NULL')
            ->set($this->db->quoteName('carrier') . ' = NULL')
            ->set($this->db->quoteName('tracking_number') . ' = NULL')
            ->set($this->db->quoteName('tracking_url') . ' = NULL')
            ->set($this->db->quoteName('fulfillment_events') . ' = NULL')
            ->where($this->db->quoteName('email') . ' = :email')
            ->bind(':anonEmail', $anonymisedEmail, ParameterType::STRING)
            ->bind(':email', $email, ParameterType::STRING);

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->getAffectedRows();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadOrders(string $email): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_orders'))
            ->where($this->db->quoteName('email') . ' = :email')
            ->order($this->db->quoteName('created') . ' DESC')
            ->bind(':email', $email, ParameterType::STRING);

        $this->db->setQuery($query);
        $orders = $this->db->loadObjectList() ?: [];

        $export = [];

        foreach ($orders as $order) {
            $orderId = (int) $order->id;

            $export[] = [
                'id'           => $orderId,
                'order_no'     => $order->order_no,
                'state'        => $order->state,
                'total_cents'  => (int) $order->total_cents,
                'currency'     => $order->currency,
                'created'      => $order->created,
                'items'        => $this->loadOrderItems($orderId),
                'transactions' => $this->loadOrderTransactions($orderId),
            ];
        }

        return $export;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadOrderItems(int $orderId): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('sku'),
                $this->db->quoteName('title'),
                $this->db->quoteName('qty'),
                $this->db->quoteName('unit_price_cents'),
                $this->db->quoteName('total_cents'),
            ])
            ->from($this->db->quoteName('#__nxp_easycart_order_items'))
            ->where($this->db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);

        return $this->db->loadAssocList() ?: [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadOrderTransactions(int $orderId): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('gateway'),
                $this->db->quoteName('status'),
                $this->db->quoteName('amount_cents'),
                $this->db->quoteName('created'),
            ])
            ->from($this->db->quoteName('#__nxp_easycart_transactions'))
            ->where($this->db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);

        return $this->db->loadAssocList() ?: [];
    }
}
