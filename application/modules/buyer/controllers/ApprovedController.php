<?php
/**
 * A controller for JSON report services relating to supplier data
 *
 * @author  Attila O
 * @date    20156-03-31
 */
class Buyer_ApprovedController extends Myshipserv_Controller_Action {

        CONST
            ITEM_PER_PAGE = 20
            ;
	 /**
     * Returns JSON of all suppliers 
     *
     * @author Attila O
     * @date 2015-04-31
     */
    public function approvedSupplierListAction()
    {
        $currentPage = (int) $this->params['currentPage'];
        $keyword = htmlentities($this->params['keyword']);
        $listOrder = (int) $this->params['listOrder'];
        $isAsc = ((int) $this->params['isAsc'] == 1);
        $buyerOrg = $this->getUserBuyerOrg();
        $adapter = new Shipserv_Report_Supplier_Branches();
        $this->_helper->json((array)$adapter->getSupplierList($buyerOrg->byoOrgCode, $keyword, $listOrder, $isAsc, $currentPage,  self::ITEM_PER_PAGE));
    }

    /**
    * Export file to CSV, sending the headers, tudning off layout, template
    */
    public function approvedSupplierExportAction()
    {

        set_time_limit( 0 );

        $buyerOrg = $this->getUserBuyerOrg();
        $adapter = new Shipserv_Report_Supplier_Branches();

        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $this->getResponse()->setRawHeader( "Content-Type: application/vnd.ms-excel; charset=UTF-8" )
            ->setRawHeader( "Content-Disposition: attachment; filename=export.csv" )
            ->setRawHeader( "Expires: 0" )
            ->setRawHeader( "Cache-Control: must-revalidate, post-check=0, pre-check=0" )
            ->setRawHeader( "Pragma: public" );
        
        echo $adapter->getSupplierExcelList($buyerOrg->byoOrgCode);
    }

    public function approvedSupplierStateAction()
    {
        $state = (int)$this->params['checkedState'];

        //Logging event
        $user = Shipserv_User::isLoggedIn();
        if ($user) {
            switch ($state) {
                case 1:
                    $user->logActivity(Shipserv_User_Activity::ACM_ACTIVATED, 'PAGES_USER', $user->userId, $user->email);
                    break;
                case 0:
                    $user->logActivity(Shipserv_User_Activity::ACM_DEACTIVATED, 'PAGES_USER', $user->userId, $user->email);
                    break;
                default:
                    //There is no event to log
                    break;
            }
        }

        $buyerOrg = $this->getUserBuyerOrg();
        $adapter = new Shipserv_Report_Supplier_SetApprovedSupplierState();
        $this->_helper->json((array)$adapter->setRaseAlertState($buyerOrg->byoOrgCode, $state));
    }

    public function approvedSupplierEmailsAction()
    {
        $type = $this->params['type'];
        $buyerOrg = $this->getUserBuyerOrg();
        $adapter = new Shipserv_Report_Supplier_ApprovedSupplierEmail();
        switch ($type) {
            case 'get':
                    $this->_helper->json((array)$adapter->getEmailList($buyerOrg->byoOrgCode));
                break;
            case 'set':
                    $email = $this->params['email'];
                    $this->_helper->json((array)$adapter->insertEmail($buyerOrg->byoOrgCode, $email));
                break;
             case 'remove':
                   $email = $this->params['email'];
                   $this->_helper->json((array)$adapter->removeEmail($buyerOrg->byoOrgCode, $email));
                break;
            
            default:
               throw new Exception("Invalid request, use get/set/remove", 500);
               break;
        }
    }
}