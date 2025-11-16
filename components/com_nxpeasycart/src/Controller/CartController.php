<?php

namespace Joomla\Component\Nxpeasycart\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Session\Session;
use Joomla\Component\Nxpeasycart\Administrator\Service\CartService;
use Joomla\Component\Nxpeasycart\Site\Service\CartPresentationService;
use Joomla\Component\Nxpeasycart\Site\Service\CartSessionService;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * AJAX controller handling cart mutations on the storefront.
 */
class CartController extends BaseController
{
    /**
     * Append a product or variant to the active cart session.
     *
     * @return void
     *
     * @throws \Throwable When persistence fails mid-transaction.
     */
    public function add(): void
    {
        $app = Factory::getApplication();

        if (!Session::checkToken('post')) {
            echo new JsonResponse(null, Text::_('JINVALID_TOKEN'), true);
            $app->close();
        }

        $input     = $app->input;
        $productId = $input->getInt('product_id');
        $variantId = $input->getInt('variant_id');
        $qty       = max(1, $input->getInt('qty', 1));

        if ($productId <= 0 && $variantId <= 0) {
            echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_CART_INVALID_REQUEST'), true);
            $app->close();
        }

        $container = Factory::getContainer();
        $db        = $container->get(DatabaseInterface::class);
        $carts     = $container->get(CartService::class);
        $session   = $container->get(CartSessionService::class);
        $presenter = $container->get(CartPresentationService::class);

        try {
            $variant = $this->loadVariant($db, $variantId);

            if (!$variant) {
                echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_CART_VARIANT_NOT_FOUND'), true);
                $app->close();
            }

            $variantProductId = (int) $variant->product_id;

            if ($productId > 0 && $productId !== $variantProductId) {
                echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_CART_INVALID_REQUEST'), true);
                $app->close();
            }

            $productId = $variantProductId;
            $product   = $this->loadProduct($db, $productId);

            if (!$product) {
                echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_CART_PRODUCT_NOT_FOUND'), true);
                $app->close();
            }

            $cart    = $session->current();
            $payload = $cart['data'] ?? [];
            $items   = \is_array($payload['items'] ?? null) ? $payload['items'] : [];

            $items = $this->upsertCartItem(
                $items,
                $product,
                $variant,
                $qty
            );

            $payload['items'] = $items;

            $joomlaSession = Factory::getApplication()->getSession();

            $persisted = $carts->persist([
                'id'         => $cart['id']         ?? null,
                'session_id' => $cart['session_id'] ?? $joomlaSession->getId(),
                'user_id'    => $cart['user_id']    ?? null,
                'data'       => $payload,
            ]);

            $hydrated = $presenter->hydrate($persisted);

            // Re-attach the latest cart payload for downstream observers.
            try {
                $session->attachToApplication();
            } catch (\Throwable $exception) {
                // Non-fatal.
            }

            echo new JsonResponse(
                ['cart' => $hydrated],
                Text::_('COM_NXPEASYCART_PRODUCT_ADDED_TO_CART')
            );
        } catch (\Throwable $exception) {
            Log::add($exception->getMessage(), Log::ERROR, 'com_nxpeasycart.cart');
            $message = (defined('JDEBUG') && JDEBUG)
                ? $exception->getMessage()
                : Text::_('COM_NXPEASYCART_ERROR_CART_GENERIC');

            echo new JsonResponse(null, $message, true);
        }

        $app->close();
    }

    /**
     * Return the current cart summary for the active visitor.
     *
     * @return void
     *
     * @throws \Throwable When cart retrieval fails.
     */
    public function summary(): void
    {
        $app       = Factory::getApplication();
        $container = Factory::getContainer();

        try {
            $session   = $container->get(CartSessionService::class);
            $presenter = $container->get(CartPresentationService::class);

            $cart = $presenter->hydrate($session->current());

            echo new JsonResponse(['cart' => $cart]);
        } catch (\Throwable $exception) {
            Log::add($exception->getMessage(), Log::ERROR, 'com_nxpeasycart.cart');
            $message = (defined('JDEBUG') && JDEBUG)
                ? $exception->getMessage()
                : Text::_('COM_NXPEASYCART_ERROR_CART_GENERIC');

            echo new JsonResponse(null, $message, true);
        }

        $app->close();
    }

    /**
     * Fetch a single variant row ensuring it is active.
     *
     * @param DatabaseInterface $db        Database connector
     * @param int               $variantId Variant identifier
     *
     * @return object|null Active variant row or null when missing
     */
    private function loadVariant(DatabaseInterface $db, int $variantId): ?object
    {
        if ($variantId <= 0) {
            return null;
        }

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__nxp_easycart_variants'))
            ->where($db->quoteName('id') . ' = :variantId')
            ->bind(':variantId', $variantId, ParameterType::INTEGER)
            ->where($db->quoteName('active') . ' = 1');

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /**
     * Fetch the associated product ensuring it is published.
     *
     * @param DatabaseInterface $db        Database connector
     * @param int               $productId Product identifier
     *
     * @return object|null Active product row or null when missing
     */
    private function loadProduct(DatabaseInterface $db, int $productId): ?object
    {
        if ($productId <= 0) {
            return null;
        }

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__nxp_easycart_products'))
            ->where($db->quoteName('id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER)
            ->where($db->quoteName('active') . ' = 1');

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /**
     * Merge or append the variant line into the existing cart items array.
     *
     * @param array<int, array<string, mixed>> $items
     * @param object                           $product
     * @param object                           $variant
     * @param int                              $qty
     *
     * @return array<int, array<string, mixed>>
     */
    private function upsertCartItem(array $items, object $product, object $variant, int $qty): array
    {
        $baseCurrency = strtoupper((string) ($variant->currency ?? 'USD'));
        $unitPrice    = (int) ($variant->price_cents ?? 0);
        $options      = [];

        if (!empty($variant->options)) {
            $decoded = json_decode((string) $variant->options, true);

            if (json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
                $options = array_values(
                    array_filter(
                        $decoded,
                        static fn ($option) => \is_array($option) && isset($option['name'], $option['value'])
                    )
                );
            }
        }

        foreach ($items as $index => $item) {
            if ((int) ($item['variant_id'] ?? 0) === (int) $variant->id) {
                $existingQty = isset($item['qty']) ? max(1, (int) $item['qty']) : 1;
                $items[$index]['qty']              = $existingQty + $qty;
                $items[$index]['unit_price_cents'] = $unitPrice;
                $items[$index]['currency']         = $baseCurrency;
                $items[$index]['title']            = $variant->sku ?? $product->title;
                $items[$index]['options']          = $options;

                return $items;
            }
        }

        $items[] = [
            'product_id'       => (int) $product->id,
            'variant_id'       => (int) $variant->id,
            'title'            => $variant->sku ?? $product->title,
            'qty'              => $qty,
            'unit_price_cents' => $unitPrice,
            'currency'         => $baseCurrency,
            'options'          => $options,
        ];

        return $items;
    }
}
