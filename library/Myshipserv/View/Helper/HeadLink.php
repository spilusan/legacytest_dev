<?php

class Myshipserv_View_Helper_HeadLink extends Zend_View_Helper_HeadLink
{
    public function createData(array $attributes)
    {
		if (isset($attributes['href'])) {
			$config = $GLOBALS['application']->getBootstrap()->getOptions();
			$attributes['href'] = preg_replace('/^\/(images|css|js)\//', '/$1/' . Myshipserv_Config::getCachebusterTagAddition(), $attributes['href'], 1);
		}
        return parent::createData($attributes);
    }
	
}