<?php
/**
 * An exception for errors caused by Solarium-based wrapper classes,
 * Solarium's own exception are supposed to represent Solr technical errors, and this type should be used for
 * Shipserv logic errors
 *
 * @author  Yuriy Akopov
 * @date    2013-11-27
 * @story   S8855
 */
class Shipserv_Adapters_Solr_Exception extends Solarium_Exception {

}