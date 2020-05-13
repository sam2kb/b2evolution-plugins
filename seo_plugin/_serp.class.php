<?php
/**
 * Simple SERP Checker class
 * http://www.andreyvoev.com/simple-serp-tracker-php-class
 */

abstract class SerpChecker
{
	// the url that we will use as a base for our search
	protected $request_urls;

	// the site that we are searching for
	protected $site;

	// the keywords for the search
	protected $keywords;

	// the current page the crawler is on
	protected $current;

	// starting time of the search
	protected $time_start;

	// debug info array
	protected $debug;

	// the limit of the search results
	protected $limit;
	protected $_log = array();

	var $proxies;
	var $results;
	var $cookie_file;


   /**
	* Initial check if the base url is a string and if it has the required "keyword" and "position" keywords.
	*/
	protected function initial_check()
	{
		// get the model url from the extension class
		$url = $this->searchurl;

		// check if the url is a string
		if( !is_string($url) ) die("The url must be a string");
	}

   /**
	* Set up the proxy if used
	*
	* @param String $file OPTIONAL: if filename is not provided, the proxy will be turned off.
	*/
	function use_proxy( $file = false )
	{
		if( $file )
		{
			if( file_exists($file) )
			{
				// get a proxy from a supplied file
				$this->proxies = file($file);
			}
			else
			{
				$this->log('The proxy file doesn\'t exist');
			}
		}
	}


	function get_proxy()
	{
		if( !empty($this->proxies) )
		{
			return $this->proxies[array_rand($this->proxies)];
		}
		else
		{
			return false;
		}
	}


	function log( $var, $verbose = '#' )
	{
		$v = 'less';
		if( $verbose != '#' )
		{
			$v = $verbose;
		}

		if( is_string($var) && $verbose == '#' )
		{
			$this->_log[$v][] = $var;
		}
		else
		{
			$this->_log[$v][] = var_export($var, true);
		}
	}


	function get_log( $verbose = 'less' )
	{
		if( empty($this->_log) ) return 'no log';

		return '<pre>'.implode( '<br />', $this->_log[$verbose] ).'</pre>';
	}


	protected function get_content()
	{
		// array of curl handles
		$ch = array();

		// multi handle
		$mh = curl_multi_init();

		// loop through keyword URLs and create curl handles and add them to the multi-handle
		foreach( $this->request_urls as $keyword => $url )
		{
			$ch[$keyword] = curl_init();

			$this->data['start_'.$this->current][$keyword]['request'] = $url;

			// HTTP headers
			$header[] = "Accept: Accept	text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
			$header[] = "Cache-Control: max-age=0";
			$header[] = "Connection: keep-alive";
			$header[] = "Keep-Alive: 115";
			$header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
			$header[] = "Accept-Language: en-us,en;q=0.5";
			$header[] = "Pragma: "; // browsers keep this blank.

			curl_setopt( $ch[$keyword], CURLOPT_URL, $url );
			curl_setopt( $ch[$keyword], CURLOPT_HEADER, 0 );
			curl_setopt( $ch[$keyword], CURLOPT_HTTPHEADER, $header );
			curl_setopt( $ch[$keyword], CURLOPT_ENCODING, 'gzip,deflate' );
			curl_setopt( $ch[$keyword], CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch[$keyword], CURLOPT_FOLLOWLOCATION, true );
			curl_setopt( $ch[$keyword], CURLOPT_MAXREDIRS, 4 );
			curl_setopt( $ch[$keyword], CURLOPT_SSL_VERIFYHOST, false );
			curl_setopt( $ch[$keyword], CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch[$keyword], CURLOPT_VERBOSE, true );
		//	curl_setopt( $ch[$keyword], CURLOPT_USERAGENT, $this->useragent );
			curl_setopt( $ch[$keyword], CURLOPT_AUTOREFERER, true );

			if( $this->cookie_file && file_exists($this->cookie_file) )
			{
				curl_setopt ($ch[$keyword], CURLOPT_COOKIEFILE, $this->cookie_file);
				curl_setopt ($ch[$keyword], CURLOPT_COOKIEJAR, $this->cookie_file);
				curl_setopt( $ch[$keyword], CURLOPT_TIMEOUT, 20 );
			}
			else
			{
				curl_setopt( $ch[$keyword], CURLOPT_TIMEOUT, 5 );
			}

			if( $proxy = $this->get_proxy() )
			{	// use the selected proxy
				$this->data['start_'.$this->current][$keyword]['proxy'] = $proxy;

				//curl_setopt( $ch[$keyword], CURLOPT_HTTPPROXYTUNNEL, true );
				curl_setopt( $ch[$keyword], CURLOPT_PROXY, $proxy );
				curl_setopt( $ch[$keyword], CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
			}

			curl_multi_add_handle($mh, $ch[$keyword]);
		}

		// execute the handles
		$running = null;
		do
		{
			curl_multi_exec($mh, $running);
		}
		while($running > 0);

		// get content and remove handles
		foreach($ch as $keyword => $c)
		{
			$this->data['start_'.$this->current][$keyword]['content'] = curl_multi_getcontent($c);
			$this->data['start_'.$this->current][$keyword]['curl-info'] = curl_getinfo($c);
			$this->data['start_'.$this->current][$keyword]['curl-error'] = curl_error($c);
			curl_multi_remove_handle($mh, $c);
		}

		curl_multi_close($mh);

		return $this->data['start_'.$this->current];
	}


   /**
	* Crawl trough every page and pass the result to the get_position function until all the keywords are processed.
	*/
	protected function crawl()
	{
		$this->setup();
		$this->log( $this->request_urls );

		$requests = $this->get_content();

		foreach( $requests as $keyword=>$data )
		{
			$this->log( 'Working on keyword: '.$keyword );
			$this->log( 'CURL error: '.$data['curl-error'] );
			$this->log( $data['curl-info'], 'more' );

			if( empty($data['content']) || !empty($data['curl-error'])  )
			{
				$key = array_search($keyword, $this->keywords);
				unset($this->keywords[$key]);

				$this->log('Empty content');
				continue;
			}
			else
			{
				$this->log( htmlspecialchars($data['content']), 'more' );
			}

			if( $position = $this->get_position($data) )
			{
				if( !isset($this->results[ $this->domain_name ][$keyword]) )
				{
					$real_position = $this->current + $position;
					$this->results[ $this->domain_name ][$keyword] = $real_position;

					$this->log( 'Position ('.$keyword.'): '.$real_position );
					$this->log( 'Run time ('.$keyword.'): '.number_format(microtime(true) - $this->time_start, 3) );
				}
				// remove sucessfull keywords
				$key = array_search($keyword, $this->keywords);
				unset($this->keywords[$key]);
			}
			else
			{
				$this->log('Trying next set of results: '.$this->current.'+');
			}
		}

		if( count($this->keywords) > 0 )
		{
			if( $this->current < $this->limit )
			{
				$this->current += 10;
				$this->crawl();
			}
		}
	}

   /**
	* Prepare the array of the keywords for every run.
	*/
	protected function setup()
	{
		// reset url array for the new keyword
		$this->request_urls = array();

		foreach( $this->keywords as $keyword )
		{
			$url = str_replace( array('[keyword]', '[position]', '[page]'),
								array(urlencode($keyword), $this->current, ($this->current/10)),
								$this->searchurl );

			$this->request_urls[$keyword] = $url;
		}
	}


	/**
	 * Constructor function for all new tracker instances.
	 *
	 * @param Array $keywords
	 * @param String $site
	 * @param Int $limit OPTIONAL: number of results to search
	 * @return tracker
	 */
	function run( $keywords, $domain_name, $limit = 100 )
	{
		if( !is_array($keywords) ) $keywords = array($keywords);

		// the keywords we are searching for
		$this->keywords = $keywords;

		// the domain we are checking the position of
		$this->domain_name = $domain_name;

		// set the maximum results we will search trough
		$this->limit = $limit;

		// setup the array for the results
		$this->results = array();

		// starting position
		$this->current = 0;

		// start benchmarking
		$this->time_start = microtime(true);

		// user agent string
		$this->useragent = 'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0)2011-10-16 20:21:07';

		// set the time limit of the script execution - default is 6 min.
		set_time_limit(360);

		// check if all the required parameters are set
		$this->initial_check();

		$this->crawl();
	}

   /**
	* Return the results from the search.
	*/
	function get_results()
	{
		// Save results to the database

		return $this->results;
	}


	function display_results()
	{
		$results = $this->get_results();

		if( empty($results) )
		{
			echo '<p class="error">IP temporarily blocked</p>';
			return;
		}

		foreach( $results as $domain=>$keywords )
		{
			asort($keywords);

			$r[$domain] = "<h3>$domain</h3>\n";
			$r[$domain] .= '<table cellpadding="0" cellspacing="0" border="0">';
			foreach( $keywords as $word=>$position )
			{
				$r[$domain] .= "\n".'<tr><td class="position">'.$position.'</td><td class="word">'.$word.'</td></tr>';
			}
			$r[$domain] .= "\n</table>";
		}
		echo implode( "\n<hr />\n", $r );
	}


   /**
	* Return the debug information - time taken, etc.
	*
	* @return Array $this->debug
	*/
	function get_debug_info()
	{
		return $this->debug;
	}


   /**
	* Find the occurrence of the site in the results page. Specific for every search engine.
	*
	* @param String $html OPTIONAL: override the default html if needed
	* @return String $baseurl;
	*/
	abstract function get_position($html);


	function getElementsByClassName( DOMDocument $DOMDocument, $ClassName )
	{
		$Elements = $DOMDocument->getElementsByTagName("*");
		$Matched = array();

		foreach( $Elements as $node )
		{
			if( ! $node->hasAttributes() ) continue;

			$classAttribute = $node->attributes->getNamedItem('class');

			if( ! $classAttribute ) continue;

			$classes = explode(' ', $classAttribute->nodeValue);

			if( in_array($ClassName, $classes) ) $Matched[] = $node;
		}
		return $Matched;
	}
}


class Google extends SerpChecker
{
	function __construct()
	{	// [keyword] [page] [position]
		$this->searchurl = 'http://www.google.com/search?q=[keyword]&start=[position]';
	}


	function get_position( $keyword )
	{
		// Use custom error handler
		libxml_use_internal_errors(true);

		// process the html and return either a numeric value of the position of the site in the current page or false
		$DOM = new DOMDocument();
		$DOM->loadHTML($keyword['content']);
		$nodes = $DOM->getElementsByTagName('cite');

		// found is false by default, we will set it to the position of the site in the results if found
		$found = false;

		// start counting the results from the first result in the page
		$position = 1;
		$urls = array();
		foreach($nodes as $node)
		{
			$node = $node->nodeValue;
			// search for links that look like this: cmsreport.com › Blogs › Bryan's blog
			if( preg_match('/\s/', $node) )
			{
				$site = explode(' ',$node);
			}
			else
			{
				$site = explode('/',$node);
			}

			if( preg_match( '~^https?~', $site[0] ) )
			{
				continue;
			}
/*
			$urls[$position] = array_merge( array(
					'domain' => $site[0],
					'target_url' => $node,
					'position' => $position,
				), $keyword );

			$urls[$position]['content'] = '';
*/
			if( stristr( $site[0], $this->domain_name ) )
			{
				$found = true;
				$place = $position;
			}
			$position++;
		}

		if( $found ) return $place;
		return false;
	}
}


class Yandex extends SerpChecker
{
	function __construct()
	{	// [keyword] [page] [position]
		$this->searchurl = 'http://yandex.ru/yandsearch?p=[page]&text=[keyword]&lr=102582';
		$this->cookie_file = 'cookie_yandex.txt';

		if( file_exists($this->cookie_file) )
		{
			unlink($this->cookie_file);
		}
		touch($this->cookie_file);
	}


	function get_position( $keyword )
	{
		// Use custom error handler
		libxml_use_internal_errors(true);

		// process the html and return either a numeric value of the position of the site in the current page or false
		$DOM = new DOMDocument();
		$DOM->loadHTML($keyword['content']);

		$nodes = $this->getElementsByClassName( $DOM, 'b-serp-url__item');

		// found is false by default
		$found = false;

		// start counting the results from the first result in the page
		$position = 1;
		$urls = array();
		foreach($nodes as $node)
		{
			$siteurl = '';

			$nodes = $node->getElementsByTagName('a');
			foreach( $nodes as $el_a )
			{
				$siteurl = $el_a->getAttribute('href');
				break;
			}
			if( $siteurl == '' ) continue;

			$this->log( 'Checking if "'.$this->domain_name.'" matches URL: '.$siteurl, 'more' );

			preg_match( '~^(https?://)?([^/]+)~', $siteurl, $matches );
			//var_export($matches);

			$this->log( 'URL matches: '.var_export($matches, true), 'more' );
			if( count($matches) < 2 ) continue;

			$this->all_urls['position_'.($position+$this->current)] = $siteurl;

			if( stristr( $siteurl, $this->domain_name ) )
			{
				$this->log( 'Matched! Position in current set: '.$position, 'more' );

				$found = true;
				$place = $position--;
				break;
			}
			$position++;
		}

		if( $found ) return $place;
		return false;
	}
}

?>