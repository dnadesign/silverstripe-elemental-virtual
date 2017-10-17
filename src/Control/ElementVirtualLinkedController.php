<?php

namespace DNADesign\ElementalVirtual\Control;

use DNADesign\Elemental\Controllers\ElementController;
use Exception;

class ElementVirtualLinkedController extends ElementController
{

    /**
     * Returns the current element in scope rendered into its' holder
     *
     * @return HTML
     */
    public function ElementHolder()
    {
        return $this->renderWith('ElementHolder_VirtualLinked');
    }

    /**
     * @param string $action
     *
     * @return string
     */
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
    public function redirect($url, $code = 302)
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
        } catch (Exception $e) {
            $controller = $this->LinkedElement()->getController();
            $retVal = call_user_func_array(array($controller, $method), $arguments);
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

    public function hasAction($action)
    {
        if (parent::hasAction($action)) {
            return true;
        }

        $controller = $this->LinkedElement()->getController();

        return $controller->hasAction($action);
    }

    public function checkAccessAction($action)
    {
        if (parent::checkAccessAction($action)) {
            return true;
        }

        $controller = $this->LinkedElement()->getController();

        return $controller->checkAccessAction($action);
    }
}
