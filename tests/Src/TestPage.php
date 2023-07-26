<?php

namespace DNADesign\ElementalVirtual\Tests\Src;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

class TestPage extends SiteTree implements TestOnly
{
    private static $table_name = 'TestElementalPage';
}
