<?php

namespace Joomla\Component\Nxpeasycart\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Resolves template-specific styling tokens for the storefront.
 */
class TemplateAdapter
{
    /**
     * Resolve adapter tokens for the active template.
     *
     * @return array<string, mixed>
     */
    public static function resolve(): array
    {
        $app      = Factory::getApplication();
        $template = $app->getTemplate(true);
        $name     = strtolower((string) ($template->template ?? ''));

        $defaults = [
            'container_class'        => 'container',
            'button_primary_extra'   => '',
            'button_secondary_extra' => '',
            'section_link_class'     => '',
            'category_tile_class'    => '',
            'css_vars'               => [
                '--nxp-color-primary'          => '#0d6efd',
                '--nxp-color-primary-contrast' => '#ffffff',
                '--nxp-color-text'             => '#1f2933',
                '--nxp-color-muted'            => '#4b5563',
                '--nxp-color-border'           => 'rgba(15, 23, 42, 0.12)',
                '--nxp-color-surface'          => '#ffffff',
                '--nxp-color-surface-alt'      => '#f8fafc',
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
                    '--nxp-color-primary'          => 'var(--cassiopeia-color-primary, #0d6efd)',
                    '--nxp-color-primary-contrast' => 'var(--cassiopeia-color-text-inverse, #ffffff)',
                    '--nxp-color-text'             => 'var(--cassiopeia-color-text, #1f2933)',
                    '--nxp-color-muted'            => 'var(--cassiopeia-color-muted, #4b5563)',
                    '--nxp-color-border'           => 'var(--cassiopeia-border-color, rgba(15, 23, 42, 0.12))',
                    '--nxp-color-surface'          => 'var(--cassiopeia-color-background, #ffffff)',
                    '--nxp-color-surface-alt'      => 'var(--cassiopeia-color-card, #f8fafc)',
                ],
            ],
            'helixultimate' => [
                'container_class'        => 'container',
                'button_primary_extra'   => 'btn btn-primary',
                'button_secondary_extra' => 'btn btn-outline-primary',
                'section_link_class'     => 'btn btn-link p-0 fw-semibold',
                'category_tile_class'    => 'card border-0 shadow-sm',
                'css_vars'               => [
                    '--nxp-color-primary'          => 'var(--sp-primary, #0d6efd)',
                    '--nxp-color-primary-contrast' => '#ffffff',
                    '--nxp-color-text'             => 'var(--sp-body-color, #1f2933)',
                    '--nxp-color-muted'            => 'rgba(0, 0, 0, 0.6)',
                    '--nxp-color-border'           => 'var(--sp-border-color, rgba(15, 23, 42, 0.12))',
                    '--nxp-color-surface'          => '#ffffff',
                    '--nxp-color-surface-alt'      => 'var(--sp-section-bg, #f8fafc)',
                ],
            ],
            'shaper_helixultimate' => [
                'container_class'        => 'container',
                'button_primary_extra'   => 'btn btn-primary',
                'button_secondary_extra' => 'btn btn-outline-primary',
                'section_link_class'     => 'btn btn-link p-0 fw-semibold',
                'category_tile_class'    => 'card border-0 shadow-sm',
                'css_vars'               => [
                    '--nxp-color-primary'          => 'var(--sp-primary, #0d6efd)',
                    '--nxp-color-primary-contrast' => '#ffffff',
                    '--nxp-color-text'             => 'var(--sp-body-color, #1f2933)',
                    '--nxp-color-muted'            => 'rgba(0, 0, 0, 0.6)',
                    '--nxp-color-border'           => 'var(--sp-border-color, rgba(15, 23, 42, 0.12))',
                    '--nxp-color-surface'          => '#ffffff',
                    '--nxp-color-surface-alt'      => 'var(--sp-section-bg, #f8fafc)',
                ],
            ],
            'ja_purity_iv' => [
                'container_class'        => 'container',
                'button_primary_extra'   => 'btn btn-primary btn-lg',
                'button_secondary_extra' => 'btn btn-outline-primary btn-lg',
                'section_link_class'     => 'btn btn-link px-0 fw-semibold',
                'category_tile_class'    => 'card border-0 shadow-sm',
                'css_vars'               => [
                    '--nxp-color-primary'          => 'var(--t4-primary, #0d6efd)',
                    '--nxp-color-primary-contrast' => '#ffffff',
                    '--nxp-color-text'             => 'var(--t4-body-color, #212529)',
                    '--nxp-color-muted'            => 'var(--t4-secondary, #6c757d)',
                    '--nxp-color-border'           => 'var(--t4-border-color, rgba(0, 0, 0, 0.1))',
                    '--nxp-color-surface'          => 'var(--t4-body-bg, #ffffff)',
                    '--nxp-color-surface-alt'      => 'var(--t4-gray-100, #f8f9fa)',
                ],
            ],
        ];

        if (isset($map[$name])) {
            return array_replace_recursive($defaults, $map[$name]);
        }

        if (str_contains($name, 'helixultimate')) {
            return array_replace_recursive($defaults, $map['helixultimate']);
        }

        return $defaults;
    }
}
