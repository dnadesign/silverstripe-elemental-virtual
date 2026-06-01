<?php

namespace DNADesign\ElementalVirtual\Forms;

use Override;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;

/**
 * @package elemental
 */
class ElementalGridFieldDeleteAction extends GridFieldDeleteAction
{

    #[Override]
    public function getColumnContent($gridField, $record, $columnName)
    {
        if (!$record->canDelete()) {
            return;
        }

        if ($record->VirtualClones()->count() > 0) {
            return false;
        }

        return parent::getColumnContent($gridField, $record, $columnName);
    }
}
