<?php
class Myshipserv_View_Helper_Printable extends Zend_View_Helper_Abstract
{
	public function Printable() {
        return $this;
	}
    
    public function init() {
        
    }
    
    public function generateURL($doctype, $docid, $user, $branchcode, $supplierTnid = "") {
    	
        $query = http_build_query(array(
            'docid' => $docid,
            'usercode' => $user->usercode,
            'branchcode' => $branchcode,
            'doctype' => $doctype == 'PO' ? 'ORD' : $doctype,
            'custtype' => $user->isAdmin ? 'admin' : 'buyer',
        	'supbranchcode' => $supplierTnid,
            'md5' => $user->md5code
        ));
        
        return "http://" . Shipserv_Object::getHostname() . "/printables/app/print?".$query;          
    }
}