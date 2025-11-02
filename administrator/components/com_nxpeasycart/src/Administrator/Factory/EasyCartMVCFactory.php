<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Factory;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Factory\MVCFactory;

/**
 * Custom MVC factory aware of the admin/site namespace split.
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
