<?php
/**
 *
 * This file implements the Mail Sender plugin for {@link http://b2evolution.net/}.
 *
 *
 * @copyright (c)2011 Sonorth Corp. - {@link http://www.sonorth.com/}
 * @author Sonorth Corp.
 *
 * All rights reserved.
 *
 * THIS COMPUTER PROGRAM IS PROTECTED BY COPYRIGHT LAW AND INTERNATIONAL TREATIES.
 * UNAUTHORIZED REPRODUCTION OR DISTRIBUTION OF MAIL SENDER PLUGIN,
 * OR ANY PORTION OF IT THAT IS OWNED BY SONORTH CORP., MAY RESULT IN SEVERE CIVIL
 * AND CRIMINAL PENALTIES, AND WILL BE PROSECUTED TO THE MAXIMUM EXTENT POSSIBLE UNDER THE LAW.
 *
 * THE MAIL SENDER PLUGIN FOR B2EVOLUTION CONTAINED HEREIN IS PROVIDED "AS IS."
 * SONORTH CORP. MAKES NO WARRANTIES OF ANY KIND WHATSOEVER WITH RESPECT TO THE
 * MAIL SENDER PLUGIN FOR B2EVOLUTION. ALL EXPRESS OR IMPLIED CONDITIONS,
 * REPRESENTATIONS AND WARRANTIES, INCLUDING ANY WARRANTY OF NON-INFRINGEMENT OR
 * IMPLIED WARRANTY OF MERCHANTABILITY OR FITNESS FOR A PARTICULAR PURPOSE,
 * ARE HEREBY DISCLAIMED AND EXCLUDED TO THE EXTENT ALLOWED BY APPLICABLE LAW.
 *
 * IN NO EVENT WILL SONORTH CORP. BE LIABLE FOR ANY LOST REVENUE, PROFIT OR DATA,
 * OR FOR DIRECT, SPECIAL, INDIRECT, CONSEQUENTIAL, INCIDENTAL, OR PUNITIVE DAMAGES
 * HOWEVER CAUSED AND REGARDLESS OF THE THEORY OF LIABILITY ARISING OUT OF THE USE OF
 * OR INABILITY TO USE THE MAIL SENDER PLUGIN FOR B2EVOLUTION, EVEN IF SONORTH CORP.
 * HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class mail_sender_plugin extends Plugin
{
	var $name = 'Mail Sender';
	var $code = 'mail_sender';
	var $priority = 30;
	var $version = '1.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://www.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=16454';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;

	var $files = array();
	var $report_body = false;
	var $hide_tab = false;

	// Mailer class, relative to /plugins/ directory
	var $php_mailer_class = 'mail_sender_plugin/inc/class.phpmailer.php';

	/*
	 * Advanced settings
	 */

	// Experimental
	// Allow users to automatically unsubscribe
	var $allow_unsubscribe = true;

	// Uploads folder, relative to /plugins/ directory
	var $path = 'mail_sender_plugin/uploads/';

	// Delay between emails in milliseconds, sleep for 0.1 sec ('false' to disable)
	var $sleep_time = 100;

	// Optional "Reply-To" email address
	// Useful if you want users reply to this email rather than sender's address
	var $reply_to = false;

	// 1 - display debug info (emails get sent)
	// 2 - display debug info and die (stop after the first email)
	// 3 - display debug info (do not send emails)
	var $debug = 0;

	/*
	 * External emails source
	 */
	var $ext_source = false; // 'true' to enable

	// SQL to get the emails column.
	// Example: 'SELECT email FROM emails_table WHERE type = $OPTION$'
	var $ext_sql = 'SELECT email FROM newsletter_emails WHERE status = 1';

	// Will be displayed as source options like <option value="users">Users</option>
	// and replaced in SQL. So the above SQL may look like
	// 'SELECT email FROM emails_table WHERE type = "users"'
	// if you selected "Users" in "Emails source" menu
	var $ext_options = array(
				'newsletter' => 'Newsletter Subscribers',
			);


	/**
	 * Init
	 *
	 * This gets called after a plugin has been registered/instantiated.
	 */
	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Send emails to registered users');
		$this->long_desc = $this->short_desc;

		$this->path = $GLOBALS['plugins_path'].$this->path;
		$this->php_mailer_class = $GLOBALS['plugins_path'].$this->php_mailer_class;
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
	 * Event handler: Called when we detect a version change (in {@link Plugins::register()}).
	 *
	 * Use this for your upgrade needs.
	 *
	 * @param array Associative array of parameters.
	 *              'old_version': The old version of your plugin as stored in DB.
	 *              'db_row': an array with the columns of the plugin DB entry (in T_plugins).
	 *                        The key 'plug_version' is the same as the 'old_version' key.
	 * @return boolean If this method returns false, the Plugin's status gets changed to "needs_config" and
	 *                 it gets unregistered for the current request.
	 */
	function PluginVersionChanged( & $params )
	{
		if( version_compare( $params['old_version'], '0.3', '<' ) )
		{	// Add user_ID column to reports table
			global $DB;

			$this->db_add_col( $this->get_sql_table('reports'), 'user_ID', 'int(11) default "0" AFTER ID' );
		}
		return true;
	}


	function BeforeInstall()
	{
		$this->check_uploads_dir();
		return true;
	}


	/*
	function BeforeUninstallPayload( & $params )
	{	// TODO: Keep reports table after uninstall

	}
	*/


	function GetDbLayout()
	{
		return array(
			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('uploads')." (
					ID int(11) NOT NULL auto_increment,
					name text NOT NULL,
					type varchar(200) NOT NULL,
					size int(11) default '0',
					name_md5 varchar(32) NOT NULL,
					PRIMARY KEY ID ( ID )
				)",

			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('reports')." (
					ID int(11) NOT NULL auto_increment,
					user_ID int(11) default '0',
					successfull int(11) default '0',
					failed int(11) default '0',
					total int(11) default '0',
					report text NOT NULL,
					date datetime NOT NULL default '2000-01-01 00:00:00',
					PRIMARY KEY ID ( ID )
				)",
		);
	}


	/**
	 * Define settings that the plugin uses/provides.
	 */
	function GetDefaultSettings()
	{
		global $admin_email;

		return array(
				'attachments' => array(
					'label' => $this->T_('Enable attachments'),
					'defaultvalue' => 1,
					'type' => 'checkbox',
					'note' => $this->T_('Do you want to send emails with attachments?'),
				),
				'charset' => array(
					'label' => $this->T_('Email charset'),
					'defaultvalue' => 'utf-8',
					'type' => 'text',
					'note' => $this->T_('Example: utf-8, iso-8859-1, windows-1251 etc. Note: it\'s highly recommended to use utf-8 here.'),
				),
				'send_report' => array(
					'label' => $this->T_('Report email'),
					'defaultvalue' => $admin_email,
					'type' => 'text',
					'size' => 40,
					'note' => $this->T_('Send detailed reports to this email address. Leave empty to disable.'),
				),
				'plugin_end' => array(
					'layout' => 'end_fieldset',
				),
				'server_start' => array(
					'layout' => 'begin_fieldset',
					'label' => $this->T_('Server settings'),
				),
				'mailer' => array(
					'label' => $this->T_('Method to send mail'),
					'type' => 'select',
					'defaultvalue' => 'mail',
					'options' => array(
						'mail'	=> $this->T_('PHP mail (default)'),
						'sendmail'	=> $this->T_('Sendmail'),
						'smtp'	=> $this->T_('SMTP'),
					),
				),
				'sendmail' => array(
					'label' => $this->T_('Sendmail path'),
					'type' => 'text',
					'size' => 40,
					'defaultvalue' => '/usr/sbin/sendmail',
					'note' => $this->T_('Path of the sendmail program'),
				),
				'smtp_host' => array(
					'label' => $this->T_('SMTP Host'),
					'type' => 'text',
					'size' => 40,
					'defaultvalue' => 'localhost',
					'note' => sprintf( $this->T_('Sets the SMTP hosts<br />All hosts must be separated by a semicolon. You can also specify a different port for each host by using this format: %s. Hosts will be tried in order.'), '[hostname:port] (e.g. "smtp1.example.com:25;smtp2.example.com")'),
				),
				'smtp_port' => array(
					'label' => $this->T_('SMTP Port'),
					'type' => 'text',
					'size' => 5,
					'defaultvalue' => 25,
					'note' => $this->T_('Default SMTP server port'),
					'valid_pattern' => array( 'pattern' => '~^[0-9]+$~i', 'error' => $this->T_( 'Please enter valid port number' ) ),
				),
				'smtp_secure' => array(
					'label' => $this->T_('Security settings'),
					'type' => 'select',
					'defaultvalue' => '',
					'options' => array(
						''	=> $this->T_('None (default)'),
						'ssl'	=> $this->T_('SSL'),
						'tls'	=> $this->T_('TLS'),
					),
				),
				'smtp_username' => array(
					'label' => $this->T_('SMTP username'),
					'type' => 'text',
					'size' => 20,
				),
				'smtp_password' => array(
					'label' => $this->T_('SMTP password'),
					'type' => 'password',
					'size' => 20,
				),
				'server_end' => array(
					'layout' => 'end_fieldset',
				),
				'setting_start' => array(
					'layout' => 'begin_fieldset',
					'label' => $this->T_('Defaults'),
				),
				'sender_name' => array(
					'label' => $this->T_('Sender\'s name'),
					'defaultvalue' => $this->T_('Site Administrator'),
					'type' => 'text',
					'size' => 40,
				),
				'sender_email' => array(
					'label' => $this->T_('Sender\'s email address'),
					'defaultvalue' => $admin_email,
					'type' => 'text',
					'size' => 40,
				),
				'subject' => array(
					'label' => $this->T_('Message subject'),
					'type' => 'text',
					'size' => 80,
				),
				'body' => array(
					'label' => $this->T_('Message text'),
					'type' => 'html_textarea',
					'rows' => 10,
					'cols' => 65,
				),
				'setting_end' => array(
					'layout' => 'end_fieldset',
				),
				'exclude_start' => array(
					'layout' => 'begin_fieldset',
					'label' => $this->T_('Exclude users/groups'),
				),
				'group_ids' => array(
					'label' => $this->T_('Exclude groups'),
					'type' => 'select_group',
					'multiple' => 'multiple',
					'defaultvalue' => 1,
					'allow_none' => 'allow_none',
					'note' => $this->T_('Don\'t send emails to selected user groups. Use "CTRL" and "SHIFT" keys to select multiple items.'),
				),
				'user_ids' => array(
					'label' => $this->T_('Exclude users'),
					'type' => 'select_user',
					'multiple' => 'multiple',
					'defaultvalue' => 1,
					'allow_none' => 'allow_none',
					'note' => $this->T_('Don\'t send emails to selected users. Use "CTRL" and "SHIFT" keys to select multiple items.'),
				),
			);
	}


	function GetDefaultUserSettings()
	{
		return array(
				'mail_sender' => array(
					'label' => $this->T_('Receive emails'),
					'defaultvalue' => true,
					'type' => 'checkbox',
					'note' => $this->T_('Check this if you want to receive emails from site administrator.'),
				),
			);
	}


	function PluginSettingsUpdateAction()
	{
		if( $this->Settings->get('send_report') && !is_email($this->Settings->get('send_report')) )
		{
			$this->msg( $this->T_('Report email address is invalid!'), 'error' );
			return false;
		}
	}


	// EXPERIMENTAL: easy unsubscribe
	function BeforeBlogDisplay()
	{
		global $Plugins, $baseurl, $ReqHost, $ReqURI, $Hit;

		if( @preg_match( '~unsubscribe/([a-z0-9]{32})/([a-z0-9]{32})/$~i', str_replace( $baseurl, '', $ReqHost.$ReqURI ), $matches ) )
		{	// Our hit
			if( !empty($Hit) && $Hit->get_agent_type() != 'browser' )
			{	// Do not allow robot hits ;)
				if( ! headers_sent() )
				{
					header('HTTP/1.0 400 Bad Request');
				}
				return false;
			}

			if( $User = $this->get_unsubscribe_user( $matches[1], $matches[2] ) )
			{
				if( $MailSender = & $Plugins->get_by_code('mail_sender') )
				{
					$MailSender->UserSettings->set( 'mail_sender', 0, $User->user_ID );
					$MailSender->UserSettings->dbupdate();

					echo '<h4>'.$this->T_('You have been unsubscribed from our mailing list!').'</h4>';
				}
				else
				{
					echo '<h4>'.$this->T_('The Mail Sender plugin is disabled or uninstalled').'</h4>';
				}
			}
			else
			{
				echo '<h4>'.$this->T_('Requested user not found').'</h4>';
			}
			echo '<br /><code>'.make_clickable($baseurl).'</code>';
			die;
		}
	}


	/**
	 * Event handler: Gets invoked in /admin/_header.php for every backoffice page after
	 * the menu structure is build.
	 */
	function AdminAfterMenuInit()
	{
		// add our tab
		$this->register_menu_entry($this->name);
	}


	function check_perms()
	{
		global $current_User;

		$this->hide_tab = 1;

		if( !is_logged_in() )
		{
			$this->msg( $this->T_('You\'re not allowed to send emails!'), 'error' );
			return;
		}

		if( !$current_User->check_perm( 'options', 'edit' ) )
		{
			$this->msg( $this->T_('You\'re not allowed to send emails!'), 'error' );
			return;
		}

		if( param( 'msdellock', 'boolean' ) )
		{
			if( $this->shutdown('unlock') )
			{
				$this->msg( $this->T_('File lock removed.'), 'success' );
			}
		}
		elseif( $this->locked() )
		{
			return;
		}

		$this->can_upload();

		$this->hide_tab = 0;
	}


	function can_upload( $msg = true )
	{
		global $Settings, $current_User;

		if( ! $Settings->get('upload_enabled') )
		{	// Upload is globally disabled
			if( $msg ) $this->msg( $this->T_('Upload is disabled.'), 'note' );

			return false;
		}

		if( ! $current_User->check_perm( 'files', 'add' ) )
		{	// We do not have permission to add files
			if( $msg ) $this->msg( $this->T_('You have no permission to add/upload files.'), 'note' );

			return false;
		}
		return true;
	}


	function locked( $msg = true )
	{
		if( file_exists($this->path.'_lock') )
		{
			if( $msg )
			{
				$rm_lock_url = regenerate_url( 'action,plugin_ID,msdellock', 'action=edit_settings&amp;plugin_ID='.$this->ID.'&amp;msdellock=true');
				$this->msg( sprintf( $this->T_("<p>It seems like another process is already sending emails. Please wait for it to complete.</p>\n<p>If you beleve this is a mistake <a %s>click here</a> to remove the lock!</p>"), 'href="'.$rm_lock_url.'"'), 'error' );
			}
			return true;
		}
		return false;
	}


	function AdminTabAction()
	{
		global $Messages, $Settings, $DB, $current_User, $basepath, $success_emails, $failed_emails;

		$this->check_perms();
		if( $this->hide_tab == 1 ) return;

		// We want to delete the requested file
		if( $msdelfile = param( 'msdelfile', 'string' ) )
		{
			if( @unlink($this->path.$msdelfile) )
			{
				$this->msg( $this->T_('File deleted.'), 'success' );
			}

			if( $DB->query( 'DELETE FROM '.$this->get_sql_table('uploads').' WHERE name_md5 = '.$DB->quote($msdelfile) ) )
			{
				$this->msg( $this->T_('File settings deleted.'), 'success' );
			}
		}

		if( isset($_FILES) && !empty( $_FILES['uploadfile']['tmp_name'] ) && $this->can_upload(false) )
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
					# (min_php: 4.3.10, 5.0.3) case UPLOAD_ERR_NO_TMP_DIR:
						// Missing a temporary folder.
						$failedFiles = $this->T_('Missing a temporary folder (upload_tmp_dir in php.ini).');
						break;

					default:
						$failedFiles = $this->T_('Unknown error.').' #'.$_FILES['uploadfile']['error'] ;
				}
			}

			load_funcs( 'tools/model/_system.funcs.php' );
			$memory_limit = system_check_memory_limit();
			// system_check_memory_limit() may return NULL
			// http://forums.b2evolution.net/viewtopic.php?p=83696
			if( !empty($memory_limit) )
			{
				if( ($this->get_total_size() + $_FILES['uploadfile']['size']) > ($memory_limit * 1024) )
				{	// PHP may run out of memory if attachments size >= PHP memory_limit
					$this->msg( sprintf( $this->T_('Total attachments size should not exceed %sMb!'), number_format( $memory_limit / 1024, 0 ) ), 'error' );
					return false;
				}
			}

			if( is_uploaded_file( $_FILES['uploadfile']['tmp_name'] ) )
			{	// Attempt to move the uploaded file to the requested target location:
				if( move_uploaded_file( $_FILES['uploadfile']['tmp_name'] , $this->path.md5($_FILES['uploadfile']['name']) ) )
				{
					$ctype = $_FILES['uploadfile']['type'];
					if( empty($ctype) )
					{
						$ctype = $this->get_mime_type($_FILES['uploadfile']['name']);
					}

					$sql = 'INSERT INTO '.$this->get_sql_table('uploads').'
							(name, type, size, name_md5) VALUES (
							'.$DB->quote( $_FILES['uploadfile']['name'] ).',
							'.$DB->quote( $ctype ).',
							'.$_FILES['uploadfile']['size'].',
							'.$DB->quote( md5($_FILES['uploadfile']['name']) ).')';

					if( $DB->query($sql) )
					{	// File saved in DB
						$this->msg( sprintf( $this->T_('The file &laquo;%s&raquo; has been successfully uploaded.'), $_FILES['uploadfile']['name'] ), 'success' );
					}
				}
				else
				{
					$failedFiles  = $this->T_('An unknown error occurred when moving the uploaded file on the server.');
				}
			}
			else
			{	// Ensure that a malicious user hasn't tried to trick the script into working on files upon which it should not be working.
				$failedFiles  = $this->T_('The file does not seem to be a valid upload! It may exceed the upload_max_filesize directive in php.ini.');
			}

			if( !empty($failedFiles) )
			{	// Transmit file error to next page!
				$this->msg( $failedFiles, 'error' );
				unset($failedFiles);
			}
		}
		elseif( isset($_POST['attach']) )
		{	// Attach button clicked with no file selected
			$this->msg( $this->T_('Select a file first.') );
		}
		elseif(	param( $this->get_class_id('sender_email'), 'string' ) &&
				param( $this->get_class_id('sender_name'), 'string' ) &&
				param( $this->get_class_id('subject'), 'string' ) &&
				param( $this->get_class_id('body'), 'html' ) &&
				!isset($_POST['attach']) )
		{
			$this->start = abs( param( $this->get_class_id('start'), 'integer' ) );
			$this->limit = abs( param( $this->get_class_id('limit'), 'integer' ) );

			$this->source = param( $this->get_class_id('source'), 'string' );
			$this->as_text = param( $this->get_class_id('as_text'), 'boolean' );
			$this->as_html = param( $this->get_class_id('as_html'), 'boolean' );
			$this->sender_email = param( $this->get_class_id('sender_email'), 'string' );
			$this->sender_name = param( $this->get_class_id('sender_name'), 'string' );
			$this->subject = param( $this->get_class_id('subject'), 'string' );
			$this->body = param( $this->get_class_id('body'), 'html' );

			// Use provided emails
			$this->custom_emails = param( $this->get_class_id('emails'), 'html' );

			if( !$this->subject || !$this->sender_name || !$this->body )
			{
				$this->msg( 'All fields are mandatory!' );
				return false;
			}

			// Validate the input
			if( !is_email($this->sender_email) )
			{
				$this->msg( 'Sender\'s email "'.$this->sender_email.'" is invalid.' );
				return false;
			}

			// Start sending emails
			$this->start_sending();

			if( ! $Messages->has_errors() )
			{
				$this->msg( sprintf( $this->T_('Successfull: %sFailed: %sTotal: %s'), $success_emails.'<br />', $failed_emails.'<br />', $this->total ), 'success' );
			}
		}
		elseif( param( $this->get_class_id('start_sending') ) )
		{
			$this->msg( $this->T_('All fields are mandatory'), 'error' );
		}
		// Load files settings
		$this->files = $DB->get_results( 'SELECT * FROM '.$this->get_sql_table('uploads'), 'ARRAY_A' );
	}


	function AdminTabPayload()
	{
		if( $this->hide_tab == 1 ) return;

		$opt = array();// '<option value="" selected="selected">Website Users (default)</option>' );
		if( count($this->ext_options) > 0 )
		{
			foreach( $this->ext_options as $k => $v )
			{
				$opt[] = '<option value="'.$k.'">'.$v.'</option>';
			}
		}
		$source_options = implode( "\n", $opt );

		// Display the form
		$Form = new Form( 'admin.php', '', 'post', 'none', 'multipart/form-data' );
		$Form->begin_form( 'fform' );
		$Form->begin_fieldset( $this->T_('Mail Sender') );
		$Form->hidden_ctrl(); // needed to pass the "ctrl=tools" param
		$Form->hiddens_by_key( get_memorized() ); // needed to pass all other memorized params, especially "tab"
		$Form->hidden( $this->get_class_id('start_sending'), 1 );

		if( ! $sender_email = param( $this->get_class_id('sender_email'), 'string' ) )
		{
			$sender_email = $this->Settings->get('sender_email');
		}
		if( ! $sender_name = param( $this->get_class_id('sender_name'), 'string' ) )
		{
			$sender_name = $this->Settings->get('sender_name');
		}
		if( ! $form_subject = param( $this->get_class_id('subject'), 'string' ) )
		{
			$form_subject = $this->Settings->get('subject');
		}
		if( ! $form_body = param( $this->get_class_id('body'), 'html' ) )
		{
			$form_body = $this->Settings->get('body');
		}

		$Form->text_input( $this->get_class_id('start'), param( $this->get_class_id('start'), 'integer', 1 ), 5, $this->T_('Starting email'), $this->T_('Start sending from this email, skipping all previous.') );
		$Form->text_input( $this->get_class_id('limit'), param( $this->get_class_id('limit'), 'integer', 500 ), 5, $this->T_('Limit'), $this->T_('The number of emails you want to send at a time. Set 0 to send all available.') );
		echo '<hr />';
		$Form->checkbox( $this->get_class_id('as_text'), true, $this->T_('Send as text'), $this->T_('Send email as plain text.') );
		$Form->checkbox( $this->get_class_id('as_html'), true, $this->T_('Send as HTML'), $this->T_('Send email as HTML formatted text.') );
		$Form->text_input( $this->get_class_id('sender_email'), $sender_email, 40, $this->T_('From email'), $this->T_('Sender\'s email.') );
		$Form->text_input( $this->get_class_id('sender_name'), $sender_name, 40, $this->T_('From name'), $this->T_('Sender\'s name.') );
		$Form->text_input( $this->get_class_id('subject'), $form_subject, 40, $this->T_('Subject') );

		if( $this->ext_source )
		{
			$Form->select_input_options( $this->get_class_id('source'), $source_options, $this->T_('Emails source'), $this->T_('Get emails list from selected source') );
		}

		if( $this->Settings->get('attachments') && $this->can_upload(false) )
		{	// Attachments enabled
			$this->check_uploads_dir( false );

			echo '<fieldset><div class="label"><label>'.$this->T_('Choose a file').':</label></div>';
			echo '<div class="input">
					<input name="uploadfile" size="40" type="file" />
					<input type="submit" name="attach" value="'.format_to_output( $this->T_('Attach file'), 'formvalue' ).'" class="ActionButton" >
				  </div></fieldset>';

			if( !empty($this->files) )
			{	// Display uploaded files
				foreach( $this->files as $Fkey => $Fvalue )
				{
					echo '<fieldset><div class="label"><label>'.($Fkey+1).':</label></div>';
					echo '<div class="input"><table width="350" border="0" cellspacing="0" cellpadding="0"><tr>
							<td nowrap="nowrap" width="95%">'.$Fvalue['name'].'</td>
							<td nowrap="nowrap" style="padding:0 20px">'.number_format( $Fvalue['size']/1024, 2 ).' '.$this->T_('KB').'.</td>
							<td><a href="'.regenerate_url( '', 'msdelfile='.$Fvalue['name_md5']).'"
									title="'.$this->T_('Delete this file').'">'.get_icon('delete').'</a></td>
						 </tr></table></div></fieldset>';
				}
				echo '<fieldset><div class="label"><label></label></div>';
				echo '<div class="input"><span class="notes">'.$this->T_('Total size').': '.$this->get_total_size( true ).'</span></div></fieldset>';
			}
		}
		$Form->textarea( $this->get_class_id('emails'), param($this->get_class_id('emails'), 'html'), 3, $this->T_('Email addresses'), $this->T_('One email address per line.'), 65 );

		$Form->textarea( $this->get_class_id('body'), $form_body, 10, $this->T_('Message text'), '', 65 );

		$Form->end_fieldset();
		$Form->end_form( array(
				array( 'submit', 'submit', $this->T_('Send message !'), 'SaveButton' ),
				array( 'reset', '', $this->T_('Reset'), 'ResetButton' ) ) );
	}


	function start_sending()
	{
		global $Messages, $DB, $success_emails, $failed_emails, $current_User;

		if( $this->locked() ) return;

		$this->shutdown('lock');

		if( function_exists( 'set_time_limit' ) )
		{
			@set_time_limit( 3600 ); // 60 minutes
		}
		ignore_user_abort(true);
		@ini_set( 'max_execution_time', '1800' );
		@ini_set( 'max_input_time', '1800' );
		@ini_set( 'memory_limit', '256M' );

		$Messages->clear( 'all' );
		if( !isset($success_emails) ) { $GLOBALS['success_emails'] = 0; }
		if( !isset($failed_emails) ) { $GLOBALS['failed_emails'] = 0; }

		// Load settings
		$this->files = $DB->get_results( 'SELECT * FROM '.$this->get_sql_table('uploads'), 'ARRAY_A' );
		$this->group_ids = $this->Settings->get('group_ids');
		$this->user_ids = $this->Settings->get('user_ids');

		if( $this->custom_emails )
		{	// Use external supplied emails

			// Validate emails field
			$emails_data = $emails = $this->custom_emails;
			if( !is_array($emails) )
			{
				if( strstr( $emails, 'tp://' ) || is_file( $emails ) )
				{	// Load emails list from remote or local file
					if( !$emails_data = $this->get_data($emails) )
					{
						$this->msg( sprintf( $this->T_('The file %s cannot be read!'), '<b>'.$emails.'</b>' ), 'error' );
					}
				}
			}
			$rows = $this->array_trim( explode( "\n" , $emails_data ) );
		}
		elseif( $this->ext_source && $this->source )
		{	// Use external emails source
			$SQL = str_replace( '$OPTION$', $DB->quote($this->source), $this->ext_sql );
			$rows = $DB->get_results( $SQL, 'ARRAY_N' );
		}
		else
		{	// Get b2evo emails
			$rows = array();
			$UserCache = & get_UserCache();
			for( $recipient_User = & $UserCache->get_first();
				!is_null($recipient_User);
				$recipient_User = & $UserCache->get_next() )
			{
				if( $this->check_user( $recipient_User ) )
				{
					$rows[] = array( $recipient_User->get('email'),
									 $recipient_User->get('preferredname'),
									 $recipient_User->login );
				}
			}
		}

		if( empty($rows) )
		{
			$this->msg( $this->T_('No emails found. Nothing to do.') );
			return false;
		}

		$msg = array();
		$all = $i = 1;
		$total_emails = count($rows);

		if( $this->start > $total_emails )
		{
			$this->msg( sprintf( $this->T_('There are only %d emails found. Decrease the starting email number.'), $total_emails ), 'note' );
			return;
		}

		if( $this->limit == 0 ) $this->limit = NULL; // No limit
		$rows = array_slice( $rows, ($this->start - 1), $this->limit );

		// Debug
		if( $this->debug )
		{
			echo '<h2 style="margin-top:30px">Emails count: '.$total_emails.'</h2>';
			echo '<h2>Emails limit: '.$this->limit.'</h2>';
			echo '<h2>First '.($this->start-1).' emails skipped</h2>';
			echo '<h2>Emails to send: '.count($rows).'</h2>';
		}

		if( ! file_exists($this->php_mailer_class) )
		{
			$this->msg( 'Mailer class not found', 'error' );
			return false;
		}

		// Construct the Mail class
		require $this->php_mailer_class;

		$this->Mail = new PHPMailer();

		$this->Mail->XMailer = $this->name.' v'.$this->version;
		$this->Mail->Mailer = $this->Settings->get('mailer');
		$this->Mail->Sendmail = $this->Settings->get('sendmail');
		$this->Mail->Host = $this->Settings->get('smtp_host');
		$this->Mail->Port = $this->Settings->get('smtp_port');
		$this->Mail->SMTPSecure = $this->Settings->get('smtp_secure');
		$this->Mail->Username = $this->Settings->get('smtp_username');
		$this->Mail->Password = $this->Settings->get('smtp_password');


		$this->Mail->CharSet = trim($this->Settings->get('charset'));
		$this->Mail->SetFrom( $this->sender_email, $this->sender_name );
		$this->Mail->Subject = $this->subject;

		if( $this->reply_to )
		{
			$this->Mail->AddReplyTo( $this->reply_to, $this->sender_name );
		}

		if( count($this->files) > 0 )
		{	// Get files
			$Files = $this->get_file_content( $this->files );
			if( empty($Files) ) return false;

			foreach( $Files as $File )
			{
				$this->Mail->AddStringAttachment( $File['content'], $File['name'], 'base64', $File['ctype'] );
			}
		}

		foreach( $rows as $row )
		{
			$email = (is_array($row) && isset($row[0])) ? $row[0] : $row;
			$user_name = (isset($row[1]) && is_array($row)) ? $row[1] : '';
			$user_login = (isset($row[2]) && is_array($row)) ? $row[2] : '';
			$ok = (empty($user_name)) ? $email : $user_name;

			if( !is_email( $email ) )
			{
				// Debug
				if( $this->debug ) echo '<br />Bad email: '.$email;

				continue;
			}

			// Debug
			if( $this->debug )
			{
				echo '<br />Email: '.$email;
				echo '<br />Name: '.$user_name;
				echo '<br />Login: '.$user_login;
			}

			if( $this->send_mail( $email, $user_name, $user_login ) )
			{
				$msg[] = sprintf( ($i).'. '.$this->T_('OK ( %s )'), $ok );
				$success_emails++;
			}
			else
			{
				$msg[] = sprintf( ($i).'. '.$this->T_('Sorry, could not send email ( %s )'), $ok );
				$failed_emails++;
			}
			$i++;
			$all++;
		}

		$this->Mail->ClearAttachments();


		if( is_email($this->Settings->get('send_report')) )
		{	// Create report message
			global $localtimenow;

			$this->total = $i-1;

			$this->body = '['.date('Y-m-d H:i:s', $localtimenow).'] '.
						sprintf( $this->T_('Mail Sender report from %s'), '"'.$_SERVER['HTTP_HOST'].'"' )."\n\n".
						implode( "\n", $msg )."\n\n".
						sprintf( $this->T_('Successfull: %sFailed: %sTotal: %s'),
						$success_emails."\n", $failed_emails."\n", $this->total."\n" );

			$this->Mail->Subject = $this->T_('Mail Sender report');
			//$this->as_html = false;

			// Send report
			$this->send_mail( $this->Settings->get('send_report') );

			// Save report in DB
			$DB->query('INSERT INTO '.$this->get_sql_table('reports').'
						(user_ID, successfull, failed, total, report, date) VALUES (
						'.(int)$current_User->ID.',
						'.$success_emails.',
						'.$failed_emails.',
						'.$this->total.',
						'.$DB->quote( implode( "\n", $msg ) ).',
						'.$DB->quote( date('Y-m-d H:i:s', $localtimenow) ).'
					)'
				);
		}

		$this->shutdown('unlock');
	}


	// Send mail
	function send_mail( $to, $to_name = NULL, $user_login = '' )
	{
		$to = trim($to);
		$charset = $this->Settings->get('charset');

		if( $this->sleep_time )
		{	// Sleep
			usleep( $this->sleep_time * 1000 );
		}


		// ===================
		// Check the input

		if( empty($to) )
		{
			$this->msg( 'All fields are mandatory!', 'error' );
			return false;
		}
		if( !is_email($to) ) return false;


		// ===================
		// Unsubscribe

		$unsubscribe = NULL;
		$unsubscribe_plain = NULL;

		if( $this->allow_unsubscribe && !empty($user_login) )
		{	// Add unsubscribe link for users
			$md5email = strtoupper(md5($to_saved));
			$md5login = strtoupper(md5($user_login));
			$uns_url = $baseurl.'index.php/unsubscribe/'.$md5login.'/'.$md5email.'/';
			$uns_link = '<a href="'.$uns_url.'">unsubscribe</a>';

			$unsubscribe = "<div style=\"font-size:12px;color:#999999\">\n\n\n\n-- \n".sprintf('Click %s if you don\'t want to receive messages from %s', $uns_link, $ReqHost).'</div>';

			// Additional link for plain text emails
			$unsubscribe_plain = "\n\nUnsubscribe link: ".$uns_url;

			if( $this->debug )
			{	// Debug
				echo '<h2>Unsubscribe link</h2>';
				pre_dump($uns_link);
			}

			$unsubscribe = $this->convert_charset( $unsubscribe, $charset );
			$unsubscribe_plain = $this->convert_charset( $unsubscribe_plain, $charset );
		}

		// Convert message encoding
		$body = $this->convert_charset( $this->body, $charset );
		$plain_body = strip_tags($body.$unsubscribe).$unsubscribe_plain;

		$html_body = '<div style="font-family:Verdana, sans-serif; font-size:14px; color:#333">';
		$html_body .= autobrize( make_clickable($body).$unsubscribe );
		$html_body .= '</div>';


		// ===================
		// Construct the email

		// Clear all addresses from previous loop
		$this->Mail->ClearAllRecipients();

		$this->Mail->AddAddress( $to, $to_name );

		if( $this->as_text && $this->as_html )
		{
			$this->Mail->AltBody = $plain_body;
			$this->Mail->MsgHTML($html_body);
		}
		elseif( $this->as_text )
		{	// Send in plain text only
			$this->Mail->Body = $plain_body;
		}
		elseif( $this->as_html )
		{	// Send in HTML only
			//$this->Mail->AltBody = 'To view the message, please use an HTML compatible email viewer!';
			$this->Mail->MsgHTML($html_body);
		}

		switch( $this->debug )
		{
			case 1:
				echo '<h2>TO:</h2>';
				pre_dump($to);
				echo '<h2>SUBJECT:</h2>';
				pre_dump($subject);

				if( $this->Mail->Send() ) return true;
				break;

			case 2:
				echo '<h2>TO:</h2>';
				pre_dump($to);
				echo '<h2>SUBJECT:</h2>';
				pre_dump($subject);

				$this->Mail->Send();
				die;
				break;

			case 3:
				echo '<h2>TO:</h2>';
				pre_dump($to);
				return true;
				break;

			case 0:
			default:
				// Send email
				if( $this->Mail->Send() ) return true;
		}
		// $this->Mail->ErrorInfo

		return false;
	}


	// Check if we want to send message to this user
	function check_user( $User )
	{
		global $UserSettings;

		if( !$this->UserSettings->get( 'mail_sender', $User->ID ) )
		{	// User doesn't want to receive emails
			return false;
		}

		if( !empty($this->group_ids) )
		{	// Check group ids
			if( in_array( $User->group_ID, $this->group_ids ) )
				return false;
		}

		if( !empty($this->user_ids) )
		{	// Check user ids
			if( in_array( $User->ID, $this->user_ids ) )
				return false;
		}
		return true;
	}


	// Quoted printable encode
	function quoted_printable_encode( $string, $charset = 'utf-8' )
	{
		$string = rawurlencode($string);
		$string = str_replace("%","=",$string);
		return "=?".$charset."?Q?".$string."?=";
	}


	// Trim array
	function array_trim( $array )
	{
		return array_map( 'trim', $array );
	}


	// Get MIME type of the file
	function get_mime_type( $filename )
	{
		$file_extension = strtolower( substr( strrchr( $filename, '.' ), 1 ) );

		switch( $file_extension )
		{
			case "pdf": $ctype = "application/pdf"; break;
			case "exe": $ctype = "application/octet-stream"; break;
			case "zip": $ctype = "application/zip"; break;
			case "doc": $ctype = "application/msword"; break;
			case "xls": $ctype = "application/vnd.ms-excel"; break;
			case "ppt": $ctype = "application/vnd.ms-powerpoint"; break;
			case "gif": $ctype = "image/gif"; break;
			case "png": $ctype = "image/png"; break;
			case "jpeg":
			case "jpg": $ctype = "image/jpg"; break;
			case "mp3": $ctype = "audio/mpeg"; break;
			case "wav": $ctype = "audio/x-wav"; break;
			case "mpeg":
			case "mpg":
			case "mpe": $ctype = "video/mpeg"; break;
			case "mov": $ctype = "video/quicktime"; break;
			case "avi": $ctype = "video/x-msvideo"; break;

			default: $ctype = "application/octet-stream";
		}
		return $ctype;
	}


	function get_file_content( $files )
	{
		if( empty($files) )
		{
			$this->msg( $this->T_('File not found') );
			return false;
		}

		$Files = $info = array();
		if( is_array( $files ) )
		{
			foreach( $files as $file )
			{
				if( !$content = $this->get_data( $this->path.$file['name_md5'] ) )
				{
					$this->msg( 'Cannot attach file ['.$file['name'].']' );
					return false;
				}
				$Files[] = array('name' => $file['name'], 'ctype' => $file['type'], 'content' => $content);
			}
			//die( var_export($Files) );
		}
		return $Files;
	}


	function get_total_size( $kb = false )
	{
		global $DB;

		// Load files settings
		$this->files = $DB->get_results( 'SELECT * FROM '.$this->get_sql_table('uploads'), 'ARRAY_A' );

		$size = 0;
		if( !empty($this->files) )
		{
			foreach( $this->files as $file )
			{
				$size = $size + $file['size'];
			}
		}

		if( $kb )
		{
			return number_format( $size/1024, 2 ).' '.$this->T_('KB').'.';
		}
		return $size;
	}


	function check_uploads_dir( $msg = true )
	{
		if( !is_dir($this->path) ) @mkdir($this->path);

		if( $msg && !is_writable($this->path) )
		{
			$this->msg( sprintf( $this->T_('You must create the following directory with write permissions (777):%s'), '<br />'.$this->path), 'error' );
		}
	}


	/**
	 * Convert a string from one charset to another.
	 *
	 */
	function convert_charset( $string, $dest_charset, $src_charset = NULL )
	{
		if( $dest_charset == $src_charset || $dest_charset == '' )
		{ // no conversation required
			return $string;
		}
		if( empty($src_charset) )
		{
			if( function_exists('mb_detect_encoding') )
			{
				$detect_string = $string.'a';
				$src_charset = mb_detect_encoding($detect_string, 'UTF-8, ISO-8859-1, ISO-8859-15', true);
			}
		}
		if( function_exists('mb_convert_variables') )
		{ // mb_string extension:
			mb_convert_variables( $dest_charset, $src_charset, $string );
		}
		return $string;
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


	function get_unsubscribe_user( $login, $email )
	{
		global $DB;

		$SQL = 'SELECT * FROM T_users
				WHERE MD5(user_login) = "'.$DB->escape( strtolower(trim($login)) ).'"
				AND MD5(user_email) = "'.$DB->escape( strtolower(trim($email)) ).'"
				LIMIT 1';

		if( $User = $DB->get_row($SQL) )
		{
			return $User;
		}
		return false;
	}


	function shutdown( $task )
	{
		switch($task)
		{
			case 'lock':
				if( @touch( $this->path.'_lock' ) )
				{	// Lock file created, make sure we delete it at shutdown
					register_shutdown_function( array( &$this, 'shutdown' ), 'unlock' );
				}
				break;

			case 'unlock':
				if( file_exists($this->path.'_lock') )
				{
					if( @unlink( $this->path.'_lock' ) ) return true;
				}
				break;
		}
		return false;
	}


	/**
	 * @return boolean Does a given column name exist in DB?
	 */
	function db_col_exists( $table, $col_name )
	{
		global $DB;

		$col_name = strtolower($col_name);

		foreach( $DB->get_results('SHOW COLUMNS FROM '.$table) as $row )
			if( strtolower($row->Field) == $col_name )
				return true;

		return false;
	}


	/**
	 * Add a column, if it does not already exist.
	 */
	function db_add_col( $table, $col_name, $col_desc )
	{
		global $DB;

		if( $this->db_col_exists($table, $col_name) )
			return false;

		$DB->query( 'ALTER TABLE '.$table.' ADD COLUMN '.$col_name.' '.$col_desc );
	}
}

?>