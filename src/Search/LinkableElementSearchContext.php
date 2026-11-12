<?php

namespace DNADesign\ElementalVirtual\Search;

use Override;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\Search\SearchContext;

/**
 * Search context for the lazy loaded linked element dropdown.
 *
 * SearchableDropdownField's lazy load search otherwise queries the model's
 * default search context, which ignores the field's source list and drops the
 * search term because the title field isn't a searchable field (#85).
 */
class LinkableElementSearchContext extends SearchContext
{
    public function __construct(
        private readonly DataList $linkableElements,
        private readonly string $titleField
    ) {
        parent::__construct($linkableElements->dataClass());
    }

    /**
     * Sort is ignored so that the linkable list's own ordering is kept
     */
    #[Override]
    public function getQuery($searchParams, $sort = false, int|array|null $limit = null, $existingQuery = null)
    {
        $list = $this->linkableElements;
        $term = trim((string) (is_array($searchParams) ? reset($searchParams) : ''));

        if ($term !== '') {
            $list = $list->filter([$this->titleField . ':PartialMatch' => $term]);
        }

        return $limit ? $list->limit($limit) : $list;
    }
}
