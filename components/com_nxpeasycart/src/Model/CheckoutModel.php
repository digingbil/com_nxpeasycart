<?php

namespace Joomla\Component\Nxpeasycart\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Administrator\Service\CacheService;
use Joomla\Component\Nxpeasycart\Administrator\Service\PaymentGatewayService;
use Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService;
use Joomla\Component\Nxpeasycart\Administrator\Service\ShippingRuleService;
use Joomla\Component\Nxpeasycart\Administrator\Service\TaxService;
use Joomla\Component\Nxpeasycart\Site\Service\CartPresentationService;
use Joomla\Component\Nxpeasycart\Site\Service\CartSessionService;

/**
 * Checkout model aggregating cart + configuration data.
 */
class CheckoutModel extends BaseDatabaseModel
{
    private ?array $payload = null;

    /**
     * Return the checkout payload consumed by the template + Vue island.
     */
    public function getCheckout(): array
    {
        if ($this->payload !== null) {
            return $this->payload;
        }

        $container = Factory::getContainer();

        $cartSession  = $container->get(CartSessionService::class);
        $presentation = $this->getPresentationService();

        $cart = $presentation->hydrate($cartSession->current());

        $cache         = $this->getCacheService();
        $shippingRules = $cache->remember(
            'checkout.shipping',
            fn () => $this->getShippingService()->paginate(['search' => ''], 50, 0)['items'] ?? [],
            300
        );
        $taxRates = $cache->remember(
            'checkout.tax',
            fn () => $this->getTaxService()->paginate(['search' => ''], 50, 0)['items'] ?? [],
            300
        );
        $settings = $this->getSettingsService()->all();
        $payments = $this->getPaymentService()->getConfig();

        $this->payload = [
            'cart'           => $cart,
            'shipping_rules' => $shippingRules,
            'tax_rates'      => $taxRates,
            'settings'       => $settings,
            'payments'       => $payments,
        ];

        return $this->payload;
    }

    private function getPresentationService(): CartPresentationService
    {
        $container = Factory::getContainer();

        if ($container->has(CartPresentationService::class)) {
            return $container->get(CartPresentationService::class);
        }

        return new CartPresentationService($container->get(DatabaseInterface::class));
    }

    private function getShippingService(): ShippingRuleService
    {
        $container = Factory::getContainer();

        if ($container->has(ShippingRuleService::class)) {
            return $container->get(ShippingRuleService::class);
        }

        return new ShippingRuleService($container->get(DatabaseInterface::class));
    }

    private function getTaxService(): TaxService
    {
        $container = Factory::getContainer();

        if ($container->has(TaxService::class)) {
            return $container->get(TaxService::class);
        }

        return new TaxService($container->get(DatabaseInterface::class));
    }

    private function getSettingsService(): SettingsService
    {
        $container = Factory::getContainer();

        if ($container->has(SettingsService::class)) {
            return $container->get(SettingsService::class);
        }

        return new SettingsService($container->get(DatabaseInterface::class));
    }

    private function getPaymentService(): PaymentGatewayService
    {
        $container = Factory::getContainer();

        if ($container->has(PaymentGatewayService::class)) {
            return $container->get(PaymentGatewayService::class);
        }

        return new PaymentGatewayService($this->getSettingsService());
    }

    private function getCacheService(): CacheService
    {
        $container = Factory::getContainer();

        if ($container->has(CacheService::class)) {
            return $container->get(CacheService::class);
        }

        return new CacheService($container->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class));
    }
}
