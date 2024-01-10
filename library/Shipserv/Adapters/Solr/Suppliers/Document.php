<?php
/**
 * Represents a document from Suppliers Solr index
 *
 * @author  Yuriy Akopov
 * @date    2014-02-11
 * @story   S9493
 */
class Shipserv_Adapters_Solr_Suppliers_Document extends Shipserv_Adapters_Solr_Document {
    /**
     * @return  int
     */
    public function getId() {
        return (int) $this->{Shipserv_Adapters_Solr_Suppliers_Index::FIELD_ID};
    }

    /**
     * @return  string
     */
    public function getDescription() {
        return $this->{Shipserv_Adapters_Solr_Suppliers_Index::FIELD_DESCRIPTION};
    }

    /**
     * @return  string
     */
    public function getName() {
        return $this->{Shipserv_Adapters_Solr_Suppliers_Index::FIELD_NAME};
    }

    /**
     * @return  string
     */
    public function getCountry() {
        return $this->{Shipserv_Adapters_Solr_Suppliers_Index::FIELD_COUNTRY};
    }
}