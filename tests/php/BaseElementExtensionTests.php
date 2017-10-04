<?php

namespace DNADesign\Elemental\Virtual\Tests;

use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

class BaseElementExtensionTests extends SapphireTest
{
    public function testAvailableGlobally()
    {

    }

    public function testVirtualElementAnchor()
    {
        $baseElement1 = BaseElement::create(array('Title' => 'Element 2', 'Sort' => 1));
        $baseElement1->write();
        $baseElement2 = BaseElement::create(array('Title' => 'Element 2', 'Sort' => 2));
        $baseElement2->write();
        $baseElement3 = BaseElement::create(array('Title' => 'Element 2', 'Sort' => 3));
        $baseElement3->write();
        $virtElement1 = ElementVirtualLinked::create(array('LinkedElementID' => $baseElement2->ID));
        $virtElement1->write();
        $virtElement2 = ElementVirtualLinked::create(array('LinkedElementID' => $baseElement3->ID));
        $virtElement2->write();

        $area = ElementalArea::create();
        $area->Widgets()->add($baseElement1);
        $area->Widgets()->add($virtElement1);
        $area->Widgets()->add($virtElement2);
        $area->write();

        $recordSet = $area->Elements()->toArray();
        foreach ($recordSet as $record) {
            $record->getAnchor();
        }

        $this->assertEquals('element-2', $recordSet[0]->getAnchor());
        $this->assertEquals('element-2-2', $recordSet[1]->getAnchor());
        $this->assertEquals('element-2-3', $recordSet[2]->getAnchor());
    }
}
