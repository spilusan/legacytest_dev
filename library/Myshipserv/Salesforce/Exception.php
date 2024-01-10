<?php
/**
 * Base exception for SalesForce related problem
 *
 * @author  Yuriy Akopov
 * @date    2016-02-04
 * @story   S15735
 */
class Myshipserv_Salesforce_Exception extends Exception
{
    /**
     * Sends a notification email with exception details
     *
     * @author  Yuriy Akopov
     * @date    2017-05-30
     * @story   DE7384
     *
     * @param   string  $note
     *
     * @throws  Exception
     */
    public function sendNotification($note = null)
    {
        if (strlen($note) === 0) {
            $note = "Salesforce error";
        }

        $mail = new Myshipserv_SimpleEmail();
        $mail->setSubject($note . ":" . $this->getMessage());
        $mail->setBody(
            implode(
                PHP_EOL,
                array(
                    get_class($this),
                    $this->getMessage(),
                    "File: " . $this->getFile() . ", line: " . $this->getLine()
                )
            )
        );

        $mail->send(Myshipserv_Config::getSalesForceSyncReportEmail());
    }
}