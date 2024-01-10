<?php
/**
 * Exception for problems related to synchronising Consortia data between Oracle and Salesforce
 *
 * Not very elegant here because we can inherit from either Myshipserv_Consortia_Exception or
 * Myshipserv_Salesforce_Exception which aren't in the same bloodline.
 *
 * @author  Yuriy Akopov
 * @date    2017-11-30
 * @story   DEV-1170
 */
class Myshipserv_Salesforce_Consortia_Exception extends Myshipserv_Salesforce_Exception
{

}