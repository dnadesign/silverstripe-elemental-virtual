<?php

namespace DNADesign\ElementalVirtual\Tests\Src;

use DNADesign\Elemental\Controllers\ElementController;
use SilverStripe\Dev\TestOnly;
use SilverStripe\View\Requirements;

class TestElementController extends ElementController implements TestOnly
{
    protected function init()
    {
        parent::init();

        Requirements::customScript('window.testElementInit = true;', 'TestElementControllerInit');
    }
}
