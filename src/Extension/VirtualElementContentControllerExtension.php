<?php

namespace DNADesign\ElementalVirtual\Extensions;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Core\Extension;

class VirtualElementalContentControllerExtension extends Extension
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'handleElement'
    ];

    public function handleElement()
    {
        $id = $this->owner->getRequest()->param('ID');

        if (!$id) {
            return $this->owner->httpError(400, 'no element ID provided');
        }

        $element = BaseElement::get()->filter('ID', $id)->First();
        $page = $this->owner->data();

        if ($element && $element->canView()) {
            $useElement = clone $element;

            // modify the element to appear on the correct 'Page' so that
            // any breadcrumbs and titles are correct.
            $elementalAreaRelations = $this->owner->getElementalRelations();
            $id = null;

            foreach ($elementalAreaRelations as $elementalAreaRelation) {
                $id = $page->$elementalAreaRelation()->ID;

                if ($id) {
                    break;
                }
            }

            $useElement->ParentID = $id;
            $useElement->setAreaRelationNameCache($elementalAreaRelation);
            $controller = $useElement->getController();

            return $controller;
        }

        return $this->owner->httpError(404);
    }
}
