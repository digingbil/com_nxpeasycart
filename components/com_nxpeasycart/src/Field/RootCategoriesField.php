<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Site\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Multi-select field listing root categories.
 *
 * @since 0.1.5
 */
class RootCategoriesField extends ListField
{
    /**
     * Field type.
     *
     * @var string
     *
     * @since 0.1.5
     */
    protected $type = 'RootCategories';

    /**
     * {@inheritdoc}
     *
     * @since 0.1.5
     */
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
                $db->quoteName('sort'),
            ])
            ->from($db->quoteName('#__nxp_easycart_categories'))
            ->where(
                '(' . $db->quoteName('parent_id') . ' IS NULL'
                . ' OR ' . $db->quoteName('parent_id') . ' = 0)'
            );

        $query->order($db->quoteName('sort') . ' ASC')
            ->order($db->quoteName('title') . ' ASC');

        $db->setQuery($query);

        $rows = $db->loadObjectList() ?: [];

        if (empty($rows)) {
            $options[] = (object) [
                'value'    => '',
                'text'     => Text::_('COM_NXPEASYCART_FIELD_ROOT_CATEGORIES_EMPTY'),
                'disabled' => true,
            ];

            return $options;
        }

        foreach ($rows as $row) {
            $options[] = (object) [
                'value' => (int) $row->id,
                'text'  => (string) $row->title,
            ];
        }

        return $options;
    }
}
