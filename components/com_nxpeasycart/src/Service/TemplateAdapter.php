<?php

namespace Joomla\Component\Nxpeasycart\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

/**
 * Resolves template-specific styling tokens for the storefront.
 *
 * @since 0.1.5
 */
class TemplateAdapter
{
    /**
     * Resolve adapter tokens for the active template.
     *
     * @return array<string, mixed>
     *
     * @since 0.1.5
     */
    public static function resolve(): array
    {
        return self::applyUserOverrides(self::resolveWithoutOverrides());
    }

    /**
     * Resolve adapter tokens WITHOUT user overrides (for showing defaults in admin).
     *
     * @return array<string, mixed>
     *
     * @since 0.1.5
     */
    public static function resolveWithoutOverrides(): array
    {
        static $cache = [];

        $app      = Factory::getApplication();
        $template = $app->getTemplate(true);
        $name     = strtolower((string) ($template->template ?? ''));
        $cacheKey = $name . ':' . md5(json_encode($template->params ?? []));

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $defaults = [
            'container_class'        => 'container',
            'button_primary_extra'   => '',
            'button_secondary_extra' => '',
            'section_link_class'     => '',
            'category_tile_class'    => '',
            'css_vars'               => [
                '--nxp-ec-color-primary'          => '#4f6d7a',
                '--nxp-ec-color-primary-contrast' => '#ffffff',
                '--nxp-ec-color-primary-focus'    => 'rgba(79, 109, 122, 0.2)',
                '--nxp-ec-color-text'             => '#1f2933',
                '--nxp-ec-color-muted'            => '#6b7280',
                '--nxp-ec-color-border'           => 'rgba(15, 23, 42, 0.1)',
                '--nxp-ec-color-surface'          => '#ffffff',
                '--nxp-ec-color-surface-alt'      => '#f5f7fa',
                '--nxp-ec-color-success'          => '#027a48',
                '--nxp-ec-color-error'            => '#b42318',
                '--nxp-ec-radius-md'              => '0.75rem',
                '--nxp-ec-radius-lg'              => '1rem',
                '--nxp-ec-radius-pill'            => '999px',
                '--nxp-ec-shadow-card'            => '0 6px 16px rgba(15, 23, 42, 0.08)',
                '--nxp-ec-shadow-hover'           => '0 12px 28px rgba(15, 23, 42, 0.14)',
            ],
        ];

        $map = [
            'cassiopeia' => [
                'container_class'        => 'container component-content',
                'button_primary_extra'   => 'btn btn-primary btn-lg',
                'button_secondary_extra' => 'btn btn-outline-primary btn-lg',
                'section_link_class'     => 'btn btn-link p-0 fw-semibold',
                'category_tile_class'    => 'card border-0',
                'css_vars'               => [
                    '--nxp-ec-color-primary'          => 'var(--cassiopeia-color-primary, #0d6efd)',
                    '--nxp-ec-color-primary-contrast' => 'var(--cassiopeia-color-text-inverse, #ffffff)',
                    '--nxp-ec-color-primary-focus'    => 'rgba(13, 110, 253, 0.25)',
                    '--nxp-ec-color-text'             => 'var(--cassiopeia-color-text, #1f2933)',
                    '--nxp-ec-color-muted'            => 'var(--cassiopeia-color-muted, #4b5563)',
                    '--nxp-ec-color-border'           => 'var(--cassiopeia-border-color, rgba(15, 23, 42, 0.12))',
                    '--nxp-ec-color-surface'          => 'var(--cassiopeia-color-background, #ffffff)',
                    '--nxp-ec-color-surface-alt'      => 'var(--cassiopeia-color-card, #f8fafc)',
                    '--nxp-ec-color-success'          => '#027a48',
                    '--nxp-ec-color-error'            => '#b42318',
                ],
            ],
            'ja_purity_iv' => [
                'container_class'        => 'container',
                'button_primary_extra'   => 'btn btn-primary btn-lg',
                'button_secondary_extra' => 'btn btn-outline-primary btn-lg',
                'section_link_class'     => 'btn btn-link px-0 fw-semibold',
                'category_tile_class'    => 'card border-0 shadow-sm',
                'css_vars'               => [
                    '--nxp-ec-color-primary'          => 'var(--t4-primary, #0d6efd)',
                    '--nxp-ec-color-primary-contrast' => '#ffffff',
                    '--nxp-ec-color-primary-focus'    => 'rgba(13, 110, 253, 0.25)',
                    '--nxp-ec-color-text'             => 'var(--t4-body-color, #212529)',
                    '--nxp-ec-color-muted'            => 'var(--t4-secondary, #6c757d)',
                    '--nxp-ec-color-border'           => 'var(--t4-border-color, rgba(0, 0, 0, 0.1))',
                    '--nxp-ec-color-surface'          => 'var(--t4-body-bg, #ffffff)',
                    '--nxp-ec-color-surface-alt'      => 'var(--t4-gray-100, #f8f9fa)',
                    '--nxp-ec-color-success'          => '#027a48',
                    '--nxp-ec-color-error'            => '#b42318',
                ],
            ],
        ];

        if (isset($map[$name])) {
            return array_replace_recursive($defaults, $map[$name]);
        }

        if (str_contains($name, 'helixultimate')) {
            if ($template->params instanceof Registry) {
                $params = $template->params;
            } elseif (is_array($template->params ?? null)) {
                $params = new Registry($template->params);
            } else {
                $params = new Registry();
            }

            $cache[$cacheKey] = self::resolveHelix($defaults, $params);
            return $cache[$cacheKey];
        }

        return $cache[$cacheKey] = $defaults;
    }

    /**
     * Adapt storefront tokens for Helix Ultimate templates based on the active preset.
     *
     * @since 0.1.5
     */
    private static function resolveHelix(array $defaults, Registry $params): array
    {
        [, $palette] = self::extractHelixPalette($params);

        $primary = self::normalizeHex($palette['link_color'] ?? '#4f6d7a', '#4f6d7a');
        $text    = self::normalizeHex($palette['text_color'] ?? '#1f2933', '#1f2933');
        $surface = self::normalizeHex($palette['bg_color'] ?? '#ffffff', '#ffffff');

        $mutedBase   = self::normalizeHex($palette['topbar_text_color'] ?? '', $text);
        $muted       = self::mixColor($mutedBase, '#ffffff', 0.4);
        $border      = self::mixColor($text, '#ffffff', 0.82);
        $surfaceAlt  = self::normalizeHex(
            $palette['menu_dropdown_bg_color'] ?? '',
            self::mixColor($surface, '#f5f7fa', 0.35)
        );
        $contrast    = self::getContrastingColor($primary);
        $focusColor  = self::hexToRgba($primary, 0.2);

        return array_replace_recursive($defaults, [
            'container_class'        => 'container component-content',
            'button_primary_extra'   => 'btn btn-primary btn-lg',
            'button_secondary_extra' => 'btn btn-outline-primary btn-lg',
            'section_link_class'     => 'btn btn-link px-0 fw-semibold text-decoration-none',
            'category_tile_class'    => 'card border-0 shadow-sm h-100 text-center p-4',
            'css_vars'               => [
                '--nxp-ec-color-primary'          => $primary,
                '--nxp-ec-color-primary-contrast' => $contrast,
                '--nxp-ec-color-primary-focus'    => $focusColor,
                '--nxp-ec-color-text'             => $text,
                '--nxp-ec-color-muted'            => $muted,
                '--nxp-ec-color-border'           => $border,
                '--nxp-ec-color-surface'          => $surface,
                '--nxp-ec-color-surface-alt'      => $surfaceAlt,
                '--nxp-ec-color-success'          => '#027a48',
                '--nxp-ec-color-error'            => '#b42318',
            ],
        ]);
    }

    /**
     * Extract the active Helix preset palette.
     *
     * @return array{0:string,1:array<string,string>}
     *
     * @since 0.1.5
     */
    private static function extractHelixPalette(Registry $params): array
    {
        $presetKey = 'preset1';

        $presetRaw = $params->get('preset');

        if (is_string($presetRaw) && $presetRaw !== '') {
            $decoded = json_decode($presetRaw, true);

            if (is_array($decoded) && !empty($decoded['preset'])) {
                $presetKey = (string) $decoded['preset'];
            }
        }

        $palette = [];

        $presetsRaw = $params->get('presets-data');

        if (is_string($presetsRaw) && $presetsRaw !== '') {
            $decoded = json_decode($presetsRaw, true);

            if (
                is_array($decoded)
                && isset($decoded[$presetKey]['data'])
                && is_array($decoded[$presetKey]['data'])
            ) {
                $palette = $decoded[$presetKey]['data'];
            }
        }

        return [$presetKey, $palette];
    }

    private static function normalizeHex(?string $color, string $fallback): string
    {
        if (!is_string($color) || $color === '') {
            return strtolower($fallback);
        }

        $color = trim($color);

        if ($color === '') {
            return strtolower($fallback);
        }

        if ($color[0] !== '#') {
            $color = '#' . $color;
        }

        if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
            return strtolower($fallback);
        }

        if (strlen($color) === 4) {
            $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
        }

        return strtolower($color);
    }

    private static function mixColor(string $base, string $blend, float $ratio): string
    {
        $ratio = max(0.0, min(1.0, $ratio));
        [$r1, $g1, $b1] = self::hexToRgb($base);
        [$r2, $g2, $b2] = self::hexToRgb($blend);

        $r = (int) round($r1 * (1 - $ratio) + $r2 * $ratio);
        $g = (int) round($g1 * (1 - $ratio) + $g2 * $ratio);
        $b = (int) round($b1 * (1 - $ratio) + $b2 * $ratio);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private static function hexToRgb(string $hex): array
    {
        $hex = self::normalizeHex($hex, '#000000');

        return [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ];
    }

    private static function hexToRgba(string $hex, float $alpha): string
    {
        [$r, $g, $b] = self::hexToRgb($hex);

        return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, $alpha);
    }

    private static function getContrastingColor(string $hex, string $dark = '#111827', string $light = '#ffffff'): string
    {
        [$r, $g, $b] = self::hexToRgb($hex);

        $luminance = (0.2126 * ($r / 255)) + (0.7152 * ($g / 255)) + (0.0722 * ($b / 255));

        return $luminance > 0.6 ? $dark : $light;
    }

    /**
     * Apply user color overrides from settings.
     *
     * @since 0.1.5
     */
    private static function applyUserOverrides(array $resolved): array
    {
        try {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName(['key', 'value']))
                ->from($db->quoteName('#__nxp_easycart_settings'))
                ->where($db->quoteName('key') . ' LIKE ' . $db->quote('visual.%'));

            $db->setQuery($query);
            $rows = $db->loadObjectList();

            $overrides = [];
            foreach ($rows as $row) {
                if ($row->value !== '' && $row->value !== null) {
                    $overrides[$row->key] = $row->value;
                }
            }

            if (!empty($overrides['visual.primary_color'])) {
                $resolved['css_vars']['--nxp-ec-color-primary'] = $overrides['visual.primary_color'];
            }
            if (!empty($overrides['visual.text_color'])) {
                $resolved['css_vars']['--nxp-ec-color-text'] = $overrides['visual.text_color'];
            }
            if (!empty($overrides['visual.surface_color'])) {
                $resolved['css_vars']['--nxp-ec-color-surface'] = $overrides['visual.surface_color'];
            }
            if (!empty($overrides['visual.border_color'])) {
                $resolved['css_vars']['--nxp-ec-color-border'] = $overrides['visual.border_color'];
            }
            if (!empty($overrides['visual.muted_color'])) {
                $resolved['css_vars']['--nxp-ec-color-muted'] = $overrides['visual.muted_color'];
            }
        } catch (\Throwable $exception) {
            // Silently ignore database errors - fall back to template defaults
        }

        return $resolved;
    }
}
