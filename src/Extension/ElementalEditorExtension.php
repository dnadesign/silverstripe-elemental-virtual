<?php

namespace DNADesign\ElementalVirtual\Extensions;

use DNADesign\Elemental\Models\BaseElement;
use DNADesign\ElementalVirtual\Forms\ElementalGridFieldAddExistingAutocompleter;
use DNADesign\ElementalVirtual\Forms\ElementalGridFieldDeleteAction;
use DNADesign\ElementalVirtual\Model\ElementVirtual;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\ORM\DataExtension;

class ElementalEditorExtension extends DataExtension
{
    public function updateField($gridField)
    {
        $searchList = BaseElement::get()->filter('AvailableGlobally', 1);

        $gridField->getConfig()
            ->removeComponentsByType(GridFieldDeleteAction::class)
            ->addComponent($autocomplete = new ElementalGridFieldAddExistingAutocompleter('toolbar-header-right'))
            ->addComponent(new ElementalGridFieldDeleteAction());

        $autocomplete->setSearchList($searchList);
        $autocomplete->setResultsFormat('($ID) $Title');
        $autocomplete->setSearchFields(array('ID', 'Title'));
    }

    public function updateGetTypes(&$types)
    {
        if (isset($types[ElementVirtual::class])) {
            unset($types[ElementVirtual::class]);
        }
    }
}
