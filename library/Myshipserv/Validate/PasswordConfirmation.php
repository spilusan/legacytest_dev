<?php

class Myshipserv_Validate_PasswordConfirmation extends Zend_Validate_Abstract
{
    const NOT_MATCH = 'notMatch';
	
	// A default that works for registration form
	private $confirmField = 'registerConfirmPassword';
	
    protected $_messageTemplates = array(
        self::NOT_MATCH => 'Password confirmation does not match'
    );

    public function isValid($value, $context = null)
    {
        $value = (string) $value;
        $this->_setValue($value);
		
        if (is_array($context)) {
            if (isset($context[$this->confirmField])
                && ($value == $context[$this->confirmField]))
            {
                return true;
            }
        } elseif (is_string($context) && ($value == $context)) {
            return true;
        }
		
        $this->_error(self::NOT_MATCH);
        return false;
    }
	
	public function setConfirmFieldName ($confirmField)
	{
		$this->confirmField = $confirmField;
	}
}
