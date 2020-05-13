<?php
/**
 * This file implements the Latest posts widget for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2008-2012 Russian b2evolution - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Russian b2evolution
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class latest_posts_plugin extends Plugin
{
	var $name = 'Latest posts';
	var $code = 'latest_posts';
	var $priority = 30;
	var $version = '0.3.1';
	var $group = 'Sonorth Corp.';
	var $author = 'Russian b2evolution';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=16492';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Display latest posts sorted by main category');
		$this->long_desc = $this->T_('Display latest posts sorted by main category');
	}


	/**
	* Get definitions for widget specific editable params
	*/
	function get_widget_param_definitions( $params )
	{
		return array(
				'title' => array(
					'label' => $this->T_('Widget title'),
					'defaultvalue' => $this->T_('Latest posts'),
					'type' => 'text',
					'note' => $this->T_('Widget title displayed in skin.'),
				),
				'more' => array(
					'label' => $this->T_('More link'),
					'defaultvalue' => $this->T_('More...'),
					'type' => 'text',
				),
				'disp_order' => array(
					'label' => $this->T_('Order'),
					'note' => $this->T_('Order to display items'),
					'type' => 'select',
					'defaultvalue' => 'ASC',
					'options' => array( 'DESC' => $this->T_('Newest to oldest'),
										'ASC' => $this->T_('Oldest to newest'),
										/*'RAND' => $this->T_('Random selection')*/ ),
				),
				'blog_ID' => array(
					'label' => $this->T_('Blog'),
					'note' => $this->T_('ID of the blog to use, leave empty for the current blog.'),
					'size' => 4,
				),
				'cols' => array(
					'label' => $this->T_('Columns'),
					'note' => $this->T_('Number of columns/categories displayed in one row.'),
					'defaultvalue' => 2,
					'size' => 4,
				),
				'limit' => array(
					'label' => $this->T_('Display'),
					'note' => $this->T_('Max items to display.'),
					'size' => 4,
					'defaultvalue' => 5,
				),
			);
	}


	function SkinBeginHtmlHead()
	{
		global $plugins_url;
		require_css( $plugins_url.$this->classname.'/'.$this->code.'.css', true );
	}


	/**
	 * Event handler: SkinTag (widget)
	 *
	 * @param array Associative array of parameters.
	 * @return boolean did we display?
	 *
	 */
	function SkinTag( $params )
	{
		/**
		 * Default params:
		 */
		if(!isset($params['block_start'])) $params['block_start'] = '<div class="bSideItem widget_plugin_'.$this->code.'">';
		if(!isset($params['block_end'])) $params['block_end'] = "</div>\n";
		if(!isset($params['block_title_start'])) $params['block_title_start'] = '<h3>';
		if(!isset($params['block_title_end'])) $params['block_title_end'] = '</h3>';

		if(!isset($params['title'])) $params['title'] = $this->T_('Latest posts');
		if(!isset($params['more'])) $params['more'] = $this->T_('More...');
		if(!isset($params['disp_order'])) $params['disp_order'] = 'DESC';
		if(!isset($params['blog_ID'])) $params['blog_ID'] = NULL;
		if(!isset($params['cols'])) $params['cols'] = 2;
		if(!isset($params['limit'])) $params['limit'] = 5;

		// Display our widget
		$this->disp_cat_item_list( $params );
	}



	/**
	 * List of items by category
	 *
	 * @param array MUST contain at least the basic display params
	 */
	function disp_cat_item_list( $params )
	{
		global $Blog;
		global $timestamp_min, $timestamp_max;

		$BlogCache = & get_Cache( 'BlogCache' );

		// Get all public blogs
		// $blog_array = $BlogCache->load_public();

		if( empty($params['blog_ID']) )
		{
			$lp_Blog = $Blog;
		}
		else
		{
			$lp_Blog = & $BlogCache->get_by_ID( $params['blog_ID'], false );
		}

		if( !is_object($lp_Blog) )
		{
			echo $params['block_start'];
			echo $this->T_('The requested Blog doesn\'t exist any more!');
			echo $params['block_end'];
			return;
		}


		# This is the list of categories to restrict the blog to (cats will be displayed recursively)
		# Example: $cat = '4,6,7';
		$cat = '';

		# This is the array if categories to restrict the blog to (non recursive)
		# Example: $catsel = array( 4, 6, 7 );
		$catsel = array();

		// Compile cat array stuff:
		$cat_array = array();
		$cat_modifier = '';
		compile_cat_array( $cat, $catsel, /* by ref */ $cat_array, /* by ref */  $cat_modifier, $lp_Blog->ID );

		$limit = 1000;

		$lp_BlogList = new ItemListLight( $lp_Blog, $timestamp_min, $timestamp_max, $limit );

		$lp_BlogList->set_filters( array(
				'cat_array' => $cat_array,
				'cat_modifier' => $cat_modifier,
				'orderby' => 'main_cat_ID datecreated',
				'order' => $params['disp_order'],
				'unit' => 'posts',
			), false ); // we don't want to memorise these params

		// Run the query:
		$lp_BlogList->query();

		if( ! $lp_BlogList->get_num_rows() )
		{ // empty list:
			return;
		}

		echo $params['block_start'];

		echo $params['block_title_start'];
 		echo $params['title'];
		echo $params['block_title_end'];

		echo '<table border="0" cellspacing="20" cellpadding="0" class="'.$this->code.'_table">';

		/**
		 * @var ItemLight
		 */
		$nb_cols = $params['cols'];
		$count = 0;
		$prev_post_ID = 0;
		while( $Item = & $lp_BlogList->get_category_group() )
		{
			if( $count % $nb_cols == 0 )
			{
				echo "\n<tr>";
			}
			if( $Item->ID != $prev_post_ID )
			{
				$prev_post_ID = $Item->ID;
				$count++;
			}

			// Open new cat:
			echo "\n\t".'<td valign="top" class="'.$this->code.'_td">';

			// ======================================
			$Chapter = & $Item->get_main_Chapter();
			$cat_name = $Chapter->get( 'name', 'htmlbody' );

			echo '<span class="'.$this->code.'_cat_name">'.$cat_name.'</span> ';
			echo '<a href="'.$Chapter->get_permanent_url().'">'.$params['more'].'</a>';

			echo '<ul>';
			$i = 0;
			while( $Item = & $lp_BlogList->get_item() )
			{
				if( $i >= $params['limit'] )
					continue;

				$Item->title( array(
						'before' => '<li>',
						'after'  => '</li>',
					) );

				$i++;
			}
			echo '</ul>';
			// ======================================

			echo '</td>';
			if( $count % $nb_cols == 0 )
			{
				echo '</tr>';
			}
		}

		if( $count && ( $count % $nb_cols != 0 ) )
		{
			echo '</tr>';
		}
		echo '</table>';

		echo $params['block_end'];
	}
}

?>