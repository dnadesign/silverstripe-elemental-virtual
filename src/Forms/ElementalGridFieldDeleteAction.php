<?php

namespace DNADesign\ElementalVirtual\Forms;

use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridField_FormAction;

/**
 * @package elemental
 */
class ElementalGridFieldDeleteAction extends GridFieldDeleteAction
{

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
