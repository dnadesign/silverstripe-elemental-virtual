<?php

namespace DNADesign\ElementalVirtual\Tests;

use DNADesign\Elemental\Controllers\ElementController;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\ElementalVirtual\Api\UpdateVirtualTitles;
use DNADesign\ElementalVirtual\Tests\Src\TestElement;
use DNADesign\ElementalVirtual\Tests\Src\TestElementController;
use DNADesign\ElementalVirtual\Tests\Src\TestPage;
use DNADesign\ElementalVirtual\Model\ElementVirtual;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\View\Requirements;

class BaseElementExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'BaseElementExtensionTest.yml';

    /** @var TestPage */
    protected $page;

    protected static $extra_dataobjects = [
        TestElement::class,
        TestPage::class
    ];

    protected function setUp(): void
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

        $controller = ElementController::create($element);
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

    public function testVirtualLinkedSummaryWithoutTitleHasNoLeadingWhitespace(): void
    {
        $linked = $this->objFromFixture(TestElement::class, 'element1');
        $linked->Title = '';

        $this->assertSame(
            sprintf('[no title] (%s #%s - used on Page 1)', $linked->getType(), $linked->ID),
            $linked->getVirtualLinkedSummary()
        );
    }

    public function testVirtualLinkedSummaryForUnusedElement(): void
    {
        $element = TestElement::create(['Title' => 'Orphan']);
        $element->write();

        $this->assertSame(
            sprintf('Orphan (%s #%s - not currently in use)', $element->getType(), $element->ID),
            $element->getVirtualLinkedSummary()
        );
    }

    public function testVirtualLookupTitleIsNotSqlEscaped(): void
    {
        $linked = $this->objFromFixture(TestElement::class, 'element1');
        $linked->Title = "Bob's block";
        $linked->write();

        $linked = TestElement::get()->byID($linked->ID);
        $this->assertStringStartsWith("Bob's block (", $linked->VirtualLookupTitle);
    }

    public function testUpdateVirtualTitlesBackfillsMissingTitles(): void
    {
        $linked = $this->objFromFixture(TestElement::class, 'element1');
        $linked->Title = "Bob's block";
        $linked->AvailableGlobally = true;
        $linked->write();
        DB::prepared_query('UPDATE "Element" SET "VirtualLookupTitle" = NULL WHERE "ID" = ?', [$linked->ID]);

        UpdateVirtualTitles::update_virtual_titles();

        $this->assertSame(
            $linked->getVirtualLinkedSummary(),
            DB::prepared_query('SELECT "VirtualLookupTitle" FROM "Element" WHERE "ID" = ?', [$linked->ID])->value()
        );
    }

    /**
     * @see https://github.com/dnadesign/silverstripe-elemental-virtual/issues/42
     */
    public function testDefaultGlobalElementsConfigIsApplied(): void
    {
        Config::modify()->set(BaseElement::class, 'default_global_elements', true);
        $element = TestElement::create();
        $element->write();
        $this->assertTrue((bool) TestElement::get()->byID($element->ID)->AvailableGlobally);

        Config::modify()->set(BaseElement::class, 'default_global_elements', false);
        $element = TestElement::create();
        $element->write();
        $this->assertFalse((bool) TestElement::get()->byID($element->ID)->AvailableGlobally);
    }

    /**
     * @see https://github.com/dnadesign/silverstripe-elemental-virtual/issues/75
     */
    public function testVirtualOwnerIsNotStoredInLinkedRecord(): void
    {
        $linked = $this->objFromFixture(TestElement::class, 'element1');
        $virtual = ElementVirtual::create(['LinkedElementID' => $linked->ID]);
        $linkedFromVirtual = $virtual->LinkedElement();

        $this->assertSame($virtual, $linkedFromVirtual->getVirtualOwner());
        $this->assertArrayNotHasKey('_virtualOwner', $linkedFromVirtual->toMap());
        $this->assertFalse($linkedFromVirtual->isChanged());

        $virtual->write();
        $this->assertTrue($virtual->isInDB());
    }

    /**
     * @see https://github.com/dnadesign/silverstripe-elemental-virtual/issues/28
     */
    public function testBlockSchemaContentIsAString(): void
    {
        $linked = $this->objFromFixture(TestElement::class, 'element1');
        $linked->TestValue = 'Hello';
        $linked->write();

        $virtual = $this->objFromFixture(ElementVirtual::class, 'virtual1');
        $schema = $virtual->getBlockSchema();

        $this->assertSame('<p>Hello</p>', $schema['content']);
    }

    public function testLinkableElementsAreSortedWithUntitledAndUnusedLast(): void
    {
        $linked = $this->objFromFixture(TestElement::class, 'element1');
        $linked->AvailableGlobally = true;
        $linked->write();

        $unused = TestElement::create(['Title' => 'AAA unused', 'AvailableGlobally' => true]);
        $unused->write();
        $untitled = TestElement::create(['Title' => '', 'AvailableGlobally' => true]);
        $untitled->write();
        $hidden = TestElement::create(['Title' => 'Hidden', 'AvailableGlobally' => false]);
        $hidden->write();

        $virtual = $this->objFromFixture(ElementVirtual::class, 'virtual1');
        $ids = $virtual->getLinkableElements()->column('ID');

        $this->assertSame([$linked->ID, $untitled->ID, $unused->ID], array_map('intval', $ids));
    }

    /**
     * @see https://github.com/dnadesign/silverstripe-elemental-virtual/issues/77
     */
    public function testEditLinkWhenOriginalPageIsArchived(): void
    {
        $this->logInWithPermission('ADMIN');
        $virtual = $this->objFromFixture(ElementVirtual::class, 'virtual1');

        $this->assertStringContainsString('/admin/', $virtual->getLinkedElementEditLink());

        $this->page->doArchive();
        DataObject::flush_and_destroy_cache();
        $virtual = ElementVirtual::get()->byID($virtual->ID);

        $this->assertNull($virtual->getLinkedElementEditLink());

        $existing = $virtual->getCMSFields()->fieldByName('Root.Main.Existing');
        $this->assertStringNotContainsString('Click here to edit the original', $existing->getContent());
        $this->assertStringContainsString('archived or deleted', $existing->getContent());
    }

    /**
     * @see https://github.com/dnadesign/silverstripe-elemental-virtual/issues/78
     */
    public function testRenderingInitialisesLinkedElementController(): void
    {
        Config::modify()->set(TestElement::class, 'controller_class', TestElementController::class);
        Requirements::clear();

        $element = $this->objFromFixture(ElementVirtual::class, 'virtual1');
        $element->forTemplate();

        $this->assertArrayHasKey('TestElementControllerInit', Requirements::backend()->getCustomScripts());
    }
}
