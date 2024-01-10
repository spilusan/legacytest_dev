<?php
class Shipserv_ProductServices extends Shipserv_Object{
	
	protected $id;
	public $products;
	protected $supplier;
	
	public static function getInstanceBySupplier( $supplier )
	{
		$object = new self();
		$object->supplier = $supplier;
				
		$products['premiumListing'] = $object->hasPremiumListing();
		$products['smartSupplier'] = $object->hasSmartSupplier();
		$products['catalogue'] = $object->hasCatalogue();
		$products['bannerAdvert'] = $object->hasBannerAdvert();
		$products['adNetwork'] = $object->hasAdNetwork();
		$products['MAListing'] = $object->hasMAListing();
		$products['spotlight'] = $object->hasSpotlight();
		$products['tradeNet'] = $object->hasTradeNet();
		$products['brandVerification'] = $object->hasBrandVerification();
		
		$object->products = $products;
		return $object;
	}
	
	private function hasBrandVerification()
	{
		$sql = "
		SELECT COUNT(*) TOTAL 
		FROM 
		  pages_company_brands
		WHERE
		  pcb_company_id=:tnid
		  AND pcb_auth_level='OWN'
		  AND pcb_is_authorised='Y'
		  AND pcb_is_deleted='N'
		";
		$row = $this->getDb()->fetchAll($sql, array("tnid" => $this->supplier->tnid));
		return ($row[0]['TOTAL']>0)?true:false;		
	}
	
	private function hasBannerAdvert()
	{
		$sql = "select COUNT(*) TOTAL from pages_active_banner where pab_tnid=:tnid";
		$row = $this->getDb()->fetchAll($sql, array("tnid" => $this->supplier->tnid));
		return ($row[0]['TOTAL']>0)?true:false;
	}
	
	private function hasPremiumListing()
	{
		return $this->supplier->isPremium();
	}

	private function hasSmartSupplier()
	{
		$sql = "select COUNT(*) TOTAL from supplier_branch where spb_smart_product_name = 'SmartSupplier' and spb_sts = 'ACT' and spb_list_in_suppliier_dir = 'Y' and spb_account_deleted = 'N' AND spb_branch_code=:tnid";
		$row = $this->getDb()->fetchAll($sql, array("tnid" => $this->supplier->tnid));
		return ($row[0]['TOTAL']>0)?true:false;
	}
	private function hasCatalogue()
	{
		return ( $this->supplier->onlineCatalogue != 0 && $this->supplier->onlineCatalogue != "" ) ? true: false;
		
		return false;
	}
	private function hasAdNetwork()
	{
		return false;
	}
	private function hasMAListing()
	{
		$sql = "select COUNT(*) TOTAL from directory_entry_attachment where is_ma = 1 and deleted_on_utc is null AND supplier_branch_code=:tnid";
		$row = $this->getDb()->fetchAll($sql, array("tnid" => $this->supplier->tnid));
		return ($row[0]['TOTAL']>0)?true:false;
	}
	private function hasSpotlight()
	{
		$sql = "select COUNT(*) TOTAL from pages_spotlight_listing where sysdate >= psl_expiration_from_date and sysdate <= psl_expiration_to_date AND
		 psl_spb_branch_code=:tnid";
		$row = $this->getDb()->fetchAll($sql, array("tnid" => $this->supplier->tnid));
		return ($row[0]['TOTAL']>0)?true:false;
	}
	private function hasTradeNet()
	{
		$sql = "select COUNT(*) TOTAL from supplier_branch where spb_sts = 'ACT' and spb_list_in_suppliier_dir = 'Y' and spb_account_deleted = 'N' AND spb_branch_code=:tnid";
		$row = $this->getDb()->fetchAll($sql, array("tnid" => $this->supplier->tnid));
		return ($row[0]['TOTAL']>0)?true:false;
	}
	
	public static function getInstanceByBuyerId( $tnid )
	{
		
	}
	
}