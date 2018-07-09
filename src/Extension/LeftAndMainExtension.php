<?php

namespace DNADesign\ElementalVirtual\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

/**
 * Class LeftAndMainExtension.
 */
class LeftAndMainExtension extends Extension
{
    public function init()
    {
        Requirements::css('dnadesign/silverstripe-elemental-virtual:css/elemental-admin.css');
    }
}
