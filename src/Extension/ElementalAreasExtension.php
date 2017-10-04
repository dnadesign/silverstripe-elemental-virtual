<?php

namespace DNADesign\Elemental\Virtual\Extensions;

use SilverStripe\ORM\DataExtension;
use DNADesign\Elemental\Virtual\Forms\ElementalGridFieldAddExistingAutocompleter;
use DNADesign\Elemental\Virtual\Forms\ElementalGridFieldDeleteAction;

class ElementalAreasExtension extends DataExtension
{
    public function updateElementalAreaGridField($gridField)
    {
        $gridField->getConfig()
            ->removeComponentsByType(GridFieldDeleteAction::class)
            ->addComponent($autocomplete = new ElementalGridFieldAddExistingAutocompleter('buttons-before-right'))
            ->addComponent(new ElementalGridFieldDeleteAction());

        $autocomplete->setSearchList($searchList);
        $autocomplete->setResultsFormat('($ID) $Title');
        $autocomplete->setSearchFields(array('ID', 'Title'));
    }
}
