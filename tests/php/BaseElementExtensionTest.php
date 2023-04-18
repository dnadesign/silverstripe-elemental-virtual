<?php

namespace DNADesign\ElementalVirtual\Tests;

use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Tests\Src\TestElement;
use DNADesign\Elemental\Tests\Src\TestPage;
use DNADesign\ElementalVirtual\Model\ElementVirtual;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

class BaseElementExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'BaseElementExtensionTest.yml';

    protected static $extra_dataobjects = [
        TestElement::class,
        TestPage::class
    ];

    public function setUp(): void
    {
        parent::setUp();

        Config::modify()->set('Page', 'can_be_root', true);

        $this->page = $this->objFromFixture(TestPage::class, 'page1');
        $this->page->publishRecursive();
    }

    public function testVirtualElementAnchor()
    {
        Config::modify()->set(BaseElement::class, 'disable_pretty_anchor_name', true);

        $element = $this->objFromFixture(ElementVirtual::class, 'virtual1');
        $linked = $this->objFromFixture(TestElement::class, 'element1');

        $this->assertEquals('e' . $linked->ID, $element->getAnchor());
    }

    public function testUpdateCmsFields()
    {
        $linked = $this->objFromFixture(TestElement::class, 'element1');

        // should show that this element has virtual clones
        $list = $linked->getCMSFields()->dataFieldByName('VirtualClones')->getList();

        $this->assertEquals(1, $list->count());
    }
}
