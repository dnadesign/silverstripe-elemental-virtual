<?php

namespace SilverStripe\Elemental\Virtual\Extensions;

use SilverStripe\ORM\DataExtension;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Elemental\Virtual\Forms\ElementalGridFieldAddExistingAutocompleter;
use SilverStripe\Elemental\Virtual\Forms\ElementalGridFieldDeleteAction;
use SilverStripe\Elemental\Virtual\Model\ElementVirtual;

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
