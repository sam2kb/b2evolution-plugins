<?php
/**
 *
 * This file implements the LiveJournal Crosspost plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2008-2012 Russian b2evolution - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Russian b2evolution
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class lj_crosspost_plugin extends Plugin
{
	var $name = 'LiveJournal Crosspost';
	/**
	 * Code, if this is a renderer or pingback plugin.
	 */
	var $code = 'lj_crosspost';
	var $priority = 10;
	var $version = '1.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Russian b2evolution';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=17523';

	var $apply_rendering = 'opt-in';
	var $number_of_installs = 1;

	var $types = '1';
	var $marker = '';
	var $try_again = true;	// Should we restore a post (send it to LJ) if it was manually removed from LJ


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Automatically crosspost a blog entry to your LiveJournal');
		$this->long_desc = $this->T_('Automatically copies all posts to a LiveJournal or other LiveJournal-based blog. Editing or deleting a post will be replicated as well.');
	}


	function GetDbLayout()
	{
		return array(
			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('ids')." (
					lj_ID INT(11) NOT NULL,
					post_ID INT(11) NOT NULL,
					user_ID INT(11) NOT NULL DEFAULT '1',
					PRIMARY KEY (lj_ID),
					INDEX (post_ID),
					INDEX (user_ID)
				)",
		);
	}


	/**
	 * We require b2evo 4.1 or above.
	 */
	function GetDependencies()
	{
		return array(
				'requires' => array(
					'app_min' => '4.1',
				),
			);
	}


	/**
	 * Define settings that the plugin uses/provides.
	 */
	function GetDefaultSettings()
	{
		return array(
				'post_all' => array(
					'label' => $this->T_('Enable "Post all"'),
					'type' => 'checkbox',
					'defaultvalue' => true,
					'note' => $this->T_('Enable "Post all" operation from Tools > LiveJournal. If checked you can send all posts from selected blogs to LiveJournal.'),
				),
				'delete_all' => array(
					'label' => $this->T_('Enable "Delete all"'),
					'type' => 'checkbox',
					'defaultvalue' => false,
					'note' => $this->T_('Enable "Delete all" operation from Tools > LiveJournal. If checked you can delete LiveJournal entries linked with blog posts.'),
				),
				'header' => array(
					'label' => $this->T_('Post header'),
					'type' => 'html_textarea',
					'rows' => 3,
					'cols' => 60,
					'note' => $this->T_('Enter post header text here. This text will be added to the top of each post.').'<br /><br />'.
					sprintf( $this->T_('The following tags will be replaced with the real values: %s'), '$post_title$, $post_url$, $blog_name$, $blog_url$.' ).'<br />'.
					sprintf( $this->T_('Example: You are reading %s, original post blogged on %s.'), htmlspecialchars('"<a href="$post_url$">$post_title$</a>"'), htmlspecialchars('<a href="$blog_url$">$blog_name$</a>') ),
				),
				'footer' => array(
					'label' => $this->T_('Post footer'),
					'type' => 'html_textarea',
					'rows' => 3,
					'cols' => 60,
					'note' => $this->T_('Enter post footer text here. This text will be added to the bottom of each post.').'<br /><br />'.
					sprintf( $this->T_('The following tags will be replaced with the real values: %s'), '$post_title$, $post_url$, $blog_name$, $blog_url$.' ).'<br />'.
					sprintf( $this->T_('Example: You are reading %s, original post blogged on %s.'), htmlspecialchars('"<a href="$post_url$">$post_title$</a>"'), htmlspecialchars('<a href="$blog_url$">$blog_name$</a>') ),
				),
			);
	}


	/**
	 * Define user settings that the plugin uses/provides.
	 */
	function GetDefaultUserSettings()
	{
		return array(
				'host' => array(
					'label' => $this->T_('LJ-compliant host'),
					'defaultvalue' => 'www.livejournal.com',
					'size' => 25,
					'note' => $this->T_('If you are using a LiveJournal-compliant site other than LiveJournal, enter the domain name here. LiveJournal users can use the default value.'),
				),
				'user' => array(
					'label' => $this->T_('LJ Username'),
					'size' => 25,
				),
				'pass' => array(
					'label' => $this->T_('LJ Password'),
					'size' => 25,
					'type' => 'password',
				),
				'community' => array(
					'label' => $this->T_('LJ Community'),
					'size' => 25,
					'note' => $this->T_('If you wish your posts to be copied to a community, enter the community name here. Leaving this space blank will copy the posts to the specified user\'s journal instead.'),
				),
				'security' => array(
					'label' => $this->T_('LJ Privacy level'),
					'type' => 'select',
					'options' => array(
						  'public' => $this->T_('Public'),
						  'private' => $this->T_('Private'),
						  'friends' => $this->T_('Friends'),
						  //'friends_comm' => T_('Friends community'),
						),
					'defaultvalue' => 'public',
					'note' => $this->T_('Default privacy level for LiveJournal posts.'),
				),
				'comments' => array(
					'label' => $this->T_('Comment settings'),
					'type' => 'select',
					'options' => array(
						  '1' => $this->T_('Allowed'),
						  '2' => $this->T_('Not allowed'),
						  '3' => $this->T_('Do not notify'),
						),
					'defaultvalue' => '1',
					'note' => '<br />'.
							  '[ '.$this->T_('Allowed').' ] - '.$this->T_('Check this enable comments.').'<br />'.
							  '[ '.$this->T_('Not allowed').' ] - '.$this->T_('Check this to disable comments.').'<br />'.
							  '[ '.$this->T_('Do not notify').' ] - '.$this->T_('Check this to not receive comments on LiveJournal posts by email.').'<br />',
				),
				'more' => array(
					'label' => $this->T_('Handling of &lt;!--More--&gt;'),
					'type' => 'select',
					'options' => array(
						  'link' => $this->T_('Link back'),
						  'cut' => $this->T_('LJ cut'),
						  'copy' => $this->T_('Copy'),
						),
					'note' => $this->T_('How should we handle &lt;!--More--&gt; tags?').'<br />'.
							  '[ '.$this->T_('Link back').' ] - '.$this->T_('Link back to b2evolution post.').'<br />'.
							  '[ '.$this->T_('LJ cut').' ] - '.$this->T_('Use an lj-cut to split the entry on LiveJournal.').'<br />'.
							  '[ '.$this->T_('Copy').' ] - '.$this->T_('Copy the entire post to LiveJournal.').'<br />',
				),
				'header' => array(
					'label' => $this->T_('Post header'),
					'type' => 'html_textarea',
					'rows' => 3,
					'cols' => 60,
					'defaultvalue' => $this->Settings->get('header'),
					'note' => $this->T_('Enter post header text here. This text will be added to the top of each post.').'<br /><br />'.
					sprintf( $this->T_('The following tags will be replaced with the real values: %s'), '$post_title$, $post_url$, $blog_name$, $blog_url$.' ).'<br />'.
					sprintf( $this->T_('Example: You are reading %s, original post blogged on %s.'), htmlspecialchars('"<a href="$post_url$">$post_title$</a>"'), htmlspecialchars('<a href="$blog_url$">$blog_name$</a>') ),
				),
				'footer' => array(
					'label' => $this->T_('Post footer'),
					'type' => 'html_textarea',
					'rows' => 3,
					'cols' => 60,
					'defaultvalue' => $this->Settings->get('footer'),
					'note' => $this->T_('Enter post footer text here. This text will be added to the bottom of each post.').'<br /><br />'.
					sprintf( $this->T_('The following tags will be replaced with the real values: %s'), '$post_title$, $post_url$, $blog_name$, $blog_url$.' ).'<br />'.
					sprintf( $this->T_('Example: You are reading %s, original post blogged on %s.'), htmlspecialchars('"<a href="$post_url$">$post_title$</a>"'), htmlspecialchars('<a href="$blog_url$">$blog_name$</a>') ),
				),
			);
	}


	function check_perms()
	{
		global $current_User;

		if( !is_logged_in() )
		{	// Not logged in
			$this->msg( $this->T_('You\'re not allowed to view this page!'), 'error' );
			return false;
		}
		if( !$this->UserSettings->get('user') || !$this->UserSettings->get('pass') )
		{	// Plugin not configured
			$this->go_to_profile_msg();
			return false;
		}
		if( !$this->Settings->get('post_all') && !$this->Settings->get('delete_all') )
		{	// Global post/delete operations are disabled
			$this->msg( $this->T_('Global post/delete operations are disabled in plugin settings.'), 'error' );
			return false;
		}
		return true;
	}


	function AdminAfterMenuInit()
	{	// add our tab
		$this->register_menu_entry( 'LiveJournal' );
	}


	function AdminTabAction()
	{
		if( !$this->check_perms() ) return;

		if( param( $this->get_class_id('delete_all') ) )
		{
			$this->delete_all_posts();
		}
		elseif( param( $this->get_class_id('post_all') ) )
		{
			$blog_id = param( $this->get_class_id('blog_id'), 'string' );
			if( param( $this->get_class_id('type'), 'string' ) )
			{
				$this->types = NULL;
			}

			$this->add_all_posts($blog_id);
		}
	}


	function AdminTabPayload()
	{
		global $current_User;

		if( !$this->check_perms() ) return;

		if( $this->Settings->get('post_all') )
		{
			// Post all
			$Form = new Form( 'admin.php', '', 'post' );
			$Form->begin_form( 'fform', $this->T_('LiveJournal Crosspost') );
			$Form->begin_fieldset( sprintf( $this->T_('Post all items created by or assigned to %s'), '"'.$current_User->get('preferredname').'"') );
			$Form->hidden_ctrl(); // needed to pass the "ctrl=tools" param
			$Form->hiddens_by_key( get_memorized() ); // needed to pass all other memorized params, especially "tab"
			$Form->hidden( $this->get_class_id('post_all'), 1 );

			$Form->checkbox( $this->get_class_id('type'), false, $this->T_('All post types'), $this->T_('Check to send entries of all types (posts, pages, links, podcasts etc). Leave unchecked to send posts only.') );
			$Form->text_input( $this->get_class_id('blog_id'), '', 5, $this->T_('Blog ID'), $this->T_('Send all your posts from selected blog to LiveJournal.<br />Enter "All" without quotes to send your posts from all public blogs to LiveJournal (<span style="color:red">not recommended if you have a lot of posts</span>).<br /><br />All existing posts will be updated.') );

			$Form->end_fieldset();
			$Form->end_form( array( array( 'value' => $this->T_('Post all !'), 'onclick' => 'return confirm(\''.$this->T_('You are about to send all posts to LiveJournal!\nDo you want to continue?').'\')' ) ) );
		}

		if( $this->Settings->get('delete_all') )
		{
			// Delete all
			$Form = new Form( 'admin.php', '', 'post' );
			$Form->begin_form( 'fform' );
			$Form->begin_fieldset( sprintf( $this->T_('Delete all'), '"'.$current_User->get('preferredname').'"') );
			$Form->hidden_ctrl(); // needed to pass the "ctrl=tools" param
			$Form->hiddens_by_key( get_memorized() ); // needed to pass all other memorized params, especially "tab"
			$Form->hidden( $this->get_class_id('delete_all'), 1 );

			echo '<fieldset><div class="label"><label></label></div>';
			echo '<div class="input"><span class="notes">'.$this->T_('Click to delete all LiveJournal entries linked with your posts.').' <span style="color:red">'.T_('This cannot be undone!').'</span></span></div></fieldset>';

			$Form->end_fieldset();
			$Form->end_form( array( array( 'value' => $this->T_('Delete all posts !'), 'onclick' => 'return confirm(\''.$this->T_('You are about to delete all entries from LiveJournal!\nThis cannot be undone!').'\')' ) ) );
		}
	}


	function RenderItemAsHtml( & $params )
	{
		// This is not actually a renderer,
		// we're just using the checkbox
		// to see if we should process the given post.
	}


	function AfterItemInsert( & $params )
	{
		global $Plugins;
		// Check to see if we want to crosspost
		$renders = $Plugins->validate_list( $params['Item']->get_renderers() );
		// If the renderer checkbox is unchecked, then do nothing
		if( !in_array($this->code, $renders) ) return;

		$options = array(
				//'nocomments'	=> 0,
				//'noemail'		=> 0,
				//'security'	=> 'public',
			);

		$this->update_item( $params['Item'], $options );
	}


	function AfterItemUpdate( & $params )
	{
		return $this->AfterItemInsert( $params );
	}


	function AfterItemDelete( & $params )
	{
		$this->delete_post( $params['Item']->ID );
	}


	// Get lj_ID assigned to the Item
	function get_ljid( $post_ID, $user_ID = '#' )
	{
		global $DB, $current_User;

		if( $user_ID == '#' ) $user_ID = $current_User->ID;

		$SQL = 'SELECT lj_ID
				  FROM '.$this->get_sql_table('ids').'
				  WHERE post_ID = '.$post_ID.'
				  AND user_ID = '.$user_ID;

		if( is_numeric($post_ID) && is_numeric($user_ID) && $lj_ID = $DB->get_var($SQL) ) return $lj_ID;

		return false;
	}


	// Assign lj_ID to the Item
	function set_ljid( $lj_ID, $post_ID, $user_ID = '#' )
	{
		global $DB, $current_User;

		if( $user_ID == '#' ) $user_ID = $current_User->ID;
		if( !is_numeric($lj_ID) || !is_numeric($post_ID) || !is_numeric($user_ID) ) return false;

		$SQL = 'INSERT INTO '.$this->get_sql_table('ids').'( lj_ID, post_ID, user_ID )
				  VALUES( '.$lj_ID.', '.$post_ID.', '.$user_ID.' )';

		return $DB->query($SQL);
	}


	// Delete lj_ID assigned to the Item
	function delete_ljid( $post_ID = '#', $lj_ID = '#', $user_ID = '#' )
	{
		global $DB, $current_User;

		if( $user_ID == '#' ) $user_ID = $current_User->ID;
		if( (!is_numeric($post_ID) && !is_numeric($lj_ID)) || !is_numeric($user_ID) ) return false;

		$SQL = 'DELETE FROM '.$this->get_sql_table('ids').'
				  WHERE ( lj_ID = '.$DB->quote($lj_ID).'
				  OR post_ID = '.$DB->quote($post_ID).' )
				  AND user_ID = '.$user_ID;

		return $DB->query($SQL);
	}


	// Check if this lj_ID already exists in db
	function ljid_exists( $lj_ID, $user_ID = '#' )
	{
		global $DB, $current_User;

		if( $user_ID == '#' ) $user_ID = $current_User->ID;
		if( !is_numeric($lj_ID) || !is_numeric($user_ID) ) return false;

		$SQL = 'SELECT lj_ID
				  FROM '.$this->get_sql_table('ids').'
				  WHERE lj_ID = '.$lj_ID.'
				  AND user_ID = '.$user_ID;

		if( $DB->get_var($SQL) ) return true;
		return false;
	}


	function check_more( $content )
	{
		$content_parts = explode( '<!--more-->', $content );
		if( count($content_parts) < 2 )
		{ // This is NOT an extended post:
			return false;
		}
		return true;
	}


	function replace_tags( $content, $Item )
	{
		if( !is_object($Item) ) return NULL;

		$Item->load_Blog();

		$search = array(
				'$post_title$',
				'$post_url$',
				'$blog_name$',
				'$blog_url$',
			);

		$replace = array(
				$Item->title,
				$Item->get_permanent_url(),
				$Item->Blog->name,
				$Item->Blog->gen_blogurl(),
			);

		return str_replace($search, $replace, $content);
	}


	function go_to_profile_msg()
	{
		$url = 'href="'.$admin_url.'?ctrl=user&amp;user_tab=advanced&amp;user_ID='.$current_User->ID.'#ffield_edit_plugin_'.$this->ID.'_set_host"';
		$this->msg( sprintf( $this->T_('You must set LiveJournal <a %s>credentials</a> first.'), $url ), 'note' );
	}


	// Get array of all post_IDs
	function get_post_ids( $user_ID = '#' )
	{
		global $DB, $current_User;

		if( $user_ID == '#' ) $user_ID = $current_User->ID;

		$query = 'SELECT post_ID
				  FROM '.$this->get_sql_table('ids').'
				  WHERE user_ID = '.$user_ID;

		if( is_numeric($user_ID) && $post_ids = $DB->get_col($query) ) return $post_ids;

		return false;
	}


	function add_all_posts( $Blog = NULL )
	{
		global $current_User;

		$BlogCache = & get_BlogCache();

		if( is_string($Blog) && @preg_match( '/all/i', $Blog ) )
		{	// Use all public blogs
			$blog_array = $BlogCache->load_public();

			if( function_exists( 'set_time_limit' ) )
			{
				set_time_limit( 900 ); // 15 minutes ought to be enough for everybody *g
			}
			@ini_set( 'max_execution_time', '900' );
			$this->time_limit_set = true;

			foreach( $blog_array as $blog )
			{
				$l_Blog = & $BlogCache->get_by_ID( $blog, false, false );
				$this->add_all_posts( $l_Blog );
			}
		}
		elseif( !is_object($Blog) )
		{
			if( ! ($Blog = & $BlogCache->get_by_ID( $Blog, false, false ) ) )
			{
				$this->msg( $this->T_('The requested blog does not exist (any more?)'), 'error' );
				return;
			}
		}

		if( !is_object($Blog) ) return;

		if( ! $this->time_limit_set )
		{	// Don't do it for each blog in "All" mode
			if( function_exists( 'set_time_limit' ) )
			{
				set_time_limit( 900 ); // 15 minutes ought to be enough for everybody *g
			}
			@ini_set( 'max_execution_time', '900' );
		}

		load_class( 'items/model/_item.class.php', 'Item' );
		load_class( 'items/model/_itemlist.class.php', 'ItemList');

		// Get Items
		$ItemList = new ItemList2( $Blog, NULL, 'now', 1000 );
		$ItemList->set_filters( array(
				'order'		=>	'ASC',
				'unit'		=>	'all',
				'types'		=>	$this->types,
				'visibility_array'	=>	array('published'),
				'author_assignee'	=>	$current_User->ID,
			) );
		// Run the query:
		$ItemList->query();

		//var_export($ItemList->ItemQuery);
		//die();

		if( $ItemList->result_num_rows == 0 )
		{
			$this->msg( sprintf( $this->T_('Nothing to sent from blog %s'), '#'.$Blog->ID ), 'note' );
			return;
		}

		while( $Item = & $ItemList->get_item() )
		{
			$options = array(
					//'backdated'	=> true,
				);
			$this->update_item( $Item, $options );
			$this->marker = '';
		}
	}


	function delete_all_posts()
	{
		if( $IDs = $this->get_post_ids() )
		{
			if( function_exists( 'set_time_limit' ) )
			{
				set_time_limit( 900 ); // 15 minutes ought to be enough for everybody *g
			}
			@ini_set( 'max_execution_time', '900' );

			foreach( $IDs as $post_ID )
			{
				$this->delete_post( $post_ID );
			}
		}
		else
		{
			$this->msg( $this->T_('You don\'t have any posts linked with your LiveJournal account.'), 'note' );
		}
	}


	// Delete post from LJ linked to specified b2evo post_ID
	function delete_post( $post_ID = '#' )
	{
		if( $this->get_ljid( $post_ID ) )
		{	// Delete linked LJ post
			$this->update_item( $post_ID, array( 'delete' => true ) );
			$this->marker = '';
		}
		//$this->delete_ljid( $post_ID );  // Just in case ;)
	}


	// Add/Update/Delete the post in LJ depending on 'post_ID' param.
	function update_item( $Item, $params = array() )
	{
		global $admin_url;

		if( $this->marker == 1 || (!isset($params['delete']) && !is_object($Item)) )
		{	// Bad request
			return false;
		}
		if( !$this->UserSettings->get('user') || !$this->UserSettings->get('pass') )
		{	// Plugin not configured
			$this->go_to_profile_msg();
			return;
		}

		if( !isset($params['delete']) )
		{	// We're not deleting, let's get post content
			if( $Item->status != 'published' && $this->get_ljid( $Item->ID ) )
			{	// Post status changed, let's delete it from LJ
				$this->delete_post($Item->ID);
				return false;
			}
			elseif( $Item->status != 'published' )
			{	// Don't add non-puplic posts
				return false;
			}

			load_class( 'items/model/_item.class.php', 'Item' );
			load_class( 'items/model/_itemlist.class.php', 'ItemList');

			if( $tag_arr = $Item->get_tags() )
			{
				$params['taglist'] = implode( ',', $tag_arr );					// Get post tags
			}
			if( $Item->comment_status != 'open' ) $params['nocomments'] = true;	// Get comments status
			$params['post_ID'] = $Item->ID;										// Get post ID
			$params['subject'] = $Item->title;									// Get post title
			$params['date'] = mysql2timestamp( $Item->issue_date );				// Get post date
			$params['more'] = $this->UserSettings->get('more');

			$read_more = $this->T_('Read the rest of this entry').' &raquo';

			// Get post content
			$content = '';
			if( $this->UserSettings->get('header') )
			{	// Add custom header
				$content = $content.'<p>'.$this->replace_tags( $this->UserSettings->get('header'), $Item ).'</p>';
			}
			if( !empty( $Item->url ) )
			{	// Link
				$content .= make_clickable('<p>'.$Item->url.'</p>');
			}
			$content .= $Item->get_images();				// Linked images
			$r = $Item->get_content_teaser( 1, false );		// Post teaser

			switch( $params['more'] )
			{
				case 'copy':
					$r .= $Item->get_content_extension( 1, true );
					break;

				case 'cut' && $this->check_more( $Item->content ):
					$r .= '<lj-cut text="'.$read_more.'">';
					$r .= $Item->get_content_extension( 1, true );
					$r .= '</lj-cut>';
					break;

				case 'link' && $this->check_more( $Item->content ):
					// Add a link back to original post
					$r .= '<p><a href="'.$Item->get_permanent_url().'">'.$read_more.'</a></p>';
					break;
			}

			if( isset($Item->content_pages['htmlbody']) && count($Item->content_pages['htmlbody']) > 1 )
			{	// We have more than 1 page
				// Pages got generated by the code above
				switch( $params['more'] )
				{
					case 'copy':
						// Let's merge pages
						$content .= implode( '', $Item->content_pages['htmlbody'] );
						break;

					case 'cut':
						$content .= $Item->content_pages['htmlbody'][0];
						unset($Item->content_pages['htmlbody'][0]);
						$content .= '<lj-cut text="'.$read_more.'">';
						$content .= implode( '', $Item->content_pages['htmlbody'] );
						$content .= '</lj-cut>';
						break;

					case 'link':
						// Get first page only
						$content .= $Item->content_pages['htmlbody'][0];
						$content .= '<p><a href="'.$Item->get_permanent_url().'">'.$read_more.'</a></p>';
						break;
				}
			}
			else
			{	// Single page post
				$content = $content.$r;
			}
			if( $this->UserSettings->get('footer') )
			{	// Add custom footer
				$content = $content.'<p>'.$this->replace_tags( $this->UserSettings->get('footer'), $Item ).'</p>';
			}
			// Prevent unwanted post deleting
			( empty($content) ) ? $params['event'] = '1' : $params['event'] = $content;
		}
		else
		{	// Delete
			$params['post_ID'] = $Item;
		}

		// LJ Params
		$params = array_merge( array(
				'host'			=> $this->UserSettings->get('host'),
				'user'			=> $this->UserSettings->get('user'),
				'pass'			=> md5($this->UserSettings->get('pass')),
				'security'		=> $this->UserSettings->get('security'),
				'nocomments'	=> ($this->UserSettings->get('comments') == '2'),
				'noemail'		=> ($this->UserSettings->get('comments') == '3'),
				'community'		=> $this->UserSettings->get('community'),
				'delete'		=> false,		// 'true' to delete a post from LJ
				'post_ID'		=> '',
				'event'			=> '1',			// Prerendered post content
				'subject'		=> '1',			// Post subject
				'date'			=> time(),		// Post date (NOW by default)
				'taglist'		=> '',			// List, of, tags
				'lineendings'	=> 'unix',		// pc, mac, unix
				'preformatted'	=> true,		// We always send preformatted text
				'revtime'		=> time(),		// Post last edit date (NOW by default)
				'backdated'		=> false,		// Set to true if this item shouldn't show up
												// on people's friends lists (because it occurred in the past)
			), $params );

		if( $lj_ID = $this->send_request( $params ) )
		{
			if( is_numeric($lj_ID) ) return $lj_ID;	// Good!!!

			// Let's try again
			$this->try_again = false;
			switch( $lj_ID )
			{
				case 'Client error':
					$this->delete_ljid( $params['post_ID'] );
					$params['method'] = 'LJ.XMLRPC.postevent';
					break;

				case 'Incorrect time':
					$params['date'] = time();
					break;
			}
			return $this->send_request( $params );
		}
		return false;
	}


	// Send request to add/update/delete the post to LJ
	function send_request( $params )
	{
		global $Messages, $debug, $app_name, $app_version, $evo_charset;

		$this->marker = 1;

		$params['lj_ID'] = $this->get_ljid($params['post_ID']);
		$params['community'] = (!empty($params['community']) ? $params['community'] : $params['user']);
		$params['useragent'] = $app_name.'-'.$app_version;  // e.g. b2evolution-4.1.3

		if( $params['delete'] === true )
		{	// We want to delete the post from LJ
			if( !empty($params['lj_ID'] ) )
			{	// Let's empty the 'event'
				$params['event'] = NULL;
			}
			else
			{	// No linked LJ post found
				return true;
			}
		}

		// Construct XML-RPC client:
		load_funcs('xmlrpc/model/_xmlrpc.funcs.php');

		// Fix encoding
		$GLOBALS['xmlrpc_internalencoding'] = 'UTF-8';

		$client = new xmlrpc_client( '/interface/xmlrpc', $params['host'] );
		$client->request_charset_encoding = 'UTF-8';	// Fix encoding 2
		$client->debug = $debug;

		$msg = new xmlrpcmsg('LJ.XMLRPC.getchallenge');
		$result = $client->send($msg);

		if( $ret = xmlrpc_logresult( $result, $Messages, false ) )
		{	// Response is not an error, let's process it:
			$response = $result->value();
			if( $response->kindOf() == 'struct' )
			{ // Decode struct:
				$response = xmlrpc_decode_recurse($response);
				if( !isset( $response['challenge'] ) )
				{
					$this->msg( T_('Incomplete reponse.'), 'error' );
				}
				else
				{
					// Convert encoding to UTF-8
					$params['subject'] = convert_charset( $params['subject'], 'UTF-8', $evo_charset );
					$params['event'] = convert_charset( $params['event'], 'UTF-8', $evo_charset );
					$params['taglist'] = convert_charset( $params['taglist'], 'UTF-8', $evo_charset );

					// Are we creating or updating a post?
					$method = (empty($params['lj_ID'])) ? 'LJ.XMLRPC.postevent' : 'LJ.XMLRPC.editevent';
					// Allow overriding
					if( !empty($params['method']) ) $method = $params['method'];

					$post = array(
						'username'		=> new xmlrpcval($params['user']),
						'auth_method'	=> new xmlrpcval('challenge'),
						'auth_challenge'=> new xmlrpcval($response['challenge']),
						'auth_response'	=> new xmlrpcval(md5($response['challenge'].$params['pass'])),
						'ver'			=> new xmlrpcval('1'),
						'lineendings'	=> new xmlrpcval($params['lineendings']),
						'itemid'		=> new xmlrpcval($params['lj_ID']),
						'event'			=> new xmlrpcval($params['event']),
						'subject'		=> new xmlrpcval($params['subject']),
						'year'			=> new xmlrpcval(date('Y', $params['date'])),
						'mon'			=> new xmlrpcval(date('n', $params['date'])),
						'day'			=> new xmlrpcval(date('j', $params['date'])),
						'hour'			=> new xmlrpcval(date('G', $params['date'])),
						'min'			=> new xmlrpcval(date('i', $params['date'])),
						'usejournal'	=> new xmlrpcval($params['community']),
						'security'		=> new xmlrpcval($params['security']),
						'props'			=> new xmlrpcval(
											array('opt_nocomments'	=> new xmlrpcval($params['nocomments']),
												  'opt_noemail'		=> new xmlrpcval($params['noemail']),
												  'opt_preformatted'=> new xmlrpcval($params['preformatted']),
												  'opt_backdated'	=> new xmlrpcval($params['backdated']),
												  'taglist'			=> new xmlrpcval($params['taglist']),
												  'useragent'		=> new xmlrpcval($params['useragent']),
												  'revtime'			=> new xmlrpcval($params['revtime']),
												), 'struct' )
						);

					$message = new xmlrpcmsg( $method, array( new xmlrpcval($post, 'struct') ) );
					// Display generated message
					//echo nl2br(htmlentities($message->serialize()));
					//die();

					$result = $client->send($message);

					if( $ret = xmlrpc_logresult( $result, $Messages, false ) )
					{ // Response is not an error, let's process it:
						$response = $result->value();
						if( $response->kindOf() == 'struct' )
						{ // Decode struct:
							$response = xmlrpc_decode_recurse($response);

							if( $method == 'LJ.XMLRPC.postevent' )
							{	// Add
								if( $this->ljid_exists($response['itemid']) )
								{	// A post with the same content already exists in LiveJournal
									$this->msg( $this->T_('Unable to save LJ post ID in database. A post with the same content already exists in LiveJournal.'), 'error' );
								}
								elseif( $this->set_ljid( $response['itemid'], $params['post_ID'] ) === false )
								{
									$this->msg( $this->T_('Unable to save LJ post ID in database.'), 'error' );
								}
								$this->msg( sprintf( $this->T_('The post #%s has been added to LiveJournal.'), '<a href="'.$response['url'].'" target="_blank">'.$response['itemid'].'</a>' ), 'success' );
							}
							elseif( $method == 'LJ.XMLRPC.editevent' && !empty($params['event']) )
							{	// Update
								$this->msg( sprintf( $this->T_('The post #%s has been updated on LiveJournal.'), '<a href="'.$response['url'].'" target="_blank">'.$response['itemid'].'</a>' ), 'success' );
							}
							elseif( $method == 'LJ.XMLRPC.editevent' && empty($params['event']) )
							{	// Delete
								$this->msg( sprintf( $this->T_('The post #%d has been deleted from LiveJournal.'), $response['itemid'] ), 'success' );
								if( !$this->delete_ljid( '#', $response['itemid'] ) )
								{
									$this->msg( $this->T_('Unable to delete LJ post ID from database.'), 'error' );
								}
							}
							return $response['itemid'];
						}
						else
						{
							$this->msg( $this->T_('Invalid response.'), 'error' );
						}
					}
					elseif( $this->try_again && $Messages->has_errors() )
					{	// There was an error, see if we should try again
						$search = ": Client error: Can't edit post from requested journal (302)";
						$search2 = ": Incorrect time value: You have an entry which was posted at";

						for( $i = 0; $i < $Messages->count; $i++ )
						{
							if( $Messages->messages_type[$i] == 'error' )
							{
								// The post was manualy deleted from LJ
								if( preg_match( '~'.quotemeta($search).'~', $Messages->messages_text[$i] ) ) $second_try = 'Client error';
								// Bad date, set it to NOW and try again
								if( preg_match( '~'.quotemeta($search2).'~', $Messages->messages_text[$i] ) ) $second_try = 'Incorrect time';

								if( !empty($second_try) && $Messages->count == 1 )
								{	// Let's clear the error and try to create the post again
									// Only if there was one message!
									$Messages->clear();
									return $second_try;
								}
							}
						}
					}
				}
			}
			else
			{
				$this->msg( $this->T_('Invalid response.'), 'error' );
			}
		}
		return false;
	}
}

?>