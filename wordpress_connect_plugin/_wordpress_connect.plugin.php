<?php
/**
 * This file implements the WordPress Connect plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2012 Russian b2evolution - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Russian b2evolution
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class wordpress_connect_plugin extends Plugin
{
	var $name = 'WordPress Connect';
	var $code = 'sn_wpconnect';
	var $priority = 10;
	var $version = '1.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = '';

	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;

	// Internal
	private $_debug = false; // set TRUE to enable
	private $_temp_disabled = false;
	private $_initial_method_params = NULL;
	private $_sync_Cache = array();


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Sync b2evolution posts and comments to your WordPress blog.');
		$this->long_desc = '';
	}


	function GetDependencies()
	{
		return array(
				'requires' => array(
					'app_min' => '4.1',
				),
			);
	}


	function GetDbLayout()
	{
		return array(
			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('sync')." (
					wpc_b2evo_ID INT(11) NOT NULL,
					wpc_b2evo_type VARCHAR(15) NOT NULL,
					wpc_wp_ID INT(11) NOT NULL,
					wpc_user_ID INT(11) NOT NULL,
					UNIQUE b2evo_key (wpc_b2evo_ID, wpc_b2evo_type),
					INDEX (wpc_b2evo_ID),
					INDEX (wpc_wp_ID),
					INDEX (wpc_user_ID)
				)",
		);
	}


	/**
	 * Define user settings that the plugin uses/provides.
	 */
	function GetDefaultUserSettings()
	{
		return array(
				'enabled' => array(
					'label' => $this->T_('Enable sync'),
					'type' => 'checkbox',
					'defaultvalue' => false,
					'note' => $this->T_('Check to enable synchronization.'),
				),
				'host' => array(
					'label' => $this->T_('Wordpress XML-RPC URL'),
					'size' => 40,
					'note' => '<br />'.$this->T_('Example: http://my-wordpress-blog.tld/xmlrpc.php'),
				),
				'user' => array(
					'label' => $this->T_('Username'),
					'size' => 15,
				),
				'pass' => array(
					'label' => $this->T_('Password'),
					'size' => 15,
					'type' => 'password',
				),
			);
	}


	function AdminAfterMenuInit()
	{	// add our tab
		$this->register_menu_entry( $this->name );
	}


	function AdminTabAction()
	{
		global $Settings, $Messages, $DB, $current_User;

		if( !$this->check_perms() ) return;

		if( $blog_ID = param( $this->get_class_id('blog_ID'), 'integer' ) )
		{
			$BlogCache = & get_BlogCache();
			$Blog = & $BlogCache->get_by_ID( $blog_ID );

			$is_blog_admin = $current_User->check_perm( 'blog_admin', 'edit', false, $blog_ID );

			$msg = $sync_i = $sync_с = array();

			if( $is_blog_admin )
			{
				// ===== Sync cats =====
				$SQL = new SQL();
				$SQL->SELECT( 'cat_ID, cat_name, cat_order' );
				$SQL->FROM( 'T_categories' );
				$SQL->WHERE( $Blog->get_sql_where_aggregate_coll_IDs('cat_blog_ID') );

				if( $Settings->get('chapter_ordering') == 'manual' )
				{	// Manual order
					$SQL->ORDER_BY( 'cat_order' );
				}
				else
				{	// Alphabetic order
					$SQL->ORDER_BY( 'cat_name' );
				}

				if( $rows = $DB->get_results( $SQL->get() ) )
				{
					$ChapterCache = & get_ChapterCache();
					foreach( $rows as $row )
					{
						if( $Chapter = & $ChapterCache->get_by_ID( $row->cat_ID, false ) !== false )
						{
							$sync_c[] = $this->alter_object( $Chapter, 'Chapter', 'edit' );
						}
					}
					$msg[] = sprintf( $this->T_('Categories synchronized: %d'), count( array_filter($sync_c) ) );
				}
			}


			// ===== Sync items =====
			load_class( 'items/model/_itemlist.class.php', 'ItemList2' );
			$ItemList = new ItemList2( $Blog, NULL, NULL );

			$ItemList->set_filters( array(
					'limit'				=> 0, // no limit
					'types'				=> NULL, // all item types
					'author_assignee'	=> ($is_blog_admin ? NULL : $current_User->ID), // allo basic users to sync their own posts only
				), false );

			// Run the query:
			$ItemList->query();

			while( $Item = & $ItemList->get_item() )
			{
				$sync_i[] = $this->alter_object( $Item, 'Item', 'edit' );
			}

			$msg[] = sprintf( $this->T_('Posts synchronized: %d'), count( array_filter($sync_i) ) );

			$Messages->clear('success'); // clear extra messages
			foreach( $msg as $ms )
			{
				$this->msg( $ms, 'success' );
			}
		}
	}


	function AdminTabPayload()
	{
		global $current_User;

		if( !$this->check_perms() ) return;

		$BlogCache = & get_BlogCache();

		// Post all
		$Form = new Form( 'admin.php', '', 'post' );
		$Form->begin_form( 'fform', $this->name );
		$Form->begin_fieldset( $this->T_('Synchronize your content with WordPress') );
		$Form->hidden_ctrl(); // needed to pass the "ctrl=tools" param
		$Form->hiddens_by_key( get_memorized() ); // needed to pass all other memorized params, especially "tab"

		$Form->select_input_object( $this->get_class_id('blog_ID'), '', $BlogCache, $this->T_('Blog'), array( 'note' => $this->T_('Select the blog you want to synchronize.<br /><br ><p class="error">All existing posts or categories will be updated!</p>') ) );

		$Form->end_fieldset();
		$Form->end_form( array( array( 'value' => $this->T_('Post all !'), 'onclick' => 'return confirm(\''.$this->T_('You are about to send all posts to LiveJournal!\nDo you want to continue?').'\')' ) ) );
	}


	function check_perms()
	{
		global $current_User;

		if( !is_logged_in() )
		{	// Not logged in
			$this->msg( $this->T_('You\'re not logged in!'), 'note' );
			return false;
		}
		if( !$this->UserSettings->get('host') || !$this->UserSettings->get('user') || !$this->UserSettings->get('pass') )
		{	// Plugin not configured
			$this->msg( $this->T_('Please configure the plugin first.') );
			return false;
		}
		return true;
	}


	/**
	 * Event handler: called at the end of {@link DataObject::dbinsert() inserting an object in the database}.
	 */
	function AfterObjectInsert( & $params )
	{
		return $this->alter_object( $params['Object'], $params['type'], 'new' );
	}


	/**
	 * Event handler: called at the end of {@link DataObject::dbupdate() updating an object in the database}.
	 */
	function AfterObjectUpdate( & $params )
	{
		return $this->alter_object( $params['Object'], $params['type'], 'edit' );
	}


	/**
	 * Event handler: called at the end of {@link DataObject::dbdelete() deleting an object from the database}.
	 */
	function AfterObjectDelete( & $params )
	{
		return $this->alter_object( $params['Object'], $params['type'], 'delete' );
	}


	function alter_object( & $Obj, $type, $action )
	{
		global $DB, $current_User;

		if( $this->_temp_disabled ) return; // temorarily disabled

		if( $this->UserSettings->get('enabled') )
		{
			if( !$this->check_perms() ) return;
		}
		else
		{
			$this->msg( $this->T_('Synchronization is currently disabled. Go to user settings and enable WordPress Connect.') );
			return;
		}

		$this->dbg($type.' #'.$Obj->ID.' '.$action);

		// Set defaults
		$params = $this->init_m_params();

		switch( $type )
		{
			case 'Chapter':
				if( ! $Obj->name ) return; // It's just a pre-action, not a regular DB insert/update/delete
				if( ! $sync_ID = $this->get_sync_ID($Obj->ID, $type) ) $action = 'new';

				if( $action != 'new' )
				{	// Add synced WP ID for edit/delete actions
					$params[] = $sync_ID;
				}

				$params[] = array_filter( array(
						'taxonomy'		=> 'category',
						'name'			=> $Obj->name,
						'slug'			=> $Obj->urlname,
						'description'	=> $Obj->description,
						'parent'		=> $this->sync_parent_cats($Obj),
					) );

				if( $wp_ID = $this->send( 'wp.'.$action.'Term', $params ) )
				{	// Change WP ID to action
					if( $action != 'new' ) $wp_ID = $action;
				}
				break;

			case 'Item':
				if( ! $sync_ID = $this->get_sync_ID($Obj->ID, $type) ) $action = 'new';

				if( $action != 'new' )
				{	// Add synced WP ID for edit/delete actions
					$params[] = $sync_ID;
					$b2evo_cats = postcats_get_byID($Obj->ID);
				}
				else
				{	// create
					$b2evo_cats = empty($Obj->extra_cat_IDs) ? array() : $Obj->extra_cat_IDs;
					array_unshift( $b2evo_cats, $Obj->main_cat_ID );
					$b2evo_cats = array_unique($b2evo_cats);
				}
				$b2evo_cats = array_combine($b2evo_cats, $b2evo_cats);

				$date = datetime_to_iso8601($Obj->issue_date);
				xmlrpc_set_type( $date, 'datetime' );

				$wp_cats = array();
				$synced_cats = $this->get_chapters($b2evo_cats);
$this->dbg('b2evo_cats:'.var_export($b2evo_cats, true) );
$this->dbg('synced:'.var_export($synced_cats, true) );

				if( !empty($synced_cats) )
				{
					// Keep existing
					$wp_cats = array_intersect_key( $synced_cats, $b2evo_cats );
					$missing_cats = array_diff_key( $synced_cats, $b2evo_cats ) + array_diff_key( $b2evo_cats, $synced_cats );
$this->dbg('existing:'.var_export($wp_cats, true) );
$this->dbg('missing:'.var_export($missing_cats, true) );

					foreach( $missing_cats as $missing_cat )
					{	// Add missing
						if( $wp_cat_ID = $this->link_existing_chapter($missing_cat) )
						{
							$wp_cats[] = $wp_cat_ID;
						}
					}
				}
				else
				{
					foreach( $b2evo_cats as $ch_ID )
					{
						$wp_cats[] = $this->link_existing_chapter( $ch_ID );
					}
				}
				$wp_cats = array_filter($wp_cats); // remove empty

				// Make sure the value is array
				$Obj->tags = empty($Obj->tags) ? array() : $Obj->tags;

				$tmp = array(
						'post_status'		=> $this->wp_or_b2evo_item_status( $Obj->status, 'wp' ),
						'post_title'		=> $Obj->title,
						'post_author'		=> $Obj->creator_user_ID,
						'post_excerpt'		=> $Obj->get_excerpt(),
						'post_content'		=> $Obj->content,
						'post_date'			=> $date,
						'post_type'			=> ($Obj->ptyp_ID == 1000 ? 'page' : 'post'),
						'post_format'		=> 'standard',
						'comment_status'	=> ( ($Obj->comment_status == 'disabled') ? 'closed' : $Obj->comment_status ),
						'ping_status'		=> 'closed',
						'sticky'			=> $Obj->featured,
						'terms'				=> array( 'category' => $wp_cats ),
						'terms_names'		=> array( 'post_tag' => $Obj->tags ),
					);

				if( $tmp['post_type'] == 'page' )
				{	// WP page cannot be assigned to a category or have tags
					unset($tmp['terms']);
					unset($tmp['terms_names']);
				}

				$params[] = $tmp;

				if( $wp_ID = $this->send( 'wp.'.$action.'Post', $params ) )
				{	// Change WP ID to action
					if( $action != 'new' ) $wp_ID = $action;
				}
				break;
		}

		if( !empty($wp_ID) )
		{
			// Update DB
			if( ! $this->wp_sync( $Obj->ID, $wp_ID, $type, $action ) )
			{
				$this->dbg('Unable to save DB changes');
			}

			return $wp_ID;
		}
	}


	function link_existing_chapter( $ch_ID, $noparent = false )
	{
		$ChapterCache = & get_ChapterCache();
		if( empty($ch_ID) ) return;
		if( ($Chapter = & $ChapterCache->get_by_ID($ch_ID, false, false)) === false ) return;

		$params = $this->init_m_params();
		$params[] = array_filter( array(
				'taxonomy'		=> 'category',
				'name'			=> $Chapter->name,
				'slug'			=> $Chapter->urlname,
				'description'	=> $Chapter->description,
				'parent'		=> ($noparent ? 0 : $this->sync_parent_cats($Chapter)),
			) );

		$wp_cat_ID = $this->send( 'wp.newTerm', $params );
		if( is_numeric($wp_cat_ID) )
		{	// Save
			if( $this->wp_sync( $Chapter->ID, $wp_cat_ID, 'Chapter', 'new' ) )
			{
				return $wp_cat_ID;
			}
		}
		return false;
	}


	function sync_parent_cats( $Chapter )
	{
		if( ! $Chapter->parent_ID ) return 0;

		$ChapterCache = & get_ChapterCache();

		$synced_ID = $this->get_sync_ID( $Chapter->parent_ID, 'Chapter' );
		if( empty($synced_ID) )
		{	// Sync new parents
			$cat_chain = array();
			while( $Chapter_p = $ChapterCache->get_by_ID( $Chapter->parent_ID, false, false ) )
			{
				array_unshift($cat_chain, $Chapter_p->ID);
				$Chapter = $Chapter_p;
			}

			// Top-level parent cat
			$this->link_existing_chapter($Chapter->parent_ID, true);

			foreach( $cat_chain as $ch_ID )
			{	// Now link all lower-level cats
				$wp_ID = $this->link_existing_chapter($ch_ID);
			}
			return $wp_ID; // first parent ID
		}
		else
		{	// Get previosly synced parent ID
			return $synced_ID;
		}
	}


	function load_sync_Cache( $b2evo_type )
	{
		global $DB, $current_User;

		$this->_sync_Cache[$b2evo_type] = array(); // init

		$SQL = 'SELECT wpc_b2evo_ID, wpc_wp_ID FROM '.$this->get_sql_table('sync').'
				WHERE wpc_b2evo_type = '.$DB->quote($b2evo_type).'
				AND wpc_user_ID = '.$DB->quote($current_User->ID);

		if( $rows = $DB->get_results($SQL) )
		{
			foreach( $rows as $row )
			{
				$this->_sync_Cache[$b2evo_type][$row->wpc_b2evo_ID] = $row->wpc_wp_ID;
			}
		}
	}


	function get_sync_ID( $b2evo_ID, $b2evo_type )
	{
		global $DB;

		if( !isset( $this->_sync_Cache[$b2evo_type] ) )
		{	// Load sync cache for this object type
			$this->load_sync_Cache($b2evo_type);
		}

		return isset($this->_sync_Cache[$b2evo_type][$b2evo_ID]) ? $this->_sync_Cache[$b2evo_type][$b2evo_ID] : false;
	}


	function get_chapters( $b2evo_cat_IDs )
	{
		global $DB, $current_User;

		if( !is_array($b2evo_cat_IDs) ) $b2evo_cat_IDs = array($b2evo_cat_IDs);

		if( !isset( $this->_sync_Cache['Chapter'] ) )
		{	// Load sync cache for this object type
			$this->load_sync_Cache('Chapter');
		}

		$IDs = array();
		foreach( $b2evo_cat_IDs as $b2evo_ID )
		{
			if( isset($this->_sync_Cache['Chapter'][$b2evo_ID]) )
			{
				$IDs[$b2evo_ID] = $this->_sync_Cache['Chapter'][$b2evo_ID];
			}
		}
		return $IDs;
	}


	function wp_sync( $b2evo_ID, $wp_ID, $type, $action )
	{
		global $DB, $current_User;

		if( !is_numeric($b2evo_ID) ) return false;

		$user_ID = $current_User->ID;

		if( is_numeric($wp_ID) )
		{	// Create
			$DB->query('REPLACE INTO '.$this->get_sql_table('sync').' ( wpc_b2evo_ID, wpc_b2evo_type, wpc_wp_ID, wpc_user_ID )
						VALUES('.$DB->quote($b2evo_ID).', '.$DB->quote($type).', '.$DB->quote($wp_ID).', '.$DB->quote($user_ID).')');

			$this->_sync_Cache[$type][$b2evo_ID] = $wp_ID; // add to cache

			$this->msg( sprintf( $this->T_('WordPress %s created'), $type ), 'success' );
		}
		elseif( $wp_ID == 'delete' )
		{	// Delete
			$DB->query('DELETE FROM '.$this->get_sql_table('sync').'
						WHERE wpc_b2evo_ID = '.$DB->quote($b2evo_ID).'
						AND wpc_b2evo_type = '.$DB->quote($type).'
						AND user_ID = '.$DB->quote($user_ID));

			$this->msg( sprintf( $this->T_('WordPress %s deleted'), $type ), 'success' );
		}
		elseif( $wp_ID == 'edit' )
		{	// Update, do not edit sync table
			$this->msg( sprintf( $this->T_('WordPress %s updated'), $type ), 'success' );
		}

		return true;
	}


	function send( $method, $params )
	{
		$this->dbg( $method.'<br /><pre>'.var_export($params, true).'</pre>' );

		$host = $this->UserSettings->get('host');
		$request = xmlrpc_encode_request( $method, $params, array('escaping'=>'markup','encoding'=>'utf-8') );

		// $this->dbg( 'Request<pre>'.var_export( htmlentities($request), true).'</pre>' );

		$c = curl_init($host);

		curl_setopt( $c, CURLOPT_URL, $host );
		curl_setopt( $c, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt( $c, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt( $c, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt( $c, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $c, CURLOPT_POSTFIELDS, $request );

		if( ! $response = curl_exec($c) )
		{
			$this->msg( curl_error($c) );
		}
		curl_close($c);

		$this->dbg('<pre>'.htmlentities( $response ).'</pre>');

		$response = xmlrpc_decode($response, 'utf-8');
		if( is_array($response) && xmlrpc_is_fault($response) )
		{	// Error
			if( strstr($response['faultString'], 'XML-RPC services are disabled on this site') )
			{	// Disable sync for current request
				$this->_temp_disabled = true;
			}
			$this->msg( 'WPC Error: '.$response['faultCode'].' - '.$response['faultString'] );
			return false;
		}
		else
		{	// OK
			$this->dbg( $response );
			return $response;
		}
	}


	function dbg( $msg )
	{
		if( $this->_debug ) $this->msg( '<b>WPC-DEBUG:</b> '.$msg, 'note');
	}


	function is_url( $url )
	{
		return @preg_match('~^https?://.{4}~', $url);
	}


	function init_m_params()
	{
		if( is_null($this->_initial_method_params) )
		{
			$this->_initial_method_params = array( 1, $this->UserSettings->get('user'), $this->UserSettings->get('pass') );
		}
		return $this->_initial_method_params;
	}


	function wp_or_b2evo_comment_status( $raw_status, $convert_to = 'b2evo' )
	{
		$status = '';

		if( $convert_to == 'b2evo' )
		{
			switch( $raw_status )
			{	// Map WP statuses to b2evo

				// Keep native b2evo statuses
				case 'published':
				case 'deprecated':
				case 'draft':
				case 'trash':
					$status = $raw_status;
					break;

				case 'hold':
					$status = 'draft';
					break;

				case 'spam':
					$status = 'deprecated';
					break;

				case 'approve':
					$status = 'published';
					break;

				default:
					$status = NULL;
			}
		}
		elseif( $convert_to == 'wp' )
		{
			switch( $raw_status )
			{	// Map b2evo statuses to WP
				case 'deprecated':
					$status = 'spam';
					break;

				case 'draft':
					$status = 'hold';
					break;

				case 'trash':
					$status = 'trash';
					break;

				default:
					$status = 'approve';
					break;
			}
		}

		return $status;
	}


	function wp_or_b2evo_item_status( $raw_status, $convert_to = 'b2evo' )
	{
		$status = '';

		if( $convert_to == 'b2evo' )
		{
			switch( $raw_status )
			{	// Map WP statuses to b2evo
				// Note: we drop 'inherit' status because b2evo doesn't support it

				// Keep native b2evo statuses
				case 'published':
				case 'deprecated':
				case 'protected':
				case 'private':
				case 'draft':
				case 'redirected':
					$status = $raw_status;
					break;

				case 'auto-draft':
				case 'pending':
					$status = 'draft';
					break;

				case 'publish':
				case 'future':
					$status = 'published';
					break;

				case 'trash':
					$status = 'deprecated';
					break;
			}
		}
		elseif( $convert_to == 'wp' )
		{
			switch( $raw_status )
			{	// Map b2evo statuses to WP
				case 'private':
				case 'draft':
					$status = $raw_status;
					break;

				case 'deprecated':
					$status = 'trash';
					break;

				case 'protected':
					$status = 'private';
					break;

				case 'published':
					$status = 'publish';
					break;

				case 'redirected':
					$status = 'published';
					break;

				default:
					$status = 'approve';
					break;
			}
		}

		return $status;
	}
}

?>