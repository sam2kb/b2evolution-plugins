<?php
/**
 * This file implements the Blog list widget for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2012 by Sonorth Corp. - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Sonorth Corp.
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class blog_list_plugin extends Plugin
{
	var $name = 'Blog list widget';
	var $code = 'BlogListWgt';
	var $priority = 30;
	var $version = '1.1.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=22919';

	var $min_app_version = '4';
	var $max_app_version = '5';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Displays a nested menu of blogs and their categories');
		$this->long_desc = $this->T_('This widget displays a nested menu of blogs and their categories.');
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


	function get_widget_param_definitions( $params )
	{
		return array(
			'title' => array(
					'type' => 'text',
					'label' => $this->T_('Widget title'),
					'defaultvalue' => $this->T_('Browse blogs'),
				),
			'cat_list' => array(
					'type' => 'checkbox',
					'label' => $this->T_('Display categories'),
					'defaultvalue' => true,
					'note' => $this->T_('Should we display categories of each blog?'),
				),
			'parent_tag' => array(
					'type' => 'text',
					'label' => $this->T_('Widget parent tag'),
					'defaultvalue' => 'li',
					'note' => $this->T_('What HTML tag should we use to wrap the widget?').'<br />'.
								$this->T_('[li] - if you want to place the widget in "Menu" or "Sidebar" containers').'<br />'.
								$this->T_('[div] - for other containers'),
					'maxlength' => 6,
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
		require_css( $this->get_plugin_url().'blog_list.css', true );

		return true;
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
		global $Blog;

		$callbacks = array(
			'line'         => array( $this, 'cat_line' ),
			'no_children'  => array( $this, 'cat_no_children' ),
			'before_level' => array( $this, 'cat_before_level' ),
			'after_level'  => array( $this, 'cat_after_level' )
		);

		$BlogCache = & get_BlogCache();
		$BlogCache->load_all();

		$ChapterCache = & get_ChapterCache();

		if(!isset($params['cat_list'])) $params['cat_list'] = true;
		if(!isset($params['parent_tag'])) $params['parent_tag'] = 'li';

		$params['block_start'] = '<'.$params['parent_tag'].' class="'.$this->code.'">';
		$params['block_end'] = '</'.$params['parent_tag'].'>';

		//$BlogCache->current_idx = -1;

		$r = '';
		while( $l_Blog = & $BlogCache->get_next() )
		{
			$r .= $params['item_start'];
			$r .= '<a href="'.$l_Blog->gen_blogurl().'">'.$l_Blog->name.'</a>';
			if( $params['cat_list'] )
			{	// Dipsplay categories
				$catlist = $ChapterCache->recurse( $callbacks, $l_Blog->ID );

				if( !empty($catlist) )
				{
					$r .= $params['group_start'];
					$r .= $catlist;
					$r .= $params['group_end'];
				}
			}
			$r .= $params['item_end'];
		}

		// Add "has_cats" class to categories/blogs with children
		$r = preg_replace( '~>(<a[^>]+>[^>]+</a>)<ul~is', ' class="has_cats">$1<ul', $r );

		// START DISPLAY:
		echo $params['block_start'];
		if( !empty($params['title']) )
		{
			echo '<a href="'.$Blog->gen_blogurl().'">'.$params['title'].'</a>';
		}
		echo $params['group_start'];
		echo $r;
		echo $params['group_end'];
		echo $params['block_end'];
	}


	/**
	 * Callback: Generate category line when it has children
	 *
	 * @param Chapter generic category we want to display
	 * @param int level of the category in the recursive tree
	 * @return string HTML
	 */
	function cat_line( $Chapter )
	{
		$r = '<li><a href="'.$Chapter->get_permanent_url().'">'.$Chapter->dget('name').'</a>';

		// Do not end line here because we need to include children first!
		// $r .= $this->disp_params['item_end'];

		return $r;
	}


	/**
	 * Callback: Generate category line when it has no children
	 *
	 * @param Chapter generic category we want to display
	 * @param int level of the category in the recursive tree
	 * @return string HTML
	 */
	function cat_no_children()
	{
		// End current line:
		return '</li>';
	}


	/**
	 * Callback: Generate code when entering a new level
	 *
	 * @param int level of the category in the recursive tree
	 * @return string HTML
	 */
	function cat_before_level( $level )
	{
		$r = '';
		if( $level > 0 )
		{	// If this is not the root:
			$r .= '<ul>';
		}
		return $r;
	}


	/**
	 * Callback: Generate code when exiting from a level
	 *
	 * @param int level of the category in the recursive tree
	 * @return string HTML
	 */
	function cat_after_level( $level )
	{
		$r = '';
		if( $level > 0 )
		{	// If this is not the root:
			$r .= '</ul>';
			// End current (parent) line:
			$r .= '</li>';
		}
		return $r;
	}
}

?>