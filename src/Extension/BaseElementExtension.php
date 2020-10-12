<?php

namespace DNADesign\ElementalVirtual\Extensions;

use DNADesign\ElementalVirtual\Forms\ElementalGridFieldDeleteAction;
use DNADesign\ElementalVirtual\Model\ElementVirtual;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\ArrayList;

class BaseElementExtension extends DataExtension
{
    /**
     * @var mixed
     */
    protected $virtualOwner;

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

        $this->AvailableGlobally = $default;
    }

    /**
     * @param ElementVirtual
     *
     * @return $this
     */
    public function setVirtualOwner(ElementVirtual $owner)
    {
        $this->virtualOwner = $owner;
        return $this;
    }

    /**
     * @return ElementVirtual
     */
    public function getVirtualOwner()
    {
        return $this->virtualOwner;
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
     *
     * @return FieldList
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
                        'ParentPageCMSEditLink' => _t(__CLASS__ . '.GridFieldUsedOn', 'Used on'),
                    ]);
            } else {
                $fields->removeByName('VirtualClones');
            }
        }
    }

    /**
     * Client specific requirement https://mbiessd.atlassian.net/browse/MWP-390
     * Delete all references of this Virtual element
     *
     */
    public function onBeforeDelete()
    {
        foreach ($this->getVirtualElements() as $virtualElement) {
            $virtualElement->delete();
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
            if ($this->virtualOwner) {
                $page->setField('ElementType', 'Linked');
            } else {
                $page->setField('ElementType', 'Master');
            }
        }

        $linkedElements = ElementVirtual::get()->filter('LinkedElementID', $this->ID);

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
