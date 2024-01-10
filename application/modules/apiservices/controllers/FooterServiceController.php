
<?php
/**
 * Class Apiservices_FooterServiceController
 *
 * general Micro services to provide support for the new ShipServ architecture
 * this is a temporary solution until real Java microservices can take over
 * Provides the current footer
 *
 */

class Apiservices_FooterServiceController extends Myshipserv_Controller_RestController
{
    /**
     * Pre dispatch is overridden here to disable layout and force skipping authentication
     *
     * @return undefined|void
     */
    public function preDispatch()
    {
        $this->_helper->layout()->disableLayout();
        parent::preDispatch(false);
    }

    /**
     * init is overridden here to skip authentication (must be done here and pre-dispatch as well)
     *
     */
    public function init()
    {
        parent::init(false);
    }

    /**
     * Maybe called on get request, and redirected to getAction
     * @return null
     */
    public function indexAction()
    {
        $this->_helper->viewRenderer('get');
        $this->getAction();
    }

    /**
     * This action going to return the HTML representation in an array of our footer partial file
     * for to be used in the new architecture
     *
     * @return null
     */
    public function getAction()
    {
        // render footer content
        $htmlToArray = new Myshipserv_ApiServices_HtmlToArray();
        $customView = new Zend_View();
        $customView->setScriptPath(APPLICATION_PATH . "/views/scripts/");
        $footerAsText = $customView->render('partials/layout/footer.phtml');

        // parse HTML content, and convert it to an array from the element ID shipservFooterRow
        $resultArray = $htmlToArray->convertHtmlToArray($footerAsText, 'shipservFooterRow');
        $this->view->json = $resultArray;

    }

}