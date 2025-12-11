<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Hierarchical category picker for menu items.
 *
 * @since 0.1.5
 */
class CategorySelectField extends ListField
{
    protected $type = 'CategorySelect';

    protected function getOptions(): array
    {
        $options = parent::getOptions();

        $container = Factory::getContainer();
        /** @var DatabaseInterface $db */
        $db = $container->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('parent_id'),
                $db->quoteName('sort'),
            ])
            ->from($db->quoteName('#__nxp_easycart_categories'))
            ->order($db->quoteName('sort') . ' ASC')
            ->order($db->quoteName('title') . ' ASC');

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $tree = [];
        foreach ($rows as $row) {
            $parent = $row->parent_id ? (int) $row->parent_id : 0;
            $tree[$parent][] = $row;
        }

        $options[] = (object) [
            'value' => '',
            'text'  => Text::_('COM_NXPEASYCART_CATEGORY_ALL'),
        ];

        $walk = function (int $parent, int $depth) use (&$walk, &$options, $tree): void {
            if (empty($tree[$parent])) {
                return;
            }

            foreach ($tree[$parent] as $row) {
                $prefix = str_repeat('- ', max(0, $depth));

                $options[] = (object) [
                    'value' => (int) $row->id,
                    'text'  => $prefix . (string) $row->title,
                ];

                $walk((int) $row->id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $options;
    }
}
