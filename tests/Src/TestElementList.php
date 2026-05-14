<?php

namespace DNADesign\ElementalVirtual\Tests\Src;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Dev\TestOnly;

/**
 * Simulates an element (e.g. ElementList) whose getSummary() returns a non-string value.
 */
class TestElementList extends BaseElement implements TestOnly
{
    private static string $table_name = 'VTestElementList';

    public function getType(): string
    {
        return 'A test element list';
    }

    public function getSummary(): array
    {
        return [];
    }

    public function getRenderTemplates($suffix = '')
    {
        return [
            __DIR__ . '/TestElement.ss'
        ];
    }
}
