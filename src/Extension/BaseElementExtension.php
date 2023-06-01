<?php

namespace DNADesign\ElementalVirtual\Extensions;

use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\ElementalVirtual\Forms\ElementalGridFieldDeleteAction;
use DNADesign\ElementalVirtual\Model\ElementVirtual;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Versioned\Versioned;

class BaseElementExtension extends DataExtension
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
        'AvailableGlobally' => 'Boolean(1)'
    ];

    /**
     * @var array $has_many
     */
    private static $has_many = [
        'VirtualClones' => ElementVirtual::class
    ];

    public function populateDefaults()
    {
        $default = $this->owner->config()->get('default_global_elements');

        $this->owner->AvailableGlobally = $default;
    }

    /**
     * @param ElementVirtual
     *
     * @return $this
     */
    public function setVirtualOwner(ElementVirtual $owner)
    {
        $this->owner->setField('_virtualOwner', $owner);
        return $this;
    }

    /**
     * @return ElementVirtual
     */
    public function getVirtualOwner()
    {
        return $this->owner->getField('_virtualOwner');
    }

    /**
     * Finds and returns elements that are virtual elements which link to this
     * element.
     *
     * @return DataList
     */
    public function getVirtualElements()
    {
        return ElementVirtual::get()->filter([
            'LinkedElementID' => $this->owner->ID
        ]);
    }

    /**
     * @return string
     */
    public function getVirtualLinkedSummary()
    {
        return sprintf('%s (%s #%s)', $this->owner->Title, $this->owner->getType(), $this->owner->ID);
    }

    /**
     * @return DataList
     */
    public function getPublishedVirtualElements()
    {
        return ElementVirtual::get()->filter([
            'LinkedElementID' => $this->owner->ID
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
        $global = $fields->dataFieldByName('AvailableGlobally');

        if ($global) {
            $fields->removeByName('AvailableGlobally');
            $fields->addFieldToTab('Root.Settings', $global);
        }

        if ($virtual = $fields->dataFieldByName('VirtualClones')) {
            if ($this->owner->VirtualClones()->Count() > 0) {
                $tab = $fields->findOrMakeTab('Root.VirtualClones');
                $tab->setTitle(_t(__CLASS__ . '.LinkedTo', 'Linked To'));

                if ($ownerPage = $this->owner->getPage()) {
                    $fields->addFieldToTab(
                        'Root.VirtualClones',
                        LiteralField::create(
                            'DisplaysOnPage',
                            sprintf(
                                "<p>"
                                . _t(__CLASS__ . '.OriginalContentFrom', 'The original content element appears on')
                                . " <a href='%s'>%s</a></p>",
                                ($ownerPage->hasMethod('CMSEditLink') && $ownerPage->canEdit()) ? $ownerPage->CMSEditLink() : $ownerPage->Link(),
                                $ownerPage->MenuTitle
                            )
                        ),
                        'VirtualClones'
                    );
                }

                $virtual->setConfig(new GridFieldConfig_Base());
                $virtual
                    ->setTitle(_t(__CLASS__ . '.OtherPages', 'Other pages'))
                    ->getConfig()
                        ->removeComponentsByType(GridFieldAddExistingAutocompleter::class)
                        ->removeComponentsByType(GridFieldAddNewButton::class)
                        ->removeComponentsByType(GridFieldDeleteAction::class)
                        ->removeComponentsByType(GridFieldDetailForm::class)
                        ->addComponent(new ElementalGridFieldDeleteAction());

                $virtual->getConfig()
                    ->getComponentByType(GridFieldDataColumns::class)
                    ->setDisplayFields([
                        'getPage.Title' => _t(__CLASS__ . '.GridFieldTitle', 'Title'),
                        'ParentCMSEditLink' => _t(__CLASS__ . '.GridFieldUsedOn', 'Used on'),
                    ]);
            } else {
                $fields->removeByName('VirtualClones');
            }
        }
    }

    /**
     * Ensure that if there are elements that are virtualised from this element
     * that we move the original element to replace one of the virtual elements
     *
     * But only if it's a delete not an unpublish
     */
    public function onBeforeDelete()
    {
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
                $clone = $this->owner->duplicate(false);

                // set clones values to first virtual's values
                $clone->ParentID = $firstVirtual->ParentID;
                $clone->Sort = $firstVirtual->Sort;

                $clone->write();
                if ($wasPublished) {
                    $clone->doPublish();
                    $firstVirtual->doUnpublish();
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
                        $virtual->doPublish();
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
        $usage = new ArrayList();

        if ($page = $this->owner->getPage()) {
            $usage->push($page);
            if ($this->owner->getField('_virtualOwner')) {
                $page->setField('ElementType', 'Linked');
            } else {
                $page->setField('ElementType', 'Master');
            }
        }

        $linkedElements = ElementVirtual::get()->filter('LinkedElementID', $this->owner->ID);

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
            $arr[] = sprintf("<a href=\"%s\" target=\"blank\">%s</a> %s", $page->CMSEditLink(), $page->Title, $type);
        }
        $html = DBHTMLText::create('UsageSummary');
        $html->setValue(implode('<br>', $arr));

        return $html;
    }
}
