<?php

namespace DNADesign\ElementalVirtual\Control;

use Override;
use DNADesign\Elemental\Controllers\ElementController;
use Exception;
use SilverStripe\Control\HTTPResponse;

class ElementVirtualLinkedController extends ElementController
{
    /**
     * @param string $action
     *
     * @return string
     */
    #[Override]
    public function Link($action = null)
    {
        if ($this->data()->virtualOwner) {
            $controller = ElementController::create($this->data()->virtualOwner);

            return $controller->Link($action);
        }

        return parent::Link($action);
    }

    /**
     * if this is a virtual request, change the hash if set.
     *
     * @param string $url
     * @param int $code
     *
     * @return HTTPResponse
     */
    #[Override]
    public function redirect(string $url, int $code = 302): HTTPResponse
    {
        if ($this->data()->virtualOwner) {
            $parts = explode('#', $url);
            if (isset($parts[1])) {
                $url = $parts[0] . '#' . $this->data()->virtualOwner->ID;
            }
        }

        return parent::redirect($url, $code);
    }



    public function __call($method, $arguments)
    {
        try {
            $retVal = parent::__call($method, $arguments);
        } catch (Exception) {
            $controller = $this->LinkedElement()->getController();
            $retVal = $controller->{$method}(...$arguments);
        }

        return $retVal;
    }

    public function hasMethod($action)
    {
        if (parent::hasMethod($action)) {
            return true;
        }

        $controller = $this->LinkedElement()->getController();
        return $controller->hasMethod($action);
    }

    #[Override]
    public function hasAction($action)
    {
        if (parent::hasAction($action)) {
            return true;
        }

        $controller = $this->LinkedElement()->getController();

        return $controller->hasAction($action);
    }

    #[Override]
    public function checkAccessAction($action)
    {
        if (parent::checkAccessAction($action)) {
            return true;
        }

        $controller = $this->LinkedElement()->getController();

        return $controller->checkAccessAction($action);
    }
}
