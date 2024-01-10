<?php 

class Shipserv_Helper_Validator {
    
    //Static only!
    private function __construct(){}
    
    /**
     * is a valid imo (ship id)
     * 
     */
    public static function isIMO($var){        
    //        IMO
    //
    //^[4-9][0-9]{6,6}$
    //Assert position at the beginning of a line (at beginning of the string or after a line break character) «^»
    //Match a single character in the range between “4” and “9” «[4-9]»
    //Match a single character in the range between “0” and “9” «[0-9]{6,6}»
    //   Exactly 6 times «{6,6}»
    //Assert position at the end of a line (at the end of the string or before a line break character) «$»
    //
    //
    //Created with RegexBuddy
        return preg_match('/^[4-9][0-9]{6,6}$/');            
    }
    
    public static function isIMPACode($var){
        return preg_match('/^[0-9][0-9][ -.]?[0-9][0-9][ -.]?[0-9][0-9]|[0-9]{3,3}[ -.]?[0-9]{3,3}$/');
    }
    
}