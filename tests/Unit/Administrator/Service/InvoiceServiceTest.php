<?php

declare(strict_types=1);

namespace Tests\Unit\Administrator\Service;

use Joomla\Component\Nxpeasycart\Administrator\Service\InvoiceService;
use Joomla\Component\Nxpeasycart\Administrator\Service\PaymentGatewayService;
use Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService;
use PHPUnit\Framework\TestCase;

final class InvoiceServiceTest extends TestCase
{
    public function testRendersBankTransferDetails(): void
    {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('all')->willReturn([
            'store' => [
                'name' => 'Demo Store',
                'url'  => 'http://example.test',
            ],
        ]);

        $gateways = $this->createMock(PaymentGatewayService::class);
        $gateways->method('getGatewayConfig')->willReturn([
            'label'        => 'Bank transfer',
            'instructions' => 'Pay by referencing the order number.',
            'account_name' => 'Demo LLC',
            'iban'         => 'DE001234567890',
            'bic'          => 'GENODEF1JEV',
        ]);

        $service = new InvoiceService($settings, $gateways);

        $order = [
            'order_no'       => 'INV-100',
            'currency'       => 'EUR',
            'items'          => [
                ['title' => 'Sample', 'sku' => 'SKU-1', 'qty' => 1, 'total_cents' => 2500],
            ],
            'subtotal_cents' => 2500,
            'total_cents'    => 2500,
            'billing'        => [
                'first_name'    => 'Ada',
                'last_name'     => 'Lovelace',
                'address_line1' => '42 Example Rd',
                'city'          => 'Paris',
                'postcode'      => '75001',
                'country'       => 'France',
            ],
        ];

        $html = $service->renderInvoiceHtml($order, [
            'payment' => [
                'method'       => 'bank_transfer',
                'instructions' => 'Pay by referencing the order number.',
                'account_name' => 'Demo LLC',
                'iban'         => 'DE001234567890',
                'bic'          => 'GENODEF1JEV',
            ],
        ]);

        $this->assertStringContainsString('INV-100', $html);
        $this->assertStringContainsString('DE001234567890', $html);
        $this->assertStringContainsString('Pay by referencing the order number.', $html);
    }

    public function testGeneratesPdfPayload(): void
    {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('all')->willReturn([]);

        $gateways = $this->createMock(PaymentGatewayService::class);
        $gateways->method('getGatewayConfig')->willReturn([]);

        $service = new InvoiceService($settings, $gateways);

        $order = [
            'order_no'       => 'INV-200',
            'currency'       => 'USD',
            'items'          => [
                ['title' => 'Notebook', 'sku' => 'NOTE-1', 'qty' => 2, 'total_cents' => 3600],
            ],
            'subtotal_cents' => 3600,
            'total_cents'    => 3600,
        ];

        $pdf = $service->generateInvoice($order);

        $this->assertSame('invoice-INV-200.pdf', $pdf['filename']);
        $this->assertNotEmpty($pdf['content']);
        $this->assertStringContainsString('%PDF', substr((string) $pdf['content'], 0, 20));
    }
}
