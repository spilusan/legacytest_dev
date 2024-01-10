<?php

class Myshipserv_SystemAlerts_SystemAlert {

    private $emailList = "";
    private $sendToSysLog = false;
    //This file will contain a summary of exception (ID and error message)
    private $todaysFileSummary = "";
    //This will contain the full stack trace and ID's related to above.
    private $todaysFileVerbose = "";
    private $todaysEmailRecord = "";
    private $hourlyEmailLimit = 20;
    private $maximumRecallErrorCount = 200;
    private $config;
    private $enabled = true;
    private $uniqueRef = "";
    private $logfileLocation = "/prod/logs/pages/error/verbose/";
    private $params = "";
    
    private $errorSummary;
    private $errorVerbose;
    public $displayError = false;
	
	public $emailAllExceptions = false;
    

    public function __construct() 
    {

        $this->config = Zend_Registry::get('config');

        $this->enabled = (bool) $this->config->shipserv->systemAlerts->enabled;

        if ($this->enabled) {

        	if (file_exists($this->logfileLocation) == false) {
        		mkdir($this->logfileLocation, 0777, true); 
        	}
        	
            $this->todaysFileSummary = date('Y') . date('n') . date('d') . '_summary.errlog';
            $this->todaysFileVerbose = date('Y') . date('n') . date('d') . '_verbose.errlog';
            $this->todaysEmailRecord = date('Y') . date('n') . date('d') . '_email.log';
            $this->emailList        = $this->config->shipserv->systemAlerts->emailList;
            $this->hourlyEmailLimit = $this->config->shipserv->systemAlerts->hourlyEmailLimit;
            $this->sendToSysLog     = $this->config->shipserv->systemAlerts->sendToSyslog;
            $this->displayError     = (bool) $this->config->shipserv->systemAlerts->displayError;
            $this->uniqueRef        = date('YmdHis');
			$this->emailAllExceptions = (bool) $this->config->shipserv->systemAlerts->emailAllExceptions;
        }
    }

    
    public function recordError($exception, $requestParams = "") 
    {
        if (!($exception instanceof Exception)) {
            $exception = new Exception('Myshipserv_SystemAlerts_SystemAlert::recordError did not receive an exception as first param. This is a dummy one!');
        }
        
        if ($this->enabled) {
            
            $errorCode = $exception->getCode();
           
            //Check if its an unwanted user like McAfee Web Security 
            $u = Shipserv_User::isLoggedIn();
            $userDetails = $u ? print_r($u, true) : "No user logged in";
            try{
            	if (strstr(strtolower($_SERVER['HTTP_USER_AGENT']), 'scanalert')) {
                	return false;
                }
                                	
                if ($u) {
                	if($u->__get('userId') == '8032795' ) {
                        return false;
                    }
                }
            } catch(Exception $e) {
                //Do nothing. 
            }
            
            $this->params = var_export($requestParams, true);
            if ($this->sendToSysLog) {
                //Write this exception to the syslog
                $syslogWriter = new Zend_Log_Writer_Syslog();
                $syslogWriter->setApplicationName('Pages');
				
                $syslogWriter->write($exception->getMessage());
                unset($syslogWriter);
            }

			$hashedError = md5($errorCode . $exception->getLine() . $exception->getFile());
			$summaryFile = $this->logfileLocation . $this->todaysFileSummary;

            //$simErrCount = $this->similarErrorCount($exception->getMessage());
            
            $fh = fopen($summaryFile, 'a+');
			
            $errorSummary = $this->uniqueRef . "|<<Code" . $errorCode . ">>|" . $hashedError . "\n";
            $this->errorSummary = $errorSummary;

            fwrite($fh, $errorSummary);
            fclose($fh);

            unset($fh);
            
           $getVariables = print_r($_GET, true);
           $postVariables = print_r($_POST, true);
           $cookieVariables = print_r($_COOKIE, true);
            
	    //Are we behind Weblogic Proxy?
	   $ipAddress = Myshipserv_Config::getUserIp();
           
	    
            $verboseDetails = <<<EOT
vvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv            
ID:
{$this->uniqueRef}
           
Message:
{$exception->getMessage()}

Error Code:
{$errorCode}

Error hash (particular to this error, line no. and file):
{$hashedError}

Location:
    Line - {$exception->getLine()}; 
    File - {$exception->getFile()}
            
Stack Trace:
{$exception->getTraceAsString()}

Request Params:
{$this->params}

GET/POST/COOKIE Vars:
GET    :{$getVariables}
POST   :{$postVariables}
COOKIES:{$cookieVariables}
    
User details:
IP: {$ipAddress}
Referrer: {$_SERVER['HTTP_REFERER']}
User-Agent: {$_SERVER['HTTP_USER_AGENT']}

{$userDetails}
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
EOT;
            
            $this->errorVerbose = $verboseDetails;

            $verboseFile = $this->logfileLocation . $this->todaysFileVerbose;
            $fh = fopen($verboseFile, 'a');

            fwrite($fh, $verboseDetails . "\n\n");
            fclose($fh);

            unset($fh);
            
            //Hand the actual sending of the message off to a dedicated proc.
            $this->sendAlert($hashedError);
            
        }
        
        
        
    }

 

    /**
     * This function will call the shell command to run tail -n, pipe to a temp 
     * file and return the results by reading the file and then destroying it.
     * 
     * @param int $lines
     * @param string $file
     * @return string
     * */
    private function phpTail($lines, $file) 
    {
        if(file_exists("/tmp/shellExecOut.txt")){
            unlink("/tmp/shellExecOut.txt");
        }
        if(file_exists($file)){
            shell_exec("tail -n " . (string)$lines . " $file 2> /dev/null > /tmp/shellExecOut.txt");
            $output = file_get_contents("/tmp/shellExecOut.txt");
            if(file_exists("/tmp/shellExecOut.txt")){
                unlink("/tmp/shellExecOut.txt");
            }   
            return $output;
        }else{
            return false;
        }
    }
    
    
    /**
     *  This checks if its ok to send an email, and sends it if so. A record is added to the email log then.
     * @param string $errorMessage 
     */
    private function sendAlert($code)
    {
        // @TODO PHP upgrade, verify if it's workiing

        return;
        $sendEmail = false;
        
        $sendingCircumstances = $this->allowEmailSend($code);
        
        $sendEmail = $sendingCircumstances;        
        
        //Can we send the email?
        if ($sendingCircumstances['send'] == true){
            $subject = 'Pages Exception in ' . APPLICATION_ENV . '. ';
            $bodyText = "";
            if (@$sendingCircumstances['exceptionalCircumstance']){
                $subjectAppend = "Error code has appeared " . $sendingCircumstances['totalOccurences'] . " times today in total.";
                $bodyText ="WARNING!!\n$subjectAppend\n\n";
            } elseif (@$sendingCircumstances['firstOccurance']){
                $bodyText = "This is a first occurence of this error today.\n\n";
            }
            $zm = new Zend_Mail('UTF-8');
            $zm->setFrom('info@shipserv.com', 'ShipServ Pages');
            $zm->setSubject($subject . $subjectAppend);
            
            $emailList = explode(",",$this->emailList);
            foreach ($emailList as $email){
                $zm->addTo($email);
            }

            $appEnv = APPLICATION_ENV;
            
            $bodyText .= "The following exception was recorded on $appEnv:\n\n$this->errorVerbose";  
            
            $zm->setBodyText($bodyText);

            $zm->send();
            //Record this send
            $fh = fopen($this->logfileLocation . $this->todaysEmailRecord, 'a');
            fwrite($fh, $this->uniqueRef . "\n");
            fclose($fh);
            unset($fh);
            return true;
        } else {
            return false;
        }
    }
     
    /**
     *  Use shell command to search logs for occurences of a file. 
     * 
     * @param string $string
     * @param string $file
     * @return integer 
     */
    private function findOccurrencesInFile($string, $file)
    {
        if (file_exists($file)) {
            $count = shell_exec('grep "' . escapeshellcmd($string) . '" ' . $file . ' | wc -l');
            return (int)$count;
        } else {
            return 0;
        }
    }
    
    /**
     * This runs through a number of checks to see if its OK to send an email.
     * @param string $code
     * @return array containing details of the sending circumstances
     */
    private function allowEmailSend($code)
    {
    	
        $return = array();
        if (!isset($code)) {
            return array('send' => false);
        }
		//Is all email set?
		if ($this->emailAllExceptions) {
            return array('send' => true);
		}
		
        //First set up some parameters we will need.
        $totalOccurences = $this->findOccurrencesInFile($code, $this->logfileLocation . $this->todaysFileSummary);
        if ($totalOccurences <= 1) {
            $firstOccurence = true;
        }
        
        //Look at the recent email log, retreive the hourly limit and compare the first record to see if it was sent less than an hour ago
        $emailRecordTail = $this->phpTail($this->hourlyEmailLimit,$this->logfileLocation . $this->todaysEmailRecord);
        $emailRecords = explode("\n", $emailRecordTail);
        
        //If no record was found, or the total number of records returned is less than hourly limit, or this is the first occurrence of this error recently
        if (!$emailRecordTail || count($emailRecords) < $this->hourlyEmailLimit ){
            $hourlyLimitHit = false;
        } else {
            //If the first record in the list returned is over an hour old, we can also send.
            $lastRecord = $emailRecords[0];
            $lastEmailTime = explode("|",$lastRecord);
            $lastEmailTime = $lastEmailTime[0];
            $oneHourAgo = date("YmdHis", strtotime("-1 hour"));
            if ($oneHourAgo > $lastEmailTime) {
                $hourlyLimitHit = false;
            } else {
                $hourlyLimitHit = true;
            }
        }
        
        
        $firstExtCirc = (int)($this->maximumRecallErrorCount / 10);
        $secondExtCirc = (int)($this->maximumRecallErrorCount / 4);
        if ((int)$totalOccurences == $firstExtCirc || (int)$totalOccurences == $secondExtCirc) {
            $exceptionalCircumstance = true;            
        }
        
        if (($firstOccurence && !$hourlyLimitHit) || $exceptionalCircumstance) {
            $return = array(
                'firstOccurance' => $firstOccurence,
                'exceptionalCircumstance' => $exceptionalCircumstance,
                'totalOccurences' => $totalOccurences,
                'send' => true                
            );
        } else {
        	$return = array(
        		'firstOccurance' => $firstOccurence,
        		'exceptionalCircumstance' => $exceptionalCircumstance,
        		'totalOccurences' => $totalOccurences,
        		'send' => false
        	);
        	
        }
        
        return $return;
    }
}