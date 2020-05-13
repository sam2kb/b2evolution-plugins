<?php
/**
 * This file implements the Breadcrumbs navigation plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2013 by Sonorth Corp. - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Sonorth Corp.
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class breadcrumbs_plugin extends Plugin
{
	var $name;
	var $code = 'breadcrumbs';
	var $priority = 30;
	var $version = '1.1.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/topic-23896';

	var $min_app_version = '4';
	var $max_app_version = '';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;


	function PluginInit( & $params )
	{
		$this->name = $this->T_('Breadcrumbs navigation');
		$this->short_desc = $this->T_('[Widget] Displays breadcrumbs navigation bar');
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


	function get_widget_param_definitions()
	{
		return array(
			'title' => array(
				'label' => $this->T_('Block title'),
				'defaultvalue' => $this->T_('You are here:'),
				'type' => 'text',
				'note' => $this->T_('Title to display in your skin.'),
			),
			'glue' => array(
				'label' => $this->T_('Crumbs delimiter'),
				'defaultvalue' => '>',
				'type' => 'text',
			),
			'display_home' => array(
				'label' => $this->T_('Home link'),
				'defaultvalue' => 1,
				'type' => 'checkbox',
				'note' => $this->T_('Check to display a "Home" link.'),
			),
		);
	}


	/**
	 * Event handler: Called when beginning the admin html head section.
	 *
	 * @param array Associative array of parameters
	 * @return boolean did we do something?
	 */
	function SkinBeginHtmlHead( & $params )
	{
		require_css( $this->get_plugin_url().str_replace( '_plugin', '', $this->classname ).'.css', true );

		return true;
	}


	function SkinTag( $params )
	{
		global $Settings, $Blog, $MainList, $baseurl, $disp, $cat;

		$params = $this->init_display($params);

		if( $params['block_start'] == '' && $params['list_start'] == '' && $params['item_start'] == '<li>' )
		{	// The widget is placed in a Menu container, let's add our class to suppress default styles
			$params['item_start'] = '<li class="widget_plugin_breadcrumbs_li">';
		}

		$crumbs = array();

		if( $params['display_home'] )
		{	// Home
			if( $default_blog_ID = $Settings->get('default_blog_ID') )
			{
				$BlogCache = & get_BlogCache();
				if( $default_Blog = & $BlogCache->get_by_ID($default_blog_ID, false) )
				{
					$homeurl = $default_Blog->gen_blogurl();
					$crumbs[] = $this->get_url( $this->T_('Home'), $homeurl );
				}
			}

			if( empty($homeurl) )
			{
				$crumbs[] = $this->get_url( $this->T_('Home'), $baseurl );
			}
		}

		if( !empty($Blog) )
		{	// Blog
			$crumbs[] = $this->get_url( $Blog->get('shortname'), $Blog->gen_blogurl() );
		}

		if( !empty($cat) && is_numeric($cat) )
		{	// Category view
			$this->build_cat_tree( $crumbs, $cat );
		}

		if( !empty($MainList) && !empty($MainList->filters['tags']) )
		{	// Tag view
			$crumbs[] = $this->get_url( $MainList->filters['tags'], $Blog->gen_tag_url($MainList->filters['tags']) );
		}

		if( in_array( $disp, array( 'single', 'page' ) ) )
		{	// Single post view
			if( !empty($MainList) )
			{
				$Item = & $MainList->get_by_idx( 0 );
			}

			if( !empty($Item) )
			{
				$this->build_cat_tree( $crumbs, $Item->main_cat_ID );
				$crumbs[] = $this->get_url( $Item->get('title'), $Item->get_permanent_url() );
			}
		}

		// No crumbs, exit here
		if( empty($crumbs) ) return;

		$r  = $params['block_start']."\n";
		if( $params['title'] && $params['block_display_title'] )
		{
			$r .= $params['block_title_start'].$params['title'].$params['block_title_end']."\n";
		}
		$r .= $params['list_start'].$params['item_start'];
		$r .= implode( $params['item_end'].$params['item_start'].$params['glue'].$params['item_end'].$params['item_start'], $crumbs );
		$r .= $params['item_end'].$params['list_start'];
		$r .= $params['block_end']."\n";

		echo $r;
	}


	function build_cat_tree( & $crumbs, $cat_ID )
	{
		if( empty($cat_ID) ) return;

		$ChapterCache = & get_ChapterCache();
		$MainChapter = $ChapterCache->get_by_ID( $cat_ID, false, false );

		if( !empty($MainChapter) )
		{
			// Save current cat
			$current_cat = $this->get_url( $MainChapter->get('name'), $MainChapter->get_permanent_url() );

			while( $Chapter_p = $ChapterCache->get_by_ID( $MainChapter->parent_ID, false, false ) )
			{
				$crumbs[] = $this->get_url( $Chapter_p->get('name'), $Chapter_p->get_permanent_url() );
				$MainChapter = $Chapter_p;
			}

			$crumbs[] = $current_cat;
		}
	}


	function get_url( $name = '', $url = '' )
	{
		return '<a href="'.$url.'">'.$name.'</a>';
	}


	/**
	 * Sets all the display parameters
	 * these will either be the default display params
	 * or the widget display params if it's in a container
	 *
	 * @param array $params
	 */
 	function init_display( $params = array() )
 	{
		// Merge basic defaults
		$params = array_merge( array(
				'block_start' => '<div class="$wi_class$">',
				'block_end' => '</div>',
				'block_display_title' => true,
				'block_title_start' => '<h3>',
				'block_title_end' => '</h3>',
				'collist_start' => '',
				'collist_end' => '',
				'coll_start' => '<h4>',
				'coll_end' => '</h4>',
				'list_start' => '<ul>',
				'list_end' => '</ul>',
				'item_start' => '<li>',
				'item_end' => '</li>',
				'link_default_class' => 'default',
				'item_text_start' => '',
				'item_text_end' => '',
				'item_text' => '%s',
				'item_selected_start' => '<li class="selected">',
				'item_selected_end' => '</li>',
				'item_selected_text' => '%s',
				'grid_start' => '<table cellspacing="1" class="widget_grid">',
				'grid_end' => '</table>',
				'grid_nb_cols' => 2,
				'grid_colstart' => '<tr>',
				'grid_colend' => '</tr>',
				'grid_cellstart' => '<td>',
				'grid_cellend' => '</td>',
				'thumb_size' => 'crop-80x80',
				// 'thumb_size' => 'fit-160x120',
				'link_selected_class' => 'selected',
				'link_type' => 'canonic',		// 'canonic' | 'context' (context will regenrate URL injecting/replacing a single filter)
				'item_selected_text_start' => '',
				'item_selected_text_end' => '',
				'group_start' => '<ul>',
				'group_end' => '</ul>',
				'notes_start' => '<div class="notes">',
				'notes_end' => '</div>',
				'tag_cloud_start' => '<p class="tag_cloud">',
				'tag_cloud_end' => '</p>',
				'limit' => 100,
			), $params );

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

		// Customize params to the current widget:
		// add additional css classes if required
		$widget_css_class = 'widget_plugin_'.$this->code.( empty( $full_params[ 'widget_css_class' ] ) ? '' : ' '.$full_params[ 'widget_css_class' ] );
		// add custom id if required, default to generic id for validation purposes
		$widget_ID = ( !empty($full_params[ 'widget_ID' ]) ? $full_params[ 'widget_ID' ] : 'widget_plugin_'.$this->code.'_'.$this->ID );
		// replace the values
		$full_params = str_replace( array( '$wi_ID$', '$wi_class$' ), array( $widget_ID, $widget_css_class ), $full_params );

		return $full_params;
	}
}

?>