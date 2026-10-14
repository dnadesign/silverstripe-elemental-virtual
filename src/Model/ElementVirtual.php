<?php

namespace DNADesign\ElementalVirtual\Model;

use Override;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\ElementalVirtual\Extensions\BaseElementExtension;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\SearchableDropdownField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

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

    private bool $inIsChanged = false;

    #[Override]
    public function isChanged($fieldName = null, $changeLevel = DataObject::CHANGE_STRICT)
    {
        if ($this->inIsChanged) {
            return false;
        }
        $this->inIsChanged = true;
        try {
            return parent::isChanged($fieldName, $changeLevel);
        } finally {
            $this->inIsChanged = false;
        }
    }

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


    #[Override]
    public function getCMSFields()
    {
        $invalid = $this->isInvalidPublishState();

        $this->beforeUpdateCMSFields(function (FieldList $fields) use ($invalid) {
            $fields->removeByName('Title');

            if ($invalid) {
                $warning = _t(
                    self::class . '.InvalidPublishStateWarning',
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

            if ($this->LinkedElementID) {
                $description = _t(self::class . '.VirtualDescription', 'This is a virtual copy of an element.');
                $editLink = $this->getLinkedElementEditLink();

                if ($editLink) {
                    $message = sprintf(
                        '<p>%s</p><p><a href="%s" target="_blank">%s</a></p>',
                        $description,
                        Convert::raw2att($editLink),
                        _t(self::class . '.EditOriginal', 'Click here to edit the original')
                    );
                } else {
                    $message = sprintf(
                        '<p>%s</p><p class="message warning">%s</p>',
                        $description,
                        _t(
                            self::class . '.OriginalNotEditable',
                            'The original element can\'t be edited because the page it belongs to has been ' .
                                'archived or deleted. Restore that page, or link this block to a different element.'
                        )
                    );
                }

                $fields->addFieldToTab('Root.Main', LiteralField::create('Existing', $message));
            }
        });
        $fields = parent::getCMSFields();
        $field = $fields->dataFieldByName('LinkedElementID');
        $titleField = $this->config()->get('linkable_title_field');
        $list = $this->getLinkableElements();

        if ($field instanceof SearchableDropdownField) {
            $field
                ->setSource($list)
                ->setLabelField($titleField);
        } elseif ($field instanceof DropdownField) {
            $field
                ->setSource($list->map('ID', $titleField)->toArray())
                ->setEmptyString(_t(self::class . '.SelectElement', '-- Select an element --'));
        }

        return $fields;
    }

    /**
     * Elements that can be selected as the linked element, sorted by lookup
     * title with untitled and unused elements at the end of the list.
     */
    public function getLinkableElements(): DataList
    {
        $titleField = $this->config()->get('linkable_title_field');
        $list = BaseElement::get()
            ->filter(['AvailableGlobally' => 1])
            ->exclude(['ClassName' => ElementVirtual::class]);

        return $this->sortDataListBetter(
            $list,
            $titleField,
            [
                BaseElementExtension::no_title_string(),
                BaseElementExtension::not_in_use_string(),
            ]
        );
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
    #[Override]
    public function getType()
    {
        return _t(self::class . '.BlockType', 'Virtual Block');
    }

    /**
     * Detect when a user has published a linked element but has not published
     * the LinkedElement.
     *
     * @return boolean
     */
    public function isInvalidPublishState(): bool
    {
        $element = $this->LinkedElement();

        return (!$element->isPublished() && $this->isPublished());
    }

    /**
     * Get a unique anchor name.
     *
     * @return string
     */
    #[Override]
    public function getAnchor(): string
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
    #[Override]
    public function getSummary(): ?string
    {
        $linked = $this->LinkedElement();
        if ($linked && $linked->exists()) {
            // Linked elements may return a DBField (e.g. DBHTMLText) which
            // can't be serialised into the block schema (#28)
            return (string) $linked->getSummary();
        }

        return null;
    }

    /**
     * @return string
     */
    #[Override]
    public function getTitle(): ?string
    {
        $linked = $this->LinkedElement();
        if ($linked && $linked->exists()) {
            return $linked->Title;
        }

        return null;
    }

    /**
     * Override to render template based on LinkedElement
     */
    #[Override]
    public function forTemplate($holder = true): string
    {
        $linked = $this->LinkedElement();
        if ($linked && $linked->exists()) {
            return $linked->forTemplate($holder);
        }

        return '';
    }

    #[Override]
    protected function provideBlockSchema(): array
    {
        $blockSchema = parent::provideBlockSchema();
        $blockSchema['content'] = $this->getSummary();
        return $blockSchema;
    }

    protected function sortDataListBetter(DataList $list, string $column, array $stringsToDemote): DataList
    {
        if (empty($stringsToDemote)) {
            return $list;
        }

        $column = Convert::symbol2sql($list->dataClass()::singleton()->baseTable() . '.' . $column);
        $cases = '';
        foreach (array_values($stringsToDemote) as $index => $phrase) {
            $escaped = Convert::raw2sql('%' . $phrase . '%');
            $cases .= sprintf("WHEN %s LIKE '%s' THEN %d ", $column, $escaped, $index + 1);
        }

        return $list->orderBy(sprintf('CASE %sELSE 0 END ASC, %s ASC', $cases, $column));
    }
}
