<?php

namespace Joomla\Component\Nxpeasycart\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Component\Nxpeasycart\Administrator\Service\OrderService;
use Joomla\Component\Nxpeasycart\Administrator\Service\RateLimiter;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\Database\DatabaseInterface;
use RuntimeException;

/**
 * Order confirmation model powering storefront order summary.
 */
class OrderModel extends BaseDatabaseModel
{
    private ?array $order = null;
    private bool $publicView = false;
    private bool $ownerView  = false;

    /**
     * {@inheritDoc}
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app   = Factory::getApplication();
        $input = $app->input;

        $this->setState('order.id', $input->getInt('id'));
        $this->setState('order.number', $input->getCmd('no', ''));
        $this->setState('order.token', $input->getString('ref', ''));
    }

    public function isPublicView(): bool
    {
        return $this->publicView;
    }

    public function isOwnerView(): bool
    {
        return $this->ownerView;
    }

    /**
     * Retrieve the order payload for confirmation display.
     */
    public function getItem(): ?array
    {
        if ($this->order !== null) {
            return $this->order;
        }

        $container = Factory::getContainer();
        $this->bootstrapContainer($container);

        $service = $container->get(OrderService::class);

        $id     = (int) $this->getState('order.id');
        $number = (string) $this->getState('order.number');
        $token  = (string) $this->getState('order.token');
        $userId = $this->getUserId();

        $order = null;

        if ($token !== '') {
            $this->enforceTokenRateLimit($token);
            $order = $service->getByPublicToken($token);
            $this->publicView = true;
            $this->ownerView  = $order !== null && $userId !== null && isset($order['user_id']) && (int) $order['user_id'] === $userId;

            if ($order && !$this->ownerView) {
                $order = $this->maskOrderForPublic($order);
            }
            $this->order = $order;

            return $this->order;
        }

        if ($id > 0) {
            $order = $service->get($id);
        } elseif ($number !== '') {
            $order = $service->getByNumber($number);
        }

        if ($order) {
            $this->ownerView = $userId !== null && isset($order['user_id']) && (int) $order['user_id'] === $userId;

            if (!$this->ownerView) {
                $order = null;
            }
        }

        $this->order = $order;

        return $this->order;
    }

    private function bootstrapContainer($container): void
    {
        $providerPath = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/services/provider.php';

        if ((!$container->has(OrderService::class) || !$container->has(RateLimiter::class)) && is_file($providerPath)) {
            $container->registerServiceProvider(require $providerPath);
        }

        if (!$container->has(OrderService::class) && $container->has(DatabaseInterface::class)) {
            $container->set(
                OrderService::class,
                static fn ($c) => new OrderService($c->get(DatabaseInterface::class))
            );
        }

        if (!$container->has(RateLimiter::class) && $container->has(CacheControllerFactoryInterface::class)) {
            $container->set(
                RateLimiter::class,
                static fn ($c) => new RateLimiter($c->get(CacheControllerFactoryInterface::class))
            );
        }
    }

    private function getUserId(): ?int
    {
        try {
            $identity = Factory::getApplication()->getIdentity();
        } catch (\Throwable $exception) {
            return null;
        }

        if (!$identity || $identity->guest) {
            return null;
        }

        return isset($identity->id) ? (int) $identity->id : null;
    }

    private function enforceTokenRateLimit(string $token): void
    {
        $container = Factory::getContainer();

        if (!$container->has(RateLimiter::class)) {
            return;
        }

        $limiter = $container->get(RateLimiter::class);
        $ip      = $this->getClientIp();
        $limit   = 60;
        $window  = 600;

        $tokenAllowed = $limiter->hit('order-status:token:' . $token, $limit, $window);
        $ipAllowed    = $ip === '' ? true : $limiter->hit('order-status:ip:' . $ip, $limit, $window);

        if (!$tokenAllowed || !$ipAllowed) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_RATE_LIMITED'), 429);
        }
    }

    private function getClientIp(): string
    {
        try {
            $server = Factory::getApplication()->input->server;
        } catch (\Throwable $exception) {
            return '';
        }

        $ip = $server->getString('REMOTE_ADDR', '');

        if ($ip === '') {
            $ip = $server->getString('HTTP_X_FORWARDED_FOR', '');
        }

        if ($ip !== '' && str_contains($ip, ',')) {
            $parts = explode(',', $ip);
            $ip    = trim($parts[0]);
        }

        return trim($ip);
    }

    private function maskOrderForPublic(array $order): array
    {
        $masked = $order;

        $masked['email']        = $this->maskEmail($order['email'] ?? '');
        $masked['billing']      = $this->maskAddress($order['billing'] ?? []);
        $masked['shipping']     = $this->maskAddress($order['shipping'] ?? []);
        $masked['transactions'] = [];
        $masked['timeline']     = [];

        return $masked;
    }

    private function maskEmail(string $email): string
    {
        $email = trim($email);

        if ($email === '' || !str_contains($email, '@')) {
            return '***';
        }

        [$local, $domain] = explode('@', $email, 2);
        $local            = $local !== '' ? $local : '***';

        return substr($local, 0, 1) . '***@' . $domain;
    }

    /**
     * @param mixed $address
     */
    private function maskAddress($address): array
    {
        if (!\is_array($address)) {
            return [];
        }

        $masked = [];

        if (!empty($address['first_name'])) {
            $masked['first_name'] = $this->maskName((string) $address['first_name']);
        }

        if (!empty($address['last_name'])) {
            $masked['last_name'] = $this->maskName((string) $address['last_name']);
        }

        if (!empty($address['city'])) {
            $masked['city'] = (string) $address['city'];
        }

        if (!empty($address['region'])) {
            $masked['region'] = (string) $address['region'];
        }

        if (!empty($address['country'])) {
            $masked['country'] = (string) $address['country'];
        }

        if (!empty($address['postcode'])) {
            $masked['postcode'] = $this->maskPostcode((string) $address['postcode']);
        }

        if (!empty($address['phone'])) {
            $masked['phone'] = $this->maskPhone((string) $address['phone']);
        }

        return $masked;
    }

    private function maskName(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return mb_substr($value, 0, 1) . str_repeat('*', max(0, mb_strlen($value) - 1));
    }

    private function maskPostcode(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 2) {
            return str_repeat('*', strlen($value));
        }

        $suffix = substr($value, -2);

        return str_repeat('*', strlen($value) - 2) . $suffix;
    }

    private function maskPhone(string $value): string
    {
        $value = preg_replace('/\D+/', '', $value ?? '') ?? '';

        if ($value === '') {
            return '';
        }

        $tail = substr($value, -2);

        return str_repeat('*', max(0, strlen($value) - 2)) . $tail;
    }
}
