<?php

namespace DNADesign\ElementalVirtual\Extensions;

use SilverStripe\Forms\FormField;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\ElementalVirtual\Api\UpdateVirtualTitles;
use DNADesign\ElementalVirtual\Forms\ElementalGridFieldDeleteAction;
use DNADesign\ElementalVirtual\Model\ElementVirtual;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Versioned\Versioned;

class BaseElementExtension extends Extension
{
    /**
     * @config
     *
     * @var boolean
     */
    private static $default_global_elements = true;

    /**
     * @var array
     */
    private static $db = [
        'AvailableGlobally' => 'Boolean(0)',
        'VirtualLookupTitle' => 'Varchar(200)'
    ];

    /**
     * @var array $has_many
     */
    private static $has_many = [
        'VirtualClones' => ElementVirtual::class
    ];

    public function onAfterPopulateDefaults()
    {
        $owner = $this->getOwner();
        $default = $owner->config()->get('default_global_elements');

        $owner->AvailableGlobally = $default;
    }


    public function onRequireDefaultRecords(): void
    {
        UpdateVirtualTitles::update_virtual_titles();
    }

    /**
     * @param ElementVirtual
     *
     * @return $this
     */
    public function setVirtualOwner(ElementVirtual $owner)
    {
        $this->getOwner()->setField('_virtualOwner', $owner);
        return $this;
    }

    /**
     * @return ElementVirtual
     */
    public function getVirtualOwner()
    {
        $owner = $this->getOwner();
        return $owner->getField('_virtualOwner');
    }

    /**
     * Finds and returns elements that are virtual elements which link to this
     * element.
     *
     * @return DataList
     */
    public function getVirtualElements()
    {
        $owner = $this->getOwner();
        return ElementVirtual::get()->filter([
            'LinkedElementID' => $owner->ID
        ]);
    }

    /**
     * @return string
     */
    public function getVirtualLinkedSummary()
    {
        $owner = $this->getOwner();

        $page = $owner->getPage();
        $page = $page ?
            _t(self::class . '.UsedOnPage', ' - used on {page}', ['page' => $page->Title]) :
            self::not_in_use_string();

        $title = trim($owner->getTitle() ?: self::no_title_string());

        $type = trim($owner->getType());

        $summary = Convert::raw2sql(sprintf(
            '%s (%s%s)',
            $title,
            $type,
            $page
        ));

        $owner->invokeWithExtensions('updateVirtualLinkedSummary', $summary);

        return $summary;
    }

    public static function not_in_use_string(): string
    {
        return _t(self::class . '.NotInUse', ' - not currently in use');
    }

    public static function no_title_string(): string
    {
        return _t(self::class . '.NoTitle', ' - no title');
    }

    /**
     * @return DataList
     */
    public function getPublishedVirtualElements()
    {
        $owner = $this->getOwner();
        return ElementVirtual::get()->filter([
            'LinkedElementID' => $owner->ID
        ])->setDataQueryParam([
            'Versioned.mode' => 'stage',
            'Versioned.stage' => 'Live'
        ]);
    }

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->getOwner();
        $global = $fields->dataFieldByName('AvailableGlobally');

        if ($global instanceof FormField) {
            $desc = _t(self::class . '.LookupDescription', 'Search for the above title when linking to this element');
            $fields->removeByName([
                'VirtualLookupTitle',
                'AvailableGlobally'
            ]);

            $fields->addFieldsToTab('Root.Settings', [
                $global,
                ReadonlyField::create('VirtualLookupTitle', _t(self::class . '.LookupTitle', 'Virtual Lookup Title'))
                    ->setDescription($desc)
            ]);
        }

        if ($owner->config()->get('inline_editable')) {
            $fields->removeByName('VirtualClones');

            return;
        }

        if (($virtual = $fields->dataFieldByName('VirtualClones')) instanceof FormField) {
            if ($owner->VirtualClones()->Count() > 0) {
                $tab = $fields->findOrMakeTab('Root.VirtualClones');
                $tab->setTitle(_t(self::class . '.LinkedTo', 'Linked To'));

                if ($ownerPage = $owner->getPage()) {
                    if ($ownerPage->hasMethod('CMSEditLink')) {
                        $link = $ownerPage->canEdit() ? $ownerPage->getCMSEditLink() : $ownerPage->Link();
                    } else {
                        $link = $ownerPage->Link();
                    }

                    $fields->addFieldToTab(
                        'Root.VirtualClones',
                        LiteralField::create(
                            'DisplaysOnPage',
                            sprintf(
                                "<p>"
                                    . _t(self::class . '.OriginalContentFrom', 'The original content element appears on')
                                    . " <a href='%s'>%s</a></p>",
                                $link,
                                $ownerPage->MenuTitle
                            )
                        ),
                        'VirtualClones'
                    );
                }

                $virtual->setConfig(GridFieldConfig_Base::create());
                $virtual
                    ->setTitle(_t(self::class . '.OtherPages', 'Other pages'))
                    ->getConfig()
                    ->removeComponentsByType(GridFieldAddExistingAutocompleter::class)
                    ->removeComponentsByType(GridFieldAddNewButton::class)
                    ->removeComponentsByType(GridFieldDeleteAction::class)
                    ->removeComponentsByType(GridFieldDetailForm::class)
                    ->addComponent(ElementalGridFieldDeleteAction::create());

                $virtual->getConfig()
                    ->getComponentByType(GridFieldDataColumns::class)
                    ->setDisplayFields([
                        'getPage.Title' => _t(self::class . '.GridFieldTitle', 'Title'),
                        'ParentCMSEditLink' => _t(self::class . '.GridFieldUsedOn', 'Used on'),
                    ]);
            } else {
                $fields->removeByName('VirtualClones');
            }
        }
    }


    public function onBeforeWrite()
    {
        $owner = $this->getOwner();
        $owner->setField('VirtualLookupTitle', $owner->getVirtualLinkedSummary());
    }

    /**
     * Ensure that if there are elements that are virtualised from this element
     * that we move the original element to replace one of the virtual elements
     *
     * But only if it's a delete not an unpublish
     */
    public function onBeforeDelete()
    {
        $owner = $this->getOwner();
        if (Versioned::get_reading_mode() == 'Stage.Stage') {
            $firstVirtual = false;
            $allVirtual = $this->getVirtualElements();

            if ($this->getPublishedVirtualElements()->Count() > 0) {
                // choose the first one
                $firstVirtual = $this->getPublishedVirtualElements()->First();
                $wasPublished = true;
            } elseif ($allVirtual->Count() > 0) {
                // choose the first one
                $firstVirtual = $this->getVirtualElements()->First();
                $wasPublished = false;
            }

            if ($firstVirtual) {
                $clone = $owner->duplicate(false);

                // set clones values to first virtual's values
                $clone->ParentID = $firstVirtual->ParentID;
                $clone->Sort = $firstVirtual->Sort;

                $clone->write();

                if ($wasPublished) {
                    $clone->publishRecursive();

                    $firstVirtual->doArchive();
                }

                // clone has a new ID, so need to repoint
                // all the other virtual elements
                foreach ($allVirtual as $virtual) {
                    if ($virtual->ID == $firstVirtual->ID) {
                        continue;
                    }

                    $pub = false;

                    if ($virtual->isPublished()) {
                        $pub = true;
                    }

                    $virtual->LinkedElementID = $clone->ID;
                    $virtual->write();

                    if ($pub) {
                        $virtual->publishRecursive();
                    }
                }

                $firstVirtual->delete();
            }
        }
    }

    /**
     * @param array $classes
     */
    public function updateAllowedElementClasses(&$classes)
    {
        if (isset($classes[ElementVirtual::class])) {
            unset($classes[ElementVirtual::class]);
        }
    }


    /**
     * get all pages where this element is used
     *
     * @return ArrayList
     */
    public function getUsage()
    {
        $usage = ArrayList::create();
        $owner = $this->getOwner();
        if ($page = $owner->getPage()) {
            $usage->push($page);
            if ($owner->getField('_virtualOwner')) {
                $page->setField('ElementType', 'Linked');
            } else {
                $page->setField('ElementType', 'Master');
            }
        }

        $linkedElements = ElementVirtual::get()->filter(['LinkedElementID' => $owner->ID]);

        foreach ($linkedElements as $element) {
            $area = $element->Parent();

            if ($area instanceof ElementalArea && $page = $area->getOwnerPage()) {
                $page->setField('ElementType', 'Linked');
                $usage->push($page);
            }
        }

        $usage->removeDuplicates();
        return $usage;
    }

    /**
     * @return DBHTMLText
     */
    public function UsageSummary()
    {
        $usage = $this->getUsage();
        $arr = [];
        foreach ($usage as $page) {
            $type = ($page->ElementType) ? sprintf("<em> - %s</em>", $page->ElementType) : null;
            $arr[] = sprintf('<a href="%s" target="blank">%s</a> %s', $page->getCMSEditLink(), $page->Title, $type);
        }

        $html = DBHTMLText::create('UsageSummary');
        $html->setValue(implode('<br>', $arr));

        return $html;
    }
}
