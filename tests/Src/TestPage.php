<?php

declare(strict_types=1);

namespace DNADesign\ElementalVirtual\Tests\Src;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

class TestPage extends SiteTree implements TestOnly
{
    private static $table_name = 'VTestElementalPage';

    private static $extensions = [
        ElementalPageExtension::class,
    ];
}
