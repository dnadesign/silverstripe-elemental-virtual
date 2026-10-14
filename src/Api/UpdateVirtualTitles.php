<?php

namespace DNADesign\ElementalVirtual\Api;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\ORM\DB;

class UpdateVirtualTitles
{
    public static function update_virtual_titles()
    {
        $update = BaseElement::get()->filter([
            'VirtualLookupTitle' => [null, ''],
            'AvailableGlobally' => 1
        ]);

        $table = BaseElement::singleton()->baseTable();

        foreach ($update as $element) {
            $title = $element->getVirtualLinkedSummary();

            // populate the new VirtualLookupTitle
            DB::query(sprintf(
                "UPDATE %s SET VirtualLookupTitle = '%s' WHERE ID = %d",
                $table,
                $title,
                $element->ID
            ));

            DB::query(sprintf(
                "UPDATE %s_Live SET VirtualLookupTitle = '%s' WHERE ID = %d",
                $table,
                $title,
                $element->ID
            ));
        }
    }
}
