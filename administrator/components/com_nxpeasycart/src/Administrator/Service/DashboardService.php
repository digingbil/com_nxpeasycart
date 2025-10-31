<?php

namespace Nxp\EasyCart\Admin\Administrator\Service;

\defined('_JEXEC') or die;

use DateTimeImmutable;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Nxp\EasyCart\Admin\Administrator\Helper\ConfigHelper;

/**
 * Aggregates high-level metrics for the admin dashboard.
 */
class DashboardService
{
    private DatabaseInterface $db;
    private SettingsService $settings;

    public function __construct(DatabaseInterface $db, SettingsService $settings)
    {
        $this->db = $db;
        $this->settings = $settings;
    }

    /**
     * Retrieve headline metrics for dashboard cards.
     */
    public function getSummary(): array
    {
        $baseCurrency = ConfigHelper::getBaseCurrency();
        $now = new DateTimeImmutable('now');
        $dayStart = $now->setTime(0, 0, 0);
        $monthStart = $now->setDate((int) $now->format('Y'), (int) $now->format('m'), 1)->setTime(0, 0, 0);

        $summary = [
            'products' => $this->summariseProducts(),
            'orders' => $this->summariseOrders($dayStart, $monthStart),
            'customers' => $this->summariseCustomers(),
            'currency' => $baseCurrency,
        ];

        return $summary;
    }

    /**
     * Assemble onboarding checklist items.
     */
    public function getChecklist(): array
    {
        $params = ComponentHelper::getParams('com_nxpeasycart');
        $baseCurrency = strtoupper($params->get('base_currency', ''));
        $summary = $this->getSummary();
        $ordersPaid = (int) ($summary['orders']['paid_total'] ?? 0);
        $productsActive = (int) ($summary['products']['active'] ?? 0);
        $hasCustomers = (int) ($summary['customers']['total'] ?? 0) > 0;
        $paymentsConfigured = (bool) $this->settings->get('payments.configured', false);

        return [
            [
                'id' => 'set_currency',
                'label' => 'COM_NXPEASYCART_CHECKLIST_SET_CURRENCY',
                'completed' => $baseCurrency !== '',
                'link' => 'index.php?option=com_nxpeasycart&view=settings',
            ],
            [
                'id' => 'add_product',
                'label' => 'COM_NXPEASYCART_CHECKLIST_ADD_PRODUCT',
                'completed' => $productsActive > 0,
                'link' => 'index.php?option=com_nxpeasycart&view=products',
            ],
            [
                'id' => 'configure_payments',
                'label' => 'COM_NXPEASYCART_CHECKLIST_CONFIGURE_PAYMENTS',
                'completed' => $paymentsConfigured,
                'link' => 'index.php?option=com_nxpeasycart&view=settings',
            ],
            [
                'id' => 'review_orders',
                'label' => 'COM_NXPEASYCART_CHECKLIST_REVIEW_ORDERS',
                'completed' => $ordersPaid > 0,
                'link' => 'index.php?option=com_nxpeasycart&view=orders',
            ],
            [
                'id' => 'invite_customers',
                'label' => 'COM_NXPEASYCART_CHECKLIST_INVITE_CUSTOMERS',
                'completed' => $hasCustomers,
                'link' => 'index.php?option=com_nxpeasycart&view=customers',
            ],
        ];
    }

    private function summariseProducts(): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                'COUNT(*) AS total',
                'SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) AS active',
            ])
            ->from($this->db->quoteName('#__nxp_easycart_products'));

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active ?? 0),
        ];
    }

    private function summariseOrders(DateTimeImmutable $dayStart, DateTimeImmutable $monthStart): array
    {
        $dayStartValue = $dayStart->format('Y-m-d H:i:s');
        $monthStartValue = $monthStart->format('Y-m-d H:i:s');

        $query = $this->db->getQuery(true)
            ->select([
                'COUNT(*) AS total',
                'SUM(CASE WHEN state = "pending" THEN 1 ELSE 0 END) AS pending',
                'SUM(CASE WHEN state = "paid" THEN 1 ELSE 0 END) AS paid',
                'SUM(CASE WHEN state = "fulfilled" THEN 1 ELSE 0 END) AS fulfilled',
                'SUM(CASE WHEN state = "refunded" THEN 1 ELSE 0 END) AS refunded',
                'SUM(CASE WHEN created >= :dayStart THEN total_cents ELSE 0 END) AS revenue_today',
                'SUM(CASE WHEN created >= :monthStart THEN total_cents ELSE 0 END) AS revenue_month',
                'SUM(CASE WHEN state IN ("paid", "fulfilled") THEN total_cents ELSE 0 END) AS paid_total',
            ])
            ->from($this->db->quoteName('#__nxp_easycart_orders'))
            ->bind(':dayStart', $dayStartValue, ParameterType::STRING)
            ->bind(':monthStart', $monthStartValue, ParameterType::STRING);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return [
            'total' => (int) ($row->total ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'paid' => (int) ($row->paid ?? 0),
            'fulfilled' => (int) ($row->fulfilled ?? 0),
            'refunded' => (int) ($row->refunded ?? 0),
            'revenue_today' => (int) ($row->revenue_today ?? 0),
            'revenue_month' => (int) ($row->revenue_month ?? 0),
            'paid_total' => (int) ($row->paid_total ?? 0),
        ];
    }

    private function summariseCustomers(): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                'COUNT(DISTINCT email) AS total',
            ])
            ->from($this->db->quoteName('#__nxp_easycart_orders'));

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return [
            'total' => (int) ($row->total ?? 0),
        ];
    }
}
