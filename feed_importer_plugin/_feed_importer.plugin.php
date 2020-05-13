<?php
/**
 *
 * This file implements the Feed Importer Pro plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2009 - 2011 by Sonorth Corp. - {@link http://www.sonorth.com/}.
 * @author Sonorth Corp.
 *
 * All rights reserved.
 *
 * THIS COMPUTER PROGRAM IS PROTECTED BY COPYRIGHT LAW AND INTERNATIONAL TREATIES.
 * UNAUTHORIZED REPRODUCTION OR DISTRIBUTION OF FEED IMPORTER PRO PLUGIN,
 * OR ANY PORTION OF IT THAT IS OWNED BY SONORTH CORP., MAY RESULT IN SEVERE CIVIL
 * AND CRIMINAL PENALTIES, AND WILL BE PROSECUTED TO THE MAXIMUM EXTENT POSSIBLE UNDER THE LAW.
 *
 * THE FEED IMPORTER PRO PLUGIN FOR B2EVOLUTION CONTAINED HEREIN IS PROVIDED "AS IS."
 * SONORTH CORP. MAKES NO WARRANTIES OF ANY KIND WHATSOEVER WITH RESPECT TO THE
 * FEED IMPORTER PRO PLUGIN FOR B2EVOLUTION. ALL EXPRESS OR IMPLIED CONDITIONS,
 * REPRESENTATIONS AND WARRANTIES, INCLUDING ANY WARRANTY OF NON-INFRINGEMENT OR
 * IMPLIED WARRANTY OF MERCHANTABILITY OR FITNESS FOR A PARTICULAR PURPOSE,
 * ARE HEREBY DISCLAIMED AND EXCLUDED TO THE EXTENT ALLOWED BY APPLICABLE LAW.
 *
 * IN NO EVENT WILL SONORTH CORP. BE LIABLE FOR ANY LOST REVENUE, PROFIT OR DATA,
 * OR FOR DIRECT, SPECIAL, INDIRECT, CONSEQUENTIAL, INCIDENTAL, OR PUNITIVE DAMAGES
 * HOWEVER CAUSED AND REGARDLESS OF THE THEORY OF LIABILITY ARISING OUT OF THE USE OF
 * OR INABILITY TO USE THE FEED IMPORTER PRO PLUGIN FOR B2EVOLUTION, EVEN IF SONORTH CORP.
 * HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

class feed_importer_plugin extends Plugin
{
	var $name = 'Feed Importer Pro';
	var $code = 'feed_importer_pro';
	var $priority = 10;
	var $version = '2.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://www.sonorth.com';
	var $help_url = 'http://b2evo.sonorth.com/show.php/feed-importer-pro-plugin?page=2';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;

	var $simplepie = '_simplepie.class.inc';	// SimplePie filename
	var $parsecsv = '_parsecsv.class.inc';		// ParseCSV filename
	var $delimeter = ',';						// Delimiter used in CSV (export only)

	// Internal
	var $limit = '';
	var $path = '';
	var $hide_tab = false;
	var $_imported = array();

	var $copyright = 'THIS COMPUTER PROGRAM IS PROTECTED BY COPYRIGHT LAW AND INTERNATIONAL TREATIES. UNAUTHORIZED REPRODUCTION OR DISTRIBUTION OF %N PLUGIN, OR ANY PORTION OF IT THAT IS OWNED BY %C, MAY RESULT IN SEVERE CIVIL AND CRIMINAL PENALTIES, AND WILL BE PROSECUTED TO THE MAXIMUM EXTENT POSSIBLE UNDER THE LAW.<br /><br />THE %N PLUGIN FOR B2EVOLUTION CONTAINED HEREIN IS PROVIDED "AS IS." %C MAKES NO WARRANTIES OF ANY KIND WHATSOEVER WITH RESPECT TO THE %N PLUGIN FOR B2EVOLUTION. ALL EXPRESS OR IMPLIED CONDITIONS, REPRESENTATIONS AND WARRANTIES, INCLUDING ANY WARRANTY OF NON-INFRINGEMENT OR IMPLIED WARRANTY OF MERCHANTABILITY OR FITNESS FOR A PARTICULAR PURPOSE, ARE HEREBY DISCLAIMED AND EXCLUDED TO THE EXTENT ALLOWED BY APPLICABLE LAW.<br /><br />IN NO EVENT WILL %C BE LIABLE FOR ANY LOST REVENUE, PROFIT OR DATA, OR FOR DIRECT, SPECIAL, INDIRECT, CONSEQUENTIAL, INCIDENTAL, OR PUNITIVE DAMAGES HOWEVER CAUSED AND REGARDLESS OF THE THEORY OF LIABILITY ARISING OUT OF THE USE OF OR INABILITY TO USE THE %N PLUGIN FOR B2EVOLUTION, EVEN IF %C HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.';

	var $unreg_msg = 'You are using the unregistered copy of <b>%N</b> plugin.<br />Please <a href="%L" target="_blank">purchase the license</a> or contact us at <em>sales@sonorth.com</em> if you believe you\'re seeing this message in error.<br /><br />Enter your license key on <a href="%PS">plugin settings</a> page.';


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Creates posts from different sources.');
		$this->long_desc = $this->T_('This plugin allows you to import posts from different sources such as XML feeds, b2evo backup files (*.b2e), and plain text files. It can export posts as b2evo backup files (*.b2e) and in CSV, XML and JSON formats.').'<br /><br /><br />'.str_replace( array('%N', '%C'), array( '<b>'.strtoupper($this->name).'</b>', strtoupper($this->author) ), $this->copyright );

		$this->path = dirname(__FILE__).'/export/';
	}


	function GetDbLayout()
	{
		return array(
			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('feeds')." (
					feed_ID int(11) NOT NULL auto_increment,
					feed_user_ID int(11) NOT NULL,
					feed_url varchar(255) NOT NULL,
					feed_title varchar(255) NOT NULL,
					feed_status int(1) NOT NULL default 1,
					feed_date datetime NOT NULL default '2000-01-01 00:00:00',
					PRIMARY KEY (feed_ID),
					INDEX (feed_user_ID, feed_date)
				)",
		);
	}


	function BeforeInstall()
	{
		global $AdminUI, $Settings, $Session, $app_version;

		$terms_accepted = param($this->code.'_terms_accepted', 'string');
		$lic_key = param($this->code.'_lic_key', 'string');

		if( $terms_accepted )
		{
			if( function_exists('url_crumb') ) $Session->assert_received_crumb('plugin');

			if( preg_match( '~^[a-z0-9]{32}$~i', $lic_key ) )
			{
				global $Settings;

				$Settings->set('sonorth_lic_key', $lic_key);
				$Settings->set('sonorth_unreg', false );
				$Settings->dbupdate();

				$this->check_updates( true, $lic_key );

				return true;
			}
			else
			{
				$this->msg( $this->T_('You entered invalid license key'), 'error' );
			}
		}

		$AdminUI->disp_html_head();
		$AdminUI->disp_body_top();
		$AdminUI->disp_payload_begin();

		$form_url = '?ctrl=plugins&action=install&plugin='.$this->classname;
		if( function_exists('url_crumb') )
		{	// b2evo 4 and up
			$form_url .= '&'.url_crumb('plugin');
		}

		echo '<div class="panelinfo" style="width:460px; margin:0 auto; padding-top:7px">';
		$Form = new Form( $form_url );
		$Form->begin_form( 'fform' );
		if( function_exists('url_crumb') ) $Form->add_crumb('plugin');

		$Form->text_input( $this->code.'_lic_key', '', 44, $this->T_('License key') );
		echo '<fieldset style="margin-top:7px">';
		echo sprintf( 'Please read the following terms carefully before installing<br />the <b>%s</b> plugin.', $this->name );
		echo '<div style="margin:25px 0 15px 0; padding-right:15px; height: 200px; overflow-y:scroll">';
		echo str_replace( array('%N', '%C'), array( '<b>'.strtoupper($this->name).'</b>', strtoupper($this->author) ), $this->copyright );
		echo '</div></fieldset>';
		echo '<p class="center"><input type="submit" name="'.$this->code.'_terms_accepted'.'" value="'.$this->T_('Install plugin').'" /></p>';

		$Form->end_form();
		echo '</div>';

		$AdminUI->disp_payload_end();
		$AdminUI->disp_global_footer();
		die();
	}


	function BeforeUninstall()
	{
		if( param( $this->code.'_uninstall', 'boolean' ) )
		{
			global $DB;

			$DB->query('DELETE FROM T_settings
						WHERE set_name REGEXP "^sonorth_"
						AND set_name != "sonorth_last_checked"');

			return true;
		}
		return NULL;
	}


	function BeforeUninstallPayload( & $params )
	{
		global $Settings;

		$params['Form']->hidden( $this->code.'_uninstall', true );
		echo '<div class="success" style="padding:15px; color:red"><b>Please save your license key:</b> '.
				$Settings->get('sonorth_lic_key').'</div>';

		return true;
	}


	function GetDefaultSettings()
	{
		global $Settings;

		$lic_key = $Settings->get('sonorth_lic_key');

		return array(
				'lic_key' => array(
					'label' => $this->T_('License key'),
					'defaultvalue' => $lic_key,
					'type' => 'text',
					'size' => 45,
					'note' => $this->T_('Enter your license key here'),
				),
			);
	}


	/**
	 * Define settings that the plugin uses/provides.
	 */
	function GetDefaultUserSettings()
	{
		return array(
				'recent_feeds' => array(
					'label' => $this->T_('Recent feeds list'),
					'defaultvalue' => 1,
					'type' => 'checkbox',
					'note' => $this->T_('Check this if you want to display a list or recently used XML feeds.'),
				),
				'post_notification' => array(
					'label' => $this->T_('Asynchronous outbound pings'),
					'defaultvalue' => 0,
					'type' => 'checkbox',
					'note' => $this->T_('Check this if you want to enable scheduled outbound pings.').'<br />'.
							$this->T_('NOTE: make sure that your scheduled jobs are properly set up.'),
				),
				'scheduled_import' => array(
					'label' => $this->T_('Scheduled import'),
					'defaultvalue' => 0,
					'type' => 'checkbox',
					'onclick'=>'jQuery("#scheduled_imort_container").show();',
					'note' => $this->T_('Check this if you want to enable scheduled XML feed import.'),
				),
			);
	}


	function PluginUserSettingsEditDisplayAfter( & $params )
	{
		$Form = & $params['Form'];

		echo '<div id="scheduled_imort_container">';

		$status_opts = array(
				'published' => $this->T_('Published (Public)'),
				'draft' => $this->T_('Draft (Not published!)'),
			);

		$opt = array();
		foreach( $status_opts as $n_opt => $v_opt )
		{
			$sel = '';
			if( $this->UserSettings->get('post_status') == $n_opt ) $sel = ' selected="selected"';
			$opt[] = '<option value="'.$n_opt.'"'.$sel.'>'.$v_opt.'</option>';
		}
		$status_options = implode( "\n", $opt );

		$Form->select_input_options( 'edit_plugin_'.$this->ID.'_set_post_status', $status_options, $this->T_('Posts status'), $this->T_('Select the status for imported posts.') );
		$Form->text_input( 'edit_plugin_'.$this->ID.'_set_limit', $this->UserSettings->get('limit'), 4, $this->T_('Limit'), $this->T_('Enter the number of items to import. Leave empty to import all available items.'), array( 'maxlength'=>5 ) );

		$Form->textarea( 'edit_plugin_'.$this->ID.'_set_header', $this->UserSettings->get('header'), 2, $this->T_('Post header'), $this->T_('Enter post header text here. This text will be added to the top of each post.'), 60 );
		$Form->textarea( 'edit_plugin_'.$this->ID.'_set_footer', $this->UserSettings->get('footer'), 2, $this->T_('Post footer'), $this->T_('Enter post footer text here. This text will be added to the bottom of each post.'), 60 );
		echo '<fieldset><div class="label"><label></label></div>';
		echo '<div class="input notes">'.sprintf( $this->T_('The following tags will be replaced with the real values when <b>XML Feed</b> is imported: %s'), '$post_title$, $post_url$, $post_author$, $feed_title$, $feed_url$, $feed_copyright$, $feed_image$.' ).'<br />'.sprintf( $this->T_('Example: Read original article %s on %s.'), htmlspecialchars('"<a href="$post_url$">$post_title$</a>"'), htmlspecialchars('<a href="$feed_url$">$feed_title$</a>') ).'</div>';
		echo '</fieldset>';


		if( $import_blog_ID = param( $this->get_class_id('import_blog_ID'), 'integer' ) )
		{	// Display categories fieldset
			$this->disp_blog_cats_fieldset( $Form, $import_blog_ID );
		}
		else
		{
			$BlogCache = & $this->getCache('BlogCache');

			$opt = array();
			$blog_array = $this->get_avaliable_blogIDs( true );
			foreach( $blog_array as $l_blog )
			{
				$l_Blog = & $BlogCache->get_by_ID($l_blog);
				$opt[] = '<option value="'.$l_blog.'">'.$l_Blog->name.'</option>';
			}
			$blog_options = implode( "\n", $opt );
			if( !empty($opt) )
			{
				$Form->select_input_options( $this->get_class_id('import_blog_ID'), $blog_options, $this->T_('Select a blog'), $this->T_('Select a blog where you want to import posts.') );

				echo '<fieldset><div class="label"><label></label></div>';
				echo '<div class="input">
						<input type="submit" value="'.format_to_output( $this->T_('Continue...'), 'formvalue' ).'">
					  </div></fieldset>';
			}
		}

		echo '</div>';

		if( ! $this->UserSettings->get('scheduled_import') )
		{	// hide import settings
			echo '<script type="text/javascript">jQuery("#scheduled_imort_container").hide();</script>';
		}
	}


	function PluginSettingsUpdateAction()
	{
		if( $lic_key = param('edit_plugin_'.$this->ID.'_set_lic_key', 'string') )
		{
			global $Settings;

			$Settings->set('sonorth_lic_key', $lic_key);
			$Settings->set('sonorth_unreg', false );
			$Settings->dbupdate();

			$this->check_updates( true, $lic_key );
		}
	}


	function PluginUserSettingsUpdateAction()
	{
		global $DB, $current_User;

		$scheduled_import = param('edit_plugin_'.$this->ID.'_set_scheduled_import', 'string');
		$main_cat_ID = param( 'post_category', 'integer' );

		if( $scheduled_import == 1 && empty($main_cat_ID) )
		{
			$this->msg( $this->T_('You must select a main category'), 'error' );
			return false;
		}

		// Get extra_cat_IDs
		$b_ID = $DB->get_var( 'SELECT cat_blog_ID
							   FROM T_categories
							   WHERE cat_ID = '.$DB->quote($main_cat_ID) );
		$extra_cat_IDs = param( 'post_extracats_'.$b_ID, 'array' );
		if( empty($extra_cat_IDs) ) $extra_cat_IDs = array();

		// Get our custom fields
		$this->UserSettings->set( 'post_status', param('edit_plugin_'.$this->ID.'_set_post_status', 'string') );
		$this->UserSettings->set( 'limit', param('edit_plugin_'.$this->ID.'_set_limit', 'integer') );
		$this->UserSettings->set( 'header', param('edit_plugin_'.$this->ID.'_set_header', 'html') );
		$this->UserSettings->set( 'footer', param('edit_plugin_'.$this->ID.'_set_footer', 'html') );

		$this->UserSettings->set( 'main_cat_ID', $main_cat_ID );
		$this->UserSettings->set( 'extra_cat_IDs', $extra_cat_IDs );
	}


	function GetCronJobs( & $params )
	{
		return array(
			array(
				'name' => $this->T_('Import new posts from XML feeds').' ('.$this->name.')',
				'ctrl' => 'import_new_posts',
			),
		);
	}


	function GetExtraEvents()
	{
		return array(
				'preview'	=> 'Preview range results.',
			);
	}


	function GetHtsrvMethods()
	{
		return array( 'preview', 'preview', 'preview' );
	}


	function htsrv_preview( $params = array() )
	{
		$textarea_value = param($this->get_class_id('preview_range'), 'html');

		if( $address_list = $this->get_address_list($textarea_value) )
		{
			if( !is_logged_in() )
			{	// Not logged in
				$this->T_('You don\'t have permissions to access this page');
				exit(0);
			}

			foreach( $address_list as $address )
			{
				if( stristr($address, '[range:') && ($range = $this->get_range($address)) )
				{
					foreach( $range as $r )
					{
						$address_list_tmp[] = $r;
					}
				}
				else
				{
					$address_list_tmp[] = $address;
				}
			}

			if( ! empty($address_list_tmp) )
			{
				echo '<div style="font-family: Arial serif">
						<h3 style="margin: 5px; padding:0">'.$this->T_('Total items').': '.count($address_list_tmp).'</h3>
						<div style="color:#333; max-height:420px; border: 1px solid #ccc; overflow:auto"">
						  <ol><li><em>'.implode( "</em></li>\n<li><em>", $address_list_tmp ).'</em></li></ol>
						</div>
						<p style="color:#666; font-size:12px; margin:4px; padding:0">'.$this->T_('You don\'t need to copy these addresses, just make sure they look valid.').'</p>
					  </div>';

				exit(0);
			}
			$this->T_('Failed to decode ranges');
		}
		else
		{
			$this->T_('Bad request params');
		}
		exit;
	}


	function ExecCronJob( & $params )
	{	// We provide only one cron job, so no need to check $params['ctrl'] here.
		global $DB, $Messages;

		$code = 1;
		$message = 'Ok';

		$SQL = 'SELECT DISTINCT feed_user_ID
				FROM '.$this->get_sql_table('feeds').'
				WHERE feed_status = 1';

		if( $users_IDs = $DB->get_col($SQL) )
		{
			foreach( $users_IDs as $user_ID )
			{
				if( !$this->UserSettings->get( 'scheduled_import', $user_ID ) )
				{
					//$message .= $this->T_('Scheduled XML feed import is disabled in plugin settings');
					//return array( 'code' => 0, 'message' => $message );

					// Go to the next user
					continue;
				}

				$SQL = 'SELECT * FROM '.$this->get_sql_table('feeds').'
						WHERE feed_user_ID = '.$DB->quote($user_ID).'
						AND feed_status = 1
						ORDER BY feed_date ASC';

				if( $feeds = $DB->get_results($SQL) )
				{
					$this->limit = $this->UserSettings->get( 'limit', $user_ID );
					$this->post_header = $this->UserSettings->get( 'header', $user_ID );
					$this->post_footer = $this->UserSettings->get( 'footer', $user_ID );

					$import_params = array(
							'status'		=> $this->UserSettings->get( 'post_status', $user_ID ),
							'main_cat_ID'	=> $this->UserSettings->get( 'main_cat_ID', $user_ID ),
							'extra_cat_IDs'	=> $this->UserSettings->get( 'extra_cat_IDs', $user_ID ),
							'import_type'	=> 'feed',
						);

					foreach( $feeds as $Feed )
					{	// Parse each feed and import new posts
						//$message .= $this->T_('Checking the feed for new posts').': '.$Feed->feed_url;
						//$message .= '<br />==== Message start ====<br />';
						$Messages->clear('all');

						if( $results = $this->parse_feed($Feed->feed_url) )
						{
							$import_params['feed'] = $results['feed'];
							$import_params['items'] = $results['items'];

							if( !empty($import_params['items']) )
							{	// Create posts
								$this->post( $import_params, true );
							}
						}
						//$message .= $Messages->display( '', '', false, 'all', NULL, NULL, NULL );
						//$message .= '==== Message end ====<br /><br /><br />';
					}
				}
			}
		}
		else
		{
			$code = 0;
			$message = 'All users skipped. Nothing to do.';
		}

		return array(
				'code' => $code,
				'message' => $message,
			);
	}


	/**
	 * Event handler: Gets invoked in /admin/_header.php for every backoffice page after
	 * the menu structure is build.
	 */
	function AdminAfterMenuInit()
	{
		$this->register_menu_entry( $this->name );
	}


	function AdminTabPayload()
	{
		global $DB, $Messages, $current_User, $db_config, $rsc_url;

		if( $this->hide_tab == 1 ) return;
		$Messages->clear('all'); // Reset all messages

		$url = '?ctrl=tools&amp;tab=plug_ID_'.$this->ID.'&amp;'.$this->get_class_id().'_manage=feeds';
		$top_nav['feeds'] = '<a href="'.$url.'">'.$this->T_('Manage XML feeds').'</a>';

		$url = '?ctrl=tools&amp;tab=plug_ID_'.$this->ID;
		$top_nav['back'] = '<a href="'.$url.'">'.$this->T_('Back to Import/Export tab').'</a>';

		if( param( $this->get_class_id('manage'), 'string' ) )
		{	// Manage feeds tab
			$Form = new Form( 'admin.php', '', 'post' );
			$Form->begin_form( 'fform' );
			$Form->hidden_ctrl();
			$Form->hiddens_by_key( get_memorized() );
			$Form->hidden( $this->get_class_id('manage'), 'feeds' );

			if( function_exists('url_crumb') ) $Form->add_crumb('plugin');

			echo '<div>'.$top_nav['back'].'</div>';
			echo '<br /><h2>'.$this->T_('Manage XML feeds').'</h2>';

			// We want to display the recent feeds list
			$SQL = 'SELECT * FROM '.$this->get_sql_table('feeds').'
					WHERE feed_user_ID = '.$current_User->ID;

			$Results = new Results( $SQL, '', '---D-', 0 );
			$Results->title = $this->T_('Saved XML feeds');

			function feed_results_td_status( $feed_status )
			{
				global $Plugins;
				$FIP = $Plugins->get_by_code('feed_importer_pro');

				if( empty($feed_status) )
				{
					return get_icon('disabled', 'imgtag', array('title'=>$FIP->T_('The feed is disabled.')) );
				}
				else
				{
					return get_icon('enabled', 'imgtag', array('title'=>$FIP->T_('The feed is enabled.')) );
				}

			}
			$Results->cols[] = array(
					'th' => /* TRANS: shortcut for enabled */ $this->T_('En'),
					'order' => 'feed_status',
					'td' => '% feed_results_td_status( #feed_status# ) %',
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
				);


			function feed_results_td_title( $feed_title )
			{
				global $Plugins;
				$FIP = $Plugins->get_by_code('feed_importer_pro');
				return $FIP->cut_text( $feed_title, 100 );
			}
			$Results->cols[] = array(
					'th' => $this->T_('Title'),
					'td' => '% feed_results_td_title( #feed_title# ) %',
					'order' => 'feed_title',
				);

			$Results->cols[] = array(
					'th' => $this->T_('URL'),
					'td' => '<a href="$feed_url$" target="_blank">'.$this->T_('Open in new window').'</a>',
					'order' => 'feed_url',
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
				);

			$Results->cols[] = array(
					'th' => $this->T_('Date Time'),
					'td' => '%mysql2localedatetime_spans( #feed_date# )%',
					'th_class' => 'shrinkwrap',
					'td_class' => 'timestamp',
					'order' => 'feed_date',
				);


			function feed_results_td_actions( $feed_ID, $feed_status )
			{
				global $Plugins;
				$FIP = $Plugins->get_by_code('feed_importer_pro');

				$r = '';
				if( empty($feed_status) )
				{
					$r .= action_icon( $FIP->T_('Enable'), 'activate', regenerate_url( '', 'feed_importer_feed_ID='.$feed_ID.'&amp;action=enable' ), '', 5 );
				}
				else
				{
					$r .= action_icon( $FIP->T_('Disable'), 'deactivate', regenerate_url( '', 'feed_importer_feed_ID='.$feed_ID.'&amp;action=disable' ), '', 5 );
				}
				$r .= action_icon( $FIP->T_('Delete'), 'delete', regenerate_url( '', 'feed_importer_feed_ID='.$feed_ID.'&amp;action=delete' ), '', 5, 1, array( 'onclick' => 'return confirm(\''.$FIP->T_('Do you really want to delete this feed?').'\')' ) );
				return $r;
			}
			$Results->cols[] = array(
					'th' => $this->T_('Actions'),
					'td' => '% feed_results_td_actions( #feed_ID#, #feed_status# ) %',
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
				);

			$Results->display();
			$Form->end_form();
		}
		else
		{
			$BlogCache = & $this->getCache('BlogCache');

			$import_blog_ID = param( $this->get_class_id('import_blog_ID'), 'integer' );

			// Import tab
			if( !empty($import_blog_ID) && $this->hide_tab != 'import' )
			{	// Display the import fieldset only if user has post permissions
				$Form = new Form( 'admin.php', '', 'post' );
				$Form->begin_form( 'fform' );
				$Form->hidden_ctrl();
				$Form->hiddens_by_key( get_memorized() );

				if( function_exists('url_crumb') ) $Form->add_crumb('plugin');

				$Form->hidden( 'start_import', '1' );
				$Form->hidden( $this->get_class_id('import_blog_ID'), $import_blog_ID );

				echo '<div>'.$top_nav['feeds'].' | '.$top_nav['back'].'</div>';
				echo '<br /><h2>'.$this->T_('Import').': blog #'.$import_blog_ID.'</h2>';
				$Form->begin_fieldset();

				if( $this->UserSettings->get('recent_feeds') )
				{	// We want to display the recent feeds list
					$SQL = 'SELECT * FROM '.$this->get_sql_table('feeds').'
							WHERE feed_user_ID = '.$DB->quote($current_User->ID).'
							AND feed_status = 1
							ORDER BY feed_date ASC
							LIMIT 0,10';

					if( $feeds = $DB->get_results($SQL) )
					{
						$feeds_options = '<option value="" selected="selected">'.$this->T_('None').'</option>';
						foreach( $feeds as $key => $Feed )
						{
							$feed_url = @parse_url($Feed->feed_url);
							$feeds_options .= '<option value="'.$Feed->feed_ID.'">'.$feed_url['host'].' =&gt; '.$this->cut_text( $Feed->feed_title, 50 ).'</option>';
						}
						$Form->select_input_options( $this->get_class_id('feed_ID'), $feeds_options, $this->T_('Saved XML feeds'), $this->T_('Select a feed you already used or enter new address below.') );
					}
				}

				$field_id = $this->get_class_id('address');
				$preview_url = $this->get_htsrv_url('preview').'&amp;'.$this->get_class_id('preview_range').'=';
				$preview_link = '<a id="preview_range" style="display:inline; padding-left: 7px" href="'.$preview_url.'"
						onclick="return pop_up_window( \''.$preview_url.'\' + encodeURIComponent(jQuery(\'#'.$field_id.'\').val()), \'range_preview\', 750, 500, \'scrollbars=yes, status=yes, resizable=yes, menubar=no\' )"
						target="_blank" title="'.$this->T_('Preview results').'"><img
						src="'.$this->get_plugin_url( true ).'/res/mag.png" alt="" /></a>';

				echo '<fieldset id="ffield_'.$field_id.'" style="padding-bottom: 10px">
						<div class="label"><label for="'.$field_id.'">'.$this->T_('Address').':</label></div>
						<div class="input"><img src="'.$rsc_url.'img/blank.gif" alt="" height="1" width="1"><textarea sourceindex="5" cols="60" class="form_textarea_input" name="'.$field_id.'" id="'.$field_id.'" rows="3"></textarea>
						'.$preview_link.'
						<img src="'.$rsc_url.'img/blank.gif" alt="" height="1" width="1"><br />
						<span class="notes" style="padding-bottom:10px">'.
						sprintf( $this->T_('Enter either remote (URL) or local (file path) address. Example: %s'),
						'<br /><br /><b><em>&rarr; http://domain.tld/rss.php?id=[range:1-9-2]&name=[range:A-Z]</em></b>
						<br /><b><em>&rarr; /home/user/public_html/blogs/somefile.CSV</em></b>
						<br /><br />' ).
						sprintf( $this->T_('You can use ranges of numbers and/or letters in format %s'), '<b>[range: START-LIMIT-STEP]</b>').'<br />'.
						sprintf( $this->T_('Multiple range sets are ok, third param is optional, see <a %s>PHP range()</a> for details.'),
						'href="http://www.php.net/manual/en/function.range.php" target="_blank"' ).'</span></div>
					</fieldset>';

				//$Form->textarea( $this->get_class_id('address'), '', 3, $this->T_('Address').$preview_link, sprintf( $this->T_('Enter either remote (URL) or local (file path) address.<p>You can use ranges of numbers and/or letters in format <b>[range: START-LIMIT-STEP]</b>: <br /><em>"http://domain.tld/rss.php?id=[range:1-9-2]&name=[range:A-Z]"</em><br />Multiple range sets are ok, third param is optional, see <a %s>PHP range()</a> for details.</p>'), 'href="http://www.php.net/manual/en/function.range.php" target="_blank"' ), 60 );

				echo '<script type="text/javascript">
					function import_hide() {
						jQuery(".b2evo-import").hide();
						jQuery(".feed-import").hide();
					}

					jQuery( function() {
						import_hide();

						jQuery("#'.$this->get_class_id('import_type').'").change(function() {
							import_hide();
							jQuery("." + jQuery(this).val() + "-import").fadeIn("fast");
						}).change();

						jQuery("#'.$this->get_class_id('import_cats').'").click(function() {
							jQuery(".cats-import").fadeIn("fast");
							if( jQuery(this).is(":checked") ) { jQuery(".cats-import").fadeOut("fast"); }
						});
					})
				</script>';

				$import_options = '<option value="feed">'.$this->T_('XML Feed').'</option>
									<option value="b2evo">'.$this->T_('b2evolution backup ( *.b2e )').'</option>
									<option value="text">'.$this->T_('CSV formatted file').'</option>';
				$Form->select_input_options( $this->get_class_id('import_type'), $import_options, $this->T_('Import format'), $this->get_help_link('$readme').' '.$this->T_('What do you want to import?') );

				$status_opts = array(
						'published' => $this->T_('Published (Public)'),
						'draft' => $this->T_('Draft (Not published!)'),
					);

				$opt = array();
				foreach( $status_opts as $n_opt => $v_opt )
				{
					$sel = '';
					if( $this->UserSettings->get('post_status') == $n_opt ) $sel = ' selected="selected"';
					$opt[] = '<option value="'.$n_opt.'"'.$sel.'>'.$v_opt.'</option>';
				}
				$status_options = implode( "\n", $opt );

				$Form->select_input_options( $this->get_class_id('post_status'), $status_options, $this->T_('Posts status'), $this->T_('Select the status for imported posts.') );
				$Form->text_input( $this->get_class_id('limit'), $this->UserSettings->get('limit'), 4, $this->T_('Limit'), $this->T_('Enter the number of items to import. Leave empty to import all available items.'), array( 'maxlength'=>5 ) );
				$Form->checkbox( $this->get_class_id('check_existance'), true, $this->T_('Check post existance'), $this->T_('Should we skip imported items if they already exist in the database.') );

				echo '<div class="b2evo-import">';
				$Form->checkbox( $this->get_class_id('import_cats'), false, $this->T_('Import categories'), $this->T_('Should we import categories? If checked, you don\'t need to choose categories below. (<b>*.b2e format only</b>).') );
				$Form->checkbox( $this->get_class_id('import_comments'), true, $this->T_('Import comments'), $this->T_('Should we import post comments, trackbacks etc? (<b>*.b2e format only</b>).') );
				echo '</div>';

				echo '<div class="feed-import">';
				$Form->textarea( $this->get_class_id('header'), $this->UserSettings->get('header'), 2, $this->T_('Post header'), $this->T_('Enter post header text here. This text will be added to the top of each post.'), 60 );
				$Form->textarea( $this->get_class_id('footer'), $this->UserSettings->get('footer'), 2, $this->T_('Post footer'), $this->T_('Enter post footer text here. This text will be added to the bottom of each post.'), 60 );
				echo '<fieldset><div class="label"><label></label></div>';
				echo '<div class="input notes">'.sprintf( $this->T_('The following tags will be replaced with the real values when <b>XML Feed</b> is imported: %s'), '$post_title$, $post_url$, $post_author$, $feed_title$, $feed_url$, $feed_copyright$, $feed_image$.' ).'<br />'.sprintf( $this->T_('Example: Read original article %s on %s.'), htmlspecialchars('"<a href="$post_url$">$post_title$</a>"'), htmlspecialchars('<a href="$feed_url$">$feed_title$</a>') ).'</div>';
				echo '</fieldset>';
				echo '</div>';

				// Display categories fieldset
				echo '<div class="cats-import">';
				$css = "<br />\n".'<style type="text/css">fieldset.extracats div.extracats { height:500px }</style>';
				$this->disp_blog_cats_fieldset( $Form, $import_blog_ID, $css );
				echo '</div>';

				$Form->end_fieldset();
				$Form->end_form( array( array( 'value' => $this->T_('Import !'), 'onclick' => 'return confirm(\''.$this->T_('Do you really want to continue?').'\')' ) ) );
			}


			if( empty($import_blog_ID) )
			{
				echo '<div>'.$top_nav['feeds'].'</div>';

				echo '<br /><h2>'.$this->T_('Import').'</h2><div class="fieldset_wrapper fieldset">
						<fieldset class="fieldset" style="background: #FFF">';

				$Form = new Form( 'admin.php', '', 'post' );
				$Form->begin_form( 'fform' );
				$Form->hidden_ctrl();
				$Form->hiddens_by_key( get_memorized() );
				$Form->begin_fieldset( $this->T_('Pick a target blog') );

				if( function_exists('url_crumb') ) $Form->add_crumb('plugin');

				$opt = array();
				$blog_array = $this->get_avaliable_blogIDs( true );
				foreach( $blog_array as $l_blog )
				{
					$l_Blog = & $BlogCache->get_by_ID($l_blog);
					$opt[] = '<option value="'.$l_blog.'">#'.$l_blog.' :: '.$l_Blog->name.'</option>';
				}
				$blog_options = implode( "\n", $opt );
				if( !empty($opt) )
				{
					$Form->select_input_options( $this->get_class_id('import_blog_ID'), $blog_options, $this->T_('Select a blog'), $this->T_('Select a blog where you want to import posts.') );

					echo '<fieldset><div class="label"><label></label></div>';
					echo '<div class="input">
							<input type="submit" value="'.format_to_output( $this->T_('Continue...'), 'formvalue' ).'" class="ActionButton" />
						  </div></fieldset>';
				}
				$Form->end_fieldset();
				$Form->end_form();
				echo '</fieldset></div>';


				echo '<br /><h2>'.$this->T_('Export').'</h2><div class="fieldset_wrapper fieldset">
						<fieldset class="fieldset" style="background: #FFF">';

				$current_User->get_Group();

				$export_opts = array(
						array( 'b2e', $this->T_('b2e').' - <span class="notes">'.$this->T_('Feed Importer internal format. Use this for import or backup. Unreadable text.').'</span><br />'),
						array( 'csv', $this->T_('CSV').' - <span class="notes">'.$this->T_('A comma-separated values file (multiline)' ).'</span><br />'),
						array( 'xml', $this->T_('XML').' - <span class="notes">'.$this->T_('Extensible Markup Language').'</span><br />')
					);

				if( function_exists('json_encode') )
				{
					$export_opts[] = array( 'json', $this->T_('JSON').' - <span class="notes">'.$this->T_('JavaScript Object Notation').'</span>');
				}

				$blog_array = $this->get_avaliable_blogIDs( false, 'blog_ismember', 'view', true );

				$opt = array();
				foreach( $blog_array as $l_blog )
				{
					if( $l_Blog = & $BlogCache->get_by_ID($l_blog, false, false) )
					{
						$opt[] = '<option value="'.$l_blog.'">#'.$l_blog.' :: '.$l_Blog->name.'</option>';
						$can_export = 1;
					}
					else
					{
						$opt[] = '<option value="">'.$l_blog.'</option>';
					}
				}
				$blog_options = implode( "\n", $opt );

				$target_options = array(
						array('items', $this->T_('Posts').' - <span class="notes">'.
							$this->T_('Export posts only. You may later import them into any category.').'</span><br />'),
						array('cats', $this->T_('Categories').' - <span class="notes">'.
							$this->T_('Export categories only' ).'</span><br />'),
						array('items-cats', $this->T_('Posts and categories').' - <span class="notes">'.
							$this->T_('Export posts and their categories. Select this to make an exact copy of exported blog.' ).'</span>')
					);

				if( empty($can_export) )
				{
					echo '<p class="red"><b>'.$this->T_('No blogs avaliable for export yet.').'</b><p>';
					echo '</fieldset></div>';
					return;
				}

				$Form = new Form( 'admin.php', '', 'post' );
				$Form->begin_form( 'fform' );
				$Form->hidden_ctrl();
				$Form->hiddens_by_key( get_memorized() );

				if( function_exists('url_crumb') ) $Form->add_crumb('plugin');

				$Form->begin_fieldset( $this->T_('Select what kind of content you want to export') );

				echo '<fieldset style="border: 1px solid #CCC"><legend style="margin-left: 10px; color: #333; font-size: 16px; font-weight: normal">'.$this->T_('Posts and categories').'...</legend>';
				$Form->select_input_options( $this->get_class_id('ex_target_ID'), $blog_options, $this->T_('Select a blog'),
						$this->get_help_link('$readme').' '.$this->T_('Export all items from selected blog.') );

				$Form->radio( $this->get_class_id('target'), 'items', $target_options, $this->T_('Content type'), false, '' );
				echo '</fieldset>';

				if( $current_User->Group->ID == 1 )
				{
					echo '<fieldset style="border: 1px solid #CCC"><legend style="margin-left: 10px; color: #333; font-size: 16px; font-weight: normal">...'.$this->T_('or a complete database table').'</legend>';

					$target_options = $db_config['aliases'];
					ksort($target_options);
					array_unshift( $target_options, '= = = = = = = =' );

					$opt = array();
					foreach( $target_options as $k => $v )
					{
						$opt[] = '<option value="'.$k.'">'.$v.'</option>';
					}
					$target_options = implode( "\n", $opt );

					$Form->select_input_options( $this->get_class_id('table'), $target_options,
							$this->T_('Select a table'), $this->T_('This will export all table fields.') );

					echo '</fieldset>';
				}

				$Form->radio( $this->get_class_id('format'), 'b2e', $export_opts, $this->T_('Export format') );

				echo '<div class="label"><label></label></div>';
				echo '<div class="input">
						<input type="submit" name="export" value="'.format_to_output( $this->T_('Export !'), 'formvalue' ).'" class="ActionButton" />
					  </div>';

				$Form->end_fieldset();
				$Form->end_form();

				echo '</fieldset></div>';
			}
		}
	}


	function AdminTabAction()
	{
		global $Messages, $Session, $DB, $current_User, $localtimenow;

		$this->check_perms();
		if( $this->hide_tab == 1 ) return;

		// Check if export directory exists
		$this->check_dir();

		if( $Feed = $this->get_Feed() )
		{	// We have perms to manage the requested feed

			if( function_exists('url_crumb') ) $Session->assert_received_crumb('plugin');

			set_param( $this->get_class_id('manage'), 'feeds' ); // Return to the right view

			$action = param( 'action', 'string' );
			switch($action)
			{
				case 'enable':
					$SQL = 'UPDATE '.$this->get_sql_table('feeds').'
							SET feed_status = 1
							WHERE feed_ID = '.$Feed->feed_ID;
					if( $DB->query($SQL) ) $this->msg( $this->T_('The feed has been enabled!'), 'success' );
					break;

				case 'disable':
					$SQL = 'UPDATE '.$this->get_sql_table('feeds').'
							SET feed_status = 0
							WHERE feed_ID = '.$Feed->feed_ID;
					if( $DB->query($SQL) ) $this->msg( $this->T_('The feed has been disabled!'), 'success' );
					break;

				case 'delete':
					$SQL = 'DELETE FROM '.$this->get_sql_table('feeds').'
							WHERE feed_ID = '.$Feed->feed_ID;
					if( $DB->query($SQL) ) $this->msg( $this->T_('The feed has been deleted!'), 'success' );
					break;
			}

		}


		if( isset($_POST['export']) )
		{	// Export
			global $app_version;

			if( function_exists('url_crumb') ) $Session->assert_received_crumb('plugin');

			$current_User->get_Group();

			$ex_target_ID = 0;

			$target = param( $this->get_class_id('target'), 'string' );
			$format = param( $this->get_class_id('format'), 'string' );
			$table = param( $this->get_class_id('table'), 'string' );

			// echo $target.'<br>'.$format.'<br>'.$table; die;

			if( in_array( $target, array('cats','items','items-cats') ) )
			{
				if( !$ex_target_ID = param( $this->get_class_id('ex_target_ID'), 'integer' ) )
				{
					$this->msg( $this->T_('Invalid or empty blog ID'), 'error' );
					return;
				}
				if( $target != 'items' && ! $current_User->check_perm( 'blog_cats', '', false, $ex_target_ID ) )
				{
					$this->msg( $this->T_('You don\'t have permissions to export categories'), 'error' );
					return;
				}

				$filename = 'blog'.$ex_target_ID.'_'.$target;

				$BlogCache = & $this->getCache('BlogCache');
				if( $Blog = & $BlogCache->get_by_ID( $ex_target_ID, false, false ) )
				{
					$filename .= '-'.$Blog->urlname;
				}
			}
			elseif( preg_match('~^T_~i', $table) )
			{
				if( $current_User->Group->ID != 1 )
				{
					$this->msg( $this->T_('You must be a member of group #1 to export database tables'), 'error' );
					return;
				}
				$filename = $table;
				$target = $table;
			}
			else
			{
				$this->msg( sprintf( $this->T_('Invalid export target [%s]'), $target ), 'error' );
				return;
			}

			// echo $target.'<br>'.$filename.'<br>'.$format; die;

			switch($format)
			{
				case 'b2e':
					// Create CSV data
					if( $target == 'items-cats' )
					{
						if( !$export_content = $this->create_csv( $this->b2evo_export($ex_target_ID, $format, 'items') ) ) return;

						// Append categories
						$export_content .= "\n[categories]\n".$this->create_csv( $this->b2evo_export($ex_target_ID, $format, 'cats') );
					}
					else
					{
						if( !$export_content = $this->create_csv( $this->b2evo_export($ex_target_ID, $format, $target) ) ) return;
					}

					// Add an info line
					$info_text = 'Generator: b2evolution '.$app_version.'; ';
					$info_text .= 'Export type: '.$target.'; Date: '.date2mysql($localtimenow).';';

					$export_content = $info_text."\r\n\r\n".$export_content;
					break;

				case 'csv':
					// Create CSV data
					if( !$export_content = $this->create_csv( $this->b2evo_export($ex_target_ID, $format, $target) ) ) return;
					break;

				case 'xml':
					if( !$export_content = $this->create_xml( $this->b2evo_export($ex_target_ID, $format, $target), $target ) ) return;
					break;

				case 'json':
					if( !$export_content = $this->create_json( $this->b2evo_export($ex_target_ID, $format, $target), $target ) ) return;
					break;

				default:
					$this->msg( sprintf( $this->T_('Invalid export format [%s]'), $format ), 'error' );
					return;
			}

			if( $this->zip( $export_content, $filename, $ex_target_ID, $format ) )
			{
				//$dl_link = 'href="'.$this->get_plugin_url( true ).'export/'.$filename.'.zip"';
				//$this->msg( sprintf( $this->T_('Exported file zipped. <a %s>Download it now</a>.'), $dl_link ), 'success' );

				$this->msg( sprintf( $this->T_('File path: %s'), $this->path.$filename.'.zip' ), 'success' );
			}
		}
		elseif( isset($_POST['start_import']) && $this->hide_tab != 'import' )
		{	// Import
			if( function_exists('url_crumb') ) $Session->assert_received_crumb('plugin');

			$import_blog_ID = param( $this->get_class_id('import_blog_ID'), 'integer' );
			$import_type = param( $this->get_class_id('import_type'), 'string' );
			$import_cats = param( $this->get_class_id('import_cats'), 'boolean' );

			$address_list = $this->get_address_list( param( $this->get_class_id('address'), 'html' ) );
			if( empty($address_list) && $import_type != 'feed' )
			{
				$this->msg( $this->T_('The address field is empty.'), 'error' );
				return;
			}

			if( $import_type == 'b2evo' && $import_cats )
			{
				$main_cat_ID = 0;
				$extra_cat_IDs = array();
			}
			else
			{
				if( !$main_cat_ID = param( 'post_category', 'integer' ) )
				{
					$this->msg( $this->T_('You must select a main category'), 'error' );
					return;
				}

				// Get blog ID
				$b_ID = $DB->get_var( 'SELECT cat_blog_ID FROM T_categories
									   WHERE cat_ID = '.$DB->quote($main_cat_ID) );

				if( !$extra_cat_IDs = param( 'post_extracats_'.$b_ID, 'array' ) )
				{
					$extra_cat_IDs = array();
				}
			}

			$feed_ID 			= param( $this->get_class_id('feed_ID'), 'integer' );
			$post_status 		= param( $this->get_class_id('post_status'), 'string' );
			$check_existance 	= param( $this->get_class_id('check_existance'), 'boolean' );
			$import_comments 	= param( $this->get_class_id('import_comments'), 'boolean' );
			$this->post_header 	= param( $this->get_class_id('header'), 'html' );
			$this->post_footer 	= param( $this->get_class_id('footer'), 'html' );
			$this->limit 		= param( $this->get_class_id('limit'), 'integer' );

			$import_params = array(
					'status'		=> $post_status,
					'main_cat_ID'	=> $main_cat_ID,
					'extra_cat_IDs'	=> $extra_cat_IDs,
					'blog_ID'		=> $import_blog_ID,
				);

			if( function_exists('set_time_limit') )
			{
				@set_time_limit( 1800 ); // 30 minutes
			}
			@ini_set( 'max_execution_time', '1800' );
			@ini_set( 'max_input_time', '1800' );
			@ini_set( 'memory_limit', '256M' );

			if( empty($address_list) && !empty($feed_ID) )
			{
				$SQL = 'SELECT feed_url FROM '.$this->get_sql_table('feeds').'
						WHERE feed_ID = '.$DB->quote($feed_ID).'
						AND feed_user_ID = '.$DB->quote($current_User->ID);

				if( !$address_list = $DB->get_col($SQL) )
				{
					$this->msg( $this->T_('Saved feed was not found in the database'), 'error' );
					return;
				}
			}

			foreach( $address_list as $address )
			{
				if( stristr($address, '[range:') && ($range = $this->get_range($address)) )
				{
					foreach( $range as $r )
					{
						$address_list_tmp[] = $r;
					}
				}
				else
				{
					$address_list_tmp[] = $address;
				}
			}
			$address_list = $address_list_tmp;

			// Process all addresses
			foreach( $address_list as $address )
			{
				$this->msg_head( sprintf($this->T_('Processing: %s'), $address) );

				switch( $import_type )
				{
					// XML Feed
					case 'feed':
						if( !$results = $this->parse_feed($address) ) continue; // Parse a feed

						$import_params['import_type'] = 'feed';
						$import_params['feed'] = $results['feed'];
						$import_params['items'] = $results['items'];
						break;

					// b2evo backup
					case 'b2evo':
						if( !$content = $this->get_data($address, true) ) continue;

						$first_string = explode("\n", $content, 2);
						$first_string = $first_string[0];

						if( preg_match( '~Export type: (.*?);~', $first_string, $match ) )
						{
							$import_params['content_type'] = $match[1];
						}

						$content = explode( "\n[categories]\n", $content );

						if( count($content) == 2 && !empty($content[1]) )
						{
							if( !$cats = $this->read_csv($content[1]) )
							{
								$this->msg( sprintf( $this->T_('Invalid data supplied or format mismatch in %s'), $address ), 'error' );
								continue;
							}
							$import_params['cats'] = $cats;
						}

						if( !$items = $this->read_csv( $content[0], 2 ) )
						{
							$this->msg( sprintf( $this->T_('Invalid data supplied or format mismatch in %s'), $address ), 'error' );
							continue;
						}

						if( $import_params['content_type'] == 'cats' )
						{
							$import_params['cats'] = $items;
							$items = array();
						}

						$import_params['import_type'] = 'b2evo';
						$import_params['items'] = $items;
						$import_params['import_cats'] = $import_cats;
						$import_params['import_comments'] = $import_comments;
						break;

					// Text file
					case 'text':
						if( ! $content = $this->get_data($address, true) ) continue;
						if( !$content = $this->read_csv($content) )
						{
							$this->msg( sprintf( $this->T_('Invalid data supplied in %s'), $address ), 'error' );
							return;
						}
						$import_params['import_type'] = 'text';
						$import_params['items'] = $content;
						break;
				}

				if( !empty($import_params['items']) || !empty($import_params['cats']) )
				{	// Create posts
					$this->post( $import_params, $check_existance );
				}
			}
		}
	}


	function post( $options = array(), $check_existance = true )
	{
		global $DB, $Settings, $current_User, $timestamp, $baseurl;

		if( $options['import_cats'] )
		{	// Import categories
			if( empty($options['cats']) )
			{
				$this->msg( $this->T_('Couldn\'t find categories data in supplied file'), 'note' );
				return;
			}

			$this->import_categories($options['cats'], $options['blog_ID']);

			if( empty($this->_imported['cats']) )
			{
				$this->msg( $this->T_('Couldn\'t import categories'), 'note' );
				return;
			}
			else
			{
				$this->msg( sprintf( $this->T_('%d categories imported'), count($this->_imported['cats']) ), 'success' );
			}
		}

		if( empty($options['items']) || !is_array($options['items']) ) return false;

		$ChapterCache = & $this->getCache('ChapterCache');
		$ItemCache = & $this->getCache('ItemCache');

		load_class( 'items/model/_item.class.php', 'Item' );

		$total = count($options['items']);
		if( !$this->limit || $this->limit > $total )
		{
			$this->limit = $total;
		}

		for( $try_i=0; $try_i < $this->limit; $try_i++ )
		{
			$msg = array();
			$item = $options['items'][$try_i];
			if( !is_array($item) ) continue;

			if( $options['import_type'] == 'b2evo' )
			{	// Decode item values
				$temp = array();
				foreach( $item as $k => $v )
				{
					$temp[$k] = @base64_decode($v);
				}
				$item = $temp;
			}

			// Check if post already exists in our DB
			if( $check_existance && ($skip_ID = $this->post_exists($item)) )
			{
				$this->msg( sprintf( $this->T_('The post %s already exists in DB, skipping...'), '#'.$skip_ID ), 'note' );
				continue;
			}
			if( isset($options['feed']['locale']) )
			{	// Set locale from feed
				$item['locale'] = $options['feed']['locale'];
			}

			// Default params
			$params = array_merge( array(
						'author_ID'		=> $current_User->ID,
						'title'			=> '',
						'content'		=> '',
						'date'			=> '',
						'main_cat_ID'	=> $options['main_cat_ID'],
						'extra_cat_IDs'	=> $options['extra_cat_IDs'],
						'status'		=> $options['status'],
						'locale'		=> '#',
						'urltitle'		=> '',
						'url'			=> '',
						'comment_status'=> 'open',
						'renderers'		=> array('default'),
						'ptyp_ID'		=> 1,
						'pst_ID'		=> NULL,

						'tags'			=> '',
						'priority'		=> '',
						'excerpt'		=> '',
						'views'			=> '',
						'datedeadline'	=> '',
					), $item );

			if( ! is_array($params['extra_cat_IDs']) )
			{	// Custom extra_cat_IDs
				$params['extra_cat_IDs'] = $this->array_trim( explode( ',', $params['extra_cat_IDs'] ) );
			}

			if( $options['import_cats'] )
			{	// Adjust cat IDs
				if( isset($this->_imported['cats']['orig_'.$params['main_cat_ID']]) )
				{
					$params['main_cat_ID'] = $this->_imported['cats']['orig_'.$params['main_cat_ID']];
				}

				$tmp = array();
				foreach( $params['extra_cat_IDs'] as $cat_ID )
				{
					if( isset($this->_imported['cats']['orig_'.$cat_ID]) )
					{
						$tmp[] = $this->_imported['cats']['orig_'.$cat_ID];
					}
				}
				$params['extra_cat_IDs'] = $tmp;
			}
			else
			{
				if( $params['main_cat_ID'] != $options['main_cat_ID'] )
				{	// Custom main_cat_ID, check if it exists
					if( $ChapterCache->get_by_ID( $params['main_cat_ID'], false ) === false )
					{	// Category not found, use the selected one
						$params['main_cat_ID'] = $options['main_cat_ID'];
					}
				}
			}

			// Make sure main cat is in extracat list and there are no duplicates
			$params['extra_cat_IDs'][] = $params['main_cat_ID'];
			$params['extra_cat_IDs'] = array_unique( $params['extra_cat_IDs'] );
			// Check permission on statuses:
			if( !$current_User->check_perm( 'cats_post!'.$params['status'], 'edit', false, $params['extra_cat_IDs'] ) )
			{	// Permission denied
				$this->msg( $this->T_('Permission denied.'), 'error' );
				continue;
			}

			if( empty($params['date']) )
			{	// If date not set, use current time
				$params['date'] = date('Y-m-d H:i:s',time());
			}

			if( $options['import_type'] != 'feed' )
			{	// For text and b2evo imports only
				$content = '';
				if( $this->post_header )
				{	// Add custom header
					$content .= $this->post_header;
				}
				$content .= $params['content'];  // Get content
				if( $this->post_footer )
				{	// Add custom footer
					$content .= $this->post_footer;
				}
				$params['content'] = $content;
			}

			// Convert locale ( e.g. 'en' to 'en-US' )
			if( $params['locale'] != '#' && !preg_match( '/[a-z]{2}-[A-Z]{2}(-.{1,14})?/', $params['locale'] ) ) $params['locale'] = locale_by_lang( $params['locale'] );

			// Check HTML sanity for non-b2evo imports
			if( $options['import_type'] != 'b2evo' )
			{
				if( ($params['title'] = check_html_sanity( $params['title'], 'posting' )) === false )
				{
					$this->msg( sprintf( $this->T_('Invalid post title: %s'), $params['title'] ), 'error' );
					continue;
				}
				if( ($params['content'] = check_html_sanity( $params['content'], 'posting' )) === false  )
				{
					$this->msg( $this->T_('Invalid post content'), 'error' );
					continue;
				}
			}

			$edited_Item = new Item();  // New Item

			if( empty($params['excerpt']) )
			{	// Autogenerate excerpt
				$params['excerpt'] = $this->cut_text( $params['content'], 240 );
			}

			// Needed for 'excerpt' generation
			if( !empty($params['main_cat_ID']) ) $edited_Item->set( 'main_cat_ID', $params['main_cat_ID'] );

			if( !empty($params['excerpt']) ) $edited_Item->set( 'excerpt', $params['excerpt'] );
			if( !empty($params['tags']) ) $edited_Item->set_tags_from_string($params['tags']);
			if( !empty($params['priority']) ) $edited_Item->set( 'priority', $params['priority'] );
			if( !empty($params['views']) ) $edited_Item->set( 'views', $params['views'] );
			if( !empty($params['datedeadline']) ) $edited_Item->set( 'datedeadline', $params['datedeadline'] );

			// b2evo 3
			if( !empty($params['featured']) ) $edited_Item->set( 'featured', $params['featured'] );
			if( !empty($params['order']) ) $edited_Item->set( 'order', $params['order'] );
			if( !empty($params['titletag']) ) $edited_Item->set( 'titletag', $params['titletag'] );

			// b2evo 3.3
			if( !empty($params['metadesc']) ) $edited_Item->set( 'metadesc', $params['metadesc'] );
			if( !empty($params['metakeywords']) ) $edited_Item->set( 'metakeywords', $params['metakeywords'] );
			if( !empty($params['editor_code']) ) $edited_Item->set( 'editor_code', $params['editor_code'] );

			// b2evo 4
			if( !empty($params['excerpt_autogenerated']) ) $edited_Item->set( 'excerpt_autogenerated', $params['excerpt_autogenerated'] );
			if( !empty($params['dateset']) ) $edited_Item->set( 'dateset', $params['dateset'] );
			//if( !empty($params['canonical_slug_ID']) ) $edited_Item->set( 'canonical_slug_ID', $params['canonical_slug_ID'] );
			//if( !empty($params['tiny_slug_ID']) ) $edited_Item->set( 'tiny_slug_ID', $params['tiny_slug_ID'] );


			// CUSTOM FIELDS double
			for( $i = 1 ; $i <= 5; $i++ )
			{
				if( !empty($params['double'.$i]) )
				{
					$edited_Item->set( 'double'.$i, $params['double'.$i] );
				}
			}

			// CUSTOM FIELDS varchar
			for( $i = 1 ; $i <= 3; $i++ )
			{
				if( !empty($params['varchar'.$i]) )
				{
					$edited_Item->set( 'varchar'.$i, $params['varchar'.$i] );
				}
			}

			// INSERT NEW POST INTO DB:
			if( $ID = $edited_Item->insert(
						$params['author_ID'],
						$params['title'],
						$params['content'],
						$params['date'],
						$params['main_cat_ID'],
						$params['extra_cat_IDs'],
						$params['status'],
						$params['locale'],
						$params['urltitle'],
						$params['url'],
						$params['comment_status'],
						$params['renderers'],
						$params['ptyp_ID'],
						$params['pst_ID'] ) )
			{
				$link = '<a href="'.url_add_param( $baseurl, 'p='.$ID ).'" target="_blank">'.
					$this->cut_text( $params['title'], 40 ).'</a>';

				$msg[] = $this->T_('New post created:').' &laquo;'.$link.'&raquo;';

				// schedule notifications & pings
				if( true )
				{
					$notifications_mode = $Settings->get('outbound_notifications_mode');

					if( $notifications_mode != 'off' &&
					   	$params['status'] == 'published' &&
						! in_array( $params['ptyp_ID'], array( 1500,1520,1530,1570,1600,3000 ) ) &&
						$notifications_mode != 'immediate' )
					{
						// We want asynchronous post processing:

						// CREATE OBJECT:
						load_class( '/cron/model/_cronjob.class.php', 'Cronjob' );

						$edited_Cronjob = new Cronjob();

						// start datetime. We do not want to ping before the post is effectively published:
						$edited_Cronjob->set( 'start_datetime', $edited_Item->issue_date );

						// name:
						$edited_Cronjob->set( 'name', sprintf( T_('Send notifications for &laquo;%s&raquo;'), strip_tags($params['title']) ) );

						// controller:
						$edited_Cronjob->set( 'controller', 'cron/jobs/_post_notifications.job.php' );

						// params: specify which post this job is supposed to send notifications for:
						$edited_Cronjob->set( 'params', array( 'item_ID' => $ID ) );

						// Save cronjob to DB:
						$edited_Cronjob->dbinsert();

						// Memorize the cron job ID which is going to handle this post:
						$edited_Item->set( 'notifications_ctsk_ID', $edited_Cronjob->ID );

						// Record that processing has been scheduled:
						$edited_Item->set( 'notifications_status', 'todo' );
					}

					global $Plugins;
					if( $Plugin = & $Plugins->get_by_code( 'translit_urls' ) )
					{
						$tmp_params = array( 'Item' => & $edited_Item );
						$Plugins->call_method_if_active( $Plugin->ID, 'AdminBeforeItemEditCreate', $tmp_params );
					}

					// Save the new processing status to DB
					$edited_Item->dbupdate();
				}
			}
			else
			{
				$this->msg( $this->T_('Unable to create a post').' "'.$params['title'].'"', 'error' );
			}

			if( !empty($ID) && !empty($params['slugs']) )
			{	// We want to import slugs
				// Existing slugs will not be updated, this will break old links to an Item
				$slugs = @unserialize($params['slugs']);

				if( is_array($slugs) )
				{
					$snum = 0;
					foreach( $slugs as $slug )
					{
						$SQL = 'INSERT IGNORE INTO T_slug ( slug_itm_ID, slug_title, slug_type )
								VALUES( '.$ID.', '.$DB->quote($slug).', '.$DB->quote('item').' )';

						if( $DB->query($SQL) ) $snum ++; // Increase the counter
					}
					if( $snum > 0 ) $msg[] = sprintf( $this->T_('%d slugs imported'), $snum );
				}
			}

			if( !empty($ID) && isset($options['import_comments']) && !empty($params['comments']) )
			{	// We want to import comments
				$comments = @unserialize($params['comments']);

				if( is_array($comments) )
				{
					$cnum = 0;
					foreach( $comments as $comment )
					{
						$SQL_fields = 'comment_post_ID, comment_type, comment_status,
									comment_author, comment_author_email, comment_author_url,
									comment_author_IP, comment_date, comment_content,
									comment_rating, comment_featured, comment_nofollow,
									comment_spam_karma, comment_allow_msgform';

						$SQL_values = $ID.',
									'.$DB->quote($comment['type']).',
									'.$DB->quote($comment['status']).',
									"'.$DB->escape($comment['author']).'",
									"'.$DB->escape($comment['author_email']).'",
									"'.$DB->escape($comment['author_url']).'",
									'.$DB->quote($comment['author_IP']).',
									'.$DB->quote($comment['date']).',
									"'.$DB->escape($comment['content']).'",
									'.$DB->quote($comment['rating']).',
									'.$DB->quote($comment['featured']).',
									'.$DB->quote($comment['nofollow']).',
									'.$DB->quote($comment['spam_karma']).',
									'.$DB->quote($comment['allow_msgform']);

						if( !empty($comment['secret']) )
						{	// b2evo 4
							$SQL_fields .= ', comment_secret';
							$SQL_values .= ', "'.$DB->escape($comment['secret']).'"';
						}

						$SQL = 'INSERT INTO T_comments( '.$SQL_fields.' ) VALUES( '.$SQL_values.' )';

						//var_export($SQL); die;

						if( $DB->query($SQL) ) $cnum ++; // Increase the counter
					}
					if( $cnum > 0 ) $msg[] = sprintf( $this->T_('%d comments imported'), $cnum );
				}
			}

			$this->msg( implode( ', ', $msg ).'.', 'success' );
		}
	}


	/*
	 * We get some extra params from feed we don't actually use
	 * like author or feed name/shortdesc. The idea is to take
	 * all data that may be used in the future.
	 */
	function parse_feed( $feed_url )
	{
		global $DB, $current_User, $evo_charset, $current_charset, $use_strict, $localtimenow;

		// Make sure the simple pie class is loaded
		require_once dirname(__FILE__).'/inc/'.$this->simplepie;

		if( ! $content = $this->get_data($feed_url) ) return false;

		$SimplePie = new SimplePie();
		$SimplePie->set_useragent( $this->name.' v'.$this->version.' by '.$this->author );
		$SimplePie->set_raw_data( $content );
		//$SimplePie->set_feed_url( $feed_url );
		$SimplePie->enable_cache( false );
		$SimplePie->remove_div( true );
		$SimplePie->strip_comments( true );
		$SimplePie->set_output_encoding( $evo_charset );
		$SimplePie->init();
		$SimplePie->handle_content_type();

		if( $feed_items = $SimplePie->get_items() )
		{	// We have feed items
			//var_export($feed_items);

			$link = NULL;
			if( $img_url = $SimplePie->get_image_url() )
			{	// Get feed image
				$img = '<img src="'.$img_url.'" width="'.$SimplePie->get_image_width().'" height="'.$SimplePie->get_image_height().'" alt="" />';
				$link = '<a href="'.$SimplePie->get_image_link().'" title="'.$SimplePie->get_image_title().'">'.$img.'</a>';
			}

			$feed = array(
					'title'		=> $SimplePie->get_title(),
					'url'		=> $feed_url,
				//	'url'		=> $SimplePie->get_permalink(),
					'shortdesc'	=> $SimplePie->get_description(),
					'locale'	=> $SimplePie->get_language(),
					'num_items'	=> $SimplePie->get_item_quantity(),
					'icon'		=> $SimplePie->get_favicon(),
					'copyright'	=> $SimplePie->get_copyright(),
					'image'		=> $link,
				);

			$items = array();
			foreach( $feed_items as $feed_item )
			{
				$author = $date = NULL;
				if( $Author = $feed_item->get_author() )
				{	// Get author
					$author = $Author->get_name();
				}
				$tags = array();
				if( $Cats = $feed_item->get_categories() )
				{	// Get tags/categories
					if( count($Cats) > 0 )
					{
						foreach( $Cats as $Cat )
						{
							$tags[] = $Cat->term;
						}
						$tags = @implode( ',', $tags );
					}
				}
				if( $Date = $feed_item->get_date('U') )
				{	// Get date
					$date = date2mysql($Date);
				}

				$info = array(
						'post_title'	=> $feed_item->get_title(),
						'post_url'		=> $feed_item->get_permalink(),
						'post_author'	=> $author,
						'feed_title'	=> $feed['title'],
						'feed_url'		=> $feed['url'],
						'feed_copyright'=> $feed['copyright'],
						'feed_image'	=> $feed['image'],
					);

				$content = '';
				if( $this->post_header )
				{	// Add custom header
					$content .= $this->replace_tags( $this->post_header, $info );
				}
				$content .= $feed_item->get_content();  // Get content
				if( $this->post_footer )
				{	// Add custom footer
					$content .= $this->replace_tags( $this->post_footer, $info );
				}

				// We need to validate feedburners crap
				$content = str_replace( '</img>', '', preg_replace_callback( '~<img([^>]+?)/*>~is', array( $this, 'validate_images' ), $content ) );

				if( $use_strict )
				{
					$content = preg_replace( '~target="[^"]+?"~', '', $content );
				}

				// Build an item
				$items[] = array(
						'title'		=>	$info['post_title'],
						'permalink'	=>	$info['post_url'],
						'date'		=>	$date,
						'author'	=>	$author,
						'tags'		=>	$tags,
						'content'	=>	$content,
						'orig_content'	=>	$feed_item->get_content(),
						'enclosure'	=>	$feed_item->get_enclosure(),
						'copyright'	=>	$feed_item->get_copyright(),
					);
			}
			// Let's save the feed in DB
			if( $feed_ID = $this->feed_exists( $feed['url'] ) )
			{
				$DB->query('UPDATE '.$this->get_sql_table('feeds').'
							SET feed_title = '.$DB->quote( $feed['title'] ).',
								feed_date = '.$DB->quote( date('Y-m-d H:i:s', $localtimenow) ).'
							WHERE feed_ID = '.$feed_ID);
			}
			else
			{
				$DB->query('INSERT INTO '.$this->get_sql_table('feeds').'
							(feed_user_ID, feed_url, feed_title, feed_date) VALUES (
							'.(int)$current_User->ID.',
							'.$DB->quote( $feed['url'] ).',
							'.$DB->quote( $feed['title'] ).',
							'.$DB->quote( date('Y-m-d H:i:s', $localtimenow) ).'
						)'
					);
			}

			//var_export($items);
			return array( 'feed' => $feed, 'items' => $items );
		}
		else
		{	// Display an error
			if( !$error = $SimplePie->error() )
			{
				$error = 'Unknown SimplePie error';
			}
			$this->msg( $error, 'error' );
		}
		return false;
	}


	function import_categories( $cats, $blog_ID, $decode = true )
	{
		global $DB;

		if( empty($cats) ) return;

		$children = array();
		foreach( $cats as $cat )
		{
			if( $decode ) $cat = array_map( 'base64_decode', $cat );

			// Skip imported cats
			if( isset($this->_imported['cats']['orig_'.$cat['cat_ID']]) ) continue;

			if( !empty($cat['cat_parent_ID']) && empty($this->_imported['cats']['orig_'.$cat['cat_parent_ID']]) )
			{
				$children[] = $cat;
				continue;
			}

			$parent_ID = NULL;
			if( isset($this->_imported['cats']['orig_'.$cat['cat_parent_ID']]) )
			{
				$parent_ID = $this->_imported['cats']['orig_'.$cat['cat_parent_ID']];
			}

			if( $ID = cat_create( $cat['cat_name'], $parent_ID, $blog_ID, $cat['cat_description'], true ) )
			{
				$this->_imported['cats']['orig_'.$cat['cat_ID']] = $ID;
			}
		}

		// Now import subcats
		if( !empty($children) ) return $this->import_categories( $children, $blog_ID, false );

		return;
	}


	// b2evo export
	// TODO: cats, blogs
	function b2evo_export( $target_ID = 1, $format = 'b2e', $what = 'items' )
	{
		global $DB, $current_User, $app_version;

		if( function_exists('set_time_limit') )
		{
			@set_time_limit( 1800 ); // 30 minutes
		}
		@ini_set( 'max_execution_time', '1800' );
		@ini_set( 'max_input_time', '1800' );
		@ini_set( 'memory_limit', '256M' );

		switch( $what )
		{
			case 'cats':
				// Export categories
				if( $cats = $this->get_blog_cats( $target_ID ) )
				{
					$r['csv_fields'] = array_keys($cats[0]);

					if( $format == 'b2e' )
					{
						foreach( $cats as $cat )
						{
							$r['items'][] = array_map( 'base64_encode', $cat );
						}
					}
					else
					{
						$r['items'] = array_values($cats);
					}
				}
				break;

			case 'items':
				$BlogCache = & $this->getCache('BlogCache');

				load_class( 'items/model/_itemlist.class.php', 'Itemlist' );

				if( (($Blog = & $BlogCache->get_by_ID( $target_ID, false, false )) === false) )
				{
					$this->msg( $this->T_('Blog not found'), 'error' );
					return false;
				}

				$ItemList = new ItemList2( $Blog, NULL, NULL, 999999 );
				$ItemList->set_filters( array(
						'visibility_array' => array( 'published', 'protected', 'private', 'draft', 'deprecated', 'redirected' ),
						'order'			=> 'DESC',
						'unit'			=> 'posts',
						'types'			=> NULL,
					), false ); // Don't memorize settings

				$ItemList->query();
				if( $ItemList->result_num_rows != 0 )
				{
					$r = array();
					while( $Item = & $ItemList->get_item() )
					{
						$Item->tags = implode( ', ', $Item->get_tags() );
						$extra_cat_IDs = implode( ', ', postcats_get_byID($Item->ID) );

						// b2evo 2.x stuff
						$complete_Item = array(
								'title'			=> $Item->title,
								'content'		=> $Item->content,
								'date'			=> $Item->issue_date,
								'status'		=> $Item->status,
								'locale'		=> $Item->locale,
								'urltitle'		=> $Item->urltitle,
								'url'			=> $Item->url,
								'comment_status'=> $Item->comment_status,
								'ptyp_ID'		=> $Item->ptyp_ID,
								'pst_ID'		=> $Item->pst_ID,

								'tags'			=> $Item->tags,
								'priority'		=> $Item->priority,
								'excerpt'		=> $Item->excerpt,
								'views'			=> $Item->views,
								'datedeadline'	=> $Item->datedeadline,

								'main_cat_ID'	=> $Item->main_cat_ID,
								'extra_cat_IDs'	=> $extra_cat_IDs,
							);


						if( version_compare( $app_version, '2.4.9', '>' ) )
						{	// b2evo 3.x stuff
							$complete_Item = $complete_Item + array(
									'featured'		=> $Item->featured,
									'order'			=> $Item->order,
									'titletag'		=> $Item->titletag,
									'double1'		=> $Item->double1,
									'double2'		=> $Item->double2,
									'double3'		=> $Item->double3,
									'double4'		=> $Item->double4,
									'double5'		=> $Item->double5,
									'varchar1'		=> $Item->varchar1,
									'varchar2'		=> $Item->varchar2,
									'varchar3'		=> $Item->varchar3,
								);
						}


						if( version_compare( $app_version, '3.2', '>' ) )
						{	// b2evo 3.3 stuff
							$complete_Item = $complete_Item + array(
									'metadesc'			=> $Item->metadesc,
									'metakeywords'		=> $Item->metakeywords,
									'editor_code'		=> $Item->editor_code,
								);
						}

						if( version_compare( $app_version, '4', '>' ) )
						{	// b2evo 4 stuff
							$complete_Item = $complete_Item + array(
									'excerpt_autogenerated'		=> $Item->excerpt_autogenerated,
									'dateset'					=> $Item->dateset,
									//'canonical_slug_ID'		=> $Item->canonical_slug_ID,
									//'tiny_slug_ID'			=> $Item->tiny_slug_ID,
								);
						}

						switch($format)
						{
							case 'b2e': // Encoded
								// Get item comments (serialized)
								$complete_Item['comments'] = $this->get_item_comments( $Item, $Blog );

								if( version_compare( $app_version, '4', '>' ) )
								{	// Add slugs for b2evo 4
									$complete_Item['slugs'] = $this->get_item_slugs($Item);
								}

								$temp = array();
								foreach( $complete_Item as $k => $v )
								{
									$temp[$k] = base64_encode($v);
								}
								$r['items'][] = $temp;
								break;

							case 'csv':
							case 'xml':
							case 'json':
								$r['items'][] = $complete_Item;
								break;
						}
					}

					$r['csv_fields'] = array_keys($complete_Item);
				}
				break;

			case (strpos( $what, 'T_' ) === 0):
				$SQL = 'SHOW COLUMNS FROM '.$what;
				$r['csv_fields'] = $DB->get_col($SQL, 0);

				$SQL = 'SELECT * FROM '.$what;
				if( $rows = $DB->get_results($SQL, ARRAY_A) )
				{
					if( $format == 'b2e' )
					{
						foreach( $rows as $row )
						{
							$r['items'][] = array_map( 'base64_encode', $row );
						}
					}
					else
					{
						$r['items'] = $rows;
					}
				}
				break;
		}
		if( !empty($r) ) return $r;
	}


	function check_perms()
	{
		global $Settings;

		$ps_url = '?ctrl=plugins&amp;action=edit_settings&amp;plugin_ID='.$this->ID;
		if( ! $Settings->get('sonorth_lic_key') )
		{
			$this->msg( sprintf( $this->T_('Please enter your license key on <a %s>plugin settings</a> page.'), 'href="'.$ps_url.'"'), 'error' );
			$this->hide_tab = 1;
			return false;
		}

		if( !is_logged_in() )
		{	// Not logged in
			$this->msg( $this->T_('You\'re not allowed to view this page!'), 'error' );
			$this->hide_tab = 1;
			return false;
		}

		$this->check_updates();
		if( !empty($this->update_msg) )
		{
			$this->msg($this->update_msg, 'success');
		}

		if( $Settings->get('sonorth_unreg') == get_class($this) )
		{
			$this->msg( str_replace( array('%L', '%N', '%PS'), array($this->help_url.'#get', $this->name, $ps_url), $this->unreg_msg ), 'error' );
			$this->hide_tab = 1;
			return false;
		}

		if( !$this->get_avaliable_blogIDs( true ) ) return false;

		return true;
	}


	function check_dir( $msg = true )
	{
		global $current_User;

		if( !$this->path ) $this->path = dirname(__FILE__).'/export/';
		if( !is_dir($this->path) ) mkdir_r($this->path);

		if( !is_writable($this->path) )
		{
			if( $msg )
			{
				$msg = $this->T_('Unable to create directory for exported files, contact the admin.');
				$msg2 = sprintf( $this->T_('You must create the following directory with write permissions (777):%s'), '<br />'.$this->path);

				if( $current_User->Group->ID == 1 ) $msg = $msg2;
				$this->msg( $msg, 'error' );
			}
			return false;
		}
		// Create index.html file
		$file = $this->path.'index.html';

		if( !file_exists($file) )
		{
			$this->save_to_file( '', $file, 'a' );
			if( !file_exists($file) )
			{
				if( $msg )
				{
					$msg = $this->T_('Cannot create <i>index.html</i> file!');
					$msg2 = sprintf( $this->T_('Make sure the directory [%s] has write permissions (777)'), $this->path );
					if( $current_User->Group->ID == 1 ) $msg .= '<br />'.$msg2;
					$this->msg( $msg, 'error' );
				}
				return false;
			}
		}
		return true;
	}


	// Check if post already exists in DB
	function post_exists( $item = array() )
	{
		global $DB, $current_User;

		if( !is_object($current_User) ) return false;

		// Compare post date and title
		if( !empty($item['date']) && !empty($item['title']) )
		{
			$SQL = 'SELECT post_ID FROM T_items__item
					WHERE post_datestart = '.$DB->quote($item['date']).'
					AND post_title = '.$DB->quote($item['title']).'
					AND post_creator_user_ID = '.$current_User->ID;

			if( $ID = $DB->get_var($SQL) ) return $ID;
		}

		// The date is empty, let's count words and compare content
		if( empty($item['date']) && !empty($item['title']) && (!empty($item['content']) || !empty($item['orig_content'])) )
		{
			if( ! function_exists('bpost_count_words') )
			{	// Load funcs if needed
				load_funcs( 'items/model/_item.funcs.php' );
			}
			$content = (!empty($item['orig_content'])) ? $item['orig_content'] : $item['content'];
			$wordcount = bpost_count_words($content);

			$SQL = 'SELECT post_ID, post_content FROM T_items__item
					WHERE post_wordcount = '.(int)$wordcount.'
					AND post_title = '.$DB->quote($item['title']).'
					AND post_creator_user_ID = '.$current_User->ID;

			if( $rows = $DB->get_results($SQL) )
			{	// Check every post
				foreach( $rows as $row )
				{
					if( $row->post_content == $content ) return $row->post_ID;
				}
			}
		}
		return false;
	}


	// Check if a feed is already exists in DB
	function feed_exists( $url = NULL )
	{
		global $DB, $current_User;

		$SQL = 'SELECT feed_ID FROM '.$this->get_sql_table('feeds').'
				WHERE feed_url = "'.$DB->escape($url).'"
				AND feed_user_ID = '.$current_User->ID;

		if( empty($url) || !$feed_ID = $DB->get_var($SQL) ) return false;
		return $feed_ID;
	}


	/**
	 * Removes border and adds any missing alt tags if in strict mode
	 *
	 * @param string $img
	 * @return string : valid image tag
	 */
	function validate_images( $img )
	{
		// Kill feedburner's crap images
		if( stristr( $img[1], 'feeds.feedburner.com/~r/' ) ||
			stristr( $img[1], 'feedproxy.google.com/~r/' ) ) return '';

		$img = preg_replace(
				array(
					'~\sborder=".+?"~',
					'~\sid=".+?"~',
					),
				array(
					'',
					'',
					), $img[1] );

	  // Add alt
	  if( !preg_match( '~alt=~', $img ) ) $img .= ' alt=""';

	  return '<img'.$img.' />';
	}


	// Read remote or local file
	function get_data( $filename, $msg = false )
	{
		global $basepath, $app_name;

		// Set user agent
		@ini_set( 'user_agent', $this->name.' v'.$this->version.' (+'.$this->help_url.')' );

		// TODO: Transform to real path, then check strpos() === 0
		$base = realpath($basepath);
		$filename = realpath($filename);

		if( ! preg_match('~^(ht|f)tps?://~', $filename) && strpos($filename, $base) !== 0 )
		{	// Make sure we are inside the b2evo directory!!!
			$this->msg( sprintf( $this->T_('You are not allowed to view files outside of %s directory:<br />%s<br />%s'), $app_name, $basepath, $filename ), 'error' );

			return false;
		}

		if( ! $content = @file_get_contents($filename) )
		{
			$content = fetch_remote_page( $filename, $info );
			if($info['status'] != '200') $content = '';
		}
		// Return content
		if( !empty($content) ) return $content;

		if( $msg )
		{
			$this->msg( sprintf( $this->T_('Unable to read data from %s'), $filename ), 'error' );
		}
		return false;
	}


	function replace_tags( $content, $info = array() )
	{
		$search = array(
				'$post_title$',
				'$post_url$',
				'$post_author$',
				'$feed_title$',
				'$feed_url$',
				'$feed_copyright$',
				'$feed_image$',
			);
		$replace = array(
				$info['post_title'],
				$info['post_url'],
				$info['post_author'],
				$info['feed_title'],
				$info['feed_url'],
				$info['feed_copyright'],
				$info['feed_image'],
			);
		return str_replace($search, $replace, $content);
	}


	/**
	 * Save text data to file, create target file if it doesn't exist
	 *
	 * @param string data to be written
	 * @param string filename (full path to a file)
	 * @param string fopen mode
	 */
	function save_to_file( $content, $filename, $mode = 'w', $msg = false )
	{
		$path = $this->path;

		if( ! mkdir_r($path) )
		{
			if( $msg )
			{
				$this->msg( sprintf( $this->T_('You must create the following directory with write permissions (777):%s'), '<br />'.$path ), 'error' );
			}
			return false;
		}

		if( $f = @fopen( $path.$filename, $mode ) )
		{
			@fwrite( $f, $content );
			@fclose($f);
			return true;
		}
		return false;
	}


	function read_csv( $data, $offset = false )
	{
		global $Messages;

		// Normalize line endings
		$data = preg_replace( '/(\r\n)|(\r|\n)/', "\n", $data );

		//$data = $this->change_encoding( $data, 'UTF-8' );
		if( $offset )
		{	// Remove n lines from the top
			$data = implode( "\n", array_slice( explode( "\n", $data ), $offset ) );
		}

		// Make sure the parsecsv class is loaded
		require_once dirname(__FILE__).'/inc/'.$this->parsecsv;

		$CSV = new parseCSV( NULL );
		$CSV->auto($data);

		if( $CSV->error > 0 )
		{	// Errors detected
			foreach( $CSV->error_info as $error )
			{
				$Messages->add( $error['info'], 'error' );
			}
			return false;
		}
		else
		{
			//var_export($CSV->data); die;
			return $CSV->data;
		}
	}


	function create_csv( $params = array(), $delimeter = NULL )
	{
		// Make sure the parsecsv class is loaded
		require_once dirname(__FILE__).'/inc/'.$this->parsecsv;

		if( empty($delimeter) ) $delimeter = $this->delimeter;

		$CSV = new parseCSV();
		if( $content = $CSV->output( NULL, $params['items'], $params['csv_fields'], $delimeter ) )
		{
		//	$content = $this->change_encoding( $content, 'UTF-8' );
			return $content;
		}
		else
		{
			$this->msg( $this->T_('Unable to create CSV file.'), 'error' );
			return false;
		}
	}


	function create_xml( $params = array(), $root = 'items' )
	{
		if( empty($params['items']) ) return false;

		$r = '<?xml version="1.0" encoding="UTF-8"?'.'>';
		$r .= "\n<$root>\n";
		foreach( $params['items'] as $item )
		{
			$r .= "  <node>\n";
			foreach( $item as $key => $value )
			{
				switch($key)
				{
					case ( $value != '' && !preg_match( '~^[\w :.\-_]+$~i', $value ) ):
						$r .= "    <$key><![CDATA[$value]]></$key>\n";
						break;

					default:
						$r .= "    <$key>$value</$key>\n";
				}
			}
			$r .= "  </node>\n";
		}
		$r .= "</$root>";

		return $r;
	}


	function create_json( $params = array(), $root = 'items' )
	{
		if( empty($params['items']) || !function_exists('json_encode') ) return false;

		return json_encode($params['items']);
	}


	function zip( $data, $zipname, $blog_ID, $extension = 'b2e' )
	{
		global $Messages, $inc_path;

		$zipname = $zipname.'.'.$extension;

		if( $this->save_to_file( $data, $zipname ) )
		{
			$this->msg( sprintf( $this->T_('Blog #%d exported'), $blog_ID ), 'success' );
		}

		load_class( '_ext/_zip_archives.php', 'zip_file' );

		$options = array(
			'basedir'	=> $this->path,
			'inmemory'	=> 1,
			'overwrite'	=> 1,
			'recurse'	=> 0,
			'level'		=> 9,
			'type'		=> 'zip',
		);

		$zipfile = new zip_file( $zipname.'.zip' );
		$zipfile->set_options( $options );
		$zipfile->add_files( $zipname );
		$zipfile->create_archive();

		if( $zipfile->error )
		{
			foreach($zipfile->error as $v)
			{
				$Messages->add( $v, 'error' );
			}
			return false;
		}

		if( !headers_sent() )
		{
			$zipfile->download_file();
			exit(0);
		}
		return true;
	}


	function change_encoding( $data, $output, $input = NULL )
	{
		if( empty($input) && function_exists('mb_detect_encoding') )
		{
			$input = @mb_detect_encoding( $data.'L', 'auto' );
		}
		if( strtolower($output) == strtolower($input) ) return $data;

		if( function_exists('mb_convert_encoding') && ($return = @mb_convert_encoding($data, $output, $input)) )
		{
			return $return;
		}
		elseif( function_exists('iconv') && ($return = @iconv($input, $output, $data)) )
		{
			return $return;
		}
		else
		{	// We can't do anything
			return $data;
		}
	}


	/**
	 * Trim array
	 */
	function array_trim( $array )
	{
		return array_map( 'trim', $array );
	}


	/**
	 * Cut text by words
	 *
	 * @param string: text to process
	 * @param integer: the number of characters to cut from the start (if the value is positive)
	 * or from the end (if negative)
	 * @param string: additional string, added before the cropped text
	 * @param string: additional string, added after the cropped text
	 * @param string: words delimeter
	 *
	 * @return processed string
	 */
	function cut_text( $string, $cut = 250, $before_text = '...', $after_text = '...', $delimeter = ' ' )
	{
		$string = trim( preg_replace( '/[\s]+/i', ' ', format_to_output( $string, 'text' ) ) );
		$length = abs($cut);

		if( function_exists('mb_strlen') && function_exists('mb_substr') )
		{
			if( $length < mb_strlen($string) && $cut > 0 )
			{
				while ( $string{$length} != $delimeter && $length > 0 )
				{
					$length--;
				}
				return mb_substr($string, 0, $length).$after_text;
			}
			elseif( $length < mb_strlen($string) && $cut < 0 )
			{
				$string = strrev($string);

				while ( $string{$length} != $delimeter && $length > 0 )
				{
					$length--;
				}
				return strrev( mb_substr($string, 0, $length).$before_text );
			}
			else
			{
				return $string;
			}
		}
		else
		{
			if( $length < strlen($string) && $cut > 0 )
			{
				while ( $string{$length} != $delimeter && $length > 0 )
				{
					$length--;
				}
				return substr($string, 0, $length);
			}
			elseif( $length < strlen($string) && $cut < 0 )
			{
				$string = strrev($string);

				while ( $string{$length} != $delimeter && $length > 0 )
				{
					$length--;
				}
				return strrev( substr($string, 0, $length) );
			}
			else
			{
				return $string;
			}
		}
	}


	function get_Feed()
	{
		global $DB, $current_User;

		if( $feed_ID = param( 'feed_importer_feed_ID', 'integer' ) )
		{
			$SQL = 'SELECT * FROM '.$this->get_sql_table('feeds').'
					WHERE feed_ID = '.$DB->quote( $feed_ID ).'
					AND feed_user_ID = '.$current_User->ID;

			if( $Feed = $DB->get_row($SQL) ) return $Feed;
		}
		return false;
	}


	function get_item_comments( $Item, $Blog )
	{
		global $app_version;

		$comments = array();

		load_class('comments/model/_commentlist.class.php', 'CommentList2');

		if( version_compare( $app_version, '4', '<' ) )
		{	// b2evo < 4
			$CommentList = new CommentList( NULL, "'comment', 'linkback', 'trackback', 'pingback'", array(), $Item->ID, '', 'ASC' );
		}
		else
		{
			$CommentList = new CommentList2( $Blog );

			// Filter list:
			$CommentList->set_default_filters( array(
					'comments' => NULL,
					'post_ID'  => $Item->ID,
				) );

			$CommentList->load_from_Request();
			$CommentList->query();
		}

		while( $Comment = & $CommentList->get_next() )
		{
			if( $Comment->get_author_User() )
			{
				$Comment_author = $Comment->author_User->get_preferred_name();
			}
			else
			{
				$Comment_author = $Comment->author;
			}

			$r = array(
					'type'			=> $Comment->type,
					'status'		=> $Comment->status,
					'author'		=> $Comment_author,
					'author_email'	=> $Comment->get_author_email(),
					'author_url'	=> $Comment->get_author_url(),
					'author_IP'		=> $Comment->author_IP,
					'date'			=> $Comment->date,
					'content'		=> $Comment->content,
					'rating'		=> $Comment->rating,
					'featured'		=> $Comment->featured,
					'nofollow'		=> $Comment->nofollow,
			//	?	'karma'			=> $Comment->karma,
					'spam_karma'	=> $Comment->spam_karma,
					'allow_msgform'	=> $Comment->allow_msgform,
				);

			if( version_compare( $app_version, '4', '>' ) )
			{
				$r['secret'] = $Comment->secret;
			}

			$comments[] = $r;
		}
		return (!empty($comments)) ? serialize($comments) : '';
	}


	function get_item_slugs( $Item )
	{
		$slugs = array();

		$SlugCache = & get_SlugCache();
		$SlugCache->load_all();

		foreach( $SlugCache->cache as $loop_Obj )
		{
			if( $loop_Obj->itm_ID == $Item->ID )
			{
				$slugs[] = $loop_Obj->title;
			}
		}
		return (!empty($slugs)) ? serialize($slugs) : '';
	}


	function get_blog_cats( $blog_ID )
	{
		global $DB;

		return $DB->get_results( 'SELECT * FROM T_categories WHERE cat_blog_ID = '.$DB->quote($blog_ID), ARRAY_A );
	}


	function get_avaliable_blogIDs( $msg = false, $permname = 'blog_post_statuses', $permlevel = 'edit', $add_null_option = false )
	{
		$BlogCache = & $this->getCache('BlogCache');
		$blog_array = $BlogCache->load_user_blogs( $permname, $permlevel, NULL );

		if( empty($blog_array) && $msg )
		{
			$this->msg( $this->T_('Sorry, you have no permission to post yet. The Import feature is unavaliable.'), 'note' );
			$this->hide_tab = 'import';
		}
		else
		{
			if( $add_null_option )
			{
				array_unshift( $blog_array, '= = = = = = = =' );
			}

			return $blog_array;
		}
		return false;
	}


	function disp_categories_fieldset( & $Form )
	{
		$blog_IDs = $this->get_avaliable_blogIDs();
		if( !empty($blog_IDs) && count($blog_IDs) > 0 )
		{
			foreach( $blog_IDs as $l_blog )
			{	// Display fieldset
				$this->disp_blog_cats_fieldset( $Form, $l_blog );
			}
		}
	}


	function disp_blog_cats_fieldset( & $Form, $blog_ID = NULL, $before = '<br />', $after = '<br />' )
	{
		global $app_version;

		if( empty($blog_ID) ) return;

		load_class( 'items/model/_item.class.php', 'Item' );

		$edited_Item = new Item();

		$edited_Item->extra_cat_IDs = array();
		set_param( 'edited_Item', $edited_Item );
		set_param( 'cat_ID', '' );
		set_param( 'post_extracats', array() );

		$BlogCache = & $this->getCache('BlogCache');

		set_working_blog($blog_ID);
		$l_Blog = & $BlogCache->get_by_ID($blog_ID);

		if( version_compare( $app_version, '2.4.9', '<' ) )
		{	// b2evo 2.x
			echo $before;

			$content = cat_select( false );
			// Remove comments
			$content = preg_replace( '~<p[^>]*?>.*?</p>~is', '', $content );
			// Fix extracats
			$content = preg_replace( '~post_extracats~', 'post_extracats_'.$blog_ID, $content );
			$Form->begin_fieldset( sprintf( $this->T_('Categories for blog "%s"'), $l_Blog->name ), array( 'style'=>'margin:0; padding:0; border: 1px solid #9db0bc' ) );
			echo $content;
			$Form->end_fieldset();
		}
		else
		{
			ob_start();
			cat_select( $Form );
			$content = ob_get_clean();

			// Remove comments
			$content = preg_replace( '~<p[^>]*?>.*?</p>~is', '', $content );
			// Remove new category field
			$content = preg_replace( '~<tr[^>]*?>(.*?)id="sel_maincat_new"(.*?)</tr>~i', '', $content );
			// Fix extracats
			$content = preg_replace( '~post_extracats~', 'post_extracats_'.$blog_ID, $content );
			// Fix fieldset titles
			$content = preg_replace( '~<div[\s]+class="fieldset_title_bg"[^>]*?>.*?</div>~is', '<div class="fieldset_title_bg">'.sprintf( $this->T_('Categories for blog "%s"'), $l_Blog->name ).'</div>', $content );

			if( version_compare( $app_version, '2.9', '<' ) )
			{
				$before .= '<div style="margin:0 auto; max-width:700px">';
				$after .= '</div>';
			}


			echo $before;

			if( version_compare( $app_version, '4', '<' ) )
			{	// b2evo 3.x
				echo '
						<style type="text/css">
						#itemform_categories { margin:0 auto; padding:0; max-width:700px }
						table.catselect { border-left: 1px solid #9db0bc; border-right: 1px solid #9db0bc }
						</style>';
			}
			else
			{	// b2evo 4.x
				echo '
						<style type="text/css">
						#itemform_categories { margin:0 auto; padding:0 }
						table.catselect { border: 1px solid #9db0bc }
						.extracats, .fieldset_wrapperextracats { margin:0 auto 20px auto; border-bottom: 1px solid #9db0bc; max-width:700px !important; max-height:400px !important }
						</style>';

			}
			echo $content;
		}

		echo $after;
	}


	function get_address_list( $string )
	{
		$array = array_filter( array_map('trim', explode("\n", $string)));
		$array = array_unique($array);

		if( !empty($array) ) return $array;
		return false;
	}


	function get_range( $str )
	{
		$GLOBALS['fimp_address_range'] = array();
		$this->transform_range($str);
		$range = array_filter( array_unique($GLOBALS['fimp_address_range']) );

		return (empty($range) ? false : $range);
	}


	function transform_range( $str )
	{
		preg_match_all( '~\[range:([0-9a-z]+)\s?-\s?([0-9a-z]+)(?:\s?-\s?([0-9a-z]+))?\]~i', $str, $matches, PREG_SET_ORDER );

		if( !empty($matches[0]) )
		{
			$match = $matches[0];
			$step = isset($match[3]) ? $match[3] : 1;

			if( version_compare(PHP_VERSION, '5', '>=') )
			{	// A "step" param is added in PHP 5
				$range = range( $match[1], $match[2], $step );
			}
			else
			{
				$range = range( $match[1], $match[2] );
			}

			foreach( $range as $num )
			{
				$GLOBALS['fimp_address_range'][] = $this->transform_range( str_ireplace( $match[0], $num, $str ) );
			}
		}

		if( !stristr( $str, '[range:' ) ) return $str;
		return;
	}


	function msg_head( $str, $type = 'notes' )
	{
		$this->msg( '<span style="font-size:18px">'.$str.'</span>', $type );
	}


	function getCache( $objectName )
	{
		global $app_version;

		if( version_compare( $app_version, '4' ) > 0 )
		{	// b2evo 4 and up
			$func_name = 'get_'.$objectName;
			if( function_exists($func_name) )
			{
				return $func_name();
			}
		}
		else
		{
			return get_Cache($objectName);
		}
	}


	function check_updates( $force = false, $lic_key = '' )
	{
		global $Settings, $servertimenow, $app_version, $baseurl, $ReqHost;

		$check_every = 86400 * 3; // check every 3 days

		if( $lic_key == '' )
		{
			$lic_key = $Settings->get('sonorth_lic_key');
		}
		$last_checked = $Settings->get('sonorth_last_checked');
		if( !$force && $last_checked > $servertimenow - $check_every ) return false;

		$Settings->set( 'sonorth_last_checked', $servertimenow );

		$lic = base64_encode(serialize(array('base' => $baseurl, 'host' => $ReqHost)));

		// Construct XML-RPC client:
		load_funcs('xmlrpc/model/_xmlrpc.funcs.php');
		$client = new xmlrpc_client('/xmlrpc.php', 'rpc.sonorth.com', 80);

		$info = new xmlrpcval( array(
					'product'		=> new xmlrpcval( get_class($this) ),
					'version'		=> new xmlrpcval( $this->version ),
					'app_type'		=> new xmlrpcval( 'b2evolution' ),
					'app_version'	=> new xmlrpcval( $app_version ),
					'lic'			=> new xmlrpcval( $lic ),
					'lic_key'		=> new xmlrpcval( $lic_key ),
				), 'struct' );

		$message = new xmlrpcmsg('check_update.plugin', array($info) );

		if( ($result = $client->send($message)) && !$result->faultCode() )
		{
			$value = xmlrpc_decode_recurse($result->value());
			//var_export($value);
			if( is_array($value) && count($value) > 1 )
			{
				if( !empty($value['version']) && version_compare( $value['version'], $this->version ) )
				{	// There's a newer version available
					$this->update_msg = sprintf( '<span style="color:red; font-weight:bold">New version %s is available. %s</span>', $value['version'], get_icon('download').' <a href="'.$value['url'].'" target="_blank">Download now</a>' );
				}
			}
			elseif(	$value == 'Unregistered copy' )
			{
				$Settings->set( 'sonorth_unreg', get_class($this) );
			}
		}
		$Settings->dbupdate();
		return false;
	}
}
?>