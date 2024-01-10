<?php
/**
 * Defines methods for our ActiveRecord-like classes
 *
 * As we don't use ActiveRecord pattern widely in the app for historical reasons only bunch of new classes added by
 * Yuriy Akopov are built in that way so far.
 *
 * We are yet to agree over the implementation and usefulness for other developers, so this has been defined as in interface
 * yet to allow new classes to inherited from the legacy ones if needed and older classes to be modified in accordance to
 * the pattern
 *
 * @todo: we will need to develop a proper parent class or set of 5.4 traits one day if other developers agree it's worth it
 *
 * @author  Yuriy Akopov
 * @date    2013-08-19
 * @story   S7924
 */
interface Shipserv_Helper_Database_Object {
    // Constants in interfaces don't work properly in PHP 5.2: https://bugs.php.net/bug.php?id=49472
    /*
    const
        TABLE_NAME = null,
        COL_ID = null
    ;
    */

    /**
     * Initialises object's member fields with values loaded from TABLE_NAME table into an associative array
     *
     * @param   array   $fields
     */
    function fromDbRow(array $fields);

    /**
     * Returns an associative array of columns values ready to be inserted into TABLE_NAME table built from
     * object's member fields
     *
     * @return array
     */
    function toDbRow();

    /**
     * Factory method that reads the specified row from TABLE_NAME table and creates a new object initialised from
     * that row using fromDbRow()
     *
     * @param   int $id
     *
     * @return  Shipserv_Helper_Database_Object
     * @throws  Shipserv_Helper_Database_Exception
     */
    static function getInstanceById($id);

    /**
     * If primary key member variable is null, inserts a new record into TABLE_NAME table, otherwise updates
     * a corresponding one
     */
    function save();

    /**
     * Returns the value of primary key member variable
     *
     * @return int|null
     */
    function getId();
}