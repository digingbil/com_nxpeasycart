<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * Simple key/value settings storage for component configuration.
 */
class SettingsService
{
    /**
     * @var DatabaseInterface
     */
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Fetch a single setting by key.
     */
    public function get(string $key, $default = null)
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('value'))
            ->from($this->db->quoteName('#__nxp_easycart_settings'))
            ->where($this->db->quoteName('key') . ' = :key')
            ->bind(':key', $key, ParameterType::STRING);

        $this->db->setQuery($query);
        $value = $this->db->loadResult();

        if ($value === null) {
            return $default;
        }

        return $this->decodeValue($value) ?? $default;
    }

    /**
     * Persist a setting value.
     */
    public function set(string $key, $value): void
    {
        $payload = $this->encodeValue($value);

        $object = (object) [
            'key'   => $key,
            'value' => $payload,
        ];

        try {
            if ($this->exists($key)) {
                $this->db->updateObject('#__nxp_easycart_settings', $object, 'key');
            } else {
                $this->db->insertObject('#__nxp_easycart_settings', $object);
            }
        } catch (\Throwable $exception) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_SETTINGS_SAVE_FAILED'), 0, $exception);
        }
    }

    /**
     * Return all settings as associative array.
     */
    public function all(): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('key'),
                $this->db->quoteName('value'),
            ])
            ->from($this->db->quoteName('#__nxp_easycart_settings'))
            ->order($this->db->quoteName('key') . ' ASC');

        $this->db->setQuery($query);

        $rows     = $this->db->loadObjectList() ?: [];
        $settings = [];

        foreach ($rows as $row) {
            $settings[$row->key] = $this->decodeValue($row->value);
        }

        return $settings;
    }

    private function exists(string $key): bool
    {
        $query = $this->db->getQuery(true)
            ->select('1')
            ->from($this->db->quoteName('#__nxp_easycart_settings'))
            ->where($this->db->quoteName('key') . ' = :key')
            ->bind(':key', $key, ParameterType::STRING);

        $this->db->setQuery($query, 0, 1);

        return (bool) $this->db->loadResult();
    }

    private function encodeValue($value): string
    {
        if ($value === null || \is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function decodeValue(?string $value)
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        if ($this->looksLikeJson($trimmed)) {
            $decoded = json_decode($trimmed, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    private function looksLikeJson(string $value): bool
    {
        $first = $value[0];
        $last  = $value[strlen($value) - 1];

        return ($first === '{' && $last === '}') || ($first === '[' && $last === ']');
    }
}
