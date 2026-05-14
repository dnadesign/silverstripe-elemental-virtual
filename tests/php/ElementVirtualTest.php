<?php

namespace DNADesign\ElementalVirtual\Tests;

use DNADesign\ElementalVirtual\Model\ElementVirtual;
use DNADesign\ElementalVirtual\Tests\Src\TestElement;
use DNADesign\ElementalVirtual\Tests\Src\TestElementList;
use DNADesign\ElementalVirtual\Tests\Src\TestPage;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

class ElementVirtualTest extends SapphireTest
{
    protected static $fixture_file = 'ElementVirtualTest.yml';

    protected static $extra_dataobjects = [
        TestElement::class,
        TestElementList::class,
        TestPage::class,
    ];

    public function setUp(): void
    {
        parent::setUp();
        Config::modify()->set('Page', 'can_be_root', true);
    }

    /**
     * When the linked element returns a string summary, the block schema content should be that string.
     */
    public function testProvideBlockSchemaWithStringSummary(): void
    {
        $virtual = $this->objFromFixture(ElementVirtual::class, 'virtual_string');

        $method = new \ReflectionMethod(ElementVirtual::class, 'provideBlockSchema');
        $method->setAccessible(true);
        $schema = $method->invoke($virtual);

        $this->assertIsString($schema['content']);
    }

    /**
     * When the linked element returns a non-string summary (e.g. ElementList), the block schema
     * content must be null so that React can render it without error.
     */
    public function testProvideBlockSchemaWithNonStringSummaryIsNull(): void
    {
        $virtual = $this->objFromFixture(ElementVirtual::class, 'virtual_list');

        $method = new \ReflectionMethod(ElementVirtual::class, 'provideBlockSchema');
        $method->setAccessible(true);
        $schema = $method->invoke($virtual);

        $this->assertNull($schema['content']);
    }
}
