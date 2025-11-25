<?php

declare(strict_types=1);

namespace Tests\Unit\Administrator\Service;

use Joomla\Component\Nxpeasycart\Administrator\Service\PaymentGatewayService;
use Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService;
use PHPUnit\Framework\TestCase;

final class PaymentGatewayServiceTest extends TestCase
{
    public function testNormalisesBankTransferCredentials(): void
    {
        $settings = $this->createMock(SettingsService::class);

        $settings->method('get')->willReturnOnConsecutiveCalls([], [
            'bank_transfer' => [
                'enabled'      => true,
                'label'        => 'Bank',
                'instructions' => 'Pay by transfer',
                'account_name' => 'Acme Ltd',
                'iban'         => 'GB29NWBK60161331926819',
                'bic'          => 'NWBKGB2L',
            ],
        ]);

        $settings->expects($this->once())
            ->method('set')
            ->with(
                'payment_gateways',
                $this->callback(function ($value) {
                    $bank = $value['bank_transfer'] ?? [];

                    return ($bank['enabled'] ?? false) === true
                        && ($bank['iban'] ?? '') === 'GB29NWBK60161331926819'
                        && ($bank['bic'] ?? '') === 'NWBKGB2L';
                })
            );

        $service = new PaymentGatewayService($settings);

        $config = $service->saveConfig([
            'bank_transfer' => [
                'enabled'      => true,
                'label'        => 'Bank',
                'instructions' => 'Pay by transfer',
                'account_name' => 'Acme Ltd',
                'iban'         => ' gb29 nwbk 6016 1331 9268 19 ',
                'bic'          => 'nwbkgb2l',
            ],
        ]);

        $this->assertSame('GB29NWBK60161331926819', $config['bank_transfer']['iban']);
        $this->assertSame('NWBKGB2L', $config['bank_transfer']['bic']);
    }
}
