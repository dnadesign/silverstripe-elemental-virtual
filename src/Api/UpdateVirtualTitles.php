<?php

namespace DNADesign\ElementalVirtual\Api;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\ORM\DB;

/**
 * Back-fills the VirtualLookupTitle for globally available elements that
 * don't have one yet. Writes directly to the draft and live tables so that
 * the elements' publish state is not changed.
 */
class UpdateVirtualTitles
{
    public static function update_virtual_titles(): void
    {
        $update = BaseElement::get()->filter([
            'VirtualLookupTitle' => [null, ''],
            'AvailableGlobally' => 1
        ]);

        $table = BaseElement::singleton()->baseTable();

        foreach ($update as $element) {
            $title = $element->getVirtualLinkedSummary();

            foreach ([$table, $table . '_Live'] as $tableName) {
                DB::prepared_query(
                    sprintf('UPDATE "%s" SET "VirtualLookupTitle" = ? WHERE "ID" = ?', $tableName),
                    [$title, $element->ID]
                );
            }
        }
    }
}
