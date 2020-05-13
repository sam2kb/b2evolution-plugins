<?php
/**
 * This file implements the Posts Timeline widget for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2012 Russian b2evolution - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Russian b2evolution
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class posts_timeline_plugin extends Plugin
{
	var $name = 'Posts Timeline';
	var $code = 'sn_ptimeline';
	var $priority = 30;
	var $version = '1.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Russian b2evolution';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;

	// Internal
	var $_locales = array();

	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Display latest posts from all public blogs');
		$this->long_desc = $this->T_('This widget displays the latest posts from all public blogs. Blog admins can "follow" individual feeds.');
	}


	/**
	* Get definitions for widget specific editable params
	*/
	function get_widget_param_definitions( $params )
	{
		$locales = $this->get_available_locales();

		return array(
				'title' => array(
					'label' => $this->T_('Widget title'),
					'defaultvalue' => $this->T_('Posts timeline'),
					'type' => 'text',
					'note' => $this->T_('Widget title displayed in skin.'),
				),
				'locale' => array(
					'label' => $this->T_('Posts locale'),
					'note' => $this->T_('Only show posts written in selected locale (language).'),
					'type' => 'select',
					'options' => $locales,
					'defaultvalue' => '',
				),
				'limit_sub' => array(
					'type' => 'integer',
					'label' => $this->T_('Limit (follow)'),
					'note' => $this->T_('Max items to display from the blogs you follow.<br />Enter 0 disable.'),
					'size' => 4,
					'defaultvalue' => 6,
				),
				'limit_public' => array(
					'type' => 'integer',
					'label' => $this->T_('Limit (public)'),
					'note' => $this->T_('Max items to display from public blogs.<br />Enter 0 disable.'),
					'size' => 4,
					'defaultvalue' => 3,
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
		global $DB, $Blog;

		// Init default params
		$params = $this->init_display($params);

		$r = array();
		if( $blog_subs = $Blog->get_setting('sn_ptimeline_blog_subs') )
		{	// First display selected blogs
			$params['limit'] = abs($params['limit_sub']);
			$r = $this->get_posts( $params, $blog_subs );

			$params['limit'] = abs($params['limit_public']);
			$r += $this->get_posts( $params, $blog_subs, true );
		}
		else
		{
			$params['limit'] = abs($params['limit_sub']) + abs($params['limit_public']);
			$r = $this->get_posts( $params );
		}

		if( !empty($r) )
		{
			echo $params['block_start'];

			echo $params['block_title_start'];
			echo $params['title'];
			echo $params['block_title_end'];
			echo '<ul class="'.$this->code.'">'.implode( "\n", $r ).'</ul>';
			echo $params['block_end'];
		}
	}


	function get_posts( $params, $blog_subs = '', $not_in = false )
	{
		global $DB, $Blog, $current_User, $ReqURI, $localtimenow;

		$limit = $params['limit'] * 2; // Get more in case we filter out some of them at a later time

		if( !empty($blog_subs) )
		{
			$where = 'blog_ID IN ('.$DB->quote($blog_subs).')';
			if( $not_in )
			{
				$where = str_replace( 'IN (', 'NOT IN (', $where );
				$where .= ' AND blog_in_bloglist <> 0';
			}
		}
		else
		{
			$where = 'blog_in_bloglist <> 0';
		}

		$SQL = 'SELECT post_ID
				FROM T_items__item
					INNER JOIN T_postcats ON post_ID = postcat_post_ID
					INNER JOIN T_categories ON postcat_cat_ID = cat_ID
					INNER JOIN T_blogs ON cat_blog_ID = blog_ID
				WHERE '.$where.'
				AND post_ptyp_ID = 1
				AND post_status = "published"';

		if( !empty($params['locale']) )
		{
			$SQL .= ' AND post_locale = "'.$DB->escape($params['locale']).'"';
		}

		$SQL .= ' AND post_datestart <= "'.remove_seconds($localtimenow).'"
				ORDER BY post_ID DESC, post_datestart DESC
				LIMIT '.$limit;

		$r = array();
		if( $rows = $DB->get_col($SQL) )
		{
			$BlogCache = & get_BlogCache();
			$ItemCache = & get_ItemCache();

			if( $params['limit'] > count($rows) ) $params['limit'] = count($rows);

			// Make sure it's an array
			if( empty($blog_subs) ) $blog_subs = array();

			$current_blog = 0;
			for( $n=0; $n<$params['limit']; $n++ )
			{
				if( ($Item = & $ItemCache->get_by_ID($rows[$n], false, false)) === false ) continue;

				$blog_ID = $Item->get_blog_ID();
				if( ($lBlog = & $BlogCache->get_by_ID($blog_ID, false, false)) === false ) continue;

				$k = (string) $Item->ID;

				$button = '';
				$btn_class = '';
				if( is_logged_in() && $current_User->check_perm_blogowner($Blog->ID) )
				{	// Current user is the owner of displayed blog
					$btn_class = 'sn_btn';

					// Add subscribtion
					$action = 'add';
					$title = 'Follow posts from this blog';
					if( in_array( $blog_ID, $blog_subs ) )
					{	// Remove subscribtion
						$action = 'remove';
						$title = 'Unfollow posts from this blog';
					}
					$plugin_url = $this->get_htsrv_url('do', array(
								'sblog' => $blog_ID,
								'tblog' => $Blog->ID,
								'action' => $action,
								'redirect_to' => rawurlencode($ReqURI),
							), '&', true);

					$button = action_icon( $this->T_($title), $action, $plugin_url, 0, 5 );
				}

				$r[$k] = '<li class="'.$this->code.'_item">';
				if( $current_blog != $blog_ID )
				{	// Display only once for each new blog
					$r[$k] = '<li class="'.$this->code.'_blog '.$btn_class.'">'; // overwrite!
					$r[$k] .= $button.$lBlog->get('name');
					$current_blog = $blog_ID;
					$r[$k] .= '</li><li class="'.$this->code.'_item">';
				}
				$r[$k] .= $Item->get_title();
				$r[$k] .= '</li>';
			}
		}
		return $r;
	}


	function GetHtsrvMethods()
	{
		return array('do');
	}


	function htsrv_do( & $params )
	{
		global $DB, $current_User, $io_charset;

		// Make sure the async responses are never cached:
		header_nocache();
		header_content_type( 'text/html', $io_charset );

		if( empty($params['redirect_to']) ) $params['redirect_to'] = '';

		$params['redirect_to'] = rawurldecode($params['redirect_to']);
		if( preg_match( '~^(ht|f)tps?://~', $params['redirect_to'] ) )
		{	// Either empty or absolute URL, reset it
			$params['redirect_to'] = '';
		}

		if( empty($params['action']) || empty($params['sblog']) || empty($params['tblog']) )
		{
			$this->err( $this->T_('Invalid request'), $params['redirect_to'] );
		}
		if( ! is_logged_in() )
		{
			$this->err( $this->T_('Please login first'), $params['redirect_to'] );
		}

		$BlogCache = & get_BlogCache();

		if( ($Blog = & $BlogCache->get_by_ID( $params['tblog'], false, false )) === false )
		{
			$this->err( $this->T_('Unable to find target blog'), $params['redirect_to'] );
		}
		if( ! $current_User->check_perm_blogowner($Blog->ID) )
		{
			$this->err( $this->T_('You are not allowed to manage this blog'), $params['redirect_to'] );
		}

		if( ! $blog_subs = $Blog->get_setting('sn_ptimeline_blog_subs') )
		{
			$blog_subs = array();
		}

		switch( $params['action'] )
		{
			case 'add':
				if( $sBlog = & $BlogCache->get_by_ID( $params['sblog'], false, false ) )
				{
					$blog_subs[] = $params['sblog'];
					$msg = sprintf( $this->T_('You are now following &quot;%s&quot;.'), $sBlog->get('name') );
				}
				break;

			case 'remove':
				foreach( $blog_subs as $k=>$v )
				{
					if( $v == $params['sblog'] )
					{
						unset($blog_subs[$k]);
						$msg = $this->T_('Blog subscription removed!');
					}
				}
				break;
		}

		if( !empty($msg) )
		{
			$Blog->set_setting( 'sn_ptimeline_blog_subs', array_unique($blog_subs) );
			$Blog->dbupdate();
			$this->msg($msg, 'success');
		}
		else
		{
			$this->msg( $this->T_('Your subscriptions have not changed'), 'note');
		}

		header_redirect($params['redirect_to']);
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


	function get_available_locales()
	{
		global $DB;

		if( ! is_admin_page() ) return $this->_locales;

		if( empty($this->_locales) )
		{
			$loc = array( '' => T_('Any') );
			if( $rows = $DB->get_col('SELECT DISTINCT post_locale FROM T_items__item') )
			{
				$loc += array_combine( array_values($rows), array_values($rows) );
				ksort($loc);
			}
			$this->_locales = $loc;
		}
		return $this->_locales;
	}


	function err( $str, $redirect_to = '', $add_prefix = true )
	{
		if( $add_prefix ) $str = $this->name.': '.$str;
		$this->msg($str, 'error');
		header_redirect($redirect_to);
		die(0);
	}
}

?>