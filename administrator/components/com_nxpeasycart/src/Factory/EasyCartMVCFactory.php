<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Factory;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Factory\MVCFactory;

/**
 * Custom MVC factory aware of the admin/site namespace split.
 *
 * @since 0.1.5
 */
class EasyCartMVCFactory extends MVCFactory
{
    /**
     * Resolve the fully qualified class name for a MVC artifact.
     *
     * @param string $suffix Class suffix built by the parent factory.
     * @param string $prefix Application prefix (Administrator, Site, Api).
     *
     * @return string|null
     *
     * @since 0.1.5
     */
    protected function getClassName(string $suffix, string $prefix)
    {
        $prefix = ucfirst($prefix);

        if ($prefix === 'Site') {
            $className = 'Joomla\\Component\\Nxpeasycart\\Site\\' . $suffix;

            if (class_exists($className)) {
                return $className;
            }
        }

        if ($prefix === 'Administrator' || $prefix === 'Api') {
            $className = 'Joomla\\Component\\Nxpeasycart\\Administrator\\' . $suffix;

            if (class_exists($className)) {
                return $className;
            }
        }

        return parent::getClassName($suffix, $prefix);
    }
}
