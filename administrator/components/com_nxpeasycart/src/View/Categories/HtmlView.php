<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\View\Categories;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    /** @var array<int, array<string, mixed>> */
    protected array $items = [];

    protected $pagination;

    protected $state;

    public function display($tpl = null): void
    {
        $model = $this->getModel();

        $this->items      = $model ? $model->getItems() : [];
        $this->pagination = $model ? $model->getPagination() : null;
        $this->state      = $model ? $model->getState() : null;

        if ($tpl === null) {
            $this->setLayout('modal');
        }

        $this->document->setTitle(Text::_('COM_NXPEASYCART_FIELD_CATEGORY_SELECT'));

        parent::display($tpl);
    }
}
