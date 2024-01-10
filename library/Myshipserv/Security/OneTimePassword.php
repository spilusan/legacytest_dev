<?php
/**
 * 
 * 		To generate a token:
 *      ====================
 *     	$otp = new Myshipserv_Security_OneTimePassword('DATA_GOES_IN_HERE', 300);
 *    	$data = $otp->getKey(); // 5b0eccebd240a8c18af97baca4b68ce7095ad2wwM3FZR0Rxbm5YSTVBZFZkcUJiUT095abVcra1VQdjlaL3pEMzhCN0wxSGFOUT095a1418744697
 * 		
 * 		To get value from token:
 *     	$otp = new Myshipserv_Security_OneTimePassword;
 *    	$data = $otp->getKey('5b0eccebd240a8c18af97baca4b68ce7095ad2wwM3FZR0Rxbm5YSTVBZFZkcUJiUT095abVcra1VQdjlaL3pEMzhCN0wxSGFOUT095a1418744697');
 *    	
 * @author Elvir
 */

class Myshipserv_Security_OneTimePassword
{
	const DELIMITER = 'Z';
	const ENCRYPTION_KEY = 'simple';
	const ENCRYPTION_IV = 'initialisation_vector';
	const ENCRYPTION_METHOD = "AES-256-CBC";
	
	const EXCEPTION_INVALID_TOKEN = 'Your security token has expired or not valid';
	const TTL = 60;
	
	/**
	 * 
	 * @param string $data
	 */
	public function __construct( $data = null ){
		$this->data = $data;
		$this->ttl = self::TTL;
	}
	
	/**
	 * Delimiter gets converted to hex
	 * @return string
	 */
	public function getDelimiter() {
		return bin2hex( self::DELIMITER );
	}
	
	/**
	 * Get token, token format is:
	 * DELIMITER | MD5 ( ECRYPTED_DATA EXPIRY_TS ) | DELIMITER | ENCRYPTED_DATA | ENCRYPTED START_TS | END_TS
	 * @return string
	 */
	public function getKey(){
		$delimiter = $this->getDelimiter();
		$delimiterLen = strlen($delimiter);

		$ts = Shipserv_DateTime::unix();
		$tsExpiry = $ts + $this->ttl;
		
		$data[] = $delimiter;
		$data[] = md5($this->encrypt($this->data) . $tsExpiry);
		
		// data
		$data[] = $delimiter;
		$data[] = $this->encrypt($this->data);
		
		// start timestamp
		$data[] = $delimiter;
		$data[] = $this->encrypt($ts);
		
		// end time stamp
		$data[] = $delimiter;
		$data[] = $tsExpiry;
		
		return implode('', $data);
	}
	
	/**
	 * Getting data from token
	 * @param string $string
	 * @throws Myshipserv_Exception_MessagedException
	 */
	public function getData($string) {
				
		// check if the first string is valid delimiter
		if( strpos($string, $this->getDelimiter()) !== 0) {
			throw new Myshipserv_Exception_MessagedException( self::EXCEPTION_INVALID_TOKEN );
		}
		
		
		// split data by delimiter
		$parts = explode($this->getDelimiter(), $string);
		
		// 1st part is checksum: md5( encrypted || ts )
		if( $parts[1] != md5($parts[2] . $parts[4]) ){
			throw new Myshipserv_Exception_MessagedException( self::EXCEPTION_INVALID_TOKEN );
		}
		
		// check if request made within allowed time period
		$startTs = $this->decrypt($parts[3]);
		$endTs = $parts[4];
		$currentTs = Shipserv_DateTime::unix();
		
		// if within time period
		if( !($currentTs >= $startTs && $currentTs <= $endTs) ) {
			throw new Myshipserv_Exception_MessagedException( self::EXCEPTION_INVALID_TOKEN );
		}
		
		// now get the data
		return $this->decrypt($parts[2]);

	}
	
	/**
	 * Auxiliary function for encryption
	 * @return string
	 */
	private function _getHashedKey(){
		return hash('sha256', self::ENCRYPTION_KEY);
	}
	
	private function _getIV(){
		return substr(hash('sha256', self::ENCRYPTION_IV), 0, 16);	
	}
	
	/**
	 * Encryption
	 * @param unknown $string
	 * @return string
	 */
	public function encrypt( $string ){
		$key = $this->_getHashedKey();
		$iv = $this->_getIV();
		
		$output = openssl_encrypt($string, self::ENCRYPTION_METHOD, $key, 0, $iv);
		$output = base64_encode($output);
		
		return $output;
	}
	
	/**
	 * Decryption
	 * @param unknown $string
	 * @return string
	 */
	public function decrypt( $string ){
		$key = $this->_getHashedKey();
		$iv = $this->_getIV();
		
		$output = openssl_decrypt(base64_decode($string), self::ENCRYPTION_METHOD, $key, 0, $iv);
		return $output;
	}
}