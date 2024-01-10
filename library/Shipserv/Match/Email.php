<?php

/**
 * Prototype Class to handle emails and parse them for Match Tags.
 *
 * @todo: not in use as per 2013-10-01
 */
class Shipserv_Match_Email extends Shipserv_Object {

    public function __construct() {

    }

    public function poll() {
        $config = Zend_Registry::get('config');


        $mailConfig = array(
            'host' => $config->shipserv->match->emailPoller->server->url,
            'user' => $config->shipserv->match->emailPoller->username,
            'password' => $config->shipserv->match->emailPoller->password
        );

        $storage = new Zend_Mail_Storage_Imap($mailConfig);


        $match = new Shipserv_Match_Match(null);
        $arrayData = array();



        foreach ($storage as $mail) {

            $from = $mail->from;
            if (preg_match('/[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?/ix', $from, $regs)) {
                $from = $regs[0];
            }
            $subject = utf8_encode($mail->subject);
            if (!empty($subject)) {
                $arrayData[] = array('text' => $subject);
            }
            foreach (new RecursiveIteratorIterator($mail) as $part) {
                try {
                    if (strtok($part->contentType, ';') == 'text/plain') {
                        $foundPart = $part;
                        break;
                    }
                } catch (Zend_Mail_Exception $e) {
                    // ignore
                }
            }
            if ($foundPart) {
                $bodytext = $this->extractBodyText(trim(utf8_encode(quoted_printable_decode(strip_tags($foundPart)))));
            }
            $arrayData[] = array('text' => $bodytext);

            $results = $match->getMatchedSuppliersFromTextArray($arrayData);

            $this->sendResults($from, $results);
            //print_r($results);
            $arrayData = array();
            $uId = $storage->getUniqueId();

            //print_r($uId);

            $newmail = new Zend_Mail();
            $newmail->setFrom('email.match@shipserv.com');
            $newmail->addTo($from);
            $newmail->setSubject('Your Match Results!');
            //TODO: Loop through resutls getting Supplier info , append searched text also to the array
            $newmail->setBodyText(print_r($results, true));
            $newmail->send();
            unset($newmail);

            $storage->moveMessage($uId, "processed");
        }
    }

    private function sendResults($to, $results) {

    }

    private function extractBodyText($text) {
        $emptyCounter = 0;
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $text) as $line) {
            if (substr($line, 0, 2) == "--" || substr($line, 0, 2) == "__") {
                break;
            }

            if (empty($line)) {
                $emptyCounter++;
            } else {
                $emptyCounter = 0;
            }

            if ($emptyCounter >= 2) {
                break;
            }
            $wordcount = explode(" ", $line);
            if (trim($line) != "" and count($wordcount) <= 2) {
                break;
            }

            if (substr($line, 0, 5) == "[cid:" || substr(strtolower($line), 0, 5) == "from:") {
                break;
            }

            if (substr(strtolower($line), 0, 12) == 'sent from my') {
                break;
            }

            if (trim($line) == "") {
                continue;
            }
            if (substr($line, 0, 1) == ">") {
                break;
            }
            $output .= $line;
        }

        return $output;
    }

}

