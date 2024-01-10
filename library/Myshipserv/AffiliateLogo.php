<?php

/**
 *
 * @author Uladzimir Maroz
 */
class Myshipserv_AffiliateLogo {

	public $file;
	public $originalFileName;
	public $originalFormat;

	private $uniqueFilename;
	
	public static $thumbDimensions = array (
		array (
			"width"=>40,
			"height"=>40
		),
		array (
			"width"=>23,
			"height"=>23
		),
	);

	public function  __construct($file)
	{
		$this->file = $file;
		$this->originalFileName = basename($file);
		$this->originalFormat = strtolower(substr(strrchr($this->originalFileName, "."), 1));

	}

	/**
	 * Uploads file to FTP storage
	 *
	 * @access private
	 * @static
	 * @param array $attachments
	 * @param int $userId
	 * @return array An array of uploaded attachments with their full URL
	 */
	public function save ()
	{

		try
		{


			$filename = $this->generateFilename().".".$this->originalFormat;
			$this->upload(file_get_contents($this->file), $filename);

			foreach (self::$thumbDimensions as $thumb)
			{
				$newImage = $this->resize($thumb["width"], $thumb["height"]);
				$this->upload($newImage, $this->generateFilename()."_".$thumb["width"]."x".$thumb["height"].".".$this->originalFormat);
			}
			
		}
		catch (Exception $e)
		{
			throw $e; // rethrow the exception for the controller to handle
		}


		return array('filename' => $filename);
	}

	private function upload ($input, $filename)
	{
		$config  = Zend_Registry::get('config');

		// create an FTP connection
		try
		{
			$ftpConfig = $config->services->ftp;

			if (!$ftpConn = ftp_connect($ftpConfig->host))
			{
				throw new Myshipserv_Enquiry_Exception("Could not connect to FTP server");
			}

			if (!ftp_login($ftpConn, $ftpConfig->username, $ftpConfig->password))
			{
				throw new Myshipserv_Enquiry_Exception("Could not login to FTP server");
			}

			$ftpDir = $ftpConfig->logosDirectory;

			// check if logos directory exists
			if (!@ftp_chdir($ftpConn, $ftpDir))
			{

				throw new Exception("Unable to change to logos directory");
			}

			ftp_pasv($ftpConn, true);

			$tmpFName = tempnam("/tmp", "FOO");
			$tempFile = fopen($tmpFName, "w");
			fwrite($tempFile, $input);
			if (!ftp_put($ftpConn, $filename, $tmpFName, FTP_BINARY))
			{
				fclose($tempFile);
				unlink($tmpFName);
				throw new Exception("Unable to upload file");
			}
			fclose($tempFile);
			unlink($tmpFName);

		}
		catch (Exception $e)
		{
			throw $e; // rethrow the exception for the controller to handle
		}
	}

	private function resize ($maxWidth = 0, $maxHeight = 0)
    {
		$originalInput = file_get_contents($this->file);
        $tmpImage = imagecreatefromstring($originalInput);

        // must have one of desired_width or desired_height
        if (!$maxWidth && !$maxHeight)
        {
			return;
		}

        // get current width and height
        $currentWidth  = imagesx($tmpImage);
        $currentHeight = imagesy($tmpImage);


		if ($maxWidth)
		{
			$widthScale = $maxWidth / $currentWidth;
			if (!$maxHeight)
			{
				$heightScale = $widthScale;
			}
		}

		if ($maxHeight)
		{
			$heightScale = $maxHeight/$currentHeight;
			if (!$maxWidth)
			{
				$widthScale = $heightScale;
			}
		}

		if ($widthScale < $heightScale)
		{
			$heightScale = $widthScale;
		}
		else
		{
			$widthScale = $heightScale;
		}



        $newWidth  = $currentWidth * $widthScale;
        $newHeight = $currentHeight * $heightScale;


        // resize image
        $output = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($output, $tmpImage, 0, 0, 0, 0, $newWidth, $newHeight, $currentWidth, $currentHeight);

        // capture output
        ob_start();

		switch ($this->originalFormat)
		{
			case "jpg":
				imagejpeg($output,null,80);
				break;
			case "gif":
				imagegif($output);
				break;
			case "png":
				imagepng($output);
				break;
		}

        $newImage = ob_get_contents();
        ob_end_clean();

        ImageDestroy($output);
        ImageDestroy($tmpImage);

        return $newImage;
    }

	private function generateFilename ()
	{
		if (is_null($this->uniqueFilename))	$this->uniqueFilename = substr(md5(uniqid(rand(),true)),0,12);
		return $this->uniqueFilename;
	}


}
?>
