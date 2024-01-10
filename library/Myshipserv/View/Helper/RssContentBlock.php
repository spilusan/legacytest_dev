<?php

class Myshipserv_View_Helper_RssContentBlock extends Zend_View_Helper_Abstract
{
	/**
	 * Sets up the Rss ContentBlock helper
	 *
	 * @access public
	 * @return Myshipserv_View_Helper_RssContentBlock
	 */
	public function rssContentBlock ()
	{
		return $this;
	}

	public function init ()
	{

	}

	/**
	 * Fetches Feed and returns array with predifined number of feed items
	 *
	 * @param string $feedUri Uri of feed to fetch
	 * @param integer $limit Number of feed items to display
	 * @return array
	 */
	public function getBlockContent ($feedUri, $limit = null)
	{
	    $output = array();

	    try {

			$blockFeed = Zend_Feed_Reader::import($feedUri);

			//fix for feeds that do not have default encoding specified
			if (! $blockFeed->getDomDocument()->encoding) {
				$blockFeed->getDomDocument()->encoding = 'UTF-8';
			}

			//
			foreach ($blockFeed as $feedItem)
			{
				$feed = array();
				if ($feedItem->getTitle())
				{
					$feed['title'] = $feedItem->getTitle();

				}
				if ($feedItem->getLink())
				{
					$feed['link'] = $feedItem->getLink();
				}
				if ($feedItem->getDescription())
				{
					$feed['description'] = $feedItem->getDescription();

				}
				$output[] = $feed;
				if ($limit > 0)
				{
					$limit--;
					if ($limit == 0 )
					{
						break;
					}
				}
			}

	    } catch ( Exception $e) {
		//silent errors, we do not want to break page if feed is not accessible
		//echo $e->getMessage();
	    }
	    
	    return $output;

	}

}

?>
