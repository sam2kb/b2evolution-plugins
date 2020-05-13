<?php
/**
 *
 * This file implements the Flash tag cloud widget for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2008-2012 Russian b2evolution - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Russian b2evolution
 *
 * This plugin is based on WP-Cumusus plugin developed by Roy Tanck.
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class flash_tag_cloud_plugin extends Plugin
{
	var $name = 'Flash tag cloud';
	var $code = 'FlashTagCl';
	var $priority = 30;
	var $version = '1.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=17848';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('This plugin allows you to display your site\'s tags using a Flash movie that rotates them in 3D.');
		$this->long_desc = $this->short_desc;
	}
	
	
	/**
	 * We require b2evo 4.0 or above.
	 */
	function GetDependencies()
	{
		return array(
				'requires' => array(
					'app_min' => '4.0',
				),
			);
	}
	
	
	/**
	* Get definitions for widget specific editable params
	*/
	function get_widget_param_definitions( $params )
	{
		return array(
			'title' => array(
					'type' => 'text',
					'label' => $this->T_('Block title'),
					'defaultvalue' => $this->T_('Tag cloud'),
					'maxlength' => 100,
				),
			'blog_ids' => array(
					'type' => 'text',
					'label' => T_('Include blogs'),
					'note' => T_('A comma-separated list of Blog IDs.'),
				),
			'max_tags' => array(
					'type' => 'integer',
					'label' => $this->T_('Max # of tags'),
					'size' => 4,
					'defaultvalue' => 50,
				),
			'tag_separator' => array(
					'type' => 'text',
					'label' => $this->T_('Tag separator'),
					'defaultvalue' => ' ',
					'maxlength' => 100,
					'note' => $this->T_('For those who don\'t have Flash plugin installed.'),
				),
			'tag_min_size' => array(
					'type' => 'integer',
					'label' => $this->T_('Min tag size'),
					'size' => 3,
					'defaultvalue' => 10,
				),
			'tag_max_size' => array(
					'type' => 'integer',
					'label' => $this->T_('Max tag size'),
					'size' => 3,
					'defaultvalue' => 20,
				),
			'tag_ordering' => array(
					'type' => 'select',
					'label' => T_('Ordering'),
					'options' => array( 'ASC'  => T_('Ascending'), 'RAND' => T_('Random') ),
					'defaultvalue' => 'ASC',
					'note' => T_('How to sort the tag cloud.'),
				),
			'filter_list' => array(
					'type' => 'textarea',
					'label' => T_('Filter tags'),
					'note' => T_('This is a comma separated list of tags to ignore.'),
					'size' => 40,
					'rows' => 2,
				),
			'auto_width' => array(
					'type' => 'checkbox',
					'label' => $this->T_('Auto width'),
					'defaultvalue' => true,
					'note' => $this->T_('Stretch tag cloud to fit in parent container (width="100%"). This may not work in older browsers!'),
				),
			'width' => array(
					'type' => 'integer',
					'label' => $this->T_('Cloud width'),
					'size' => 3,
					'defaultvalue' => 250,
					'note' => 'px.',
				),
			'height' => array(
					'type' => 'integer',
					'label' => $this->T_('Cloud height'),
					'size' => 3,
					'defaultvalue' => 250,
					'note' => 'px.',
				),
			'bgcolor' => array(
					'label' => $this->T_('Cloud background color'),
					'size' => 6,
					'defaultvalue' => 'cccccc',
					'note' => $this->T_('Only if not in transparent mode'),
					'valid_pattern' => array( 'pattern' => '~^([0-9a-f]{3}){1,2}$~i', 'error' => $this->T_( 'Please enter a valid hexadecimal number for the background colour' ) ),
				),
			'tcolor' => array(
					'label' => $this->T_('Tag color'),
					'size' => 6,
					'defaultvalue' => '2b0e02',
					'note' => $this->T_('6 character hex color value'),
					'valid_pattern' => array( 'pattern' => '~^([0-9a-f]{3}){1,2}$~i', 'error' => $this->T_( 'Please enter a valid hexadecimal number for the background colour' ) ),
				),
			/*'tcolor2' => array(
					'label' => $this->T_('Tag color 2'),
					'size' => 6,
					'defaultvalue' => 'faf21e',
					'note' => $this->T_('Optional second color for gradient'),
					'valid_pattern' => array( 'pattern' => '~^([0-9a-f]{3}){1,2}$~i', 'error' => $this->T_( 'Please enter a valid hexadecimal number for the background colour' ) ),
				),*/
			'hicolor' => array(
					'label' => $this->T_('Tag hover color'),
					'size' => 6,
					'defaultvalue' => '044da7',
					'note' => $this->T_('6 character hex color value'),
					'valid_pattern' => array( 'pattern' => '~^([0-9a-f]{3}){1,2}$~i', 'error' => $this->T_( 'Please enter a valid hexadecimal number for the background colour' ) ),
				),
			'tspeed' => array(
					'type' => 'integer',
					'label' => $this->T_('Tags rotation speed'),
					'size' => 3,
					'defaultvalue' => 100,
					'note' => $this->T_('Speed (percentage, default is 100)'),
				),
			'distr' => array(
					'type' => 'checkbox',
					'label' => $this->T_('Distribute tags'),
					'defaultvalue' => true,
					'note' => $this->T_('Places tags at equal intervals instead of random'),
				),
			'trans' => array(
					'type' => 'checkbox',
					'label' => $this->T_('Use transparent mode'),
					'defaultvalue' => true,
					'note' => $this->T_('Switches on Flash\'s wmode-transparent setting'),
				),
			);
	}
	
	
	/**
	 * Event handler: SkinTag (widget)
	 */
	function SkinTag( $params )
	{
		global $plugins_url;
		
		// Init default params
		$params = $this->init_display($params);
		
		// Get tags either from database or from widget param['tags']
		$content = (!empty($params['tags'])) ? $params['tags'] : $this->get_tags( $params );
		
		if( !empty($content) )
		{	// We have something to display...
			$r  = $params['block_start'];
			$r .= $params['block_title_start'];
			$r .= $params['title'];
			$r .= $params['block_title_end'];
			
			// Convert quotes
			$tags = str_replace( array('&laquo;', '&raquo;'), '"', $content );
			
			$vars = array(
					'tagcloud'	=>	urlencode('<tags>'.$tags.'</tags>'),
					'rnumber'	=>	floor( rand() * 9999999 ),
					'tcolor'	=>	'0x'.$params['tcolor'],
				//	'tcolor2'	=>	'0x'.$params['tcolor2'],
					'hicolor'	=>	'0x'.$params['hicolor'],
					'tspeed'	=>	$params['tspeed'],
					'distr'		=>	($params['distr']) ? 'true' : 'false',
					'mode'		=>	'tags',
				);
			
			$arr = array();
			foreach( $vars as $k => $v ) $arr[] = $k.'='.$v;
			
			$query = implode( '&amp;', $arr );
			$flash_url = $plugins_url.'flash_tag_cloud_plugin/tagcloud.swf';
			$width = $params['auto_width'] ? '100%' : $params['width'].'px'; 
			
			$r .= '<object type="application/x-shockwave-flash" data="'.$flash_url.'" width="'.$width.'" height="'.$params['height'].'px">
					<param name="movie" value="'.$flash_url.'" />
					<param name="allowScriptAccess" value="always" />
					<param name="flashvars" value="'.$query.'" />';
			$r .= ($params['trans']) ? '<param name="wmode" value="transparent" />' : '';
			$r .= ($params['bgcolor'] && !$params['trans']) ? '<param name="bgcolor" value="'.$params['bgcolor'].'" />' : '';
			$r .= $content /* display non-flash tags for robots */.'
				  </object>';
			
			$r  .= $params['block_end'];
			
			echo $r;
		}
	}
	
	
	function get_tags( $params )
	{
		global $Blog, $blog;

		// Get a list of quoted blog IDs
		$blog_ids = sanitize_id_list($params['blog_ids'], true);

		if( empty($blog) && empty($blog_ids) )
		{	// Nothing to display
			return;
		}
		elseif( empty($blog_ids) )
		{	// Use current Blog
			$blog_ids = $blog;
		}

		$BlogCache = & get_BlogCache();
		
		if( function_exists('get_tags') )
		{	// b2evo v5
			$results = get_tags( $blog_ids, $params['max_tags'], $params['filter_list'] );
		}
		else
		{
			if( is_array($blog_ids) )
			{	// Get quoted ID list
				$blog_ids = $DB->quote($blog_ids);
				$where_cats = 'cat_blog_ID IN ('.$blog_ids.')';
			}
			else
			{
				$Blog = & $BlogCache->get_by_ID($blog_ids);

				// Get list of relevant blogs
				$where_cats = trim($Blog->get_sql_where_aggregate_coll_IDs('cat_blog_ID'));
			}

			// fp> verrry dirty and params; TODO: clean up
			// dh> oddly, this appears to not get cached by the query cache. Have experimented a bit, but not found the reason.
			//     It worked locally somehow, but not live.
			//     This takes up to ~50% (but more likely 15%) off the total SQL time. With the query being cached, it would be far better.

			// build query, only joining categories, if not using all.
			$sql = "SELECT LOWER(tag_name) AS tag_name, post_datestart, COUNT(DISTINCT itag_itm_ID) AS tag_count, tag_ID, cat_blog_ID
					FROM T_items__tag INNER JOIN T_items__itemtag ON itag_tag_ID = tag_ID";

			if( $where_cats != '1' )
			{	// we have to join the cats
				$sql .= "
				 INNER JOIN T_postcats ON itag_itm_ID = postcat_post_ID
				 INNER JOIN T_categories ON postcat_cat_ID = cat_ID";
			}

			$sql .= "
				 INNER JOIN T_items__item ON itag_itm_ID = post_ID
				 WHERE $where_cats
				   AND post_status = 'published' AND post_datestart < '".remove_seconds($localtimenow)."'";
			
			if( !empty($params['filter_list']) )
			{	// Filter tags
				$filter_list = explode( ',', $params['filter_list'] ) ;
				
				$filter_tags = array();
				foreach( $filter_list as $l_tag )
				{
					$filter_tags[] = '"'.$DB->escape(trim($l_tag)).'"';
				}
				
				$sql .= ' AND tag_name NOT IN ('.implode(', ', $filter_tags).')';
			}
			
			$sql .= ' GROUP BY tag_name ORDER BY tag_count DESC';
			
			if( !empty($params['max_tags']) )
			{
				$sql .= ' LIMIT '.$params['max_tags'];
			}

			$results = $DB->get_results( $sql, OBJECT, 'Get tags' );
		}
		
		if( empty($results) )
		{	// No tags!
			return;
		}

		$max_count = $results[0]->tag_count;
		$min_count = $results[count($results)-1]->tag_count;
		$count_span = max( 1, $max_count - $min_count );
		$max_size = $params['tag_max_size'];
		$min_size = $params['tag_min_size'];
		$size_span = $max_size - $min_size;
		
		if ($this->params['tag_ordering'] == 'ASC')
		{
			usort($results, array($this, 'tag_cloud_cmp'));
		}
		else if ($this->params['tag_ordering'] == 'RAND')
		{
			shuffle( $results );
		}
		
		// ======
		$count = 0;
		$r = '';
		foreach( $results as $row )
		{
			if( $count > 0 )
			{
				$r .= $params['tag_separator'];
			}
			// If there's a space in the tag name, quote it:
			$tag_name_disp = strpos($row->tag_name, ' ')
				? '&laquo;'.format_to_output($row->tag_name, 'htmlbody').'&raquo;'
				: format_to_output($row->tag_name, 'htmlbody');
			$size = floor( $row->tag_count * $size_span / $count_span + $min_size );
			
			$l_Blog = $BlogCache->get_by_id( $row->cat_blog_ID );
			$r .= $l_Blog->get_tag_link( $row->tag_name, $tag_name_disp, array(
				'style' => 'font-size:'.$size.'pt;',
				'title' => sprintf( $this->T_('Display posts tagged with &laquo;%s&raquo;'), $row->tag_name ) ) );
			$count++;
		}
		return $r;
	}


	function tag_cloud_cmp($a, $b)
	{
		return strcasecmp($a->tag_name, $b->tag_name);
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