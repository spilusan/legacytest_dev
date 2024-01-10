<?php

/**
 * This class will output an image overlayed with text result for ShipMate SIR Image report.
 *
 * Class Myshipserv_Shipmate_Sir_DownloadImgReport
 */
class Myshipserv_Shipmate_Sir_DownloadImgReport
{
    protected $downloadFileName;

    /**
     * Send the image to the output.
     *
     * @param array $params
     *
     * @throws Exception
     * @throws Myshipserv_Exception_MessagedException
     */
    public function getImageReport($params)
    {
        $sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
        if ($sessionActiveCompany->type !== 'v') {
            throw new Myshipserv_Exception_MessagedException('This report is only for Suppliers, please change your TNID', 403);
        }

        if (!(isset($params['startDate']) && isset($params['endDate']))) {
            throw new Myshipserv_Exception_MessagedException('startDate and endDate parameters are mandatory.', 500);
        }

        $startDate = $params['startDate'];
        $endDate = $params['endDate'];

        $convertedStartDate = Shipserv_DateTime::fromString($startDate);
        $convertedEndDate = Shipserv_DateTime::fromString($endDate);

        $tradeId = $sessionActiveCompany->id;
        $supplier = Shipserv_Supplier::fetch($tradeId, '', true);

        $premiumListing = (int) $supplier->premiumListing === 1;

        $brandAwarenessReport = new Shipserv_Report_Supplier_Insight_Brand();
        $leadGenerationReport = new Shipserv_Report_Supplier_Insight_LeadGeneration();

        $brandAwarenessReport->setDatePeriod($convertedStartDate, $convertedEndDate);
        $leadGenerationReport->setDatePeriod($convertedStartDate, $convertedEndDate);
        $brandAwarenessReport->setTnid($tradeId);
        $leadGenerationReport->setTnid($tradeId);

        $brandAwarenessData = $brandAwarenessReport->getData();
        $leadGenerationData = $leadGenerationReport->getData();

        $data = [
            'brandAwareness' => $brandAwarenessData,
            'leadGeneration' => $leadGenerationData,
            'premiumListing' => $premiumListing,
        ];

        $this->downloadFileName = 'SIR-'.$tradeId.'-'.$startDate.'-'.$endDate.'.jpg';
        $this->overlayTextAndExport($data);
    }

    /**
     * Do actual image manipulation.
     *
     * @param array $data
     *
     * @throws Myshipserv_Exception_MessagedException
     */
    protected function overlayTextAndExport($data)
    {
        $topOffset = 133;
        $onBoardInfluenceValue = ($data['brandAwareness']['onboard-influencer-ex'] === 'Y') ? '50,000' : '0';
        $bannerImpressionValue = number_format($data['brandAwareness']['banner-impression']);

        $searchImpressionValue = number_format($data['brandAwareness']['search-impression']);
        $profileViewValue = number_format($data['leadGeneration']['profile-view']);

        $imgPath = realpath(APPLICATION_PATH.'/../resources/shipmate/sir/');

        // Choose which template to render
        if ($data['premiumListing']) {
            $imageOverlay = new Myshipserv_Image_TextOverlay($imgPath.'/Premium_SIR.jpg');
        } else {
            $imageOverlay = new Myshipserv_Image_TextOverlay($imgPath.'/Basic_SIR.jpg');
        }

        // Create text nodes
        $onBoardInfluenceNode = new Myshipserv_Image_TextNode($onBoardInfluenceValue, 232 + $topOffset, 600);
        $advertBannerImpressionNode = new Myshipserv_Image_TextNode($bannerImpressionValue, 307 + $topOffset, 600);
        $searchImpressionNode = new Myshipserv_Image_TextNode($searchImpressionValue, 377 + $topOffset, 600);
        $profileViewNode = new Myshipserv_Image_TextNode($profileViewValue, 380 + $topOffset, 120);

        // Set font size for text nodes
        $onBoardInfluenceNode->setFontSize(14);
        $advertBannerImpressionNode->setFontSize(14);
        $searchImpressionNode->setFontSize(14);
        $profileViewNode->setFontSize(16);

        // Adjust alignments for text nodes
        $onBoardInfluenceNode->setHorizontalAlign(Myshipserv_Image_TextNode::AL_RIGHT);
        $advertBannerImpressionNode->setHorizontalAlign(Myshipserv_Image_TextNode::AL_RIGHT);
        $searchImpressionNode->setHorizontalAlign(Myshipserv_Image_TextNode::AL_RIGHT);
        $profileViewNode->setHorizontalAlign(Myshipserv_Image_TextNode::AL_CENTER);

        // Set colour and font for Profile View node
        $profileViewNode->setColor('#0a2b54');
        $profileViewNode->setFont('/Lato2OFL/Lato-Black.ttf');

        // Apply text overlay on images
        $imageOverlay->addTextNode($onBoardInfluenceNode);
        $imageOverlay->addTextNode($advertBannerImpressionNode);
        $imageOverlay->addTextNode($searchImpressionNode);
        $imageOverlay->addTextNode($profileViewNode);

        // Render image to browser
        $imageOverlay->renderAndDownload($this->downloadFileName);

        // we have to exit the process here as image will be rendered as a HTML response
        exit;
    }
}
