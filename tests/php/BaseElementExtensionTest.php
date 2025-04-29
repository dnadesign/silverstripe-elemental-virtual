<?php

namespace DNADesign\ElementalVirtual\Tests;

use DNADesign\Elemental\Controllers\ElementController;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\ElementalVirtual\Tests\Src\TestElement;
use DNADesign\ElementalVirtual\Tests\Src\TestPage;
use DNADesign\ElementalVirtual\Model\ElementVirtual;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

class BaseElementExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'BaseElementExtensionTest.yml';

    /** @var TestPage */
    protected $page;

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

    public function testVirtualElementAnchor(): void
    {
        Config::modify()->set(BaseElement::class, 'disable_pretty_anchor_name', true);

        $element = $this->objFromFixture(ElementVirtual::class, 'virtual1');
        $linked = $this->objFromFixture(TestElement::class, 'element1');

        $this->assertEquals('e' . $linked->ID, $element->getAnchor());
    }


    public function testRendersIntoHolder(): void
    {
        $element = $this->objFromFixture(ElementVirtual::class, 'virtual1');

        $controller = new ElementController($element);
        $controller->doInit();
        $template = $controller->forTemplate();

        $this->assertStringContainsString('dnadesign__elementalvirtual__tests__src__testelement', $template);
    }

    public function testUpdateCmsFields(): void
    {
        $linked = $this->objFromFixture(TestElement::class, 'element1');

        $this->assertNull($linked->getCMSFields()->dataFieldByName('VirtualClones'));

        // should show that this element has virtual clones
        Config::modify()->set(TestElement::class, 'inline_editable', false);

        $list = $linked->getCMSFields()->dataFieldByName('VirtualClones')->getList();

        $this->assertEquals(1, $list->count());
    }
}
