<?php

namespace DNADesign\ElementalVirtual\Tests\Src;

use Override;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Security\Permission;

class TestElement extends BaseElement implements TestOnly
{
    private static string $table_name = 'VTestElement';

    private static array $db = [
        'TestValue' => 'Text',
    ];

    #[Override]
    public function getType(): string
    {
        return 'A test element';
    }

    #[Override]
    public function canView($member = null)
    {
        $check = Permission::checkMember($member, 'ADMIN');
        if ($check !== null) {
            return $check;
        }

        return parent::canView($member);
    }

    #[Override]
    public function getRenderTemplates($suffix = '')
    {
        return [
            __DIR__ . '/TestElement.ss'
        ];
    }
}
