<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Service\Import;

\defined('_JEXEC') or die;

use Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter\HikashopAdapter;
use Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter\NativeAdapter;
use Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter\ShopifyAdapter;
use Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter\VirtuemartAdapter;
use Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter\WoocommerceAdapter;
use InvalidArgumentException;

/**
 * Factory for creating platform-specific import adapters.
 *
 * Supports auto-detection of platform from CSV headers.
 *
 * @since 0.3.0
 */
class PlatformAdapterFactory
{
    /**
     * Supported platform identifiers.
     *
     * @var array<int, string>
     */
    public const PLATFORMS = [
        'native',
        'shopify',
        'woocommerce',
        'virtuemart',
        'hikashop',
    ];

    /**
     * Default currency for adapters.
     *
     * @var string
     */
    private string $defaultCurrency;

    /**
     * Cached adapter instances.
     *
     * @var array<string, PlatformAdapterInterface>
     */
    private array $adapters = [];

    /**
     * Constructor.
     *
     * @param string $defaultCurrency Default currency code (e.g., 'EUR')
     *
     * @since 0.3.0
     */
    public function __construct(string $defaultCurrency = 'EUR')
    {
        $this->defaultCurrency = strtoupper($defaultCurrency);
    }

    /**
     * Get adapter for a specific platform.
     *
     * @param string $platform Platform identifier
     *
     * @return PlatformAdapterInterface
     *
     * @throws InvalidArgumentException If platform is not supported
     *
     * @since 0.3.0
     */
    public function getAdapter(string $platform): PlatformAdapterInterface
    {
        $platform = strtolower($platform);

        if (!$this->isSupported($platform)) {
            throw new InvalidArgumentException(
                sprintf('Unsupported platform: %s. Supported platforms: %s', $platform, implode(', ', self::PLATFORMS))
            );
        }

        if (!isset($this->adapters[$platform])) {
            $this->adapters[$platform] = $this->createAdapter($platform);
        }

        return $this->adapters[$platform];
    }

    /**
     * Check if a platform is supported.
     *
     * @param string $platform Platform identifier
     *
     * @return bool
     *
     * @since 0.3.0
     */
    public function isSupported(string $platform): bool
    {
        return \in_array(strtolower($platform), self::PLATFORMS, true);
    }

    /**
     * Auto-detect platform from CSV headers.
     *
     * @param array<int, string> $headers CSV header row
     *
     * @return string|null Platform identifier or null if not detected
     *
     * @since 0.3.0
     */
    public function detectPlatform(array $headers): ?string
    {
        // Normalize headers for comparison (lowercase, trimmed)
        $normalizedHeaders = array_map(function (string $header): string {
            return strtolower(trim($header));
        }, $headers);

        // Check each platform's signature headers
        // Order matters - check most specific first
        $platformPriority = ['native', 'shopify', 'woocommerce', 'virtuemart', 'hikashop'];

        foreach ($platformPriority as $platform) {
            $adapter = $this->getAdapter($platform);
            $signatureHeaders = array_map('strtolower', $adapter->getSignatureHeaders());

            // All signature headers must be present
            $allPresent = true;

            foreach ($signatureHeaders as $required) {
                if (!\in_array($required, $normalizedHeaders, true)) {
                    $allPresent = false;
                    break;
                }
            }

            if ($allPresent) {
                return $platform;
            }
        }

        return null;
    }

    /**
     * Get all supported platforms with display names.
     *
     * @return array<string, string> Platform identifier => display name
     *
     * @since 0.3.0
     */
    public function getAllPlatforms(): array
    {
        $platforms = [];

        foreach (self::PLATFORMS as $platform) {
            $adapter = $this->getAdapter($platform);
            $platforms[$platform] = $adapter->getDisplayName();
        }

        return $platforms;
    }

    /**
     * Create a new adapter instance.
     *
     * @param string $platform Platform identifier
     *
     * @return PlatformAdapterInterface
     *
     * @since 0.3.0
     */
    private function createAdapter(string $platform): PlatformAdapterInterface
    {
        $adapter = match ($platform) {
            'native'      => new NativeAdapter(),
            'shopify'     => new ShopifyAdapter(),
            'woocommerce' => new WoocommerceAdapter(),
            'virtuemart'  => new VirtuemartAdapter(),
            'hikashop'    => new HikashopAdapter(),
            default       => throw new InvalidArgumentException("Unknown platform: {$platform}"),
        };

        $adapter->setDefaultCurrency($this->defaultCurrency);

        return $adapter;
    }
}
