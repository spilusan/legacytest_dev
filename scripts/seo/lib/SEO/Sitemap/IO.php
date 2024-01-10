<?php
class SEO_Sitemap_IO
{
	public $totalUrlPerFile;
	public $data;
	public $fileName;
	public $files = array('<sitemap><loc>https://www.shipserv.com/info/sitemap.xml</loc></sitemap>');
	public $splitFile;

	const FOLDER = '/var/www/SS_content/sitemap/';

	public function write()
	{
		$index = 0;

		if( $this->splitFile === true )
		{
			foreach(array_chunk($this->data, $this->totalUrlPerFile) as $d)
			{
				$xml = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
	' . implode("\n", $d) . '
</urlset>';
				$fileName = str_replace(".xml", "_" . ++$index . ".xml", $this->fileName);
				$this->_write($xml, $fileName);
			}
		}
		else
		{
			$xml = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
	' . implode("\n", $this->data) . '
</urlset>';
			$this->_write($xml, $this->fileName);

		}

	}
	private function _write($xml, $fileName)
	{
		$this->writeToDisk($fileName, $xml);

		$this->files[] = '
		<sitemap>
			<loc>http://' . $_SERVER['HTTP_HOST'] . '/' . basename($fileName) . '</loc>
		</sitemap>
		';

	}

	public function updateIndexFile()
	{
		Logger::log("-- Updating index file: ");
		if( $this->new === true )
		{
			
		}
		else
		{
			$xml = '<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex 
	xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/siteindex.xsd"
	xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
		' . implode("\n", $this->files) . '
		<sitemap>
			<loc>http://' . $_SERVER['HTTP_HOST'] . '/sitemap_supplier.xml</loc>
		</sitemap>
</sitemapindex>';
			$this->writeToDisk( "index.xml", $xml);
		}
		
		Logger::log("-- End");

	}

	public function writeToDisk($fileName, $content)
	{
		$fileName = self::FOLDER . "" . $fileName;
		Logger::log("---- Writing: " . $fileName);
		if (!$handle = fopen($fileName, 'w+')) {
			echo "Cannot open file ($fileName)";
			exit;
		}

		// Write $somecontent to our opened file.
		if (fwrite($handle, $content) === FALSE)
		{
			echo "Cannot write to file ($fileName)";
			exit;
		}
		fclose($handle);
	}

	public function checkIfDirExist()
	{
		//exec("rm -fR /tmp/sitemap");

		// check temporary table structures
		if( is_dir( self::FOLDER ) == false )
		{
			mkdir( self::FOLDER, 0777 );
			chmod( self::FOLDER, 0777 );
		}
	}
}

class Logger
{
	private function __construct () {
	}

	public static function log ($msg, $noNewLine = false)
	{
		echo date('Y-m-d H:i:s') . "\t" . $msg;
		if( $noNewLine == false ) echo "\n";
	}

	public static function logSimple ($msg, $noNewLine = false)
	{
		echo $msg;
		if( $noNewLine == false ) echo "\n";
	}

	public static function newLine ()
	{
		echo "\n";
	}
}
