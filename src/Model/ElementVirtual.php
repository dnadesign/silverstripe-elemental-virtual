<?php

namespace DNADesign\Elemental\Virtual\Model;

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
    /**
     * @var string
     */
    private static $icon = 'elemental-virtual/images/virtual.svg';

    /**
     * @var array
     */
    private static $has_one = [
        'LinkedElement' => BaseElement::class
    ];

    /**
     * @var string
     */
    private static $description = 'Reused element';

    /**
     * @var string
     */
    private static $table_name = 'ElementVirtual';

    /**
     * @var string
     */
    private static $title = 'Virtual Element';

    /**
     * @var string
     */
    private static $singular_name = 'Virtual Element';

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
     * @return string
     */
    public function getTitle()
    {
        if ($el = $this->LinkedElement()) {
            return $el->getTitle();
        } else {
            return _t(__CLASS__, $this->config()->title);
        }
    }

    public function i18n_singular_name()
    {
        return _t(__CLASS__, $this->LinkedElement()->config()->title);
    }

    /**
     * Block should not appear in the create list
     *
     */
    public function canCreateBlock()
    {
        return false;
    }

    public function getCMSFields()
    {
        $message = sprintf(
            '<p>%s</p><p><a href="%2$s">Click here to edit the original</a></p>',
            _t('ElementVirtualLinked.DESCRIBE', 'This is a virtual copy of an element.'),
            $this->LinkedElement()->getEditLink()
        );

        $fields = new FieldList(
            new TabSet('Root', $main = new Tab('Main'))
        );

        if ($this->isInvalidPublishState()) {
            $warning = 'Error: The original element is not published. This element will not work on the live site until you click the link below and publish it.';
            $main->push(new LiteralField('WarningHeader', '<p class="message error">' .$warning. '</p>'));
        }
        $main->push(new LiteralField('Existing', $message));

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return sprintf("%s (%s)",
            $this->LinkedElement()->getType(), _t(__CLASS__ . '.virtual', 'Virtual')
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
        $element = $this->owner->LinkedElement();

        return (!$element->isPublished() && $this->owner->isPublished());
    }

    public function getCMSPublishedState()
    {
        if ($this->isInvalidPublishState()) {
            $colour = '#C00';
            $text = 'Error';
            $html = new DBHTMLText('PublishedState');
            $html->setValue(sprintf(
                '<span style="color: %s;">%s</span>',
                $colour,
                htmlentities($text)
            ));
            return $html;
        }

        $publishedState = null;

        foreach ($this->extension_instances as $instance) {
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
        $linkedElement = $this->owner->LinkedElement();

        if ($linkedElement && $linkedElement->exists()) {
            return $linkedElement->getAnchor();
        }

        return 'e'. $this->ID;
    }

    /**
     * @return string
     */
    public function getSummary()
    {
        if ($linked = $this->owner->LinkedElement()) {
            return $linked->getSummary();
        }
    }
}
