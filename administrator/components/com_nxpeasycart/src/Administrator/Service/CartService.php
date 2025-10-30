<?php

namespace Nxp\EasyCart\Admin\Administrator\Service;

\defined('_JEXEC') or die;

use JsonException;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Nxp\EasyCart\Admin\Administrator\Helper\ConfigHelper;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * Persisted cart storage service.
 */
class CartService
{
    /**
     * @var DatabaseInterface
     */
    private DatabaseInterface $db;

    /**
     * CartService constructor.
     *
     * @param DatabaseInterface $db Database connector
     */
    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Load a cart by identifier.
     */
    public function load(string $cartId): ?array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_carts'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $cartId, ParameterType::STRING);

        $this->db->setQuery($query);

        $row = $this->db->loadObject();

        if (!$row) {
            return null;
        }

        return $this->mapCartRow($row);
    }

    /**
     * Load a cart bound to the given session identifier.
     */
    public function loadBySession(string $sessionId): ?array
    {
        $sessionId = trim($sessionId);

        if ($sessionId === '') {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_carts'))
            ->where($this->db->quoteName('session_id') . ' = :session')
            ->bind(':session', $sessionId, ParameterType::STRING);

        $this->db->setQuery($query);

        $row = $this->db->loadObject();

        if (!$row) {
            return null;
        }

        return $this->mapCartRow($row);
    }

    /**
     * Persist cart payload and return the stored representation.
     */
    public function persist(array $cart): array
    {
        $id = isset($cart['id']) ? (string) $cart['id'] : '';
        $id = trim($id);

        if ($id === '') {
            $id = Uuid::uuid4()->toString();
        }

        $data = $this->normaliseCartData((array) ($cart['data'] ?? []));
        $json = $this->encodeJson($data);

        $userId = $this->toNullableInt($cart['user_id'] ?? null);
        $sessionId = $this->prepareSessionId($cart['session_id'] ?? null);

        if ($sessionId !== null) {
            $this->releaseSession($sessionId, $id);
        }

        $object = (object) [
            'id' => $id,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'data' => $json,
        ];

        if ($this->exists($id)) {
            $this->db->updateObject('#__nxp_easycart_carts', $object, 'id');
        } else {
            $this->db->insertObject('#__nxp_easycart_carts', $object);
        }

        $record = $this->load($id);

        if (!$record) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_CART_NOT_FOUND'));
        }

        return $record;
    }

    /**
     * Delete a cart record.
     */
    public function delete(string $cartId): void
    {
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__nxp_easycart_carts'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $cartId, ParameterType::STRING);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Ensure a cart exists before attempting to use it.
     */
    private function exists(string $cartId): bool
    {
        $query = $this->db->getQuery(true)
            ->select('1')
            ->from($this->db->quoteName('#__nxp_easycart_carts'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $cartId, ParameterType::STRING);

        $this->db->setQuery($query, 0, 1);

        return (bool) $this->db->loadResult();
    }

    /**
     * Normalise the cart payload ensuring base currency compliance.
     */
    private function normaliseCartData(array $data): array
    {
        $baseCurrency = ConfigHelper::getBaseCurrency();
        $currency = strtoupper((string) ($data['currency'] ?? ''));

        if ($currency === '') {
            $data['currency'] = $baseCurrency;
        } elseif ($currency !== $baseCurrency) {
            throw new RuntimeException(Text::sprintf('COM_NXPEASYCART_ERROR_CART_CURRENCY_MISMATCH', $baseCurrency));
        }

        if (isset($data['items'])) {
            if (!\is_array($data['items'])) {
                throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_CART_ITEMS_INVALID'));
            }

            foreach ($data['items'] as $index => $item) {
                if (!\is_array($item)) {
                    throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_CART_ITEMS_INVALID'));
                }

                $itemCurrency = strtoupper((string) ($item['currency'] ?? ''));

                if ($itemCurrency !== '' && $itemCurrency !== $baseCurrency) {
                    throw new RuntimeException(Text::sprintf('COM_NXPEASYCART_ERROR_CART_CURRENCY_MISMATCH', $baseCurrency));
                }

                $data['items'][$index]['currency'] = $itemCurrency ?: $baseCurrency;
                $data['items'][$index]['qty'] = $this->toPositiveInt($item['qty'] ?? 1);
            }
        }

        return $data;
    }

    /**
     * Convert a stored cart row into a domain array.
     */
    private function mapCartRow(object $row): array
    {
        $data = $this->decodeJson($row->data ?? '{}');
        $data['currency'] ??= ConfigHelper::getBaseCurrency();

        return [
            'id' => (string) $row->id,
            'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
            'session_id' => $row->session_id !== null ? (string) $row->session_id : null,
            'data' => $data,
            'updated' => (string) $row->updated,
        ];
    }

    /**
     * Encode structured data as JSON.
     */
    private function encodeJson($payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_CART_SERIALISE_FAILED'), 0, $exception);
        }
    }

    /**
     * Decode JSON payload into a PHP array.
     */
    private function decodeJson(?string $payload): array
    {
        if ($payload === null || $payload === '') {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_CART_DESERIALISE_FAILED'), 0, $exception);
        }

        return (array) $decoded;
    }

    /**
     * Cast value to nullable integer.
     */
    private function toNullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * Sanitise positive integer quantities.
     */
    private function toPositiveInt($value): int
    {
        $int = (int) $value;

        return $int > 0 ? $int : 1;
    }

    /**
     * Prepare a session identifier for storage.
     */
    private function prepareSessionId($sessionId): ?string
    {
        if ($sessionId === null) {
            return null;
        }

        $sessionId = trim((string) $sessionId);

        if ($sessionId === '') {
            return null;
        }

        return mb_substr($sessionId, 0, 128);
    }

    /**
     * Release any carts currently linked to the provided session.
     */
    private function releaseSession(string $sessionId, string $cartId): void
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_carts'))
            ->set($this->db->quoteName('session_id') . ' = NULL')
            ->where($this->db->quoteName('session_id') . ' = :session')
            ->where($this->db->quoteName('id') . ' != :current')
            ->bind(':session', $sessionId, ParameterType::STRING)
            ->bind(':current', $cartId, ParameterType::STRING);

        $this->db->setQuery($query);
        $this->db->execute();
    }
}
