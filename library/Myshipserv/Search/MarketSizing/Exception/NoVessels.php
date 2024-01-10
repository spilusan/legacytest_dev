<?php
/**
 * Is thrown when no vessels satisfy the required GMV filter so we can to return 0 without running the query
 *
 * @author  Yuriy Akopov
 * @date    2014-01-12
 * @story   S12169
 */
class Myshipserv_Search_MarketSizing_Exception_NoVessels extends Myshipserv_Exception_MarketSizing {

}