<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;

$items      = $this->items ?? [];
$pagination = $this->pagination ?? null;
$state      = $this->state ?? null;
$search     = $state ? (string) $state->get('filter.search') : '';
?>

<form action="<?php echo htmlspecialchars(Route::_('index.php?option=com_nxpeasycart&view=categories&layout=modal&tmpl=component'), ENT_QUOTES, 'UTF-8'); ?>" method="get" id="adminForm">
    <div class="subhead">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 m-0"><?php echo Text::_('COM_NXPEASYCART_FIELD_CATEGORY_SELECT'); ?></h1>
            <div class="input-group" style="max-width: 320px;">
                <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo Text::_('JSEARCH_FILTER'); ?>" />
                <button class="btn btn-secondary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            </div>
        </div>
    </div>

    <table class="table table-striped">
        <thead>
            <tr>
                <th><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
                <th class="text-center" style="width: 80px;">ID</th>
                <th class="text-center" style="width: 120px;"><?php echo Text::_('JSELECT'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)) : ?>
                <tr>
                    <td colspan="3" class="text-center text-muted"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($items as $item) : ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($item->title ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                        <td class="text-center">
                            <?php echo (int) ($item->id ?? 0); ?>
                        </td>
                        <td class="text-center">
                            <button
                                type="button"
                                class="btn btn-primary btn-sm js-nxp-category-select"
                                data-id="<?php echo (int) ($item->id ?? 0); ?>"
                                data-title="<?php echo htmlspecialchars($item->title ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <?php echo Text::_('JSELECT'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($pagination) : ?>
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <?php echo $pagination->getListFooter(); ?>
            </div>
            <div>
                <?php echo $pagination->getLimitBox(); ?>
            </div>
        </div>
    <?php endif; ?>
</form>

<script>
(function() {
    const buttons = document.querySelectorAll('.js-nxp-category-select');

    const postSelection = (id, title) => {
        const payload = { messageType: 'joomla:content-select', id: String(id), title: title };
        window.parent.postMessage(payload, window.location.origin);
        if (window.parent.Joomla && typeof window.parent.Joomla.Modal !== 'undefined') {
            try {
                const modals = window.parent.document.querySelectorAll('.joomla-dialog-content-select-field');
                modals.forEach((modal) => {
                    modal.dispatchEvent(new CustomEvent('joomla-dialog:close'));
                });
            } catch (err) {}
        }
    };

    buttons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const id = button.dataset.id || '';
            const title = button.dataset.title || '';
            postSelection(id, title);
        });
    });
})();
</script>
