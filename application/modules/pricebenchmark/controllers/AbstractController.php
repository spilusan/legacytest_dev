<?php
/**
 * Parent class for Price Benchmarking and Price Benchmarking API functions
 *
 * @author  Yuriy Akopov
 * @date    2015-05-22
 */
abstract class PriceBenchmark_AbstractController extends Myshipserv_Controller_Action {
    /**
     * @var Shipserv_Buyer
     */
    protected $buyerOrg = null;

    public function init() {
        parent::init();

        if (!Shipserv_PriceBenchmark::checkUserAccess($this)) {
            throw new Myshipserv_Exception_MessagedException("You are not authorised to access Price Benchmarking and Price Tracker API", 403);
        }

        $this->buyerOrg = $this->getUserBuyerOrg();
    }
}