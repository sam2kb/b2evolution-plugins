<?php
/**
 * Search Cloud plugin for {@link http://b2evolution.net/}.
 *
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author Yabba	- {@link http://www.astonishme.co.uk/}
 * @author Stk		- {@link http://www.astonishme.co.uk/}
 * @author sam2kb	- {@link http://b2evo.sonorth.com/}
 */

if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class search_cloud_plugin extends Plugin
{
	var $name = 'Search Cloud';
	var $code = 'evo_searchcloud';
	var $priority = 95;
	var $version = '2.1.1';
	var $group = 'Sonorth Corp.';
	var $author = 'Russian b2evolution';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=21866';

	var $min_app_version = '3';
	var $max_app_version = '5';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;

	// Internal
	var $have_shown = false;


	function PluginInit()
	{
		$this->short_desc = $this->T_('Add a search cloud to your blog');
		$this->long_desc = $this->T_('Converts your hitlog into a cloud');
	}


	function GetDbLayout()
	{
		return array(
			'CREATE TABLE IF NOT EXISTS '.$this->get_sql_table('cache').' (
				sc_ID int(11) NOT NULL AUTO_INCREMENT,
				sc_cache_time int(11) NOT NULL,
				sc_cache_data text NOT NULL,
				sc_params_md5 varchar(32) NOT NULL,
				PRIMARY KEY (sc_ID),
				UNIQUE KEY sc_params_md5 (sc_params_md5),
				KEY sc_cache_time (sc_cache_time)
			)');
	}


	/**
	 * We require b2evo 3.0 or above.
	 */
	function GetDependencies()
	{
		return array(
				'requires' => array(
					'app_min' => '3.0',
				),
			);
	}


	function GetDefaultSettings()
	{
		$r = array(
			'blog_ID' => array(
				'label' => $this->T_('Blog'),
				'note' => $this->T_('ID of the blog to use, leave empty for the current blog.'),
				'size' => 4
			),
			'cache' => array(
				'label' => $this->T_('Cache lifetime'),
				'note' => $this->T_('This is the time in minutes that the results will be cached for.'),
				'defaultvalue' => 60,
				'valid_range' => array(
					'min' => 0,
					'max' => 10000, // max 7 days
					),
				'type' => 'integer',
				'size' => 4
			),
			'limit' => array(
				'label' => $this->T_('Max results'),
				'defaultvalue' => 40,
				'note' => $this->T_('This is the maximum number of results to display'),
				'type' => 'integer',
				'size' => 4
			),
			'max_length' => array(
				'label' => $this->T_('Max length'),
				'defaultvalue' => 0,
				'note' => $this->T_('This is the maximum length for a search term ( 0 = no restrictions ). Longer terms will be trimmed.'),
				'type' => 'integer',
				'size' => 4
			),
			'unique_urls' => array(
				'label' => $this->T_('Unique URLs'),
				'note' => $this->T_('Display terms with unique target URL only.'),
				'defaultvalue' => 1,
				'type' => 'checkbox',
			),
			'order' => array(
				'label' => $this->T_('Order'),
				'note' => $this->T_('This is the the order to show the results in'),
				'defaultvalue' => 'shuffle',
				'type' => 'select',
				'options' => array( 'asc' => 'Ascending (most popular)', 'desc' => 'Descending (most popular)', 'shuffle' => 'Shuffled (most popular)', 'rand' => 'Random' )
			),
			'ignore_terms' => array(
				'label' => $this->T_('Ignore words'),
				'note' => $this->T_('This is a comma separated list of words to ignore. MySQL regex syntax.'),
				'type' => 'text',
				'size' => 40
			),
			'ignore_urls' => array(
				'label' => $this->T_('Ignore URLs'),
				'note' => $this->T_('This is a comma separated list of URLs to ignore. MySQL regex syntax.'),
				//'defaultvalue' => '.*\.(css|js)',
				'type' => 'text',
				'size' => 40
			),
			'filter_list' => array(
				'label' => $this->T_('Filter words'),
				'note' => $this->T_('This is a comma separated list of words to filter'),
				'type' => 'text',
				'size' => 40
			),
			'filter_replace' => array(
				'label' => $this->T_('Filter character'),
				'defaultvalue' => '*',
				'note' => $this->T_('This is the character that filtered words will be replaced with'),
				'type' => 'text',
				'size' => 1
			),
			'title' => array(
				'label' => $this->T_('Title'),
				'defaultvalue' => $this->T_('Search Cloud'),
				'note' => $this->T_('Weirdly enough, this is the title!'),
				'type' => 'html_input',
				'size' => 40
			),
			'hover_title' => array(
				'label' => $this->T_('Hover title'),
				'defaultvalue' => sprintf( $this->T_('Searched %s times - Read it!'), '#count#' ),
				'note' => $this->T_('This is hover title for each item in the cloud : #count# will be replaced by the hit count for the item'),
				'type' => 'html_input',
				'size' => 40
			),
		);
		return $r;
	}


	/**
	 * Get definitions for widget specific editable params
	 *
	 * @see Plugin::GetDefaultSettings()
	 * @param local params like 'for_editing' => true
	 */
	function get_widget_param_definitions( $params )
	{
		return $this->GetDefaultSettings();
	}


	function SkinBeginHtmlHead()
	{
		require_css( $this->get_plugin_url(true).'cloud.css' );
	}


	function SkinTag( & $params )
	{
		global $blog, $DB, $BlogCache, $servertimenow;

		$params = $this->init_display($params);

		if( isset($params['show_once']) && $this->have_shown )
		{	// We've already displayed
			return;
		}

		//if( ! is_logged_in() ) return;

		$this->have_shown = true;

		$blog_ID = ( is_numeric($params['blog_ID']) ? $params['blog_ID'] : $blog );
		$params_md5 = md5(serialize($params));

		// Drop cache in case of emergency :)
		//$DB->query('DELETE FROM '.$this->get_sql_table('cache'));

		// Let's check if we have a current cached version for these settings
		$SQL = 'SELECT * FROM '.$this->get_sql_table('cache').'
				WHERE sc_params_md5 = "'.$DB->escape( $params_md5 ).'"';

		if( $cache_results = $DB->get_row($SQL) )
		{	// We already have cache for these settings
			if( $cache_results->sc_cache_time > ($servertimenow - ($params['cache'] * 60)) )
			{	// Display cached results
				echo '<!-- cached cloud -->'.base64_decode( $cache_results->sc_cache_data );
				return;
			}
			$cache_ID = $cache_results->sc_ID;
		}
		else
		{
			$cache_ID = 0;
		}

		// Delete cached results older than 7 days
		$DB->query('DELETE FROM '.$this->get_sql_table('cache').' WHERE sc_cache_time < '.($servertimenow - (7 * 86488) ) );

		$SQL = 'SELECT SQL_NO_CACHE COUNT(DISTINCT hit_remote_addr) as count, keyp_ID, LOWER(keyp_phrase) as term, hit_blog_ID, hit_uri
				FROM T_hitlog
				LEFT JOIN T_track__keyphrase ON hit_keyphrase_keyp_ID = keyp_ID
				WHERE hit_blog_ID = '.$DB->quote($blog_ID).'
				AND hit_referer_type = "search"';

		if( $params['ignore_urls'] )
		{	// We want to ignore some urls
			$ignore_urls = array_filter( $this->array_trim(explode( ',', $params['ignore_urls'] )) );
			foreach( $ignore_urls as $ignore )
			{
				$SQL .= ' AND hit_uri NOT REGEXP "'.$DB->escape(trim($ignore)).'"';
			}
		}

		if( $params['ignore_terms'] )
		{	// We want to ignore some terms
			$ignore_terms = array_filter( $this->array_trim(explode( ',', $params['ignore_terms'] )) );
			foreach( $ignore_terms as $ignore )
			{
				$SQL .= ' AND LOWER(keyp_phrase) NOT REGEXP "'.$DB->escape(trim($ignore)).'"';
			}
		}
		$SQL .= 'GROUP BY keyp_ID ORDER BY count DESC';


		if( !$rows = $DB->get_results($SQL) )
		{	// No results
			return;
		}
		//var_export($rows);

		$filter_words = $this->array_trim(explode( ',', $params['filter_list'] ));

		$search_list = array();
		$replace_list = array();
		foreach( $filter_words as $filter_word )
		{
			$replace_list[] = str_repeat( $params['filter_replace'], evo_strlen($filter_word) );
			$search_list[] = '#'.preg_quote( $filter_word, '#' ).'#i';
		}

		$search_stats = array();
		$urls_array = array();
		foreach( $rows as $row )
		{
			// This URL already added, skip
			if( $params['unique_urls'] && isset($urls_array[$row->hit_uri]) ) continue;

			$term = $row->term;

			if( !empty( $replace_list ) ) $term = preg_replace( $search_list, $replace_list, $term );
			if( empty($term) ) continue;

			if( $params['max_length'] > 0 )
			{
				$term = strmaxlen( $term, $params['max_length'] );
			}

			$urls_array[$row->hit_uri] = 1;

			$search_stats[$term] = array(
					'count' 	=> $row->count,
					'url'		=> $row->hit_uri,
					'blog_ID'	=> $row->hit_blog_ID,
					'term'		=> $term,
				);
		}

		//var_export($search_stats);

		if( $params['order'] != 'rand' )
		{
			if( $params['limit'] > 0 )
			{
				$search_stats = array_slice( $search_stats, 0, $params['limit'] );
			}

			// max and min hits
			$max = $rows[0]->count;
			$min = $rows[(count($search_stats)-1)]->count;
		}

		switch( $params['order'] )
		{
			case 'rand':
				shuffle($search_stats);
				if( $params['limit'] > 0 )
				{
					$search_stats = array_slice( $search_stats, 0, $params['limit'] );
				}

				$max = 1;
				$min = 1;
				$tmp_array = array();
				foreach( $search_stats as $k => $v )
				{
					$max = max( $max, $v['count'] );
					$min = min( $min, $v['count'] );
				}
				break;

			case 'asc' :
				ksort($search_stats);
				break;

			case 'desc' :
				krsort($search_stats);
				break;

			default:
				shuffle($search_stats);
		}

		//echo "$min, $max";

		$x1 = log($min);
		$x2 = log($max) - $x1;

		$min_font = 11;
		$max_font = 22;

		if( $x2 == 0 )
		{	// Equal rating terms
			$x2 = 1;
			$min_font = 12;
		}

		$r = '';
		foreach( $search_stats as $search_stat )
		{
			$tmp_Blog = & $BlogCache->get_by_ID( $search_stat['blog_ID'] );
			$full_url = $tmp_Blog->get('baseurlroot').$search_stat['url'];

			$weight = (log($search_stat['count']) - $x1) / $x2;
			$class = $min_font + round( ($max_font - $min_font) * $weight );

			$title = '';
			if( $params['hover_title'] )
			{
				$title = ' title="'.format_to_output( str_replace( '#count#', $search_stat['count'],
							$params['hover_title'] ), 'htmlattr' ).'"';
			}

			$r .= $params['item_start'];
			$r .= '<a href="'.$full_url.'"'.$title.' style="font-size:'.$class.'px">';
			$r .= preg_replace( '~\s+~', ' ', $search_stat['term'] ).'</a>';
			$r .= $params['item_end']."\n";
		}

		if( empty($r) ) return;

		if( ! strstr( $params['block_start'], 'widget_plugin_evo_searchcloud' ) )
		{	// Add our class
			$params['block_start'] = preg_replace( '~class\s?=\s?([\'"])~is', 'class=$1widget_plugin_evo_searchcloud ', $params['block_start'] );
		}

		$output = $params['block_start'];
		if( $params['block_display_title'] )
		{
			$output .= $params['block_title_start'].$params['title'].$params['block_title_end'];
		}
		$output .= $params['list_start'].$r.$params['list_end'];
		$output .= $params['block_end'];

		// Display results
		echo '<!-- new cloud -->'.$output;

		// Let's cache the results
		/*if( empty($cache_ID) )
		{	// This is a brand new cloud
			$SQL = 'INSERT INTO '.$this->get_sql_table('cache').' ( sc_cache_time, sc_cache_data, sc_params_md5 )
					VALUES ( '.$DB->quote($servertimenow).',
							"'.$DB->escape( base64_encode($output) ).'",
							"'.$DB->escape( md5(serialize($params)) ).'" )';
		}
		else
		{	// We're updating an existing cloud
			$SQL = 'UPDATE '.$this->get_sql_table('cache').'
					SET sc_cache_time = '.$DB->quote($servertimenow).',
						sc_cache_data = "'.$DB->escape( base64_encode($output) ).'"
					WHERE sc_ID = '.$DB->quote($cache_ID);
		}*/
		$cache_time = $DB->quote($servertimenow);
		$cache_data = $DB->escape( base64_encode($output) );

		$SQL = 'INSERT INTO '.$this->get_sql_table('cache').' ( sc_cache_time, sc_cache_data, sc_params_md5 )
				VALUES ( '.$cache_time.',
						"'.$cache_data.'",
						"'.$DB->escape( md5(serialize($params)) ).'" )
				ON DUPLICATE KEY UPDATE sc_cache_time = '.$cache_time.', sc_cache_data = "'.$cache_data.'"';

		$DB->query($SQL);
	}


	// Trim array
	function array_trim( $array )
	{
		return array_map( 'trim', $array );
	}


	/**
	 * Sets all the display parameters
	 * These will either be the default display params or the widget display params if it's in a container
	 *
	 * @param array $params
	 */
 	function init_display( $params = array() )
 	{
		$temp = $this->get_widget_param_definitions( array() );

		$full_params = array();
		foreach( $temp as $setting => $values )
		{
			$full_params[$setting] = ( isset($params[$setting]) ? $params[$setting] : $this->Settings->get($setting) );
		}

		foreach( $params as $param => $value )
		{
			if( !isset($full_params[$param]) ) $full_params[$param] = $value;
		}
		return $full_params;
	}
}
?>