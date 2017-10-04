<?php

namespace DNADesign\Elemental\Virtual\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;
use DNADesign\Elemental\Virtual\Model\ElementVirtual;

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

    /**
     *
     */
    public function populateDefaults()
    {
        $this->AvailableGlobally = $this->owner->config()->get('default_global_elements');
    }

    /**
     * @param ElementVirtual
     *
     * @return $this
     */
    public function setVirtualOwner(ElementVirtual $owner)
    {
        $this->virtualOwner = $owner;
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
        return ElementVirtual::get()->filter('LinkedElementID', $this->owner->ID);
    }

    /**
     * @param FieldList
     *
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        if ($virtual = $fields->dataFieldByName('VirtualClones')) {
            if ($this->owner->VirtualClones()->Count() > 0) {
                $tab = $fields->findOrMakeTab('Root.VirtualClones');
                $tab->setTitle(_t('BaseElement.VIRTUALTABTITLE', 'Linked To'));

                if ($ownerPage = $this->owner->getPage()) {
                    $fields->addFieldToTab(
                        'Root.VirtualClones',
                        new LiteralField(
                            'DisplaysOnPage',
                            sprintf(
                                "<p>The original content element appears on <a href='%s'>%s</a></p>",
                                ($ownerPage->hasMethod('CMSEditLink') && $ownerPage->canEdit()) ? $ownerPage->CMSEditLink() : $ownerPage->Link(),
                                $ownerPage->MenuTitle
                            )
                        ),
                        'VirtualClones'
                    );
                }

                $virtual->setConfig(new GridFieldConfig_Base());
                $virtual
                    ->setTitle(_t('BaseElement.OTHERPAGES', 'Other pages'))
                    ->getConfig()
                        ->removeComponentsByType(GridFieldAddExistingAutocompleter::class)
                        ->removeComponentsByType(GridFieldAddNewButton::class)
                        ->removeComponentsByType(GridFieldDeleteAction::class)
                        ->removeComponentsByType(GridFieldDetailForm::class)
                        ->addComponent(new ElementalGridFieldDeleteAction());

                $virtual->getConfig()
                    ->getComponentByType(GridFieldDataColumns::class)
                    ->setDisplayFields(array(
                        'getPage.Title' => 'Title',
                        'ParentCMSEditLink' => 'Used on'
                    ));
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
            $allVirtual = $this->getVirtualLinkedElements();
            if ($this->getPublishedVirtualLinkedElements()->Count() > 0) {
                // choose the first one
                $firstVirtual = $this->getPublishedVirtualLinkedElements()->First();
                $wasPublished = true;
            } elseif ($allVirtual->Count() > 0) {
                // choose the first one
                $firstVirtual = $this->getVirtualLinkedElements()->First();
                $wasPublished = false;
            }
            if ($firstVirtual) {
                $origParentID = $this->ParentID;
                $origSort = $this->Sort;

                $clone = $this->duplicate(false);

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

        if ($page = $this->getPage()) {
            $usage->push($page);
            if ($this->virtualOwner) {
                $page->setField('ElementType', 'Linked');
            } else {
                $page->setField('ElementType', 'Master');
            }
        }

        $linkedElements = ElementVirtualLinked::get()->filter('LinkedElementID', $this->ID);
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
        $arr = array();
        foreach ($usage as $page) {
            $type = ($page->ElementType) ? sprintf("<em> - %s</em>", $page->ElementType) : null;
            $arr[] = sprintf("<a href=\"%s\" target=\"blank\">%s</a> %s", $page->CMSEditLink(), $page->Title, $type);
        }
        $html = DBHTMLText::create('UsageSummary');
        $html->setValue(implode('<br>', $arr));

        return $html;
    }

    /**
     * @param DBHTMLVarchar
     */
    public function updateElementIcon($icon)
    {
        $linked = $this->owner->LinkedElement();

        if ($linked) {
            $linkedIcon = $linked->config()->get('icon');

            $icon = DBField::create_field('HTMLVarchar', '<span class="el-icongroup"><img width="16px" src="' . Director::absoluteBaseURL() . $linkedIcon . '" alt="" /><img class="el-icon--virtual" width="16px" src="' . Director::absoluteBaseURL() . $icon . '" alt="" /></span>');
        }
    }
}
