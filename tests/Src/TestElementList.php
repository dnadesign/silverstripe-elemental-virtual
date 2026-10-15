<?php

namespace DNADesign\ElementalVirtual\Tests\Src;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Dev\TestOnly;

/**
 * An element whose getSummary() returns a value that isn't a string
 */
class TestElementList extends BaseElement implements TestOnly
{
    private static string $table_name = 'VTestElementList';

    public static mixed $summary = [];

    public function getType(): string
    {
        return 'A test element list';
    }

    public function getSummary(): mixed
    {
        return static::$summary;
    }

    public function getRenderTemplates($suffix = '')
    {
        return [
            __DIR__ . '/TestElement.ss'
        ];
    }
}
