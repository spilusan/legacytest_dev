<?
	
class Myshipserv_View_Helper_HeadScript extends Zend_View_Helper_HeadScript
{
	public function createData($type, array $attributes, $content = null)
	{
		if (isset($attributes['src'])) {
			$config = $GLOBALS['application']->getBootstrap()->getOptions();
			$attributes['src'] = preg_replace('/^\/(images|css|js)\//', '/$1/' . Myshipserv_Config::getCachebusterTagAddition(), $attributes['src'], 1);
		}
		return parent::createData($type, $attributes, $content);
	}
	
}