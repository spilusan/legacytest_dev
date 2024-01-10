<?php
/**
* Database object representation of main Buyer Usage Report database row
*/
class Shipserv_Report_SupplierUsage_Rec_RootRow
{
	//Dfault field values to make sure they will return something for frontend
	public $id;
	public $name;
	public $city;
	public $country;
	public $engLevel;
	public $gmv;
	public $membershipLevel;
	public $listingLevel;
	public $dateCompanyCreated;
	public $profileCompleteScore;
	public $spotlightListing;
	public $bannerAdvert;
	public $catalogueUploaded;
	public $ssoListing;
	public $brandOwner;
	public $adNetwork;
	public $showCustomersPrivacySetting;
	public $verifiedUserAccounts;
	public $nonVerifiedActiveUserAccounts;
	public $pendingJoinRequests;
	public $successfulSignIns;
	public $failedSignIns;
	public $searchEvents;
	public $supplierPageImpressions;
	public $contactRequests;
	public $pagesRfqsSent;
	public $activePromotion;
	public $activePromotionReportViews;
	public $buyersOnApPendingList;
	public $buyersOnApPromoteList;
	public $buyersOnApExcludeList;
	public $sir3ReportViews;

	/**
	* Constructor, Update row object public properties by the database row array
	* @param array $row Database fetched row
	* @param bool $extended Full text return for engagement level
	* @return unknown
	*/
	public function __construct($row = null, $extended = false)
	{
		//TODO fix it to proper fieldDef class, and create the cless
		$fieldDefs = Shipserv_Report_SupplierUsage_FieldDefinitions::getInstance();
		foreach ($fieldDefs->getReportFields() as $key => $value) {
			if (array_key_exists($value, $row)) {
				$this->$key = $row[$value];
			}
		}

		$engagementPercent = ($row['NR_DAYS'] > 0) ? $row['DAYS_WITH_EVENTS'] / $row['NR_DAYS'] * 100 : 0;

		if ($engagementPercent >= 50) {
			$this->engLevel = ($extended === false) ? 'good' : '>50% of the working days in the selected period have at least one event logged';
		} else if ($engagementPercent >= 1) {
			$this->engLevel = ($extended === false) ? 'medium' : 'At least one event and <50% of working days in the event have one event logged';
		} else {
			$this->engLevel = ($extended === false) ? 'bad' : 'No events during selected period';
		}

	}
}
