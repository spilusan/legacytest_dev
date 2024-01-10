<?php
/**
* Database object representation of main Buyer Usage Report database row
*/
class Shipserv_Report_Usage_Rec_RootRow
{
	//Dfault field values to make sure they will return something for frontend
	public $id;
	public $name;
	public $engLevel;
	public $activeTradingAccounts;
	public $dateCompanyCreated;
	public $companyAnonymityLevel;
	public $verifiedUserAccounts;
	public $nonVerifiedActiveUserAccounts;
	public $pendingJoinRequests;
	public $successfulSignIns;
	public $failedSignIns;
	public $searchEvents;
	public $supplierPageImpressions;
	public $contactRequests;
	public $pagesRFQsSent;
	public $reviewRequestsReceived;
	public $reviewsSubmitted;
	public $buyTabEvents;
	public $spendBenchmarkingReportEvents;
	public $matchDashboardEvents;
	public $matchReportEvents;
	public $sprReportViews;
	public $transactionMonitorEvents;
	public $webReporterEvents;
	public $impaPriceBenchmarkingEvents;
	public $impaSpendTrackerEvents;
	public $totalSpendReportEvents;
	public $automaticComplianceMonitoringActivated;
	public $approvedSuppliers;
	public $branchesWithRfqAutomaticRemindersActivated;
	public $branchesWithPoAutomaticRemindersActivated;
	public $branchesWithSpendBenchmarkingActivated;
	public $daysWithEvents;
	public $rfqOrdWoImoCount;
	public $sprKpReportsEnabled;

	/**
	* Constructor, Update row object public properties by the database row array
	* @param array $row Database fetched row
	* @param bool $extended Full text return for engagement level
	* @return unknown
	*/
	public function __construct($row = null, $extended = false)
	{
		$fieldDefs = Shipserv_Report_Usage_FieldDefinitions::getInstance();
		foreach ($fieldDefs->getReportFields() as $key => $value) {
			if (array_key_exists($value, $row)) {
				$this->$key = $row[$value];
			}
		}

		$this->automaticComplianceMonitoringActivated = ($row['AUTOMATIC_COMPLIANCE'] == 0) ? 'N' : 'Y';
		$engagementPercent = ($row['NR_DAYS'] > 0) ? $row['DAYS_WITH_EVENTS'] / $row['NR_DAYS'] * 100 : 0;
		
		if ($engagementPercent >= 50) {
			$this->engLevel = ($extended === false) ? 'good' : '>50% of the working days in the selected period have at least one event logged';
		} else if ($engagementPercent >= 1) {
			$this->engLevel = ($extended === false) ? 'medium' : 'At least one event and <50% of working days in the event have one event logged';
		} else {
			$this->engLevel = ($extended === false) ? 'bad' : 'No events during selected period';
		}

		$this->sprKpReportsEnabled = (Shipserv_User::getAccessSprKpi($row['BYB_BYO_ORG_CODE']) === Shipserv_User::ACCESS_SPR_KP_NO) ? 'No' : 'Yes';
	}
}
