<?php

class Eml_Logger
{
	private function __construct () { }
	
	public static function log ($msg)
	{
		echo date('Y-m-d H:i:s') . ' ' . $msg . "\n";
	}
}

class Eml_Emailer
{
	private $notificationMan;
	
	public function __construct ()
	{
		$this->notificationMan = new Myshipserv_NotificationManager(self::getDb());
	}
	
	/**
	 * Send review request e-mails to users due a notification
	 * 
	 * @return null
	 */
	public function run ()
	{
		// Iterator over users to be e-mailed
		$it = new Eml_DueEmailIterator();
		while (true)
		{
			// Read batch of users from iterator, or exit if none
			$uQ = $this->readBatch($it);
			if (!$uQ)
			{
				break;
			}
			
			// Send e-mails for this batch of users
			$this->sendEmails($uQ, $it);
		}
	}
	
	private static function getDb ()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}
	
	/**
	 * Read batch of users from iterator
	 *
	 * @return array of Shipserv_User instances
	 */
	private function readBatch (Eml_DueEmailIterator $it)
	{
		$uQ = array();
		while (true)
		{
			// Read next user and exit if none
			$u = $it->next();
			if ($u === null) break;
			
			// Store user and exit if queue full
			$uQ[] = $u;
			if (count($uQ) == 100) break;
		}
		
		return $uQ;
	}
	
	/**
	 * Sends e-mails to batch of users, marking users as 'notified' one-by-one.
	 *
	 * @param array $uQ Array of Shipserv_User
	 * @return void
	 */
	private function sendEmails (array $uQ, Eml_DueEmailIterator $it)
	{
		// Read pending review requests for queued users
		// and index them by e-mail
		$rrByEmail = $this->fetchRevReqs($uQ);
		
		// Loop on queued users and dispatch e-mails
		foreach ($uQ as $u)
		{
			// Fetch pending review requests for user
			$nEm = $this->normEmail($u->email);
			$rrArr = @$rrByEmail[$nEm];
			
			$hasPendingReqs = (bool) $rrArr;
			if ($hasPendingReqs)
			{
				// Send mail
				$boolEmailSent = $this->doSendEmail($u);
				
				// If e-mail was sent ...
				if ($boolEmailSent)
				{
					// Mark this user as notified
					$it->markEmailSent($u);
					Eml_Logger::log("User " . $u->userId . " " . $u->email . ": e-mail sent and last notification time updated");
					
					// A pause to respect the e-mail server
					sleep(1);
				}
				
				// If e-mail was not sent because no pending review requests were found ...
				// (This can still happen technically, even if we checked up-front)
				else
				{
					$hasPendingReqs = false;
				}
			}
			
			// Note: this is not an 'elseif' for a good reason
			if (!$hasPendingReqs)
			{
				Eml_Logger::log("User " . $u->userId . " " . $u->email . ": no pending requests found, skipping");
			}
		}
	}
	
	/**
	 * Sends e-mail to single user. Returns 'true' if e-mail sent OK, or
	 * 'false' if e-mail was not sent because user has no pending review requests.
	 * Other failure scenarios result in an exception.
	 * 
	 * @return bool
	 */
	private function doSendEmail (Shipserv_User $user)
	{
		try
		{
			$this->notificationMan->pendingReviewRequests($user->userId);
			return true;
		}
		catch (Myshipserv_NotificationManager_Exception $e)
		{
			if ($e->getCode() == Myshipserv_NotificationManager_Exception::RR_NONE_PENDING)
			{
				return false;
			}
			else
			{
				throw $e;
			}
		}
	}
	
	private function normEmail ($em)
	{
		return strtolower(trim($em));
	}
	
	/**
	 * Fetch pending review requests for users specified.
	 *
	 * @param array $users array of Shipserv_User instances
	 * @return array
	 */
	private function fetchRevReqs (array $users)
	{		
		// Pull e-mails from users
		$emailArr = array();
		foreach ($users as $u)
		{
			$emailArr[] = $u->email;
		}
		
		// Fetch pending review requests
		$res = Shipserv_ReviewRequest::getRequestsByEmails($emailArr);
		
		// Make sure returned keys are normalised e-mails
		foreach ($res as $k => $v)
		{
			unset($res[$k]);
			$res[$this->normEmail($k)] = $v;
		}
		
		return $res;
	}
}

/**
 * Provides access to users who are due an e-mail as an iterator.
 */
class Eml_DueEmailIterator
{
	// ID of last row supplied
	// 0 is starting value
	// -1 means no more iteration
	private $lastId = 0;
	
	// Maximum number of rows to fetch in each query
	private $pageSize = 1000;
	
	// Result set for current page
	private $currentRes = null;
	
	public function __construct ()
	{
		// Do nothing
	}
	
	/**
	 * Fetch next user.
	 *
	 * @return Shipserv_User, or null if there are no more.
	 */
	public function next ()
	{
		// If iteration has been marked completed, return
		if ($this->lastId == -1) return;
		
		// Make two attempts ...
		for ($tryCount = 0; $tryCount < 2; $tryCount++)
		{
			// If there is no current result set, run query
			if ($this->currentRes === null)
			{
				$this->currentRes = $this->getDb()->fetchAll(self::makeSql($this->lastId, $this->pageSize));
				
				// This seems superfluous, but it seems to be necessary
				reset($this->currentRes);
			}
			
			// If there is a next row, fetch it and return
			if (list($k, $row) = each($this->currentRes))
			{
				$this->lastId = $row['USR_USER_CODE'];
				return Shipserv_User::fromDb($row);
			}
			
			// No more rows: clear result set to cause new query on next loop
			$this->currentRes = null;
		}
		
		// No more rows found on final iteration: mark complete
		$this->lastId = -1;
	}
	
	/**
	 * Records that user received an e-mail at current time so that users are not
	 * e-mailed again too soon: call this immediately after e-mailing a user
	 * supplied by next().
	 *
	 * Note: do not call this method with a user who has not yet been supplied by
	 * next(). If you do, next() could well supply it again and you could
	 * double e-mail someone.
	 * 
	 * @return void
	 */
	public function markEmailSent (Shipserv_User $u)
	{
		$uId = $u->userId;
		$sql = "UPDATE PAGES_USER SET PSU_LAST_REVIEW_EMAIL = SYSDATE WHERE PSU_ID = $uId";
		
		$stmt = $this->getDb()->query($sql);
		if ($stmt->rowCount() == 0)
		{
			throw new Exception("No row found for user ID '$uId'");
		}
	}
	
	/**
	 * Constructs paged SQL query to fetch users due an e-mail.
	 *
	 * @return string
	 */
	private static function makeSql ($lastId, $pageSize)
	{
		// 0 (Sun) thru 6 (Sat)
		$dow = date('w');
		
		// Only active Pages users
		$sql = "SELECT * FROM USERS a INNER JOIN PAGES_USER b ON a.USR_USER_CODE = b.PSU_ID";
		$sql .= " WHERE a.USR_TYPE = 'P' AND (a.USR_STS = 'ACT' OR a.USR_STS IS NULL)";
		
		// Only users who are to be alerted on a weekly basis
		$sql .= " AND PSU_ALERT_STATUS = 'W'";
		
		// Only users due e-mails on today's day of the week
		// Disabled: made testing hard & happens not to be a problem
		// $sql .= " AND MOD(a.USR_USER_CODE, 7) = $dow";
		
		// Only users who have not already been e-mailed this week (e.g. if this were run twice on same day), or have never been e-mailed
		// TRUNC() turns e.g. '2010-05-25 10:00:00' back to '2010-05-25 00:00:00'
		$sql .= " AND (SYSDATE >= (TRUNC(PSU_LAST_REVIEW_EMAIL) + 7) OR PSU_LAST_REVIEW_EMAIL IS NULL)";
		
		// Paging mechanism. NB/ ROWNUM is evaluated before 'ORDER BY' is applied,
		// but it doesn't matter here.
		$sql .= " AND a.USR_USER_CODE > $lastId AND ROWNUM <= $pageSize";
		
		// Order by ID so that paging is reliable
		$sql .= " ORDER BY a.USR_USER_CODE";
		
		return $sql;
	}
	
	private function getDb ()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}
}
