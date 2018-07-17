<?php

namespace DNADesign\ElementalVirtual\Model;

use SilverStripe\ElementalVirtual\Forms\ElementalGridFieldAddExistingAutocompleter;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * Virtual Linked Element.
 *
 * As elemental is based on a natural has_one relation to an object,
 * this allows the same element to be linked to multiple pages.
 *
 * {@see ElementalGridFieldAddExistingAutocompleter}
 */
class ElementVirtual extends BaseElement
{
    private static $icon = 'dnadesign/silverstripe-elemental-virtual:images/virtual.svg';

    private static $has_one = [
        'LinkedElement' => BaseElement::class
    ];

    /**
     * @var string
     */
    private static $description = 'Reused element';

    private static $table_name = 'ElementVirtual';

    private static $singular_name = 'virtual block';

    /**
     * @param BaseElement
     * @param boolean $isSingleton
     * @param DataModel $model
     */
    public function __construct($record = null, $isSingleton = false, $model = null)
    {
        parent::__construct($record, $isSingleton, $model);

        $this->LinkedElement()->setVirtualOwner($this);
    }

    /**
     * Block should not appear in the create list
     *
     */
    public function canCreateElemental()
    {
        return false;
    }

    public function getCMSFields()
    {
        $message = sprintf(
            '<p>%s</p><p><a href="%2$s">Click here to edit the original</a></p>',
            _t(__CLASS__ . '.VirtualDescription', 'This is a virtual copy of an element.'),
            $this->LinkedElement()->getEditLink()
        );

        $fields = FieldList::create(
            TabSet::create('Root', $main = Tab::create('Main'))
        );

        if ($this->isInvalidPublishState()) {
            $warning = _t(
                __CLASS__ . '.InvalidPublishStateWarning',
                'Error: The original element is not published. This element will not work on the live site until you click the link below and publish it.'
            );
            $main->push(LiteralField::create('WarningHeader', '<p class="message error">' . $warning . '</p>'));
        }
        $main->push(LiteralField::create('Existing', $message));

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return sprintf(
            "%s (%s)",
            $this->LinkedElement()->getType(),
            _t(__CLASS__ . '.BlockType', 'Virtual')
        );
    }

    /**
     * Detect when a user has published a linked element but has not published
     * the LinkedElement.
     *
     * @return boolean
     */
    public function isInvalidPublishState()
    {
        $element = $this->LinkedElement();

        return (!$element->isPublished() && $this->isPublished());
    }

    public function getCMSPublishedState()
    {
        if ($this->isInvalidPublishState()) {
            $colour = '#C00';
            $text = _t(__CLASS__ . '.InvalidPublishStateError', 'Error');
            $html = DBHTMLText::create('PublishedState');
            $html->setValue(sprintf(
                '<span style="color: %s;">%s</span>',
                $colour,
                htmlentities($text)
            ));
            return $html;
        }

        $publishedState = null;

        foreach ($this->getExtensionInstances() as $instance) {
            if (method_exists($instance, 'getCMSPublishedState')) {
                $instance->setOwner($this);
                $publishedState = $instance->getCMSPublishedState();
                $instance->clearOwner();
                break;
            }
        }

        return $publishedState;
    }


    /**
     * Get a unique anchor name.
     *
     * @return string
     */
    public function getAnchor()
    {
        $linkedElement = $this->LinkedElement();

        if ($linkedElement && $linkedElement->exists()) {
            return $linkedElement->getAnchor();
        }

        return 'e' . $this->ID;
    }

    /**
     * @return string
     */
    public function getSummary()
    {
        if ($linked = $this->LinkedElement()) {
            return $linked->getSummary();
        }
    }
    
    /**
     * Override to render template based on LinkedElement
     *
     * @return string|null HTML
     */
    public function forTemplate($holder = true)
    {
        if ($linked = $this->LinkedElement()) {
            return $linked->forTemplate($holder);
        }
        return null;
    }
}
