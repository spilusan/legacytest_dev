<?php
/**
 * @author  Yuriy Akopov
 * @date    2014-02-11
 */
class Shipserv_Adapters_Solr_Document extends Solarium_Document_ReadOnly {
    /**
     * Returns document score
     *
     * @return  float
     */
    public function getScore() {
        return (float) $this->{Shipserv_Adapters_Solr_Index::FIELD_SCORE};
    }

    /**
     * Returns document copy for update
     *
     * @return Solarium_Query_Update
     */
    public function getUpdateDocument() {
        $solr = new Solarium_Client();
        $updateQuery = $solr->createUpdate();

        $updateDocument = $updateQuery->createDocument($this->getFields());

        return $updateDocument;
    }
}