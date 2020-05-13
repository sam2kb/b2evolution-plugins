<?php
//This file implements the Download Manager plugin for {@link http://b2evolution.net/}.
//@copyright (c)2009-2012 by Sonorth Corp. - {@link http://www.sonorth.com/}.
// (\t| )*// \s*.+
//\n\n\n
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


// TODO:
// Most Downloaded & Recent Downloads Widget
// Downloads RSS2 Feed
// read uploaded files from directory
// multiple passwords
// max downloads per pass
// max downloads per pass per month

class download_manager_plugin extends Plugin
{
	var $name = 'Download Manager';
	var $code = 'dl_manager';
	var $priority = 10;
	var $version = '1.0.2';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://www.sonorth.com';
	var $help_url = 'http://b2evo.sonorth.com/show.php/download-manager-plugin?page=2';

	var $licensed_to = array(
				'name' => 'User Name',
				'email' => 'user@mail.tld'
			);

	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;

	var $download_class = '_httpdownload.class.inc';	// HTTP download class
	var $parsecsv = '_parsecsv.class.inc';				// ParseCSV class
	var $ext_img_dir = 'img/ext/';						// Path to extension images directory
	var $delimiter = ';';								// Delimiter used in CSV export
	var $download_url_prefix = 'download';				// This prefix is needed for nice permalinks

	var $debug = false; // Set this to true to see debug info

	// Internal
	var $path = '';
	var $file_cats = array();
	var $dl_hit = false;
	var $copyright = 'THIS COMPUTER PROGRAM IS PROTECTED BY COPYRIGHT LAW AND INTERNATIONAL TREATIES. UNAUTHORIZED REPRODUCTION OR DISTRIBUTION OF %N PLUGIN, OR ANY PORTION OF IT THAT IS OWNED BY %C, MAY RESULT IN SEVERE CIVIL AND CRIMINAL PENALTIES, AND WILL BE PROSECUTED TO THE MAXIMUM EXTENT POSSIBLE UNDER THE LAW.<br /><br />THE %N PLUGIN FOR B2EVOLUTION CONTAINED HEREIN IS PROVIDED "AS IS." %C MAKES NO WARRANTIES OF ANY KIND WHATSOEVER WITH RESPECT TO THE %N PLUGIN FOR B2EVOLUTION. ALL EXPRESS OR IMPLIED CONDITIONS, REPRESENTATIONS AND WARRANTIES, INCLUDING ANY WARRANTY OF NON-INFRINGEMENT OR IMPLIED WARRANTY OF MERCHANTABILITY OR FITNESS FOR A PARTICULAR PURPOSE, ARE HEREBY DISCLAIMED AND EXCLUDED TO THE EXTENT ALLOWED BY APPLICABLE LAW.<br /><br />IN NO EVENT WILL %C BE LIABLE FOR ANY LOST REVENUE, PROFIT OR DATA, OR FOR DIRECT, SPECIAL, INDIRECT, CONSEQUENTIAL, INCIDENTAL, OR PUNITIVE DAMAGES HOWEVER CAUSED AND REGARDLESS OF THE THEORY OF LIABILITY ARISING OUT OF THE USE OF OR INABILITY TO USE THE %N PLUGIN FOR B2EVOLUTION, EVEN IF %C HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.';

	var $unreg_msg = 'You are using the unregistered copy of <b>%N</b> plugin.<br />Please <a href="%L" target="_blank">purchase the license</a> or contact us at <em>sales@sonorth.com</em> if you believe you\'re seeing this message in error.<br /><br />Enter your license key on <a href="%PS">plugin settings</a> page.';

	var $update_tpl = '<span style="color:red; font-weight:bold">New version %s is available. %s</span> <a href="%s" target="_blank">Download now</a>';


	// Init
	function PluginInit( & $params )
	{
		// load_class('plugins/model/_plugins_admin.class.php', 'Plugins_admin');
		// $Plugins_admin = new Plugins_admin();
		// if( $methods = $Plugins_admin->get_registered_events( $this ) )
		// {
			// echo "<pre>\n\tfunction ".implode( "(){}\n\tfunction ", $methods )."(){}\n</pre>"; die;
		// }

		$this->short_desc = $this->T_('Upload and manage your files');
		$this->long_desc = $this->T_('This plugin allows you to upload and manage files, track download statistic, and display links in your blog.<br />The plugin does not reveal direct links to your files, which gives you full control over the download process. You can set download speed for users and visitors, temporarily disable download of any file, restrict all downloads to registered users only, and much more.').'<br /><br /><br />'.'This product is licensed to: [<em>'.$this->licensed_to['name'].'</em>]<br /><br /><br />'.str_replace( array('%N', '%C'), array( strtoupper($this->name), strtoupper($this->author) ), $this->copyright );
	}


	function GetDbLayout()
	{
		return array(
			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('files')." (
					file_ID INT(11) NOT NULL auto_increment,
					file_user_ID INT(11) NOT NULL,
					file_cat_ID INT(11) DEFAULT '0' NOT NULL,
					file_name VARCHAR(255) NOT NULL,
					file_type VARCHAR(255) NOT NULL,
					file_size INT(11) default '0',
					file_key VARCHAR(32) NOT NULL,
					file_pass VARCHAR(255) NOT NULL,
					file_title VARCHAR(255) NOT NULL,
					file_description MEDIUMTEXT NOT NULL,
					file_downloads INT(11) NOT NULL,
					file_dl_limit INT(11) NOT NULL,
					file_status INT(1) NOT NULL default 1,
					file_datetime DATETIME NOT NULL default '2000-01-01 00:00:00',
					PRIMARY KEY (file_ID),
					UNIQUE (file_key),
					INDEX (file_user_ID),
					INDEX (file_downloads),
					INDEX (file_status)
				)",

			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('cats')." (
					cat_ID INT(11) NOT NULL auto_increment,
					cat_user_ID INT(11) NOT NULL,
					cat_name VARCHAR(255) NOT NULL,
					cat_description MEDIUMTEXT NOT NULL,
					PRIMARY KEY (cat_ID),
					INDEX (cat_user_ID)
				)",

			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('stats')." (
					stat_ID INT(11) NOT NULL auto_increment,
					stat_file_ID INT(11) NOT NULL,
					stat_reg_user_ID INT(11) NOT NULL,
					stat_remote_addr VARCHAR(40) NOT NULL,
					stat_referer VARCHAR(500) NOT NULL,
					stat_agnt_type VARCHAR(10) DEFAULT 'unknown' NOT NULL,
					stat_user_agnt VARCHAR(500) NOT NULL,
					stat_datetime DATETIME NOT NULL default '2000-01-01 00:00:00',
					PRIMARY KEY (stat_ID),
					INDEX (stat_file_ID)
				)",
		);
	}


	function BeforeInstall()
	{
		global $AdminUI, $Settings, $app_version;

		// Silent install mode, no admin skin available
		if( empty($AdminUI) ) return true;

		$terms_accepted = param($this->code.'_terms_accepted', 'string');
		$lic_key = param($this->code.'_lic_key', 'string');

		if( $terms_accepted )
		{
			if( preg_match( '~^[a-z0-9]{32}$~i', $lic_key ) )
			{
				global $Settings;

				$Settings->set($this->code.'_lic_key', $lic_key);
				$Settings->set($this->code.'_lic_unreg', false );
				$Settings->dbupdate();

				// $this->check_updates( true, $lic_key );

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
		if ( version_compare( $app_version, '4' ) > 0 )
		{	// b2evo 4 and up
			$form_url .= '&'.url_crumb('plugin');
		}

		echo '<div class="panelinfo" style="width:460px; margin:0 auto; padding-top:7px">';
		$Form = new Form( $form_url );
		$Form->begin_form( 'fform' );
		$Form->text_input( $this->code.'_lic_key', $this->get_license_key(), 44, $this->T_('License key') );
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
						WHERE set_name REGEXP "^'.$this->code.'_lic_"
						AND set_name != "'.$this->code.'_lic_last_checked"');

			return true;
		}
		return NULL;
	}


	function BeforeUninstallPayload( & $params )
	{
		global $Settings;

		$params['Form']->hidden( $this->code.'_uninstall', true );
		// echo '<div class="success" style="padding:15px; color:red"><b>Please save your license key:</b> '.$Settings->get($this->code.'_lic_key').'</div>';

		return true;
	}


	function AfterInstall()
	{
		global $DB, $current_User, $UserSettings;

		$cat_name = $this->T_('Dafault category');
		$cat_description = $this->T_('This is the default category for your files. You can rename it or change this description, but you can\'t delete this category.');

		// Create default category
		$SQL = 'INSERT INTO '.$this->get_sql_table('cats').'
					( cat_user_ID, cat_name, cat_description )
				VALUES (
					'.$DB->quote($current_User->ID).',
					"'.$DB->escape( $cat_name ).'",
					"'.$DB->escape( $cat_description ).'"
					)';

		$ID = $DB->query($SQL);

		// Save cad_ID in user settings
		$UserSettings->set( 'dl_manager_default_cat_ID', $ID );
		$UserSettings->dbupdate();
	}


// =========================
// Plugin settings

	function GetDefaultSettings()
	{
		global $Settings, $baseurl;

		$faq_url = 'http://forums.b2evolution.net/viewtopic.php?t=17685';
		$test_url = $baseurl.$this->download_url_prefix.'/XXXXX';

		if( !empty($this->Settings) )
		{
			$test_url = $baseurl.$this->Settings->get('dl_url_prefix').'/XXXXX';
		}

		return array(
				'lic_key' => array(
					'label' => $this->T_('License key'),
					'defaultvalue' => $this->get_license_key(),
					'type' => 'text',
					'size' => 45,
					'disabled' => true,
					'note' => $this->T_('Enter your license key here'),
				),
				'delimiter' => array(
					'label' => $this->T_('CSV delimiter'),
					'defaultvalue' => ';',
					'type' => 'text',
					'size' => 5,
					'note' => $this->T_('Delimiter used in CSV export'),
				),
				'dl_url_prefix' => array(
					'label' => $this->T_('URL prefix'),
					'defaultvalue' => 'download',
					'type' => 'text',
					'size' => 15,
					'note' => $this->T_('This prefix is needed for nice permalinks'),
				),
				'nice_permalinks' => array(
					'label' => $this->T_('Nice permalinks'),
					'type' => 'checkbox',
					'defaultvalue' => 0,
					'note' => sprintf( $this->T_('Check this if you want to use nice download links e.g. %s, where XXXXX is file key.'), '<b><a href="'.$test_url.'" target="_blank">'.$test_url.'</a></b>' ).'<br /><br />'.
					sprintf( $this->T_('Make sure you get the %s error message when you click on the test download link above. If you don\'t see it, please <a %s>read this FAQ</a>.'), '<b>"Invalid file key"</b>', 'href="'.$faq_url.'" target="_blank"' ),
				),
			);
	}


	function GetDefaultUserSettings()
	{
		global $UserSettings;

		return array(
				'notify_user' => array(
					'label' => $this->T_('Enable notifications'),
					'type' => 'checkbox',
					'defaultvalue' => 1,
					'note' => $this->T_('Check this if you want to receive emails regarding broken links, missing files etc.<br />Messages generated automatically, your email won\'t be displayed.'),
				),
				'key_length' => array(
					'label' => $this->T_('File key length'),
					'type' => 'integer',
					'defaultvalue' => 10,
					'size' => 2,
					'valid_range' => array( 'min'=>5, 'max'=>32 ),
					'note' => $this->T_('5-32 characters.'),
				),
				'dl_disabled_v' => array(
					'label' => $this->T_('Members only'),
					'type' => 'checkbox',
					'defaultvalue' => 0,
					'note' => $this->T_('Check this if you want to disable downloads for visitors. If chosen, only logged in users will be able to download files.'),
				),
				'dl_user_level' => array(
					'label' => $this->T_('User level'),
					'type' => 'integer',
					'defaultvalue' => 1,
					'size' => 2,
					'valid_range' => array( 'min'=>0, 'max'=>10 ),
					'note' => $this->T_('Minimum user level required to download files. "Members only" option must be checked!'),
				),
				'dl_speed_u' => array(
					'label' => $this->T_('Speed limit (Users)'),
					'type' => 'integer',
					'defaultvalue' => 0,
					'size' => 3,
					'note' => $this->T_('KB').'. '.$this->T_('Maximum download speed for registered users.').' '.$this->T_('Set zero (0) to not limit the speed.'),
				),
				'dl_speed_v' => array(
					'label' => $this->T_('Speed limit (Visitors)'),
					'type' => 'integer',
					'defaultvalue' => 500,
					'size' => 3,
					'note' => $this->T_('KB').'. '.$this->T_('Maximum download speed for unregistered users.').' '.$this->T_('Set zero (0) to not limit the speed.'),
				),
			);
	}


	function PluginUserSettingsEditDisplayAfter( & $params )
	{
		$params['Form']->radio_input( 'auto_prune_stats_mode', $this->UserSettings->get('auto_prune_stats_mode'), array(
				array(
					'value'=>'off',
					'label'=>$this->T_('Off'),
					'note'=>$this->T_('Not recommended! Your database will grow very large!'),
					'suffix' => '<br />',
					'onclick'=>'jQuery("#auto_prune_stats_container").hide();' ),
				array(
					'value'=>'page',
					'label'=>$this->T_('With every download'),
					'note'=>$this->T_('This is guaranteed to work but uses extra resources with every downloaded file.'), 'suffix' => '<br />',
					'onclick'=>'jQuery("#auto_prune_stats_container").show();' ),
				array(
					'value'=>'cron',
					'label'=>$this->T_('With a scheduled job'),
					'note'=>$this->T_('Recommended if you have your scheduled jobs properly set up.'), 'onclick'=>'jQuery("#auto_prune_stats_container").show();' ) ),
			$this->T_('Auto pruning hits') );

		echo '<div id="auto_prune_stats_container">';
		$params['Form']->text_input( 'auto_prune_stats', $this->UserSettings->get('auto_prune_stats'), 5, $this->T_('Prune after'), $this->T_('days. How many days of hits do you want to keep in the database for stats?') );
		echo '</div>';

		if( $this->UserSettings->get('auto_prune_stats_mode') == 'off' )
		{ // hide the "days" input field, if mode set to off:
			echo '<script type="text/javascript">jQuery("#auto_prune_stats_container").hide();</script>';
		}
	}


	function PluginSettingsUpdateAction()
	{
		if( $lic_key = param('edit_plugin_'.$this->ID.'_set_lic_key', 'string') )
		{
			global $Settings;

			$Settings->set($this->code.'_lic_key', $lic_key);
			$Settings->set($this->code.'_lic_unreg', false );
			$Settings->dbupdate();

			$this->check_updates( true, $lic_key );
		}
	}


	function PluginUserSettingsUpdateAction()
	{
		global $Settings, $UserSettings;

		if( !param_integer_range( 'edit_plugin_'.$this->ID.'_set_dl_user_level', 0, 10, $this->T_('User level must be between 0 and 10.') ) )
		{
			return false;
		}

		// Get our custom fields
		$this->UserSettings->set( 'auto_prune_stats_mode', param('auto_prune_stats_mode', 'string') );
		$this->UserSettings->set( 'auto_prune_stats', param('auto_prune_stats', 'string') );

		// Create uploads directory if not exists (silent)
		$this->check_dir(false);
	}


// =========================
// Handle short download urls

	function BeforeBlogDisplay()
	{
		global $debug, $ReqHost, $ReqURI, $baseurl;

		if( $this->debug )
		{
			$debug = 1;
		}

		// Get file key
		if( $key = param( 'dl_file', 'string' ) )
		{
			if( !preg_match( '~^[A-Za-z0-9]{5,32}$~', $key ) ) return;
		}
		elseif( preg_match( '~^'.$this->Settings->get('dl_url_prefix').'/([A-Za-z0-9]{5,32})$~',
					str_replace( $baseurl, '', $ReqHost.$ReqURI ),$matches ) )
		{	// Got file key
			$key = $matches[1];
		}

		// Not a download, we return
		if( empty($key) ) return;

		$params['file'] = $key;

		global $current_User, $current_charset, $current_locale, $locale_from_get, $default_locale;

		if( is_logged_in() && $current_User->get('locale') != $current_locale && !$locale_from_get )
		{
			locale_activate( $current_User->get('locale') );
			if( $current_locale == $current_User->get('locale') )
			{
				$default_locale = $current_locale;
			}
		}

		// Init charset handling:
		init_charsets( $current_charset );

		if( $file_pass = param( 'file_pass', 'html' ) )
		{	// File pass
			$params['file_pass'] = $file_pass;
		}

		if( param( 'skip', 'string' ) )
		{	// Direct download
			return $this->htsrv_dl( $params );
		}
		else
		{
			return $this->htsrv_download( $params );
		}
	}


// =========================
// Admin functions

	function GetExtraEvents()
	{
		return array(
				'dl_all'	=> 'Display download links for all files.',
				'dl_cat'	=> 'Display download links for all files in selected category.',
				'dl'		=> 'Display download link for requested file(s).',
			);
	}


	function GetHtsrvMethods()
	{
		return array( 'download', 'dl', 'browse_downloads' );
	}


	function AdminAfterMenuInit()
	{
		$this->register_menu_entry( $this->name );
	}


	function GetCronJobs( & $params )
	{
		return array(
			array(
				'name' => $this->T_('Prune old hits').' ('.$this->name.')',
				'ctrl' => 'prune_old_hits',
			),
		);
	}


	function ExecCronJob( & $params, $force = false )
	{	// We provide only one cron job, so no need to check $params['ctrl'] here.
		global $UserSettings, $current_User, $DB, $localtimenow;

		if( !$force && $this->UserSettings->get('auto_prune_stats_mode') != 'cron' )
		{	// Cron job is disabled
			$message = $this->T_('Scheduled auto pruning is disabled in plugin settings.');
			return array( 'code' => 0, 'message' => $message );
		}

		$last_prune = $UserSettings->get( $this->code.'_auto_prune' );
		if( $last_prune >= date('Y-m-d', $localtimenow) )
		{	// Already pruned today
			$message = $this->T_('Pruning has already been done today');
			return array( 'code' => 0, 'message' => $message );
		}
		$time_prune_before = ($localtimenow - ($this->UserSettings->get('auto_prune_stats') * 86400)); // 1 day

		$SQL = 'DELETE '.$this->get_sql_table('stats').'
				FROM '.$this->get_sql_table('stats').'
				LEFT JOIN '.$this->get_sql_table('files').' ON stat_file_ID = file_ID
				WHERE file_user_ID = '.$current_User->ID.'
				AND stat_datetime < "'.date('Y-m-d', $time_prune_before).'"';

		$pruned = $DB->query( $SQL, 'Autopruning downloads hit log' );
		if( $pruned > 0 )
		{
			$code = 1;
			$message = sprintf( $this->T_('Successfully pruned %d records!'), $pruned );
		}
		else
		{
			$code = -1;
			$message = $this->T_('Nothing to prune.');
		}
		$UserSettings->set( $this->code.'_auto_prune', date('Y-m-d H:i:s', $localtimenow) );
		$UserSettings->dbupdate();

		return array( 'code' => $code, 'message' => $message );
	}


	function AdminTabPayload()
	{
		if( $this->hide_tab == 1 ) return;

		global $DB, $current_User;

		$File = $this->get_File();				// Get the file if requested
		$Category = $this->get_Category();		// Get the category if requested
		$this->get_cats();						// Get file categories

		$action = param( 'action', 'string' );

		$Form = new Form( 'admin.php', '', 'post', 'none', 'multipart/form-data' );
		$Form->begin_form( 'fform', $this->T_('Download Manager') );
		$Form->hidden_ctrl();
		$Form->hiddens_by_key( get_memorized() );

		if( empty($File) && empty($Category) && $action == 'view_cats' )
		{	// Categories list mode
			$Form->begin_fieldset( $this->T_('Add new category') );
			$Form->hidden( 'action', 'create_cat' );

			$Form->text_input( $this->get_class_id().'_cat_name', '', 50, $this->T_('Name'), '', array( 'maxlength'=>255 ) );
			$Form->textarea( $this->get_class_id().'_cat_description', '', 4, $this->T_('Description'), '', 60 );

			$Form->end_fieldset();
			$Form->end_form( array( array( 'submit', 'submit', $this->T_('Create category'), 'SaveButton' ) ) );


			$SQL = 'SELECT * FROM '.$this->get_sql_table('cats').'
					WHERE cat_user_ID = '.$DB->quote($current_User->ID);

			$Results = new Results( $SQL, 'dlcats_', '--A' );
			$Results->title = $this->T_('Categories list');

			$Results->cols[] = array(
					'th' => $this->T_('ID'),
					'td' => '$cat_ID$',
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
				);

			function dl_td_files( $Category )
			{
				global $DB, $Plugins, $current_User;
				$DLM = $Plugins->get_by_code('dl_manager');

				$SQL = 'SELECT COUNT(file_ID) FROM '.$DLM->get_sql_table('files').'
						WHERE file_cat_ID = '.$DB->quote($Category->cat_ID).'
						AND file_user_ID = '.$DB->quote($current_User->ID);

				$total = $DB->get_var($SQL);
				return $total;
			}
			$Results->cols[] = array(
					'th' => $this->T_('Files'),
					'td' => '% dl_td_files( {row} ) %',
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
				);

			$Results->cols[] = array(
					'th' => $this->T_('Name'),
					'td' => '$cat_name$',
				);

			function dl_td_description( $cat_description )
			{
				return evo_substr( $cat_description, 0, 120 );
			}
			$Results->cols[] = array(
					'th' => $this->T_('Description'),
					'td' => '% dl_td_description( #cat_description# ) %',
				);

			function dl_td_actions( $cat_ID )
			{
				global $Plugins;
				$DLM = $Plugins->get_by_code('dl_manager');

				$r = '';
				// Edit
				$r .= action_icon( $DLM->T_('Edit'), 'edit', regenerate_url( 'action', 'dl_manager_cat_ID='.$cat_ID.'&amp;action=edit_cat' ), '', 5 );
				// Delete
				$r .= action_icon( $DLM->T_('Delete'), 'delete', regenerate_url( 'action', 'dl_manager_cat_ID='.$cat_ID.'&amp;action=delete_cat' ), '', 5, 1, array( 'onclick' => 'return confirm(\''.$DLM->T_('Do you really want to delete this category?').'\')' ) );
				return $r;
			}
			$Results->cols[] = array(
					'th' => $this->T_('Actions'),
					'td' => '% dl_td_actions( #cat_ID# ) %',
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
				);

			$highlight_fadeout = array();
			if( $updated_ID = param( 'dl_manager_updated_cat_ID', 'integer' ) )
			{	// If there happened something with a file_ID, apply fadeout to the row
				$highlight_fadeout = array( 'cat_ID' => array($updated_ID) );
			}

			$Results->display( NULL, $highlight_fadeout );
		}
		elseif( empty($File) && !empty($Category) && $action == 'edit_cat' )
		{	// Edit Category mode
			$Form->begin_fieldset( $this->T_('Editing category:').' &laquo;'.$Category->cat_name.'&raquo;' );
			$Form->hidden( 'dl_manager_cat_ID', $Category->cat_ID );
			$Form->hidden( $this->get_class_id().'_update', '1' );

			$Form->text_input( $this->get_class_id().'_cat_name', $Category->cat_name, 50, $this->T_('Name'), '', array( 'maxlength'=>255 ) );
			$Form->textarea( $this->get_class_id().'_cat_description', $Category->cat_description, 4, $this->T_('Description'), '', 60 );

			$Form->end_fieldset();
			$Form->end_form( array( array( 'submit', 'submit', $this->T_('Update category'), 'SaveButton' ) ) );
		}
		elseif( !empty($File) && $action == 'info' )
		{	// Download stats mode
			param( 'dl_manager_file_ID', 'integer', '', true );
			param( 'action', 'string', '', true );

			echo '<div class="right_icons">';
			echo action_icon( $File->file_name.'.CSV', 'download', regenerate_url( 'action', 'action=export_stats' ), ' '.$this->T_('Download as .CSV'), 5, 5 );
			echo '</div>';

			$SQL = 'SELECT * FROM '.$this->get_sql_table('stats').'
					WHERE stat_file_ID = '.$File->file_ID;

			$Results = new Results( $SQL, 'dl_', 'D' );
			$Results->title = sprintf( $this->T_('Downloads statistic for %s'), '&laquo;'.$File->file_name.'&raquo;' );

			// datetime:
			$Results->cols[] = array(
					'th' => $this->T_('Date Time'),
					'td' => '%mysql2localedatetime_spans( #stat_datetime# )%',
					'th_class' => 'shrinkwrap',
					'td_class' => 'timestamp',
					'order' => 'stat_datetime',
				);
			// Remote address (IP):
			$Results->cols[] = array(
					'th' => $this->T_('Remote IP'),
					'td' => '$stat_remote_addr$',
					'th_class' => 'shrinkwrap',
					'order' => 'stat_remote_addr',
				);

			function dl_results_td_referer( $hit_referer )
			{
				if( empty($hit_referer) )
				{
					return 'direct';
				}
				else
				{
					global $Plugins;
					$DLM = $Plugins->get_by_code('dl_manager');
					$ref = parse_url($hit_referer);

					return '<a href="'.$hit_referer.'" target="_blank" title="'.$DLM->T_('Open in new window').'">'.$ref['host'].'</a>';
				}

			}
			// Referer:
			$Results->cols[] = array(
					'th' => $this->T_('Referer'),
					'td' => '% dl_results_td_referer( #stat_referer# ) %',
					'th_class' => 'shrinkwrap',
					'order' => 'stat_referer',
				);
			// Agent type:
			$Results->cols[] = array(
					'th' => $this->T_('Agent type'),
					'td' => '$stat_agnt_type$',
					'th_class' => 'shrinkwrap',
					'td_class' => 'center',
					'order' => 'stat_agnt_type',
				);

			if( $current_User->check_perm( 'users', 'view' ) )
			{	// User has permissions to view user profiles
				function dl_results_td_reg_user_ID( $user_ID )
				{
					global $Plugins, $admin_url;

					$DLM = $Plugins->get_by_code('dl_manager');
					$UserCache = & get_Cache( 'UserCache' );
					// We could not find the User
					if( empty($user_ID) || ($User = $UserCache->get_by_ID( $user_ID, false )) === false ) return '-';
					$url = $admin_url.'?ctrl=users&amp;user_ID='.$User->ID;
					return '<a href="'.$url.'" title="'.$DLM->T_('View user profile').'" target="_blank">'.$user_ID.'</a>';
				}
				// Agent signature
				$Results->cols[] = array(
							'th' => $this->T_('User ID'),
							'td' => '% dl_results_td_reg_user_ID( #stat_reg_user_ID# ) %',
							'th_class' => 'shrinkwrap',
							'td_class' => 'shrinkwrap',
							'order' => 'stat_reg_user_ID',
						);
			}

			function dl_results_td_agnt_sig( $agnt_signature )
			{
				return '<span title="'.$agnt_signature.'">'.substr($agnt_signature, 0, 70).'...</span>';
			}
			// Agent signature
			$Results->cols[] = array(
						'th' => $this->T_('Agent signature'),
						'td' => '% dl_results_td_agnt_sig( #stat_user_agnt# ) %',
						'order' => 'stat_user_agnt',
					);
			$Results->display();
		}
		elseif( !empty($File) && $action == 'edit' )
		{	// File edit mode
			$Form->begin_fieldset( $this->T_('Editing file:').' &laquo;'.$File->file_name.'&raquo;' );
			$Form->hidden( 'dl_manager_file_ID', $File->file_ID );
			$Form->hidden( $this->get_class_id().'_update', '1' );
			$Form->hidden( $this->get_class_id().'_file_key', $File->file_key );
			$Form->hidden( $this->get_class_id().'_file_downloads', $File->file_downloads );

			//$Form->text_input( $this->get_class_id().'_file_downloads', $File->file_downloads, 11, $this->T_('Downloads'), '', array( 'disabled'=>true ) );
			echo '<fieldset><div class="label"><label>'.$this->T_('Hits').':</label></div>';
			echo '<div class="input" style="padding-top:3px">'.$File->file_downloads.'</div></fieldset>';

			$opt = array();
			foreach( $this->file_cats as $Category )
			{
				$sel = '';
				if( $File->file_cat_ID == $Category->cat_ID ) $sel = ' selected="selected"';
				$opt[] = '<option value="'.$Category->cat_ID.'"'.$sel.'>'.$Category->cat_name.'</option>';
			}
			$cat_options = implode( "\n", $opt );
			$Form->select_input_options( $this->get_class_id().'_file_cat_ID', $cat_options, $this->T_('Category'), $this->T_('Select a category for this file.'), array( 'maxlength'=>255 ) );
			$Form->text_input( $this->get_class_id().'_file_name', $File->file_name, 50, $this->T_('Filename'), '', array( 'maxlength'=>255 ) );
			$Form->text_input( $this->get_class_id().'_file_title', $File->file_title, 50, $this->T_('Title'), $this->T_('Used in download links.'), array( 'maxlength'=>255 ) );
			$Form->textarea( $this->get_class_id().'_file_description', $File->file_description, 4, $this->T_('Description'), $this->T_('Used in download links.'), 60 );
			$Form->text_input( $this->get_class_id().'_file_pass', $File->file_pass, 50, $this->T_('Password'), $this->T_('Type a file password (5-255 characters).'), array( 'maxlength'=>255 ) );
			$Form->text_input( $this->get_class_id().'_file_dl_limit', $File->file_dl_limit, 11, $this->T_('Download limit'), $this->T_('Disable file downloads if number of hits exceeds this number.') );

			echo '<fieldset><div class="label"><label>'.$this->T_('Change file').':</label></div>';
			echo '<div class="input"><input name="uploadfile" size="40" type="file" /></div></fieldset>';

			$Form->end_fieldset();
			$Form->end_form( array( array( 'submit', 'submit', $this->T_('Update file'), 'SaveButton' ) ) );
		}
		else
		{	// File list mode
			$Form->begin_fieldset( $this->T_('Add new file') );
			$Form->hidden( 'action', 'create_file' );

			$opt = array();
			foreach( $this->file_cats as $Category )
			{
				$opt[] = '<option value="'.$Category->cat_ID.'">'.$Category->cat_name.'</option>';
			}
			$cat_options = implode( "\n", $opt );
			$Form->select_input_options( $this->get_class_id().'_file_cat_ID', $cat_options, $this->T_('Category'), sprintf( $this->T_('Select a category for this file. <a %s>Manage categories &raquo;</a>'), 'href="'.regenerate_url( 'action', 'action=view_cats' ).'"' ), array( 'maxlength'=>255 ) );
			$Form->text_input( $this->get_class_id().'_file_title', '', 50, $this->T_('Title'), $this->T_('Used in download links.'), array( 'maxlength'=>255 ) );
			$Form->textarea( $this->get_class_id().'_file_description', '', 2, $this->T_('Description'), '', 60 );

			echo '<fieldset><div class="label"><label>'.$this->T_('Upload file').':</label></div>';
			echo '<div class="input">
				  <input name="uploadfile" size="40" type="file" />
				  <input type="submit" name="attach_file" value="'.format_to_output( $this->T_('Upload !'), 'formvalue' ).'" class="ActionButton" >
				  </div></fieldset>';
			$Form->end_fieldset();
			$Form->end_form();

			load_funcs('files/model/_file.funcs.php');

			// Query filter keywords:
			$keywords = param( 'keywords', 'string', '', true );
			$filter = param( 'filter', 'string', '', true );

			$where_clause = '';

			switch( $filter )
			{
				case 'enabled':
					$where_clause .= ' AND file_status = 1';
					break;

				case 'disabled':
					$where_clause .= ' AND file_status <> 1';
					break;

				case 'protected':
					$where_clause .= ' AND file_pass <> ""';
					break;

				case 'public':
					$where_clause .= ' AND file_pass = ""';
					break;
			}

			if( !empty( $keywords ) )
			{
				$kw_array = preg_split( '~ ~', $keywords );
				foreach( $kw_array as $kw )
				{
					$where_clause .= ' AND (file_title LIKE "%'.$DB->escape($kw).'%" OR file_name LIKE "%'.$DB->escape($kw).'%")';
				}
			}

			// Get total
			$Total = $DB->get_row( 'SELECT SUM(file_size) as size, SUM(file_downloads) as downloads
								    FROM '.$this->get_sql_table('files').'
									INNER JOIN '.$this->get_sql_table('cats').'
										ON file_cat_ID = cat_ID
								    WHERE file_user_ID = '.$current_User->ID.'
								    '.$where_clause );

			$SQL = 'SELECT * FROM '.$this->get_sql_table('files').'
					INNER JOIN '.$this->get_sql_table('cats').'
						ON file_cat_ID = cat_ID
					WHERE file_user_ID = '.$current_User->ID.'
					'.$where_clause.'
					ORDER BY cat_name ASC';

			$Results = new Results( $SQL, 'dl_', '-----D' );
			$Results->title = $this->T_('Files list');
			$Results->group_by = 'cat_ID';

			function filter_files( & $Form )
			{
				global $Plugins;
				$DLM = $Plugins->get_by_code('dl_manager');

				$Form->text( 'keywords', get_param('keywords'), 20, $DLM->T_('Keywords'), $DLM->T_('Separate with space'), 50 );
			}
			$Results->filter_area = array(
				'callback' => 'filter_files',
				'url_ignore' => 'results_antispam_page,keywords',
				'presets' => array(
					'all' => array($this->T_('All files'), '?ctrl=tools&amp;tab=plug_ID_'.$this->ID ),
					'enabled' => array($this->T_('Enabled'), '?ctrl=tools&amp;tab=plug_ID_'.$this->ID.'&amp;filter=enabled' ),
					'disabled' => array($this->T_('Disabled'), '?ctrl=tools&amp;tab=plug_ID_'.$this->ID.'&amp;filter=disabled' ),
					'protected' => array($this->T_('Protected'), '?ctrl=tools&amp;tab=plug_ID_'.$this->ID.'&amp;filter=protected' ),
					'public' => array($this->T_('Public'), '?ctrl=tools&amp;tab=plug_ID_'.$this->ID.'&amp;filter=public' ),
					)
				);

			function dl_results_td_cat( $Category )
			{
				global $Plugins;
				$DLM = $Plugins->get_by_code('dl_manager');

				$r = action_icon( $DLM->T_('Edit category'), 'edit', regenerate_url( 'action', 'dl_manager_cat_ID='.$Category->cat_ID.'&amp;action=edit_cat' ), '', 5 );

				return $r.' <span style="color:#000">'.$Category->cat_name.'</span>';
			}
			$Results->grp_cols[] = array(
					'td_colspan' => 7,  // nb_cols
					'td' => '% dl_results_td_cat( {row} ) %',
				);

			function dl_results_td_status( $file_status )
			{
				global $Plugins;
				$DLM = $Plugins->get_by_code('dl_manager');

				if( empty($file_status) )
				{
					return get_icon('disabled', 'imgtag', array('title'=> $DLM->T_('The file download is disabled.')) );
				}
				else
				{
					return get_icon('enabled', 'imgtag', array('title'=> $DLM->T_('The file download is enabled.')) );
				}
			}
			$Results->cols[] = array(
					'th' => $this->T_('En'),
					'td' => '% dl_results_td_status( #file_status# ) %',
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
					'total' => '',
				);

			function dl_results_td_icon( $File )
			{
				global $Plugins;
				$DLM = $Plugins->get_by_code('dl_manager');

				return $DLM->get_extension_icon( $File->file_name, $File->file_type );
			}
			$Results->cols[] = array(
					'th' => $this->T_('Icon'),
					'td' => '% dl_results_td_icon( {row} ) %',
					'th_class' => 'shrinkwrap',
					'td_class' => 'center',
					//'order' => 'file_key',
					'total' => '',
				);

			function dl_results_td_key( $file_key, $file_pass )
			{
				if( empty($file_pass) )
				{
					return $file_key;
				}
				else
				{
					global $Plugins;
					$DLM = $Plugins->get_by_code('dl_manager');

					return '<span style="color:#0C3;" title="'.sprintf( $DLM->T_('Password: %s'), format_to_output( $file_pass, 'htmlattr' ) ).'">'.$file_key.'</span>';
				}
			}
			$Results->cols[] = array(
					'th' => $this->T_('Key'),
					'td' => '% dl_results_td_key( #file_key#, #file_pass# ) %',
					'th_class' => 'shrinkwrap',
					'order' => 'file_key',
					'total' => '',
				);

			function dl_results_td_name( $File )
			{
				$file_title = $File->file_title;

				if( evo_strlen($file_title) > 55 )
				{
					$file_title = @evo_substr( $file_title, 0, 55).'&hellip;';
				}

				if( empty($file_title) )
				{
					$r = $File->file_name;
				}
				else
				{
					$r = '<div><b style="color:#333">'.$file_title.'</b><br /><span class="note">&rsaquo;&rsaquo; <i>'.$File->file_name.'</i></span></div>';
				}

				return $r;
			}
			$Results->cols[] = array(
					'th' => $this->T_('File'),
					'td' => '% dl_results_td_name( {row} ) %',
					'order' => 'file_name',
				);

			function dl_results_td_size( $file_size )
			{
				return '<span title="'.$file_size.' bytes">'.bytesreadable( $file_size ).'</span>';
			}
			$Results->cols[] = array(
					'th' => $this->T_('Size'),
					'td' => '% dl_results_td_size( #file_size# ) %',
					'th_class' => 'shrinkwrap',
					'td_class' => 'nowrap',
					'order' => 'file_size',
					'total' => '% dl_results_td_size( '.$Total->size.' ) %',
					'total_class' => 'shrinkwrap',
				);
			$Results->cols[] = array(
					'th' => $this->T_('Hits'),
					'td' => '$file_downloads$',
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
					'order' => 'file_downloads',
					'total' => $Total->downloads,
					'total_class' => 'shrinkwrap',
				);

			function dl_td_actions( $file_ID, $file_status, $file_key )
			{
				global $Plugins;
				$DLM = $Plugins->get_by_code('dl_manager');

				$r = '';
				if( empty($file_status) )
				{	// Enable file
					$r .= action_icon( $DLM->T_('Enable file downloads'), 'activate', regenerate_url( '', 'dl_manager_file_ID='.$file_ID.'&amp;action=enable' ), '', 5 );
				}
				else
				{	// Disable file
					$r .= action_icon( $DLM->T_('Disable file downloads'), 'deactivate', regenerate_url( '', 'dl_manager_file_ID='.$file_ID.'&amp;action=disable' ), '', 5 );
				}
				// Download
				$r .= action_icon( $DLM->T_('Download'), 'download', $DLM->get_dl_url($file_key), '', 5 );
				// Stats
				$r .= action_icon( $DLM->T_('View download stats'), 'info', regenerate_url( '', 'dl_manager_file_ID='.$file_ID.'&amp;action=info' ), '', 5 );
				// Prune hits
				$r .= action_icon( $DLM->T_('Prune all download hits'), 'unlink', regenerate_url( '', 'dl_manager_file_ID='.$file_ID.'&amp;action=prune_hits' ), '', 5, 1, array( 'onclick' => 'return confirm(\''.$DLM->T_('Do you really want to prune all download hits for this file?').'\')' ) );
				// Edit
				$r .= action_icon( $DLM->T_('Edit'), 'edit', regenerate_url( '', 'dl_manager_file_ID='.$file_ID.'&amp;action=edit' ), '', 5 );
				// Delete
				$r .= action_icon( $DLM->T_('Delete'), 'delete', regenerate_url( '', 'dl_manager_file_ID='.$file_ID.'&amp;action=delete' ), '', 5, 1, array( 'onclick' => 'return confirm(\''.$DLM->T_('Do you really want to delete this file?').'\')' ) );
				return $r;
			}
			function dl_td_global_actions()
			{
				global $Plugins;
				$DLM = $Plugins->get_by_code('dl_manager');

				// Enable all
				$r = action_icon( $DLM->T_('Enable all files'), 'activate', regenerate_url( '', 'action=enable_all' ), '', 5, 1, array( 'onclick' => 'return confirm(\''.$DLM->T_('Do you really want to enable all downloads?').'\')' ) );
				// Disable all
				$r .= action_icon( $DLM->T_('Disable all files'), 'deactivate', regenerate_url( '', '&amp;action=disable_all' ), '', 5, 1, array( 'onclick' => 'return confirm(\''.$DLM->T_('Do you really want to disable all downloads?').'\')' ) );
				// Prune all hits
				$r .= action_icon( $DLM->T_('Prune all download hits'), 'unlink', regenerate_url( '', 'action=prune_all_hits' ), '', 5, 1, array( 'onclick' => 'return confirm(\''.$DLM->T_('Do you really want to prune all download hits?').'\')' ) );
				// Delete all
				$r .= action_icon( $DLM->T_('Delete all files'), 'delete', regenerate_url( '', 'action=delete_all' ), '', 5, 1, array( 'onclick' => 'return confirm(\''.$DLM->T_('Do you really want to delete all files?').'\')' ) );
				return $r;
			}
			$Results->cols[] = array(
					'th' => $this->T_('Actions'),
					'td' => '% dl_td_actions( #file_ID#, #file_status#, #file_key# ) %',
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
					'total' => '% dl_td_global_actions() %',
				);

			$highlight_fadeout = array();
			if( $updated_ID = param( 'dl_manager_updated_file_ID', 'integer' ) )
			{	// If there happened something with a file_ID, apply fadeout to the row
				$highlight_fadeout = array( 'file_ID' => array($updated_ID) );
			}

			$Results->display( NULL, $highlight_fadeout );
		}
	}



	function AdminTabAction()
	{
		if( !$this->check_perms() ) $this->hide_tab = 1;
		if( !$this->check_dir() )  $this->hide_tab = 1;
		if( $this->hide_tab == 1 ) return;

		global $DB, $current_User, $localtimenow;

		$action = param( 'action', 'string', 'list' );

		if( $Category = $this->get_Category() )
		{	// We have perms to manage the requested category
			switch($action)
			{
				case 'delete_cat':
					if( param( 'confirm', 'integer', 0 ) )
					{	// Confirmed, Delete and move files
						$this->delete_category( $Category, true );
					}
					else
					{	// Trying to delete category
						$this->delete_category( $Category );
					}

					set_param('dl_manager_cat_ID', 0 ); // Return to the right view
					set_param('action', 'view_cats' ); // Return to the right view
					break;

				case 'edit_cat':
					// Do nothing...
					break;

				default:
					if( (param( $this->get_class_id('update'), 'integer' )) == 1 )
					{
						$cat_name = param( $this->get_class_id('cat_name'), 'string' );
						$cat_description = param( $this->get_class_id('cat_description'), 'html' );

						$SQL = 'UPDATE '.$this->get_sql_table('cats').' SET
									cat_name = "'.$DB->escape( $cat_name ).'",
									cat_description = "'.$DB->escape( $cat_description ).'"
								WHERE cat_ID = '.$Category->cat_ID;

						if( $DB->query($SQL) )
						{	// Category saved in DB
							$this->msg( $this->T_('Category settings have been updated'), 'success' );
						}
						set_param('dl_manager_cat_ID', 0 ); // Return to the right view
						set_param('action', 'view_cats' ); // Return to the right view
						set_param('dl_manager_updated_cat_ID', $Category->cat_ID ); // Save ID for fadeout
					}
			}
			// Exit now
			return true;
		}

		if( $File = $this->get_File() )
		{	// We have perms to manage the requested file
			switch($action)
			{
				case 'enable':
					$SQL = 'UPDATE '.$this->get_sql_table('files').'
							SET file_status = 1
							WHERE file_ID = '.$File->file_ID;
					if( $DB->query($SQL) ) $this->msg( $this->T_('The file download has been enabled!'), 'success' );

					set_param('dl_manager_updated_file_ID', $File->file_ID ); // Save ID for fadeout
					break;

				case 'disable':
					$SQL = 'UPDATE '.$this->get_sql_table('files').'
							SET file_status = 0
							WHERE file_ID = '.$File->file_ID;
					if( $DB->query($SQL) ) $this->msg( $this->T_('The file download has been disabled!'), 'success' );

					set_param('dl_manager_updated_file_ID', $File->file_ID ); // Save ID for fadeout
					break;

				case 'delete':
					$this->delete_file( $File );
					break;

				case 'info':
					$SQL = 'SELECT stat_ID FROM '.$this->get_sql_table('stats').'
							WHERE stat_file_ID = '.$File->file_ID.'
							LIMIT 0,1';

					if( $DB->get_results($SQL) )
					{
						set_param( 'dl_manager_file_ID', $File->file_ID );
						set_param( 'action', 'info' );
					}
					else
					{
						$this->msg( sprintf( $this->T_('The file %s has no downloads yet.'), '&laquo;'.$File->file_name.'&raquo;' ) );
						set_param( 'dl_manager_file_ID', 0 ); // Return to the right view
					}
					break;

				case 'edit':
					// Do nothing...
					break;

				case 'prune_hits':
					// Prune hits for requested file
					$SQL = 'DELETE FROM '.$this->get_sql_table('stats').'
							WHERE stat_file_ID = '.$File->file_ID;

					if( !$DB->query($SQL) ) $this->msg( $this->T_('Unable to prune download hits.') );
					$this->msg( $this->T_('The file download hits have been pruned!'), 'success' );

					set_param('dl_manager_updated_file_ID', $File->file_ID ); // Save ID for fadeout
					break;

				case 'export_stats':
					// Export stats in CSV file
					$SQL = 'SELECT * FROM '.$this->get_sql_table('stats').'
							WHERE stat_file_ID = '.$File->file_ID;

					if( $file_stats = $DB->get_results($SQL, ARRAY_N ) )
					{
						$data['csv_fields'] = array( 'Hit ID', 'File ID', 'User ID', 'IP', 'Referrer', 'User agent type', 'User agent', 'Date' );
						$data['items'] = $file_stats;

						if( $export_content = $this->create_csv( $data ) )
						{
							require_once dirname(__FILE__).'/inc/'.$this->download_class;

							$DL = new httpdownload;
							$DL->set_bydata($export_content);
							$DL->filename = $File->file_name.'.CSV';
							$DL->download(); // Download the file
						}
					}

				default:
					if( (param( $this->get_class_id('update'), 'integer' )) == 1 )
					{
						$action = 'update_file';
						$file_pass = param( $this->get_class_id('file_pass'), 'html' );

						// Min 5 chars for file password
						if( $file_pass != '' && evo_strlen($file_pass) < 5 )
						{
							$this->msg( $this->T_('File password should be at least 5 characters long.'), 'error' );
							$this->msg( $this->T_('File settings have not been updated!'), 'error' );

							// Return to the right view
							set_param('dl_manager_file_ID', $File->file_ID );
							set_param('action', 'edit' );

							return false;
						}

						$file_downloads = param( $this->get_class_id('file_downloads'), 'integer' );
						$file_dl_limit = param( $this->get_class_id('file_dl_limit'), 'integer' );
						$file_cat_ID = param( $this->get_class_id('file_cat_ID'), 'integer' );
						$file_name = param( $this->get_class_id('file_name'), 'string' );
						$file_title = param( $this->get_class_id('file_title'), 'string' );
						$file_description = param( $this->get_class_id('file_description'), 'html' );

						$SQL = 'UPDATE '.$this->get_sql_table('files').' SET
									file_downloads = '.$DB->quote( $file_downloads ).',
									file_dl_limit = '.$DB->quote( $file_dl_limit ).',
									file_pass = "'.$DB->escape( $file_pass ).'",
									file_cat_ID = '.$DB->quote( $file_cat_ID ).',
									file_name = "'.$DB->escape( $file_name ).'",
									file_title = "'.$DB->escape( $file_title ).'",
									file_description = "'.$DB->escape( $file_description ).'",
									file_datetime = '.$DB->quote( date('Y-m-d H:i:s', $localtimenow) ).'
								WHERE file_ID = '.$File->file_ID;

						if( $DB->query($SQL) )
						{	// File saved in DB
							$this->msg( sprintf( $this->T_('File settings have been updated for &laquo;%s&raquo;.'), $file_name ), 'success' );
						}
						set_param('dl_manager_file_ID', 0 ); // Return to the right view
						set_param('dl_manager_updated_file_ID', $File->file_ID ); // Save ID for fadeout
					}
			}
		}

		switch($action)
		{
			// Create actions
			case 'create_cat':
				$cat_name = param( $this->get_class_id('cat_name'), 'string' );
				$cat_description = param( $this->get_class_id('cat_description'), 'html' );

				$SQL = 'INSERT INTO '.$this->get_sql_table('cats').'
							( cat_user_ID, cat_name, cat_description )
						VALUES (
							'.$DB->quote($current_User->ID).',
							"'.$DB->escape( $cat_name ).'",
							"'.$DB->escape( $cat_description ).'"
							)';

				if( $DB->query($SQL) )
				{	// Category saved in DB
					$this->msg( sprintf( $this->T_('The category &laquo;%s&raquo; has been created.'), $cat_name ), 'success' );

					set_param('dl_manager_updated_cat_ID', $DB->insert_id ); // Save ID for fadeout
				}

				set_param('dl_manager_cat_ID', 0 ); // Return to the right view
				set_param('action', 'view_cats' ); // Return to the right view
				break;

			case 'create_file':
			case 'update_file':
				if( isset($_FILES) && !empty( $_FILES['uploadfile']['tmp_name'] ) )
				{	// Some files have been uploaded:
					if( $_FILES['uploadfile']['error'] )
					{ // PHP has detected an error!:
						switch( $_FILES['uploadfile']['error']  )
						{
							case UPLOAD_ERR_INI_SIZE: // bigger than allowed in php.ini
								$failedFiles = $this->T_('The file exceeds the upload_max_filesize directive in php.ini.');
								break;

							case UPLOAD_ERR_PARTIAL:
								$failedFiles = $this->T_('The file was only partially uploaded.');
								break;

							case UPLOAD_ERR_NO_FILE:
								// Is probably the same as empty($lName) before.
								$failedFiles = $this->T_('No file was uploaded.');
								break;

							case 6: // numerical value of UPLOAD_ERR_NO_TMP_DIR
								// (min_php: 4.3.10, 5.0.3) case UPLOAD_ERR_NO_TMP_DIR:
								// Missing a temporary folder.
								$failedFiles = $this->T_('Missing a temporary folder (upload_tmp_dir in php.ini).');
								break;

							default:
								$failedFiles = $this->T_('Unknown error.').' #'.$_FILES['uploadfile']['error'] ;
						}
					}

					if( @is_uploaded_file( $_FILES['uploadfile']['tmp_name'] ) )
					{	// Attempt to move the uploaded file to the requested target location:
						if( !empty($File) )
						{	// Update a file
							if( $moved = @move_uploaded_file( $_FILES['uploadfile']['tmp_name'] , $this->path.$File->file_key ) )
							{
								$SQL = 'UPDATE '.$this->get_sql_table('files').' SET
											file_name = '.$DB->quote( $_FILES['uploadfile']['name'] ).',
											file_type = '.$DB->quote( $_FILES['uploadfile']['type'] ).',
											file_size = '.$DB->quote( $_FILES['uploadfile']['size'] ).',
											file_datetime = '.$DB->quote( date('Y-m-d H:i:s', $localtimenow) ).'
										WHERE file_ID = '.$File->file_ID;

								if( $DB->query($SQL) )
								{	// File saved in DB
									$this->msg( $this->T_('New file uploaded.'), 'success' );

									set_param('dl_manager_updated_file_ID', $File->file_ID ); // Save ID for fadeout
								}
							}
						}
						else
						{	// Insert new file

							// Generate unique file key
							$file_key = $this->gen_file_key( $this->UserSettings->get('key_length') );

							if( $moved = @move_uploaded_file( $_FILES['uploadfile']['tmp_name'] , $this->path.$file_key ) )
							{
								$file_cat_ID = param( $this->get_class_id('file_cat_ID'), 'integer' );
								$file_title = param( $this->get_class_id('file_title'), 'string' );
								$file_description = param( $this->get_class_id('file_description'), 'html' );

								$SQL = 'INSERT INTO '.$this->get_sql_table('files').'
											( file_user_ID, file_cat_ID, file_name, file_type, file_size,
											 file_key, file_title, file_description, file_datetime )
										VALUES (
											'.$DB->quote($current_User->ID).',
											'.$DB->quote( $file_cat_ID ).',
											"'.$DB->escape( $_FILES['uploadfile']['name'] ).'",
											'.$DB->quote( $_FILES['uploadfile']['type'] ).',
											'.$DB->quote( $_FILES['uploadfile']['size'] ).',
											'.$DB->quote( $file_key ).',
											"'.$DB->escape( $file_title ).'",
											"'.$DB->escape( $file_description ).'",
											'.$DB->quote( date('Y-m-d H:i:s', $localtimenow) ).')';

								if( $DB->query($SQL) )
								{	// File saved in DB
									$this->msg( sprintf( $this->T_('The file &laquo;%s&raquo; has been successfully uploaded.'), $_FILES['uploadfile']['name'] ), 'success' );

									set_param('dl_manager_updated_file_ID', $DB->insert_id ); // Save ID for fadeout
								}
							}
						}

						if( !isset($moved) )
						{
							$failedFiles  = $this->T_('An unknown error occurred when moving the uploaded file on the server.');
						}
					}
					else
					{
						$failedFiles  = $this->T_('The file does not seem to be a valid upload! It may exceed the upload_max_filesize directive in php.ini.');
					}

					if( !empty($failedFiles) )
					{	// Transmit file error to next page!
						$this->msg( $failedFiles, 'error' );
						unset($failedFiles);
					}
				}
				elseif( isset($_POST['attach_file']) )
				{	// Attach button clicked with no file selected
					$this->msg( $this->T_('Select a file first.'), 'error' );
				}
				break;

			// Global actions
			case 'prune_all_hits':
				$results = $this->ExecCronJob( $_params, true );
				switch($results['code'])
				{
					case -1: $cat = 'note'; break;
					case  0: $cat = 'error'; break;
					case  1: $cat = 'success'; break;
				}
				$this->msg( $results['message'], $cat );
				break;

			case 'enable_all':
				$SQL = 'UPDATE '.$this->get_sql_table('files').'
						SET file_status = 1
						WHERE file_user_ID = '.$current_User->ID;
				if( $DB->query($SQL) ) $this->msg( $this->T_('All files have been enabled!'), 'success' );
				break;

			case 'disable_all':
				$SQL = 'UPDATE '.$this->get_sql_table('files').'
						SET file_status = 0
						WHERE file_user_ID = '.$current_User->ID;
				if( $DB->query($SQL) ) $this->msg( $this->T_('All files have been disabled!'), 'success' );
				break;

			case 'delete_all':
				$SQL = 'SELECT file_ID, file_key, file_name FROM '.$this->get_sql_table('files').'
						WHERE file_user_ID = '.$current_User->ID;

				if( $rows = $DB->get_results($SQL) )
				{
					$total_rows = count($rows);
					for( $i=0; $i < $total_rows; $i++ )
					{
						$this->delete_file( $rows[$i] );
					}
				}
				break;
		}
	}


// =========================
// Request and display files

	function SkinBeginHtmlHead()
	{
		global $plugins_url;
		require_css( $plugins_url.$this->classname.'/'.$this->code.'.css', true );	// Add our css
	}

	function DisplayItemAsHtml( & $params )
	{
		// <!-- dl_manager: cats="apps" files="9,8,3" -->
		$params['data'] = preg_replace( '~<\!--[\s]+dl_manager:[\s]+(.*?)[\s]+-->~ie', '$this->get_dl_links( "\\1" )', $params['data'] );
		return true;
	}


	function DisplayItemAsXml( & $params )
	{
		return $this->DisplayItemAsHtml( $params );
	}


	function AdminDisplayEditorButton( $params )
	{
		global $edited_Item;

		$button_url = str_replace( '&amp;', '&', $this->get_htsrv_url( 'browse_downloads' ) ).'&item_ID='.$edited_Item->ID;

		echo '<input type="button" value="'.$this->T_('Add downloads').
				'" onclick="return pop_up_window( \''.$button_url.'\', \'add_download_links\', 750, 560, \'scrollbars=yes, status=yes, resizable=yes, menubar=no\' )" />';
	}


	function htsrv_browse_downloads()
	{
		require dirname(__FILE__).'/inc/_browse_window.php';
	}


// =========================
// Check user perms and path

	function check_perms()
	{
		global $current_User, $Settings, $UserSettings;

		$ps_url = '?ctrl=plugins&amp;action=edit_settings&amp;plugin_ID='.$this->ID;
		if( ! $Settings->get($this->code.'_lic_key') )
		{
			$this->msg( sprintf( $this->T_('Please enter your license key on <a %s>plugin settings</a> page.'), 'href="'.$ps_url.'"'), 'error' );
			return false;
		}

		if( !is_logged_in() )
		{	// Not logged in
			$this->msg( $this->T_('You\'re not allowed to view this page!'), 'error' );
			return false;
		}
		if( !$Settings->get('upload_enabled') )
		{	// Upload is globally disabled
			$this->msg( $this->T_('Upload is disabled.'), 'error' );
			return false;
		}
		if( !$current_User->check_perm( 'files', 'add' ) )
		{	// We do not have permission to add files
			$this->msg( $this->T_('You have no permission to add/upload files.'), 'error' );
			return false;
		}

		$this->check_updates();
		if( !empty($this->update_msg) )
		{
			$this->msg($this->update_msg, 'success');
		}

		if( $Settings->get($this->code.'_lic_unreg') == get_class($this) )
		{
			$this->msg( str_replace( array('%L', '%N', '%PS'), array($this->help_url.'#get', $this->name, $ps_url), $this->unreg_msg ), 'error' );
			return false;
		}

		return true;
	}


	function check_dir( $msg = true )
	{
		global $current_User;

		if( !$this->path ) $this->check_path();
		if( !is_dir($this->path) ) mkdir_r($this->path);

		if( !is_writable($this->path) )
		{
			if( $msg )
			{
				$msg = $this->T_('Unable to create directory for uploaded files, contact the admin.');
				$msg2 = sprintf( $this->T_('You must create the following directory with write permissions (777):%s'), '<br />'.$this->path);

				if( $current_User->Group->ID == 1 ) $msg = $msg2;
				$this->msg( $msg, 'error' );
			}
			return false;
		}
		// Create .htaccess file
		$file = $this->path.'.htaccess';

		if( !file_exists($file) )
		{
			$data = 'deny from all';
			$fh = @fopen($file,'a');
			@fwrite($fh, $data);
			@fclose($fh);
			if( !file_exists($file) )
			{
				if( $msg )
				{
					$msg = $this->T_('Cannot create <i>.htaccess</i> file!');
					$msg2 = sprintf( $this->T_('Make sure the directory [%s] has write permissions (777)'), $this->path );
					if( $current_User->Group->ID == 1 ) $msg .= '<br />'.$msg2;
					$this->msg( $msg, 'error' );
				}
				return false;
			}
		}
		return true;
	}


	// Generate and memorize user dir ( $this->path )
	function check_path( $user_ID = 0 )
	{
		global $UserSettings, $current_User, $media_path;

		if( is_object($current_User) && $user_ID == 0 ) $user_ID = $current_User->ID;
		if( empty($user_ID) ) return;

		if( !$dirname = $UserSettings->get( $this->code.'_uploads', $user_ID ) )
		{	// Generate directory name
			$dirname = $this->gen_dirname();
			$UserSettings->set( $this->code.'_uploads', $dirname, $user_ID  );
			$UserSettings->dbupdate();
		}
		$this->path = $media_path.$this->code.'/'.$dirname.'/';
	}


	function gen_dirname( $length = 10 )
	{
		global $media_path;
		$dirname = strtolower( generate_random_key($length) );
		// Make sure the directory name is unique
		if( is_dir( $media_path.$this->code.'/'.$dirname.'/' ) ) return $this->gen_dirname($length);
		return $dirname;
	}


// =========================
// Download functions

	// Download requested file
	function download( $File, $dl_type = 1 )
	{
		global $DB, $Hit, $localtimenow, $current_User;

		// Should never happen
		if( empty($File) ) return;

		// Requested file
		$filename = $this->path.$File->file_key;

		require_once dirname(__FILE__).'/inc/'.$this->download_class;
		$DL = new httpdownload;

		if( is_logged_in() )
		{	// Set dl speed for users
			if( $this->UserSettings->get( 'dl_speed_u', $File->file_user_ID ) > 0 ) $DL->speed = $this->UserSettings->get('dl_speed_u');
			// Check user level
			if( is_object($current_User) )
			{
				$min_level = $this->UserSettings->get( 'dl_user_level', $File->file_user_ID );
				if( $current_User->level < $min_level )
				{
					$this->send_header( 5, $min_level );
				}
			}
		}
		else
		{	// Set dl speed for visitors
			if( $this->UserSettings->get( 'dl_speed_v', $File->file_user_ID) > 0 ) $DL->speed = $this->UserSettings->get('dl_speed_v');
		}

		switch( $dl_type )
		{
			case 1: // Set by file (default)
				$DL->set_byfile($filename);
				$DL->filename = $File->file_name;
				break;

			case 2: // Set by data
				if( !$file_data = $this->get_data( $filename ) ) $this->send_header();
				$DL->set_bydata($file_data);
				$DL->filename = $File->file_name;
				break;
		}

		// If user dl manager creates multiple connections we count a new hit every...
		// < 1 Mb     - 20 sec
		// 1-5 Mb     - 2  min
		// 5-20 Mb    - 8  min
		// 20-100 Mb  - 20 min
		// 100-700 Mb - 40 min
		// > 700 Mb   - 60 min

		$skip_counter = false;
		if( !empty($Hit) )
		{
			$SQL = 'SELECT MAX(stat_datetime) FROM '.$this->get_sql_table('stats').'
					WHERE stat_file_ID = '.$File->file_ID.'
					AND stat_remote_addr = "'.$DB->escape($Hit->IP).'"';

			$ok = true;
			if( $then = $DB->get_var( $SQL ) )
			{
				$now = date( 'Y-m-d H:i:s', $localtimenow );
				$time_lastdl = mysql2date("U",$then);
				$time_newdl = mysql2date("U",$now);
				$size = $File->file_size / (1024*1024); // Mb

				if( $size > 700 ) $interval	= 60*60;
				elseif( $size > 100 ) $interval	= 40*60;
				elseif( $size > 20 ) $interval	= 20*60;
				elseif( $size > 5 ) $interval	= 8*60;
				elseif( $size > 1 ) $interval	= 2*50;
				else $interval	= 20;

				//echo ($time_newdl - $time_lastdl).' < '.$interval; die;
				if( ($time_newdl - $time_lastdl) < $interval ) $skip_counter = true;
			}
		}

		if( !$skip_counter )
		{	// Increase dl counter
			$DB->query( 'UPDATE '.$this->get_sql_table('files').'
						SET file_downloads = '.$DB->quote( intval($File->file_downloads) + 1 ).'
						WHERE file_ID = '.$File->file_ID );

			if( !empty($Hit) )
			{
				// File found, let's log the hit
				// $Hit->log();

				$user_ID = (is_object($current_User)) ? $current_User->ID : 0;

				$DB->query( 'INSERT INTO '.$this->get_sql_table('stats').'
								( stat_file_ID, stat_reg_user_ID, stat_remote_addr, stat_referer, stat_agnt_type, stat_user_agnt, stat_datetime )
							VALUES (
								'.$DB->quote( $File->file_ID ).',
								'.$DB->quote( $user_ID ).',
								"'.$DB->escape( $Hit->IP ).'",
								"'.$DB->escape( $Hit->referer ).'",
								"'.$DB->escape( $Hit->get_agent_type() ).'",
								"'.$DB->escape( $Hit->get_user_agent() ).'",
								'.$DB->quote( date('Y-m-d H:i:s', $localtimenow) ).')' );
			}
		}
		$DL->download(); // Download the file

		exit(0);
	}


	// Display download page
	function htsrv_download( $params = array() )
	{
		return $this->htsrv_dl( $params, true );
	}


	// Silent download (no output)
	function htsrv_dl( $params = array(), $disp_page = false )
	{
		if( !empty($params['file']) )
		{	// "Short" url
			$file_key = trim($params['file']);
		}
		elseif( !$file_key = trim(param('file', 'string')) )
		{	// "Call plugin" url
			$this->send_header();
		}

		if( !$File = $this->get_File_by_key($file_key) ) $this->send_header();

		// This marker is needed in order to not log the download hit
		$this->dl_hit = true;

		// Download temporarily disabled
		if( $File->file_status != 1 ) $this->send_header(2);

		// Download limit exceeded
		if( $File->file_dl_limit > 0 )
		{
			if( $File->file_downloads >= $File->file_dl_limit ) $this->send_header(6, $File);
		}

		// The file is password protected
		// TODO: ban after X wrong tries
		if( $file_pass = $File->file_pass )
		{
			$check_pass = (!empty($params['file_pass'])) ? $params['file_pass'] : param( 'file_pass', 'html' );

			if( $check_pass != $file_pass )
			{	// Request file password
				//echo $check_pass.' != '.$file_pass;
				$this->send_header(7, $File);
			}
		}

		// Get existing uploads path
		$this->check_path( $File->file_user_ID );

		// File not found
		if( !file_exists( $this->path.$File->file_key ) ) $this->send_header( 1, $File );

		if( $this->UserSettings->get( 'dl_disabled_v', $File->file_user_ID ) && !is_logged_in() )
		{	// dl disabled for visitors
			$this->send_header(4);
		}

		if( $this->UserSettings->get( 'auto_prune_stats_mode', $File->file_user_ID ) == 'page' )
		{	// Prune old hits
			$this->ExecCronJob($_params, true);
		}

		if( !file_exists(dirname(__FILE__).'/inc/'.$this->download_class) )
		{	// DL class not found
			$this->send_header( 3, $File );
		}

		if( $disp_page )
		{	// We want to display download page
			require dirname(__FILE__).'/inc/_dl.main.php';
			exit();
		}
		else
		{	// Silent download (no output)
			$this->download( $File );
		}
	}


	// Display messages
	function send_header( $type = 0, $File = NULL, $param = NULL )
	{
		switch( $type )
		{
			case 0: // File not found
				header('HTTP/1.1 404 Not Found');
				$title = '404 Not Found';
				$msg = 'ERROR: Invalid file key.';
				break;

			case 1: // File not found
				header('HTTP/1.1 404 Not Found');
				$title = '404 Not Found';
				$msg = 'ERROR: The file you requested was renamed, removed or deleted from our servers.';
				if( $this->notify_user( $File, $msg ) )
				{
					$msg .= '<br /><br />System Administrator has been notified and this problem will be solved as soon as possible.';
				}
				break;

			case 2: // Downloads disabled
				header('HTTP/1.1 404 Not Found');
				$title = '404 Not Found';
				$msg = 'ERROR: Download is temporarily disabled.';
				break;

			case 3: // DL class file cannot be read
				header('HTTP/1.1 404 Not Found');
				$title = '404 Not Found';
				$msg = 'ERROR: Download class not found.';
				if( $this->notify_user( $File, $msg ) )
				{
					$msg .= '<br /><br />System Administrator has been notified and this problem will be solved as soon as possible.';
				}
				break;

			case 4: // Downloads disabled for visitors
				global $htsrv_url, $ReqHost, $ReqURI;
				header('HTTP/1.1 403 Forbidden');
				$title = '403 Forbidden';
				$msg = sprintf( 'ERROR: You must be logged in to download this file.<br />Please <a %s>log in</a> to continue.', 'href="'.$htsrv_url.'login.php?redirect_to='.urlencode($ReqHost.$ReqURI).'"' );
				break;

			case 5: // Low user level
				header('HTTP/1.1 403 Forbidden');
				$title = '403 Forbidden';
				$msg = sprintf( 'ERROR: You must have at least level %d to download this file.', $param );
				break;

			case 6: // Download limit exceeded
				global $baseurl;
				header('HTTP/1.1 403 Forbidden');
				$title = '403 Forbidden';
				$msg = sprintf( 'ERROR: Download limit exceeded for this file. You may want to <a %s>contact</a> System Administrator.', 'href="'.url_add_param( $baseurl, 'disp=msgform&amp;recipient_id='.$File->file_user_ID ).'"' );
				break;

			case 7: // Password form
				header('HTTP/1.1 403 Forbidden');
				$title = 'Password required';
				$body = '';

				// 'file_pass, dl_file, submit'
				$Form = new Form( $this->get_dl_url($File->file_key), '', 'post' );
				$Form->output = false;

				$body .= $Form->begin_form( 'fform' );
				$body .= $Form->hiddens_by_key( array(
								  'plugin_ID'	=> $this->ID,
								  'dl_file'		=> param( 'dl_file', 'string' ),
							  ) );

				$body .= $Form->text_input( 'file_pass', '', 20, $this->T_('File password'), '', array('maxlength'=>255) );
				$body .= $Form->end_form( array( array( 'submit', 'submit', $this->T_('Download file') ) ) );
				break;
		}

		global $evo_charset, $adminskins_url;

		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml">
		<head>
		<meta http-equiv="Content-Type" content="text/html; charset='.$evo_charset.'" />
		<meta http-equiv="Expires" content="Sun, 15 Jan 1998 17:38:00 GMT" />
		<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
		<title>'.$title.'</title>
		<link rel="stylesheet" type="text/css" href="'.$adminskins_url.'chicago/rsc/css/chicago.css" />
		<style type="text/css">
		.fform fieldset { border:none }
		.fform div.label { width:34% }
		.fform div.input { margin-left:36% }
		.whitebox_center { width:400px; margin:10px auto; padding:15px; background-color:#fff; border:2px #555 solid }
		</style>
		</head>
		<body>
		<div class="whitebox_center">';

		if( !empty($msg) ) echo '<p class="red">'.$msg.'</p>';
		if( !empty($body) ) echo $body;

		echo '</div><br /><hr /><div style="padding:0 10px; font-size:12px"><a href="'.$this->help_url.'">'.$this->name.' plugin</a></div></body></html>';
		exit();
	}


	// Prevent Hit logging
	function AppendHitLog( & $params )
	{
		if( $this->dl_hit )
		{	// Don't log the Hit, we don't want to waste b2evo stats
			return true;
		}
	}


// =========================
// Template functions

	function dl_all( $limit = 50, $echo = true )
	{
		global $DB, $Blog, $Item;

		if( is_object($Blog) ) $user_ID = $Blog->owner_user_ID; // Blog owner ID
		if( is_object($Item) ) $user_ID = $Item->creator_user_ID; // Item creator ID
		if( !is_numeric($limit) ) $limit = 20;

		if( empty($user_ID) )
		{	// We can't find a user, display the message and exit
			echo 'DL manager: User not found!';
			return;
		}

		$SQL = 'SELECT file_key FROM '.$this->get_sql_table('files').'
				WHERE file_user_ID = '.$DB->quote($user_ID).'
				AND file_status = 1
				ORDER BY file_cat_ID ASC
				LIMIT 0,'.$DB->quote($limit);

		if( $keys = $DB->get_col($SQL) )
		{
			$r = $this->get_dl_links( $keys );
			if( $echo ) echo $r;
			return $r;
		}
	}


	function dl( $keys, $echo = true )
	{
		$r = $this->get_dl_links( $keys );
		if( $echo ) echo $r;
		return $r;
	}


	function get_dl_links( $content, $limit = 50 )
	{
		global $DB, $Item;

		if( !is_object($Item) )
		{
			echo 'No Item';
			return;
		}

		$user_ID = $Item->creator_user_ID;
		$escaped_keys = $config = array();
		$list = '';

		// preg_match_all( '~(cats|files|config)="([^"]+)"~', $content, $matches );

		if( preg_match( '~config="([^"]+)"~', $content, $match ) )
		{	// config="nodesc"
			$config = $this->array_trim( explode( ',', $match[1] ) );
		}
		if( preg_match( '~cats="([^"]+)"~', $content, $match ) )
		{	// cats="1, 5, 88"
			$cats = $this->array_trim( explode( ',', $match[1] ) );
		}
		if( preg_match( '~files="([^"]+)"~', $content, $match ) )
		{	// files="GUSEEUQ4WQ, 7Y948JT8Q6"
			$files = $this->array_trim( explode( ',', $match[1] ) );
			foreach( $files as $file )
			{	// Escape file key
				$escaped_keys[] = $DB->escape($file);
			}
		}

		if( !empty($cats) )
		{
			foreach( $cats as $cat_ID )
			{	// Category ID
				if( !is_numeric($cat_ID) ) continue;

				$r = array();

				$SQL = 'SELECT * FROM '.$this->get_sql_table('files').'
						INNER JOIN '.$this->get_sql_table('cats').'
							ON file_cat_ID = cat_ID
						WHERE file_cat_ID = '.$DB->quote($cat_ID).'
						AND file_user_ID = '.$DB->quote($Item->creator_user_ID).'
						ORDER BY file_title, file_name ASC';

				if( $rows = $DB->get_results($SQL) )
				{
					foreach( $rows as $File )
					{
						if( !$link = $this->get_template_link( $File, $config ) ) continue;
						$r[] = '<li>'.$link.'</li>';
					}
				}
				if( !empty($r) )
				{
					$list .= $File->cat_name.'<ul>'.implode( "\n", $r ).'</ul>';
				}
			}
		}

		if( !empty($escaped_keys) )
		{
			$r = array();

			$SQL = 'SELECT * FROM '.$this->get_sql_table('files').'
					INNER JOIN '.$this->get_sql_table('cats').'
						ON file_cat_ID = cat_ID
					WHERE file_key IN ("'.implode( '", "', $escaped_keys ).'")
					AND file_user_ID = '.$DB->quote($Item->creator_user_ID);

			if( $rows = $DB->get_results($SQL) )
			{
				foreach( $rows as $File )
				{
					if( !$link = $this->get_template_link( $File, $config ) ) continue;
					$r[] = '<li>'.$link.'</li>';
				}
			}
			if( !empty($r) )
			{
				$list .= 'Files<ul>'.implode( "\n", $r ).'</ul>';
			}
		}

		if( !empty($list) )
		{
			return '<div class="'.$this->code.'">'.$list.'</div>';
		}
	}


	function get_template_link( $File, $config = array() )
	{
		if( !is_object($File) ) return false;

		$url = $this->get_dl_url($File->file_key);

		if( !$File->file_title )
		{
			$File->file_title = $File->file_name;
		}

		$r  = $this->get_extension_icon( $File->file_name, $File->file_type );
		$r .= ' <a href="'.$url.'" title="'.$File->file_name.'">'.$File->file_title.'</a>';
		$r .= ' <span class="'.$this->code.'_dls" title="'.sprintf( $this->T_('Downloaded %d times!'), $File->file_downloads ).'">('.$File->file_downloads.')</span>';

		if( !in_array( 'nodesc', $config ) && $File->file_description )
		{
			$r .= '<br /><span class="'.$this->code.'_desc">'.$File->file_description.'</span>';
		}
		return $r;
	}


// =========================
// Misc functions

	function delete_file( $File )
	{
		global $DB;

		if( @unlink($this->path.$File->file_key) )
		{
			$this->msg( sprintf( $this->T_('File deleted &laquo;%s&raquo;'), $File->file_name ), 'success' );
		}
		else
		{
			$this->msg( sprintf( $this->T_('Unable to delete file &laquo;%s&raquo;'), $File->file_name ), 'error' );
		}

		if( $DB->query('DELETE FROM '.$this->get_sql_table('files').' WHERE file_ID = '.$File->file_ID) )
		{
			$this->msg( $this->T_('File settings deleted.'), 'success' );
		}
		else
		{
			$this->msg( $this->T_('Unable to delete file settings.'), 'error' );
		}

		if( $DB->query('DELETE FROM '.$this->get_sql_table('stats').' WHERE stat_file_ID = '.$File->file_ID) )
		{
			$this->msg( $this->T_('File hits deleted.'), 'success' );
		}
		else
		{
			$this->msg( $this->T_('Unable to delete file hits. No hits yet?'), 'error' );
		}

		return true;
	}


	function delete_category( $Category, $move_posts = false )
	{
		global $DB, $Messages, $UserSettings, $current_User;

		// Get default category ID
		$default_cat_ID = $UserSettings->get('dl_manager_default_cat_ID');

		if( $Category->cat_ID == $default_cat_ID )
		{
			$this->msg( $this->T_('You can\'t delete the default category.'), 'error' );
			return;
		}

		$linked = array();
		if( $rows = $this->get_files_by_cat_ID( $Category->cat_ID, $current_User->ID ) )
		{
			foreach( $rows as $File )
			{
				if( $move_posts )
				{	// Lets move the post to default category
					if( !is_integer($default_cat_ID) )
					{
						$this->msg( $this->T_('Unable to move posts (default category not found).'), 'error' );
						return;
					}

					$SQL = 'UPDATE '.$this->get_sql_table('files').' SET
								file_cat_ID = '.$DB->quote($default_cat_ID).'
							WHERE file_ID = '.$DB->quote($File->file_ID);

					if( $DB->query($SQL) )
					{
						$this->msg( sprintf( $this->T_('File &laquo;%s&raquo; moved to default category.'), $File->file_name ), 'success' );
					}
					else
					{
						$this->msg( sprintf( $this->T_('Unable to move file &laquo;%s&raquo;.'), $File->file_name ), 'error' );
					}
				}
				else
				{
					$linked[] = '['.$File->file_ID.'] '.$File->file_name;
				}
			}
		}

		if( !empty($linked) && !$move_posts )
		{
			$Messages->head = array(
					'container' => sprintf( $this->T_('Cannot delete category &laquo;%s&raquo;'), $Category->cat_name ),
					'restrict' => $this->T_('The following relations prevent deletion:')
				);
			$Messages->foot = sprintf( $this->T_('All files will be moved to default category! Click <a %s>here</a> if you still want to continue?'), 'href="'.regenerate_url( 'dl_manager_cat_ID,action,confirm', 'dl_manager_cat_ID='.$Category->cat_ID.'&amp;action=delete_cat&amp;confirm=1' ).'"' );

			$this->msg( sprintf( $this->T_('%d files within category'), count($linked) ), 'note' );

			return;
		}

		if( !$Messages->count('error') )
		{	// Delete only if we moved files without errors
			if( $DB->query('DELETE FROM '.$this->get_sql_table('cats').' WHERE cat_ID = '.$Category->cat_ID) )
			{
				$this->msg( sprintf( $this->T_('Category deleted &laquo;%s&raquo;'), $Category->cat_name ), 'success' );
			}
			else
			{
				$this->msg( $this->T_('Unable to delete category.'), 'error' );
			}
		}
		else
		{
			$this->msg( $this->T_('Please fix the above errors before deleting this directory.'), 'error' );
		}

		return;
	}


	function get_File()
	{
		global $DB, $current_User;

		if( $file_ID = param( 'dl_manager_file_ID', 'integer' ) )
		{
			$SQL = 'SELECT * FROM '.$this->get_sql_table('files').'
					WHERE file_ID = '.$DB->quote( $file_ID ).'
					AND file_user_ID = '.$current_User->ID;

			if( $File = $DB->get_row($SQL) ) return $File;
		}
		return false;
	}


	function get_Category()
	{
		global $DB, $current_User;

		if( $cat_ID = param( 'dl_manager_cat_ID', 'integer' ) )
		{
			$SQL = 'SELECT * FROM '.$this->get_sql_table('cats').'
					WHERE cat_ID = '.$DB->quote( $cat_ID ).'
					AND cat_user_ID = '.$current_User->ID;

			if( $Category = $DB->get_row($SQL) ) return $Category;
		}
		return false;
	}


	function get_File_by_key( $file_key = NULL )
	{
		global $DB;

		$file_key = trim($file_key);

		if( empty($file_key) ) return false;

		// Basic injection filter
		if( preg_match( '/[^A-Za-z0-9]/', $file_key ) ) return false;

		$SQL = 'SELECT * FROM '.$this->get_sql_table('files').'
				WHERE file_key = "'.$DB->escape( $file_key ).'"';

		if( $File = $DB->get_row($SQL) )
		{
			return $File;
		}
		return false;
	}


	function get_files_by_cat_ID( $cat_ID = NULL, $user_ID = NULL )
	{
		global $DB;

		$cat_ID = trim($cat_ID);

		if( !is_numeric($user_ID) ) return false;
		if( !is_numeric($cat_ID) ) return false;

		$SQL = 'SELECT * FROM '.$this->get_sql_table('files').'
				WHERE file_cat_ID = '.$DB->quote( $cat_ID ).'
				AND file_user_ID = '.$DB->quote( $user_ID );

		if( $rows = $DB->get_results($SQL) )
		{
			return $rows;
		}
		return false;
	}


	function get_dl_url( $file_key, $type = '' )
	{
		global $baseurl;

		if( empty($type) && $this->Settings->get('nice_permalinks') )
		{
			$type = 1;
		}

		switch( $type )
		{
			case 1:
				$url = $baseurl.$this->Settings->get('dl_url_prefix').'/'.$file_key;
				break;

			case 2:
			default:
				$url = url_add_param( $baseurl.'index.php', 'dl_file='.$file_key );
				break;
		}

		return $url;
	}


	// Read remote or local file
	function get_data( $filename )
	{
		if( ! $content = @file_get_contents($filename) )
		{
			$content = fetch_remote_page( $filename, $info );
			if($info['status'] != '200') $content = '';
		}
		// Return content
		if( !empty($content) ) return $content;
		return false;
	}


	function get_extension_icon( $file_name = '', $file_type = '', $style = '' )
	{
		global $plugins_url;

		if( empty($file_name) ) return;

		if( !$this->file_ext_images )
		{	// Read extension images directory
			$filename_params = array(
					'recurse'		=> false,
					'basename'		=> true,
				);
			$this->file_ext_images = get_filenames( dirname(__FILE__).'/'.$this->ext_img_dir, $filename_params );
		}

		$ext = substr( $file_name, strrpos($file_name, '.') +1 );

		if( $key = array_search( strtolower($ext).'.gif', $this->file_ext_images ) )
		{
			$icon = $this->file_ext_images[$key];
		}
		else
		{
			$icon = 'unknown.gif';
		}

		$url = $plugins_url.$this->classname.'/'.$this->ext_img_dir.$icon;

		if( !empty($style) ) $style = 'style="'.$style.'"';

		return '<img src="'.$url.'" '.$style.' title="'.$file_type.'" alt="" />';
	}


	function gen_file_key( $length = 10 )
	{
		global $DB;

		$key = generate_random_key( $length, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' );
		$SQL = 'SELECT file_ID FROM '.$this->get_sql_table('files').'
				WHERE file_key = '.$DB->quote($key);
		if( $DB->get_var($SQL) )
		{
			return $this->gen_file_key($length);
		}
		return $key;
	}


	function get_cats()
	{
		global $DB, $current_User;

		$SQL = 'SELECT * FROM '.$this->get_sql_table('cats').'
				WHERE cat_user_ID = '.$DB->quote($current_User->ID).'
				ORDER BY cat_name ASC';

		$this->file_cats = $DB->get_results($SQL);
	}


	function create_csv( $params = array(), $delimiter = NULL )
	{
		// Make sure the parsecsv class is loaded
		require_once dirname(__FILE__).'/inc/'.$this->parsecsv;

		if( empty($delimiter) ) $delimiter = $this->Settings->get('delimiter');

		$CSV = new parseCSV();
		if( $content = $CSV->output( NULL, $params['items'], $params['csv_fields'], $delimiter ) )
		{
			return $content;
		}
		else
		{
			$this->msg( $this->T_('Unable to create CSV file.'), 'error' );
			return false;
		}
	}


	function notify_user( $File, $msg = '', $subject = '' )
	{
		global $current_User, $Hit, $notify_from, $baseurl, $admin_url, $app_version;

		if( !is_object($File) ) return false;

		// Notifications disabled
		if( !$this->UserSettings->get( 'notify_user', $File->file_user_ID ) ) return false;

		// User not found
		$UserCache = & get_Cache('UserCache');
		if( !$User = & $UserCache->get_by_ID( $File->file_user_ID, false, false ) ) return false;

		$from_name = $this->name.' plugin';
		$to_name = $User->get_preferred_name();

		if( empty($subject) )
		{
			$subject = 'Error detected for file: '.$File->file_name;
		}

		$message = '';
		if( is_logged_in() )
		{
			global $current_User;

			$message .= 'User: '.$current_User->get_preferred_name().' ('.$current_User->login.') '."\n";
			$message .= 'User ID: '.$current_User->ID."\n";
		}
		if( is_object($Hit) )
		{
			$message .= 'User IP: '.$Hit->IP."\n";
			$message .= 'Referrer: '.$Hit->referer."\n";
		}
		$message .= $msg;
		$message .= "\n\n\n\n";
		$message .= 'You can edit plugin settings if you don\'t want to receive messages from '.$from_name."\n";
		$message .= 'Unsubscribe URL: '.$admin_url.'?ctrl=users&user_ID='.$User->ID;;

		// Check b2evo version and send email
		if( version_compare( $app_version, '2.4.9', '>=' ) )
		{	// For b2evo-2.5 and up
			return send_mail( $User->email, $to_name, $subject, $message, $notify_from, $from_name );
		}
		else
		{
			$to = '"'.mail_encode_header_string($to_name).'" <'.$User->email.'>';
			$from = '"'.mail_encode_header_string($from_name).'" <'.$notify_from.'>';
			$subject = mail_encode_header_string($subject);

			return send_mail( $to, $subject, $message, $from );
		}
	}


	// Trim array
	function array_trim( $array )
	{
		return array_map( 'trim', $array );
	}


	function check_updates( $force = false, $lic_key = '' )
	{
		global $Settings, $servertimenow, $app_version, $baseurl, $ReqHost;

		// $force = true;
		$check_every = 86400 * 7; // check every 7 days

		if( $lic_key == '' )
		{
			$lic_key = $Settings->get($this->code.'_lic_key');
		}

		$last_checked = $Settings->get($this->code.'_lic_last_checked');

		if( !$force && $last_checked > $servertimenow - $check_every )
		{
			$latest_version = $Settings->get( $this->code.'_lic_version');
			if( !empty($latest_version) )
			{
				$this->update_msg = sprintf($this->update_tpl, $latest_version['v'], get_icon('download'), $latest_version['u']);
				return;
			}
			return;
		}

		$Settings->set( $this->code.'_lic_last_checked', $servertimenow );

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
			$Settings->set( $this->code.'_lic_version', '' );

			$value = xmlrpc_decode_recurse($result->value());
			if( is_array($value) && count($value) > 1 )
			{
				if( !empty($value['version']) && version_compare( $value['version'], $this->version, '>' ) )
				{	// There's a newer version available
					$this->update_msg = sprintf($this->update_tpl, $value['version'], get_icon('download'), $value['url']);

					$Settings->set( $this->code.'_lic_version', serialize( array('v'=>$value['version'], 'u'=>$value['url']) ) );
				}
			}
			elseif(	$value == 'Unregistered copy' )
			{
				$Settings->set( $this->code.'_lic_unreg', get_class($this) );
			}
		}
		$Settings->dbupdate();
		return;
	}


	function get_license_key()
	{
		return md5($this->licensed_to['name'] . $this->licensed_to['email']);
	}
}

?>