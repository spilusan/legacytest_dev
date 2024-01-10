<?php
class SEO_Sitemap_Template
{
	public $url;
	public $frequency = "weekly";
	public $priority;

	public function convertToXml()
	{
		return '
		<url>
		<loc>' . $this->url . '</loc>
		<changefreq>' . $this->frequency . '</changefreq>
		<priority>' . $this->priority . '</priority>
		</url>';
	}
}

class SEO_Sitemap_Template_Supplier extends SEO_Sitemap_Template
{
	function __construct($url)
	{
		$this->url = $url;
		$this->priority = 0.7;
	}
}

class SEO_Sitemap_Template_Category extends SEO_Sitemap_Template
{
	function __construct($url)
	{
		$this->url = $url;
		$this->priority = 0.8;
	}
}

class SEO_Sitemap_Template_Country extends SEO_Sitemap_Template
{
	function __construct($url)
	{
		$this->url = $url;
		$this->priority = 0.6;
	}
}

class SEO_Sitemap_Template_Port extends SEO_Sitemap_Template
{
	function __construct($url)
	{
		$this->url = $url;
		$this->priority = 0.5;
	}
}

class SEO_Sitemap_Template_Brand extends SEO_Sitemap_Template
{
	function __construct($url)
	{
		$this->url = $url;
		$this->priority = 0.7;
	}
}

class SEO_Sitemap_Template_Product extends SEO_Sitemap_Template
{
	function __construct($url)
	{
		$this->url = $url;
		$this->priority = 0.7;
	}
}

class SEO_Sitemap_Template_Home extends SEO_Sitemap_Template
{
	function __construct($url)
	{
		$this->url = $url;
		$this->priority = 1.0;
	}
}