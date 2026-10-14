<?php

namespace DNADesign\ElementalVirtual\Model;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\TagField\TagField;

/**
 * Virtual Linked Element.
 *
 * As elemental is based on a natural has_one relation to an object,
 * this allows the same element to be linked to multiple pages.
 *
 * @method LinkedElement() BaseElement
 */
class ElementVirtual extends BaseElement
{
    private static $icon = 'font-icon-block-virtual-page';

    private static $has_one = [
        'LinkedElement' => BaseElement::class
    ];

    /**
     * @var string
     */
    private static $class_description = 'Reused element';

    private static $table_name = 'ElementVirtual';

    private static $singular_name = 'Virtual block';

    private static $inline_editable = true;

    private static $controller_template = 'ElementHolder_VirtualLinked';

    /**
     * @config
     *
     * @var string A field to use as the title in the linkable dropdown. Must be a DatabaseField on BaseElement
     */
    private static $linkable_title_field = 'VirtualLookupTitle';

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

    public function getCMSFields()
    {
        $invalid = $this->isInvalidPublishState();

        $this->beforeUpdateCMSFields(function (FieldList $fields) use ($invalid) {
            $fields->removeByName('Title');

            if ($invalid) {
                $warning = _t(
                    __CLASS__ . '.InvalidPublishStateWarning',
                    'Error: The original element is not published. This element will not work on the live site until ' .
                        'you click the link below and publish it.'
                );

                $fields->addFieldToTab(
                    'Root.Main',
                    LiteralField::create(
                        'WarningHeader',
                        '<p class="message error">' . $warning . '</p>'
                    )
                );
            }

            $availableBlocks = BaseElement::get()
                ->filter('AvailableGlobally', 1)
                ->exclude('ClassName', ElementVirtual::class);

            $fields->replaceField(
                'LinkedElementID',
                TagField::create("LinkedElementID", $this->fieldLabel('LinkedElement'), $availableBlocks)
                    ->setIsMultiple(false)
                    ->setCanCreate(false)
                    ->setShouldLazyLoad(true)
                    ->setTitleField($this->config()->get('linkable_title_field'))
            );

            if ($this->LinkedElementID) {
                $description = _t(__CLASS__ . '.VirtualDescription', 'This is a virtual copy of an element.');
                $editLink = $this->getLinkedElementEditLink();

                if ($editLink) {
                    $message = sprintf(
                        '<p>%s</p><p><a href="%s" target="_blank">%s</a></p>',
                        $description,
                        Convert::raw2att($editLink),
                        _t(__CLASS__ . '.EditOriginal', 'Click here to edit the original')
                    );
                } else {
                    $message = sprintf(
                        '<p>%s</p><p class="message warning">%s</p>',
                        $description,
                        _t(
                            __CLASS__ . '.OriginalNotEditable',
                            'The original element can\'t be edited because the page it belongs to has been ' .
                                'archived or deleted. Restore that page, or link this block to a different element.'
                        )
                    );
                }

                $fields->addFieldToTab('Root.Main', LiteralField::create('Existing', $message));
            }
        });

        return parent::getCMSFields();
    }

    /**
     * The CMS edit link for the linked element, or null if it can't be edited
     * (e.g. it no longer exists, or the page it's on has been archived)
     */
    public function getLinkedElementEditLink(): ?string
    {
        $linked = $this->LinkedElement();
        if (!$linked || !$linked->exists()) {
            return null;
        }

        $link = $linked->getCMSEditLink();

        return $link ? Director::absoluteURL($link) : null;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return sprintf(
            _t(__CLASS__ . '.BlockType', 'Virtual Block')
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
     * @return string
     */
    public function getTitle()
    {
        if ($linked = $this->LinkedElement()) {
            return $linked->Title;
        }
    }

    /**
     * Override to render template based on LinkedElement
     */
    public function forTemplate($holder = true): string
    {
        if ($linked = $this->LinkedElement()) {
            return $linked->forTemplate($holder);
        }
        return '';
    }

    protected function provideBlockSchema()
    {
        $blockSchema = parent::provideBlockSchema();
        $blockSchema['content'] = $this->getSummary();
        return $blockSchema;
    }
}
