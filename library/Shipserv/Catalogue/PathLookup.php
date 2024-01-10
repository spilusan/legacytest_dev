<?php

/**
 * Class Shipserv_Catalogue_PathLookup
 *
 * Lookup a redirect URL for the catalogue if we have only the catalogue_item_id
 */
class Shipserv_Catalogue_PathLookup
{
    protected $db;

    /**
     * Shipserv_Catalogue_PathLookup constructor.
     */
    public function __construct()
    {
        $this->db = Shipserv_Helper_Database::getDb();
    }

    /**
     * Get null if we cannot fetch the data, otherwise the hash part of the redirection
     *
     * @param integer $id
     * @return null|string
     */
    public function getCatalogueUrlByItemId($id)
    {
        $sql = '
            SELECT
              cf.catalogue_id, 
              ci.folder_id,
              cii.identifier
            FROM
              catalogue_item ci
              join catalogue_folder cf
                on cf.id = ci.folder_id
              join catalogue_item_identifier cii
                on (
                    cii.item_id = ci.id
                    and cii.identifier_type_id = 14
                )
            WHERE 
              ci.id=:id';

        $params = array(
            'id' => (int)$id
        );

        $result = $this->db->fetchAll($sql, $params);

        if (count($result) > 0) {
            return '#catalogue/' . $result[0]['CATALOGUE_ID'] . '/' . $result[0]['FOLDER_ID'] . '/' . urlencode($result[0]['IDENTIFIER']);
        }

        return '#catalogue';
    }
}
