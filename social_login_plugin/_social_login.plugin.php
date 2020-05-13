<?php
/**
 * This file implements the Social Login plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2012 Russian b2evolution - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Russian b2evolution
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class social_login_plugin extends Plugin
{
	var $name = 'Social Login';
	var $code = 'sn_soclogin';
	var $priority = 10;
	var $version = '1.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = '';

	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;

	// Internal
	private $_temp_email = 'temporary@email';	// make the email invalid to force users change it
	private $_comments_form = false;
	private $_providers_loaded = false;
	private $_signin_allowed = NULL;
	private $_providers = array();
	private $_HA = NULL;
	private $_debug = true; // set TRUE to enable


	function PluginInit( & $params )
	{
		// Start PHP session for HA
		@session_start();

		$this->short_desc = $this->T_('Social Login and Social Sharing with multiple providers');
		$this->long_desc = '';

		$this->_providers = array(
			array('id'	=> 'OpenID'),
			array(
				'id'				=> 'Google',
				'new_app_link'      => 'https://code.google.com/apis/console/',
				'notes'				=> '1. Go to @URL@ and create a new application.
2. Fill out any required fields such as the application name and description.
3. On the "Create Client ID" popup switch to advanced settings by clicking on (more options).
4. Provide @CALLBACK@ as Application Callback URL.
5. Once you have registered, copy and paste your application "Client ID" and "Client secret" here.',
			),
			array(
				'id'				=> 'Twitter',
				'new_app_link'      => 'https://dev.twitter.com/apps',
				'notes'				=> '1. Go to @URL@ and create a new application.
2. Fill out any required fields such as the application name and description.
3. Put your website domain in the Application Website and Application Callback URL fields.
4. Provide @CALLBACK@ as Application Callback URL or simply set you domain name.
5. Set the Application Type to Browser.
6. Set the Default Access Type to Read and Write.
7. Once you have registered, copy and paste your application "Consumer key" and "Consumer secret" here.',
			),
			array(
				'id'				=> 'Facebook',
				'new_app_link'		=> 'https://www.facebook.com/developers/',
				'notes'				=> '1. Go to @URL@ and create a new application.
2. Fill out any required fields such as the application name and description.
3. Put your website domain in the Site Url field.
4. Once you have registered, copy and paste your application "App ID" and "App Secret" here.',
			),
			array('id' => 'AOL'),
			array('id' => 'Mixi'),
			array('id' => 'Stackoverflow'),
			array('id' => 'Steam'),
			//array('id' => 'Hyves'),
			array(
				'id'				=> 'Live',
				'name'				=> 'Windows Live',
				'new_app_link'      => 'https://manage.dev.live.com/ApplicationOverview.aspx',
				'notes'				=> '1. Go to @URL@ and create a new application.
2. Enter your domain name into "Redirect Domain" field.
3. Copy your "Client ID" and "Client secret" here.',
			),
			array(
				'id'				=> 'Yahoo',
				'name'				=> 'Yahoo!',
				'new_app_link'      => 'https://developer.yahoo.com/oauth/',
			),
			array(
				'id'				=> 'MySpace',
				'new_app_link'      => 'http://developer.myspace.com/',
			),
			array(
				'id'				=> 'LinkedIn',
				'new_app_link'      => 'https://developer.linkedin.com/user/login',
			),
			array(
				'id'				=> 'Vkontakte',
				'callback'          => true,
				'new_app_link'      => 'http://vk.com/developers.php',
			),
			array(
				'id'				=> 'LastFM',
				'name'				=> 'Last.FM',
				'new_app_link'      => 'http://www.lastfm.com/api/account',
			),
			array(
				'id'				=> 'Identica',
				'new_app_link'      => 'https://identi.ca/settings/oauthapps/new',
			),
			array(
				'id'				=> 'Tumblr',
				'new_app_link'      => 'http://www.tumblr.com/oauth/apps',
				'notes'				=> '1. Go to @URL@ and create a new application.
2. Provide @CALLBACK@ as Default Callback URL.
3. Copy your "Client ID" and "Client secret" here.',
			),
			array(
				'id'				=> 'GitHub',
				'callback'          => true,
				'new_app_link'      => 'https://github.com/settings/applications/new',
			),
			array(
				'id'				=> 'Goodreads',
				'callback'          => true,
				'new_app_link'      => 'http://www.goodreads.com/api/keys',
			),
			array(
				'id'				=> 'Foursquare',
				'callback'          => true,
				'new_app_link'      => 'https://www.foursquare.com/oauth/',
				'notes'				=> '1. Go to @URL@ and create a new application.
2. Provide @CALLBACK@ as Application Callback URL.
3. Copy your "Client ID" and "Client Secret" here.',
			),
			array(
				'id'				=> 'px500',
				'new_app_link'      => 'http://developers.500px.com/settings/applications',
			),
			array(
				'id'				=> 'Gowalla',
				'callback'          => true,
				'new_app_link'      => 'http://gowalla.com/api/keys',
			),
			/*array(
				'id'				=> 'Skyrock',
				'new_app_link'      => 'https://www.skyrock.com/developer/application',
			),*/
		);
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
			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('users')." (
				user_ID INT(11) unsigned NOT NULL,
				user_key VARCHAR(40) NOT NULL,
				INDEX user_ID (user_ID),
				UNIQUE KEY user_key (user_key)
			)",

			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('sessions')." (
				sess_ID INT(11) unsigned NOT NULL auto_increment,
				sess_user_ID INT(11) unsigned NOT NULL,
				sess_provider VARCHAR(30) NOT NULL,
				sess_data TEXT NOT NULL,
				PRIMARY KEY (sess_ID),
				INDEX sess_user_ID (sess_user_ID),
				INDEX sess_provider (sess_provider),
				UNIQUE KEY sess_key (sess_user_ID, sess_provider)
			)",

			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('info')." (
				info_ID INT(11) unsigned NOT NULL auto_increment,
				info_user_ID INT(11) NOT NULL,
				info_user_data TEXT NOT NULL,
				PRIMARY KEY (info_ID),
				UNIQUE KEY info_user_ID (info_user_ID)
			)",
		);
	}


	function BeforeInstall()
	{
		return $this->check_requirements();
	}


	function check_requirements( $msg = true )
	{
		if( ! function_exists('curl_init') )
		{
			if($msg) $this->msg('This plugin requires the PHP libcurl extension be installed.', 'error');
			return false;
		}

		if( ! function_exists('json_decode') )
		{
			if($msg) $this->msg('This plugin requires the JSON PHP extension to be installed.', 'error');
			return false;
		}

		if( ! session_id() )
		{
			if($msg) $this->msg('This plugin requires the <a href="http://www.php.net/manual/en/book.session.php">PHP Sessions</a> to be enabled.', 'error');
			return false;
		}

		if( ! version_compare( PHP_VERSION, '5.2.0', '>=' ) )
		{
			if($msg) $this->msg('This plugin requires the <a href="http://php.net/">PHP 5.2</a> be installed.', 'error');
			return false;
		}

		if( extension_loaded('oauth') )
		{
			if($msg) $this->msg('This plugin requires the <a href="http://php.net/manual/en/book.oauth.php">PHP PECL OAuth extension</a> be disabled.', 'error');
			return false;
		}

		return true;
	}


	function AfterInstall()
	{
		$this->msg( sprintf( $this->T_('Click here to configure the %s plugin.'),
			'<a href="admin.php?ctrl=plugins&amp;action=edit_settings&amp;plugin_ID='.$this->ID.'">'.$this->name.'</a>' ), 'success' );
	}


	function GetDefaultSettings()
	{
		global $Settings;

		$GroupCache = & get_GroupCache();

		$r = array(
			'newusers_mustvalidate' => array(
				'label' => $this->T_('New users must activate by email'),
				'type' => 'checkbox',
				'defaultvalue' => true,
				'note' => $this->T_('Check to require users to activate their account by clicking a link sent to them via email.'),
			),
			'newusers_grp_ID' => array(
				'label' => $this->T_('Group for new users'),
				'defaultvalue' => $Settings->get('newusers_grp_ID'),
				'type' => 'select',
				'options' => $GroupCache->get_option_array(),
				'note' => $this->T_('Select a group for new users.'),
			),
			'no_email' => array(
				'label' => $this->T_('If no email supplied'),
				'type' => 'select',
				'onchange' => 'toggle_noemail()',
				'options' => array(
						'request' => $this->T_('a) Prompt for an email'),
						'assign' => $this->T_('b) Assign temporary email'),
					),
				'defaultvalue' => 'request',
				'note' => $this->T_('Some provides including Twitter, Blogger and LinkedIn do not supply email addresses.<br />What should we do if someone signs-in with such provider:').
						'<br />[a] - '.$this->T_('Display a form to enter an email address DURING registration.').
						'<br />[b] - '.$this->T_('Force users to enter an email address AFTER registration, within the time frame selected below.')
			),
			'maxtime_noemail' => array(
				'label' => $this->T_('Email wait time frame'),
				'defaultvalue' => 1,
				'type' => 'integer',
				'size' => 4,
				'maxlength' => 3,
				'note' => $this->T_('[hours]. The time after which we should delete accounts that don\'t have email address.'),
			),
			'icon_size' => array(
				'label' => $this->T_('Widget icons size'),
				'defaultvalue' => '32px',
				'type' => 'select',
				'options' => array(
						'24px' => $this->T_('Small icons 24x24 pixels'),
						'32px' => $this->T_('Medium icons 32x32 pixels')
					),
				'note' => $this->T_('Select icons size for the widget displayed in login and registration forms.'),
			),
			'debug' => array(
				'label' => $this->T_('Enable debug mode'),
				'type' => 'checkbox',
				'defaultvalue' => false,
				'note' => $this->T_('Enable debug mode in HybridAuth'),
			),
			'debug_file' => array(
				'label' => $this->T_('Debug filename'),
				'defaultvalue' => 'debuginfo.txt',
				'note' => sprintf( $this->T_('Enter debug filename. No path is allowed here, the file will be created in plugins folder. <a %s>Open plugin folder</a>.'), 'href="'.$this->get_plugin_url().'"' ),
				'valid_pattern' => array( 'pattern' => '~'.$Settings->get('regexp_filename').'~', 'error' => $this->T_('Please enter a valid filename.' ) ),
			),
			'plugin_end' => array('layout' => 'end_fieldset'),
			'major_start' => array(
					'layout' => 'begin_fieldset',
					'label' => $this->T_('Major providers'),
				),
		);

		$n = 0;
		foreach( $this->_providers as $pr )
		{
			$n++;

			$name = $pr['id'];
			if( isset($pr['name']) ) $name = $pr['name'];

			$link = '';
			if( empty($pr['new_app_link']) )
			{
				$note = $this->T_('Enable this provider.');
				if( $pr['id'] == 'OpenID' )
				{
					$note = sprintf( $this->T_('Allows signing-in with Blogger, Flikr, myOpenID, VeriSign, Wordpress.com, Mail.ru and <a %s>many other providers</a>.'), 'href="https://openid.net/get-an-openid/" target="_blank"' );
				}

				$r[$pr['id']] = array(
					'label' => $this->get_provider_icon($pr).$name,
					'type' => 'checkbox',
					'defaultvalue' => false,
					'note' => $note,
				);
			}
			else
			{
				$r[$pr['id']] = array(
						'label' => $this->get_provider_icon($pr).$name,
						'type' => 'checkbox',
						'defaultvalue' => ($n<6),
						'note' => $this->T_('Enable this provider.'),
					);

				$r[$pr['id'].'_key'] = array(
						'label' => $this->T_('Application Key'),
						'type' => 'text',
						'size' => 55,
						'note' => '<br />'.$this->T_('This is usually your application ID, key, username or email address.'),
					);

				$r[$pr['id'].'_secret'] = array(
						'label' => $this->T_('Application Secret'),
						'type' => 'text',
						'size' => 55,
						'note' => '<br />'.$this->T_('This is usually your application or consumer secret.').
									'<br />'.$this->get_setting_notes($pr),
					);
			}

			switch( $n )
			{
				case 4:
					$r['major_end'] = array('layout' => 'end_fieldset');
					$r['extra_start'] = array(
							'layout' => 'begin_fieldset',
							'label' => $this->T_('Additional providers'),
						);
					break;

				case ($n > 4 && $n < 8): break;
				default:
					$r['layout_'.$pr['id']] = array('layout' => 'separator');
			}
		}

		$r['plugin_end'] = array('layout' => 'end_fieldset');

		return $r;
	}


	function PluginSettingsEditDisplayAfter()
	{
		echo '<script type="text/javascript">
				function toggle_noemail()
				{
					value = jQuery("#edit_plugin_'.$this->ID.'_set_no_email").find(":selected")[0].value;
					if( value == "request" ) {
						jQuery("#ffield_edit_plugin_'.$this->ID.'_set_maxtime_noemail").hide();
					} else {
						jQuery("#ffield_edit_plugin_'.$this->ID.'_set_maxtime_noemail").show();
					}
				}
				jQuery( function() { toggle_noemail(); });
			</script>';
	}


	function AdminAfterMenuInit()
	{
		global $ctrl, $action, $Settings;

		if( $ctrl == 'plugins' && $action == 'edit_settings' && param('plugin_ID', 'integer') == $this->ID )
		{
			if( ! $Settings->get('newusers_canregister') )
			{	// Registration disabled
				$this->msg( sprintf( $this->T_('User registration is currently disabled! You can enable it on <a %s>this page</a>.'), 'href="admin.php?ctrl=registration"' ), 'note' );
			}
			$this->check_requirements();
		}
		$this->register_menu_entry( $this->name );
	}


	function AdminTabAction()
	{
		$action = param( $this->get_class_id('action'), 'string');

		if( $action )
		{
			switch( $action )
			{
				case 'clear-all':
					$this->clear_session_data('all');
					break;

				case 'clear-php':
					$this->clear_session_data('php');
					break;

				case 'clear-db':
					$this->clear_session_data('db');
					break;

				case 'clear-user':
					$this->clear_session_data('db');
					break;

				case 'restore-session':
					$this->restore_session_data(false);
					break;
					break;

				case 'create-login':
					$login = $this->make_login_unique( param( $this->get_class_id('login'), 'string' ) );
					$this->msg($login);
					break;
			}
		}
	}


	function AdminTabPayload()
	{
		global $ctrl;

		if( !empty($_SESSION['HA::STORE']) )
		{
			echo '<pre>'.var_export($_SESSION, true).'</pre>';
		}

		$action_ID = $this->get_class_id('action');
		$action_url = regenerate_url($action_ID).'&'.$action_ID.'=';

		echo '<ul>
				<li><a href="'.$action_url.'restore-session">Restore session data</a></li>
				<li><a href="'.$action_url.'clear-all">Clear session data (everything)</a></li>
				<li><a href="'.$action_url.'clear-php">Clear session data (PHP)</a></li>
				<li><a href="'.$action_url.'clear-db">Clear session data (database)</a></li>
				<li><a href="'.$action_url.'clear-user">Clear session data (user session key)</a></li>
			  </ul>';

		$Form = new Form();
		$Form->begin_form('fform');
		$Form->begin_fieldset('Testing stuff');
		$Form->text_input( $this->get_class_id('login'), param( $this->get_class_id('login'), 'string' ), 20, 'Login', '' );
		$Form->end_fieldset();
		$Form->button( array( 'submit', '', T_('Create unique login'), 'ActionButton' ) );

		$Form->hidden($this->get_class_id('action'), 'create-login');
		$Form->hidden('ctrl', $ctrl);
		$Form->hidden('tab', 'plug_ID_'.$this->ID);
		$Form->end_form();

		$this->disp_widget('auth');
	}


	/**
	 * Event handler: Called at the end of the "User profile" form.
	 *
	 * The corresponding action event is {@link Plugin::ProfileFormSent()}.
	 *
	 * @param array Associative array of parameters
	 *   - 'Form': the user profile form generating object (by reference)
	 *   - 'User': the edited user object (by reference)
	 *   - 'edit_layout':
	 *			"public" - public frontend user profile form (info only),
	 *			"private" - private frontend user profile form (editable),
	 */
	function DisplayProfileFormFieldset( & $params )
	{
		if( $params['edit_layout'] == 'private' )
		{
			$this->disp_widget('auth');
		}
	}


	function GetHtsrvMethods()
	{
		return array('auth', 'register');
	}


	function DisplayCommentFormFieldset( & $params )
	{
		global $Blog;

		if( ! $this->signin_allowed() ) return;

		if( $Blog->get_setting('ajax_form_enabled') )
		{
			// Create the placeholder
			echo '<fieldset id="AuthFormPlaceholder" style="display:none">
					<table width="100%" cellspacing="0" border="0"><tr><td align="center"><div class="SocLogin-placeholder"></div></td></tr></table>
				  </fieldset>';

			// Move the buttons to our placeholder in feedback form
			echo '<script type="text/javascript">
				jQuery(".SocLoginClass").appendTo(".SocLogin-placeholder");
				jQuery(".SocLoginClass").show();
				jQuery("#AuthFormPlaceholder").show();
				</script>';
		}
		else
		{
			$this->disp_widget();
		}
	}


	function DisplayLoginFormFieldset( & $params )
	{
		$this->disp_widget();
	}


	function DisplayRegisterFormFieldset( & $params )
	{
		$this->disp_widget();
	}


	function SessionLoaded()
	{
		global $ReqURI, $htsrv_subdir, $blog;

		$this->dbg('SessionLoaded()');

		if( ! stristr( $ReqURI, $htsrv_subdir.'call_plugin.php' ) )
		{	// Restore HA session data only when we are not in call_plugin.php controller
			$this->restore_session_data();
		}
		else
		{
			$this->dbg('call_plugin.php');
		}

		// Add headlines to registration and login forms
		if( empty($blog) && preg_match( '~(('.preg_quote($htsrv_subdir, '~').'(login|register))|admin)\.php~i', $ReqURI ) )
		{
			require_css( $this->get_plugin_url().'rsc/'.$this->classname.'.css', true );
		}
	}


	function AdminEndHtmlHead()
	{
		require_css( $this->get_plugin_url().'rsc/'.$this->classname.'.css', true );
	}


	function SkinBeginHtmlHead()
	{
		global $Settings, $UserSettings, $current_User;

		if( is_logged_in() )
		{
			if( $time = $UserSettings->get($this->code.'_noemail') )
			{	// Display warning to users with temp email addresses
				$this->disp_noemail_message( $this->name.' plugin' );
				if( time() - ( $this->Settings->get('maxtime_noemail')*3600 ) >= $time )
				{	// Close past due accounts
					if( $current_User->update_status_from_Request( true, 'closed' ) )
					{	// user account was closed successful
						// Send notification email about closed account to users with edit users permission
						$email_template_params = array(
								'login'   => $current_User->login,
								'email'   => $current_User->email,
								'reason'  => 'No email address provided within selected time frame',
								'user_ID' => $current_User->ID,
							);
						send_admin_notification( NT_('User account closed'), 'close_account', $email_template_params );

						// log out current User
						logout();
					}
				}
			}

			if( ! $Settings->get('newusers_canregister') )
			{	// Registration disabled, we exit here
				return;
			}
		}

		require_css( $this->get_plugin_url().'rsc/'.$this->classname.'.css', true );
	}


	function SkinEndHtmlBody()
	{
		global $Blog;

		if( ! $this->signin_allowed() ) return;

		if( $this->_comments_form && $Blog->get_setting('ajax_form_enabled') )
		{	// Commetns are allowed, let's load widget javascript
			$this->disp_widget( 'login', true );
		}
	}


	function DisplayItemAsHtml( & $params )
	{
		global $c, $tb, $pb;

		// Do not check again if already set
		if( $this->_comments_form ) return;

		if( (!empty($c) || !empty($tb) || !empty($pb)) && $params['Item']->can_comment() )
		{	// Set the marker in order to display the widget code in footer
			$this->_comments_form = true;
		}
	}


	function disp_widget( $action = 'login', $no_display = false )
	{
		global $plugins_path, $ReqURI, $blog;

		if( $action == 'login' && ! $this->signin_allowed() ) return;

		// Do not show widget if AJAX comments are enabled
		// It will be enabled with jQuery
		$style = '';
		if( $no_display ) $style = ' style="display:none"';

		$params = array(
			'inskin'				=> isset($blog),
			'blog'					=> ( !empty($blog) ? intval($blog) : 0 ),
		);

		if( ! $providers = $this->get_providers() ) return;

		$size = $this->Settings->get('icon_size');
		$rsc_path = $plugins_path.$this->classname.'/rsc/'.$size.'/';

		if( ! $redirect_to = param( 'redirect_to', 'string' ) )
		{
			$redirect_to = $ReqURI;
		}

		$auth_url = url_add_param( $this->get_htsrv_url('auth'), 'action='.$action.'&amp;provider={provider}&redirect_to='.rawurlencode($redirect_to) );

		$r = '';
		switch( $action )
		{
			case 'login':
				$r .= '<span>'.$this->T_('Sign-in with another identity provider:').'</span>';
				break;

			case 'auth':
				$r .= '<span>'.$this->T_('Link your social accounts:').'</span>';
				break;
		}

		$list = array();
		foreach( $providers as $pr )
		{
			if(! file_exists( $rsc_path.strtolower($pr['id']).'.png' ) ) continue;

			$list[] = '<a href="'.str_replace( '{provider}', $pr['id'], $auth_url ).'">'.$this->get_provider_icon($pr, $size, true).'</a>';
		}

		if( !empty($list) )
		{
			$r .= '<div class="soclogin-buttons">'.implode( "\n", $list ).'</div>';
			echo '<div style="margin: 0 30px"><div class="SocLoginClass"'.$style.'>'.$r.'</div></div>';
		}
	}


	function htsrv_register( & $opts )
	{
		global $Session, $redirect_to;

		$this->dbg('htsrv_register() start');

		$redirect_to = param('redirect_to', 'string', true, true);

		if( ! $this->check_requirements( false ) )
		{
			$this->err('Unmet requirements. Check plugin\'s settings page.');
		}

		if( is_logged_in() )
		{
			$this->err('You are already registered');
		}

		// Get saved profile from session
		if( ! $U_array = $Session->get( $this->code.'_profile' ) )
		{	// No profile
			$this->err('You cannot access this page directly');
		}

		$U = new stdClass;
		foreach( $U_array as $k => $v )
		{	// Create a standard class from array
			$U->$k = $v;
		}

		if( ! is_object($U) || empty($U->b2evo_identifier) )
		{	// Invalid profile, delete it from session
			$Session->delete( $this->code.'_profile' );
			$Session->dbsave();

			$this->err('Invalid data received, registration cancelled');
		}

		$email = param('email', 'string');
		if( ! is_email($email) )
		{	// Display email request form and exit
			$this->display_new_account_form( $U, $email, true );
			exit;
		}

		// Everything seems to be OK, save the 'email' property
		$U->email = $email;

		// Delete profile from session since we are not going back
		$Session->delete( $this->code.'_profile' );
		$Session->dbsave();

		// Let's finally register the user
		if( $this->register_new_user($U, $redirect_to) )
		{	// User registered, link this provider to the user
			$this->link_successfull_provider( $U->b2evo_provider, $U->b2evo_session_key );
		}

		$this->dbg('htsrv_register() end');

		// Redirect back to caller
		header_redirect( $redirect_to );
	}


	function htsrv_auth( & $opts )
	{
		global $redirect_to;

		$this->dbg('htsrv_auth() start');

		$redirect_to = param('redirect_to', 'string', true, true);
		$action = param('action', 'string', true, true);

		if( ! $this->check_requirements( false ) )
		{
			$this->err('Unmet requirements. Check plugin\'s settings page.');
		}

		if( $action == 'login' && is_logged_in() )
		{
			$this->err('You are already logged in');
		}

		$provider = param('provider', 'string', true, true);
		if( ! $pr = $this->get_provider($provider) )
		{
			$this->err( sprintf('Unknown provider: %s', $provider) );
		}

		$openid = param('openid', 'string'); // don't memorize
		if( $provider == 'OpenID' && ! $openid && ! param('ha-return', 'boolean') )
		{	// This is an openID provider, let's display the form and exit
			$this->display_openid_form( $action, $redirect_to );
			exit;
		}

		// Memorize params regenerate_url()
		param('plugin_ID', 'string', true, true);
		param('method', 'string', true, true);

		// Force regenerate_url() create absolute URLs
		$GLOBALS['base_tag_set'] = true;

		// Tell HA to redirect back here at the end
		$adapter_params['hauth_return_to'] = regenerate_url('openid,ha-return', 'openid='.$openid.'&ha-return=true', '', '&');

		if( $openid )
		{	// OpenID submitted, let's clean it up
			// Add HTTP scheme if it's missing
			if( ! $this->is_url($openid) ) $openid = 'http://'.$openid;
			// Add forward slash to the end in order to match HA style
			if( $this->is_url($openid) && substr($openid, -1) != '/' ) $openid .= '/';
			// Add identifier to adapter params
			$adapter_params['openid_identifier'] = $openid;
		}

		try
		{
			if( $this->HA()->isConnectedWith('OpenID') && ! empty($openid) )
			{	// We submitted the form, however we already connected to previous OpenID provider
				// Reset current OpenID link and setup the new one if providers don't match

				// Get Adapter and user profile
				$AD = & $this->HA()->getAdapter('OpenID', $adapter_params);
				$U = $AD->getUserProfile();

				$this->dbg('Check OpenID (only if we already have existing OpenID connection)');
				if( $U->identifier != $openid )
				{	// User provided new OpenID, let's logout current adapter
					$AD->logout();
					$this->HA_reset(); // reset HA
					$this->dbg('Authenticating with the new OpenID provider:<br />'.$openid);
				}
			}


			// =================================
			$this->dbg('step 1');
			// STEP 1
			// Try to authenticate through HA
			if( ! $this->HA()->isConnectedWith($provider) )
			{	// Let's connect, get profile info and redirect to STEP 2
				$this->HA()->authenticate($provider, $adapter_params)->getUserProfile();

				// We should have exited already
				$this->err('Authentication failed');
			}


			// =================================
			$this->dbg('step 2');
			// STEP 2
			// We are already authenticated through HA

			// Get current adapter
			$AD = & $this->HA()->getAdapter($provider);

			// Get user profile
			$U = & $AD->getUserProfile();

			// Set some defaults to user profile
			$U->b2evo_provider = $provider;
			$U->b2evo_identifier = $this->get_identifier($U->b2evo_provider, $U->identifier);
			$U->b2evo_session_key = sha1( $U->b2evo_provider.$U->b2evo_identifier );
		}
		catch( exception $e )
		{
			$this->err( $e->getMessage() );
		}

		if( is_logged_in() )
		{	// Link provider to the user
			$this->link_successfull_provider( $U->b2evo_provider, $U->b2evo_session_key );
			$this->msg( sprintf( $this->T_('New provider linked: %s'), $U->b2evo_provider ), 'success' );
		}

		if( $action != 'login' )
		{	// We wanted to authenticate only (link new provider for future use)
			// Redirect back to caller
			header_redirect( $redirect_to );
		}


		// =================================
		$this->dbg('step 3');
		// STEP 3
		// Login linked user
		global $Session, $current_User;

		if( is_email($U->emailVerified) )
		{	// Currently only Facebook, Google, Yahaoo and Foursquare do provide the verified user email

			/**
			 * If user email is verified, then try to map to b2evo account
			 * Map only if there's one b2evo account (b2evo allows multiple accounts for a single email)
			 *
			 * WARNING: if a hacker gets access to user's Facebook account, he will be able to break into b2evo too
			 * even if the user didn't allow/link his Facebook account to b2evo account
			 */

			 // This is disabled for security reasons (see above)
			 // $existing_user_ID = $this->get_user_by_verified_email( $U->emailVerified );
		}

		if( empty($existing_user_ID) )
		{	// Check if we already have user linked to this session key
			// Note: to login with different providers, each such provider must be linked to a user first
			$existing_user_ID = $this->get_user_by_key($U->b2evo_session_key);
		}

		if( !empty($existing_user_ID) )
		{	// User found
			$UserCache = & get_UserCache();
			$current_User = & $UserCache->get_by_ID( $existing_user_ID, false, false );

			if( empty($current_User) )
			{	// User not found: unlink provider, delete session and exit
				$this->delete_provider_link($U->b2evo_session_key);
				$this->set_session_DB( $existing_user_ID, $U->b2evo_provider, 'clear' );

				$this->err( sprintf( $this->T_('Failed find b2evolution user. Unlinking current provider &laquo;%s&raquo;...'), $U->b2evo_provider ) );
			}

			if( $current_User->check_status('is_closed') )
			{	// Check and don't login if current user account was closed
				// Note: we don't unlink provider here because account might be opened later
				unset( $current_User );
				$this->err( $this->T_('This account is closed. You cannot log in.') );
			}
			else
			{	// save the user for later hits
				$Session->set_User( $current_User );
				$this->msg( sprintf( $this->T_('Successfully logged in with <b>%s</b> provider!'), $U->b2evo_provider ), 'success' );

				// Redirect back to caller
				header_redirect( $redirect_to );
			}
		}


		// =================================
		$this->dbg('step 4');
		// STEP 4
		// Register new user
		if( $this->register_new_user($U, $redirect_to) )
		{	// User registered, link this provider to the user
			$this->link_successfull_provider( $U->b2evo_provider, $U->b2evo_session_key );
		}

		$this->dbg('htsrv_auth() end');

		// Redirect back to caller
		header_redirect( $redirect_to );
	}


	function register_new_user( & $U, & $redirect_to )
	{
		global $Session, $Settings;

		if( ! $Settings->get('newusers_canregister') )
		{
			$this->err( $this->T_('User registration is currently not allowed.') );
		}

		// =================================
		// PART 1

		// Gender
		if( $U->gender && $G = substr($U->gender, 0, 1) ) $U->gender = strtoupper($G);
		if( $U->gender != 'F' && $U->gender != 'M' ) $U->gender = '';

		// Website
		if( strlen($U->webSiteURL) > 3 )
		{
			if( ! $this->is_url($U->webSiteURL) )
			{	// Add URL scheme
				$U->webSiteURL = 'http://'.$U->webSiteURL;
			}
		}
		else
		{
			$U->webSiteURL = NULL;
		}

		// Email
		$U->b2evo_email = $U->emailVerified;
		if( ! $U->emailVerified && $U->email )
		{
			$U->b2evo_email = $U->email;
		}

		$U->b2evo_email_is_temp = false;
		if( ! $U->b2evo_email || ! is_email($U->b2evo_email) )
		{	// Bad email address
			if( $this->Settings->get('no_email') == 'assign' )
			{	// Use temporariry email
				$U->b2evo_email = $this->_temp_email;
				$U->b2evo_email_is_temp = true;
			}
			else
			{
				$U_array = (array) $U;

				// Save profile to session
				$Session->delete( $this->code.'_profile' );
				$Session->set( $this->code.'_profile', $U_array ); // cast to array
				$Session->dbsave();

				// Display email request form and exit
				$this->display_new_account_form( $U );
				exit;
			}
		}

		// Login
		$U->b2evo_login = $U->displayName;
		if( ! $U->displayName )
		{
			if( $U->firstName )
			{	// Use first name
				$U->b2evo_login = $U->firstName;
			}
			elseif( $U->lastName )
			{	// Use last name
				$U->b2evo_login = $U->lastName;
			}
		}

		$strict_logins = $Settings->get('strict_logins');

		if( ! $this->sanitize_login( $U->b2evo_login, $strict_logins ) )
		{	// Bad login
			if( ! $U->b2evo_email_is_temp )
			{	// Try to get login from email (use first part before @ sign)
				$U->b2evo_login = preg_replace( '~@.+$~', '', $U->b2evo_email );

				if( ! $this->sanitize_login( $U->b2evo_login, $strict_logins ) )
				{	// Still can't get a valid login, let's generate one
					$U->b2evo_login = $this->make_login_unique('user');
				}
			}
			else
			{	// Generate unique login
				$U->b2evo_login = $this->make_login_unique('user');
			}
		}

		// Check login
		$check = is_valid_login($U->b2evo_login);
		if( ! $check || $check === 'usr' )
		{	// This should never happen since we already cleaned-up the login
			$this->dbg('Bad login: '.$U->b2evo_login);

			if( $check === 'usr' )
			{	// Special case, the login is valid however we forbid it's usage.
				$this->err('Logins cannot start with "usr_", this prefix is reserved for system use.');
			}
			elseif( $Settings->get('strict_logins') )
			{
				$this->err('Logins can only contain letters, digits and the following characters: _ .');
			}
			else
			{
				$this->err( sprintf( $this->T_('Logins cannot contain whitespace and the following characters: %s'), '\', ", >, <, @' ) );
			}
		}

		// Normalize login
		$U->b2evo_login = evo_strtolower( evo_substr( $U->b2evo_login, 0, 20 ) );

		if( user_exists($U->b2evo_login) )
		{	// Login taken
			$oldlogin = $U->b2evo_login;
			$U->b2evo_login = $this->make_login_unique( $U->b2evo_login );

			$this->msg( sprintf( $this->T_('The login &laquo;%s&raquo; is already registered, so we had to change your login to &laquo;%s&raquo;'), $oldlogin, $U->b2evo_login ), 'note' );
		}

		$this->dbg('<pre>'.var_export($U, true).'</pre>');


		// =================================
		// PART 2
		global $UserCache, $Plugins, $UserSettings, $DB, $Hit, $localtimenow, $secure_htsrv_url, $demo_mode;

		// Save trigger page
		$session_registration_trigger_url = $Session->get( 'registration_trigger_url' );
		if( empty( $session_registration_trigger_url ) && isset($_SERVER['HTTP_REFERER']) )
		{	// Trigger page still is not defined
			$session_registration_trigger_url = $redirect_to;
			$Session->set( 'registration_trigger_url', $session_registration_trigger_url );
		}

		$DB->begin();

		$new_User = new User();
		$new_User->set( 'login', $U->b2evo_login );
		$new_User->set( 'pass', md5( generate_random_passwd(14) ) ); // generate strong password
		$new_User->set( 'firstname', $U->firstName );
		$new_User->set( 'lastname', $U->lastName );
		$new_User->set( 'nickname', $U->displayName );
		$new_User->set( 'gender', $U->gender );
	//	$new_User->set( 'url', $U->webSiteURL );
		$new_User->set( 'source', $U->b2evo_provider );

		$new_User->set_datecreated( $localtimenow );
		$GroupCache = & get_GroupCache();
		$new_user_Group = & $GroupCache->get_by_ID( $this->Settings->get('newusers_grp_ID') );
		$new_User->set_Group( $new_user_Group );

		if( $U->b2evo_email_is_temp )
		{	// Do this for real emails only
			$new_User->set_email( $U->b2evo_email );
		}
		else
		{	// Simple email insert (no processing)
			$new_User->set( 'email', $U->b2evo_email );
		}
		$new_User->dbinsert();

		// User created:
		$DB->commit();
		$UserCache->add( $new_User );

		$initial_hit = $new_User->get_first_session_hit_params( $Session->ID );
		if( ! empty ( $initial_hit ) )
		{	// Save User Settings
			$UserSettings->set( 'initial_blog_ID' , $initial_hit->hit_blog_ID, $new_User->ID );
			$UserSettings->set( 'initial_URI' , $initial_hit->hit_uri, $new_User->ID );
			$UserSettings->set( 'initial_referer' , $initial_hit->hit_referer , $new_User->ID );
		}
		if( !empty( $session_registration_trigger_url ) )
		{	// Save Trigger page
			$UserSettings->set( 'registration_trigger_url' , $session_registration_trigger_url, $new_User->ID );
		}
		$UserSettings->set( 'user_ip', $Hit->IP, $new_User->ID );
		$UserSettings->set( 'user_domain', $Hit->get_remote_host( true ), $new_User->ID );
		$UserSettings->set( 'user_browser', substr( $Hit->get_user_agent(), 0 , 200 ), $new_User->ID );

		if( $U->b2evo_email_is_temp )
		{	// Save the time when temp account is created
			$UserSettings->set( $this->code.'_noemail', time(), $new_User->ID );
		}

		// TODO: Use the following info too
		/*
		'profileURL'
		'photoURL'
		'description'
		'language'
		'age'
		'birthDay'
		'birthMonth'
		'birthYear'
		'phone'
		'address'
		'country'
		'region'
		'city'
		'zip'
		*/
		$UserSettings->dbupdate();

		// Send notification email about new user registrations to users with edit users permission
		$email_template_params = array(
			//	'country'     => $country,
				'firstname'   => $U->firstName,
				'gender'      => $U->gender,
			//	'locale'      => $locale,
				'source'      => $U->b2evo_provider,
				'trigger_url' => $session_registration_trigger_url,
				'initial_hit' => $initial_hit,
				'login'       => $U->b2evo_login,
				'email'       => $U->b2evo_email,
				'new_user_ID' => $new_User->ID,
			);
		send_admin_notification( NT_('New user registration'), 'registration', $email_template_params );

		$Plugins->trigger_event( 'AfterUserRegistration', array( 'User' => & $new_User ) );

		if( $this->Settings->get('newusers_mustvalidate') )
		{ // We want that the user validates his email address:
			if( $new_User->send_validate_email($redirect_to) )
			{
				$activateinfo_link = 'href="'.$secure_htsrv_url.'login.php?action=req_validatemail'.'"';
				$this->msg( sprintf( $this->T_('An email has been sent to your email address. Please click on the link therein to activate your account. <a %s>More info &raquo;</a>'), $activateinfo_link ), 'success' );
			}
			elseif( $demo_mode )
			{
				$this->msg( $this->T_('Sorry, could not send email. Sending email in debug mode is disabled.' ), 'error' );
			}
			else
			{
				$this->msg( $this->T_('Sorry, the email with the link to activate your account could not be sent.')
					.'<br />'.T_('Possible reason: the PHP mail() function may have been disabled on the server.'), 'error' );
					// fp> TODO: allow to enter a different email address (just in case it's that kind of problem)
			}
		}

		// Autologin the user. This is more comfortable for the user and avoids
		// extra confusion when account validation is required.
		$Session->set_User( $new_User );
		$this->msg( sprintf( $this->T_('Successfully registered with <b>%s</b> provider!'), $U->b2evo_provider ), 'success' );

		// Set redirect_to pending from after_registration setting
		$after_registration = $Settings->get( 'after_registration' );
		if( $after_registration != 'return_to_original' )
		{	// Return to the specific URL which is set in the registration settings form
			$redirect_to = $after_registration;
		}

		return true;
	}


	function sanitize_login( & $login, $strict_logins )
	{
		// Remove forbidden chars from login
		$login = preg_replace( '~[\'"><@\s]~', '', $login );

		if( $strict_logins )
		{	// Remove another portion of chars from login
			$login = preg_replace( '~[^A-Za-z0-9_.]~', '', $login );
		}

		// Make sure logins don't start with "usr_"
		$login = preg_replace( '~^usr_(.*?)$~', 'user_\\1', $login );

		return ! empty($login); // true if not empty
	}


	function & HA()
	{
		if( is_null($this->_HA) )
		{
			require_once dirname(__FILE__).'/library/Hybrid/Auth.php';
			$this->_HA = new Hybrid_Auth( $this->get_config() );
		}
		return $this->_HA;
	}


	function HA_reset()
	{
		if( $this->_HA ) $this->_HA = NULL;
	}


	function get_config()
	{
		global $plugins_path;

		$debug = $this->Settings->get('debug');
		$debug_file = $plugins_path.$this->classname.'/'.$this->Settings->get('debug_file');

		if( $debug )
		{	// Try to create debug file if it's not there yet
			// If we fail to create the file HA will exit anyway
			if( ! file_exists($debug_file) ) @touch($debug_file);
		}
		else
		{	// Delete debug log for security reasons

			// Note: users can't delete anything outside of plugin's directory
			// because we don't allow paths in debug_file
			if( file_exists($debug_file) ) @unlink($debug_file);
		}

		$config = array(
				'base_url'		=> $this->get_plugin_url( true ).'library/',
				'debug_mode'	=> $debug,
				'debug_file'	=> $debug_file,
				'providers'		=> array(),
				'proxy'			=> false, // a workaround for "Undefined index: proxy" in HA v2.1.0
			);

		if( $providers = $this->get_providers() )
		{
			foreach( $providers as $pr )
			{
				$key = isset($pr['key']) ? $pr['key'] : '';
				$secret = isset($pr['secret']) ? $pr['secret'] : '';

				$config['providers'][$pr['id']] = array(
						'enabled' => true,
						'keys' => array(
								'id' => $key, // fake for OAuth1
								'key' => $key,
								'secret' => $secret,
							),
					);
			}
		}
		return $config;
	}


	function get_providers()
	{
		if( ! $this->_providers_loaded )
		{	// Load available providers
			$providers = array();

			foreach( $this->_providers as $pr )
			{
				if( $this->Settings->get($pr['id']) )
				{	// Enabled
					// There's no "name", let's use ID for name
					if( ! isset($pr['name']) ) $pr['name'] = $pr['id'];

					if( isset($pr['new_app_link']) )
					{	// Require key/secret
						$pr['key'] = $this->Settings->get($pr['id'].'_key');
						$pr['secret'] = $this->Settings->get($pr['id'].'_secret');

						// Require key OR secret to be set
						if( empty($pr['key']) && empty($pr['secret']) ) continue;
					}
					$providers[] = $pr;
				}
			}

			if( empty($providers) ) $providers = false;

			$this->_providers = $providers;
			$this->_providers_loaded = true;
		}
		return $this->_providers;
	}


	// Check if requested provider exists and enabled
	function get_provider( $provider )
	{
		if( $providers = $this->get_providers() )
		{
			foreach( $providers as $pr )
			{
				if( $pr['id'] == $provider ) return $pr;
			}
		}
		return false;
	}


	// Creates an authname that will remain unique across providers
	function get_identifier($provider, $identifier)
	{
		if( is_numeric($identifier) )
		{
			switch( $provider )
			{
				case 'Facebook':
					return 'http://www.facebook.com/profile.php?id='.$identifier;
				case 'Twitter':
					return 'http://twitter.com/account/profile?user_id='.$identifier;
				case 'Google':
					return 'https://www.google.com/profiles/'.$identifier;
			}
		}
		return $identifier;
	}


	function get_provider_icon( $pr, $size = '24px', $mark_connected = false )
	{
		if( ! is_array($pr) ) $pr = array('id'=>$pr);

		if( empty($pr['name']) )
		{
			$pr['name'] = $pr['id'];
		}

		$providerId = strtolower($pr['id']);
		$name = format_to_output($pr['name'], 'htmlattr');
		$src = $this->get_plugin_url().'rsc/'.$size.'/'.$providerId.'.png';
		$class = 'soclogin-btn';

		if( $mark_connected )
		{	// Check if this provider is connected and mark the button
			if( $this->HA()->isConnectedWith($pr['id']) )
			{
				$class = $class.' '.$class.'-connected';
				$name .= ' - Connected!'; // no translation
			}
		}
		return '<img src="'.$src.'" alt="'.$name.'" title="'.$name.'" class="'.$class.'" />';
	}


	function get_providers_array( $lowered = false )
	{
		$array = array();
		foreach( $this->_providers as $pr )
		{
			$array[] = ($lowered ? strtolower($pr['id']) : $pr['id']);
		}
		return $array;
	}


	function make_login_unique( $login )
	{
		// Crop to 14 chars to make space for 5 + 1 char suffix
		$login = evo_substr( $login, 0, 14 );

		$suffix = '-'.generate_random_key( rand(2, 5), 'abcdefghijklmnopqrstuvwxyz1234567890' );
		$len = strlen($suffix);
		$login .= $suffix;

		while( user_exists($login) )
		{	// Keep generating and checking new logins until we find the one that's not taken

			// Delete old suffix
			$login = substr( $login, - $len );

			// Add new suffix
			$suffix = '-'.generate_random_key( rand(2, 5), 'abcdefghijklmnopqrstuvwxyz1234567890' );
			$len = strlen($suffix);
			$login .= $suffix;
		}
		return $login;
	}


	function get_user_by_verified_email( $verified_email )
	{
		global $DB;

		$user_ID = $DB->get_col('SELECT user_ID FROM T_users WHERE LOWER(user_email) = '.$DB->quote( evo_strtolower($verified_email) ));

		// Allow only one user account
		if( count($user_ID) == 1 ) return $user_ID[0];

		return false;
	}


	function get_user_by_key( $key )
	{
		global $DB;
		return $DB->get_var('SELECT user_ID FROM '.$this->get_sql_table('users').' WHERE user_key = '.$DB->quote($key));
	}


	function delete_provider_link( $key )
	{
		global $DB;
		return $DB->query('DELETE FROM '.$this->get_sql_table('users').' WHERE user_key = '.$DB->quote($key));
	}


	// Get all saved HA sessions for current user
	function get_session_DB( $user_ID )
	{
		global $DB;

		$SQL = 'SELECT sess_data FROM '.$this->get_sql_table('sessions').' WHERE sess_user_ID = '.$DB->quote($user_ID);

		$session = array();
		if( $providers = $DB->get_col($SQL) )
		{
			foreach( $providers as $provider )
			{
				if( $data = unserialize($provider) )
				{	// Combine sessions from all providers together
					if( is_array($data) ) $session = array_merge( $session, $data );
				}
			}
		}

		return empty($session) ? false : $session;
	}


	function set_session_DB( $user_ID, $provider, $data )
	{
		global $DB;

		if( $data == 'clear' )
		{	// Delete all user providers
			if( empty($provider) )
			{
				$DB->query('DELETE FROM '.$this->get_sql_table('sessions').' WHERE sess_user_ID = '.$DB->quote($user_ID));
			}
			else
			{	// Delete particular user provider
				$DB->query('DELETE FROM '.$this->get_sql_table('sessions').'
							WHERE sess_user_ID = '.$DB->quote($user_ID).'
							AND sess_provider = '.$DB->quote($provider));
			}
		}
		else
		{	// Rewrite data on duplicate key
			$DB->query('INSERT INTO '.$this->get_sql_table('sessions').'
							( sess_user_ID, sess_provider, sess_data )
						VALUES ('.$DB->quote($user_ID).',
								'.$DB->quote($provider).',
								'.$DB->quote( serialize($data) ).')
						ON DUPLICATE KEY UPDATE sess_data = VALUES(sess_data)');
		}
	}


	/*
	 * Link provider to the user
	 *
	 * - we update provider data if different account with the same provider is used
	 * - we invalidate links with previous user when another user is linking a provider from backoffice
	 */
	function link_successfull_provider( $provider, $session_key )
	{
		global $Session, $DB;

		if( ! $Session->has_User() || empty($_SESSION['HA::STORE']) ) return;

		if( $some_other_user_ID = $this->get_user_by_key($session_key) )
		{	// There's already a user with this session
			if( $some_other_user_ID != $Session->user_ID )
			{	// It's some other b2evo user who have linked this provider before
				// Clear session for this provider so we can insert our session later
				$this->dbg( sprintf('Some other b2evo user have already linked same account on %s before. Unlinking that other user account first.', $provider) );
				$this->set_session_DB( $some_other_user_ID, $provider, 'clear' );
			}
		}

		// Save session
		// Get the keys to store for this provider
		if( $keys = $this->array_keys_contain( $_SESSION['HA::STORE'], '.'.strtolower($provider).'.' ) )
		{
			$data = array();
			foreach( $keys as $key )
			{	// Get values
				$data[$key] = $_SESSION['HA::STORE'][$key];
			}
			// Set user session data
			$this->set_session_DB( $Session->user_ID, $provider, $data );
			$this->dbg('Session data saved');
		}

		// Link provider
		$DB->query('INSERT INTO '.$this->get_sql_table('users').' (user_ID, user_key)
					VALUES ('.$DB->quote($Session->user_ID).', '.$DB->quote($session_key).')
					ON DUPLICATE KEY UPDATE user_ID = VALUES(user_ID), user_key = VALUES(user_key)');
	}


	function restore_session_data( $override = true )
	{
		global $Session;

		if( isset($_SESSION['HA::STORE']) && ! $override )
		{
			$this->dbg('Session data is already set, we are not going to override!');
			return NULL;
		}

		if( $Session->has_User() )
		{	// Get session data
			if( $sessiondata = $this->get_session_DB( $Session->user_ID, 1 ) )
			{	// Restore HA session data
				$_SESSION['HA::STORE'] = $sessiondata;
				//$this->_signin_allowed = false; // don't display sign-in buttons

				$this->dbg('Session data restored');
				return true;
			}
		}
		$this->dbg('Session data was NOT restored');
		return false;
	}


	/**
	 * Unlink ALL providers associated with current user
	 *
	 * Note: use set_session_DB() to unlink individual providers
	 */
	function clear_session_data( $what = 'all' )
	{
		global $Session, $DB;

		if( $what == 'all' || $what == 'user' )
		{
			if( $Session->has_User() )
			{	// Unlink user session keys associations
				$DB->query('DELETE FROM '.$this->get_sql_table('users').' WHERE user_ID = '.$DB->quote($Session->user_ID));
				$this->dbg('User session keys unlinked');
			}
		}

		if( $what == 'all' || $what == 'db' )
		{
			if( $Session->has_User() )
			{	// Clear user session data
				$this->set_session_DB( $Session->user_ID, '', 'clear' );
				$this->dbg('Session data cleared from database');
			}
		}

		if( $what == 'all' || $what == 'php' )
		{	// Also logout all current providers and destroy HA
			$this->HA()->logoutAllProviders();

			// Force logout
			if( !empty($_SESSION['HA::CONFIG']) ) unset($_SESSION['HA::CONFIG']);
			if( !empty($_SESSION['HA::STORE']) ) unset($_SESSION['HA::STORE']);

			// Reset HA
			$this->HA_reset();

			$this->dbg('PHP session cleared, all providers disconnected');
		}
	}


	function disp_noemail_message( $provider )
	{
		global $Blog, $baseurl;

		$base = $baseurl;
		if( !empty($Blog) ) $base = $Blog->gen_blogurl();
		$profile_url = url_add_param( $base, 'disp=userprefs' );

		$this->msg( sprintf( $this->T_('Although we have verified your account, %s did not provide us with your e-mail address. Please <a %s>enter one</a> to complete your registration.'), $provider, 'href="'.$profile_url.'"' ), 'note' );
	}


	function err( $str, $add_prefix = true )
	{
		global $redirect_to;

		if( $add_prefix ) $str = $this->name.': '.$str;
		$this->msg( $str, 'note' );

		$this->dbg('htsrv_auth() ERROR');

		if( !empty($redirect_to) ) header_redirect($redirect_to);

		debug_die($str);
	}


	function dbg( $msg )
	{
		if( $this->_debug ) $this->msg( '<b>SL-DEBUG:</b> '.$msg, 'note');
	}


	function signin_allowed()
	{
		global $Settings;

		if( is_null($this->_signin_allowed) )
		{
			$this->_signin_allowed = true;
			if( is_logged_in() || ! $Settings->get('newusers_canregister') )
			{
				$this->_signin_allowed = false;
			}
		}
		return $this->_signin_allowed;
	}


	function array_keys_contain( $array, $search_value, $strict = false )
    {
		$tmpkeys = array();

		$keys = array_keys($array);

		foreach ($keys as $k)
		{
			if( $strict && strpos($k, $search_value) !== false )
			{
				$tmpkeys[] = $k;
			}
			elseif( !$strict && stripos($k, $search_value) !== false )
			{
				$tmpkeys[] = $k;
			}
		}
		return $tmpkeys;
    }


	function is_url( $url )
	{
		return @preg_match('~^https?://.{4}~', $url);
	}


	function get_setting_notes( $pr )
	{
		if( empty($pr['notes']) )
		{
			$pr['notes'] = '1. Go to @URL@ and create a new application.
2. Copy your credentials here.';
		}

		$HA_url = $this->get_plugin_url( true ).'library/';

		$str = str_replace( '@URL@', '<a href="'.$pr['new_app_link'].'" target="_blank">'.$pr['new_app_link'].'</a>', $pr['notes'] );
		$str = str_replace( '@CALLBACK@', '<span style="padding:0 3px; color:#333; background:#E6E65C">'.$HA_url.'?hauth.done='.$pr['id'].'</span>', $pr['notes'] );
		$str = str_replace( "\n", '<br />', $str );

		return '<div style="margin: 5px 0">'.$str.'</div>';
	}


	function display_openid_form( $action, $redirect_to )
	{
		$title = $this->T_('Enter your OpenID');
		echo $this->get_chicago_page( 'header', array('title' => $title) );

		$prefill_openid = '';
		if( $this->HA()->isConnectedWith('OpenID') )
		{	// Already connected with OpenID
			$AD = & $this->HA()->getAdapter('OpenID');
			$U = & $AD->getUserProfile();
			$prefill_openid = $U->identifier;

			echo '<div class="action_messages"><div class="log_error">';
			echo $this->T_('You are already connected with an OpenID provider displayed below.<br /><br />The options are:<br />- keep current provider<br />- enter a new OpenID to change providers<br />- click "Cancel and return" to exit');
			echo '</div></div>';
		}

		$Form = new Form();
		$Form->begin_form('fform');
		echo '<h2>'.$this->get_provider_icon('OpenID').$title.'</h2>';
		$Form->text_input( 'openid', format_to_output($prefill_openid, 'formvalue'), 50, '', '', array('style'=>'width:99%') );
		$Form->button( array( 'submit', '', T_('Sign-in now!'), 'ActionButton' ) );

		$Form->hidden( 'plugin_ID', $this->ID );
		$Form->hidden( 'provider', 'OpenID' );
		$Form->hidden( 'redirect_to', $redirect_to );
		$Form->hidden( 'method', 'auth' );
		$Form->hidden( 'action', $action );
		$Form->end_form();

		echo '<div style="margin-top: -20px; text-align:right; font-size: 12px"><a href="'.$redirect_to.'">'.$this->T_('Cancel and return').'</a></div>';
		echo $this->get_chicago_page('footer');
	}


	function display_new_account_form( $U, $email = '', $is_failed_attempt = false )
	{
		global $redirect_to;

		$title = $this->T_('Enter your email address');
		echo $this->get_chicago_page( 'header', array('title' => $title) );

		echo '<div class="action_messages"><div class="log_error">';
		if( $is_failed_attempt )
		{
			echo sprintf( $this->T_('The email address is invalid, try another one') );
		}
		else
		{
			echo sprintf( strip_tags($this->T_('Although we have verified your account, %s did not provide us with your e-mail address. Please <a %s>enter one</a> to complete your registration.')), $U->b2evo_provider );
		}
		echo '</div></div>';

		$icon = '<img src="'.$this->get_plugin_url().'rsc/24px/email.png" class="soclogin-btn" />';

		$Form = new Form();
		$Form->begin_form('fform');
		echo '<h2>'.$icon.$title.'</h2>';
		$Form->text_input( 'email', format_to_output($email, 'formvalue'), 50, '', '', array('style'=>'width:99%') );
		$Form->button( array( 'submit', '', T_('Complete your registration!'), 'ActionButton' ) );

		$Form->hidden( 'plugin_ID', $this->ID );
		$Form->hidden( 'redirect_to', $redirect_to );
		$Form->hidden( 'method', 'register' );
		$Form->end_form();

		echo '<div style="margin-top: -20px; text-align:right; font-size: 12px"><a href="'.$redirect_to.'">'.$this->T_('Cancel and return').'</a></div>';
		echo $this->get_chicago_page('footer');
	}


	function get_chicago_page( $what = 'header', $params = array() )
	{
		$params = array_merge( array(
				'title'			=> '',
				'jquery'		=> false,
				'checkboxes'	=> false,
			), $params );

		// Add our CSS
		$this->AdminEndHtmlHead();

		if( $what == 'header' )
		{
			global $evo_charset, $adminskins_url;

			ob_start();

			echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
				<html xmlns="http://www.w3.org/1999/xhtml">
				<head>
				<meta http-equiv="Content-Type" content="text/html; charset='.$evo_charset.'" />
				<meta http-equiv="Expires" content="Sun, 15 Jan 1998 17:38:00 GMT" />
				<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
				<title>'.$params['title'].'</title>';

			require_css( 'basic_styles.css', 'rsc_url' ); // the REAL basic styles
			require_css( 'basic.css', 'rsc_url' ); // Basic styles
			require_css( 'results.css', 'rsc_url' ); // Results/tables styles
			require_css( 'item_base.css', 'rsc_url' ); // Default styles for the post CONTENT
			require_css( 'fileman.css', 'rsc_url' ); // Filemanager styles
			require_css( 'admin.global.css', 'rsc_url' ); // Basic admin styles
			require_css( $adminskins_url.'chicago/rsc/css/chicago.css', true );

			require_js( 'functions.js');
			require_js( 'form_extensions.js');
			require_js( 'rollovers.js' );
			require_js( 'dynamic_select.js' );
			require_js( 'admin.js' );

			add_css_headline('
				fieldset {  margin: 0 !important; padding: 0 !important; border:none !important }
				table.grouped tr.group td { color:#000 }
				.fieldset { border:none !important }
				.embed { text-align:center }
				.content { width: 530px; margin: 0 auto }
				.whitebox_center { margin:20px; padding:15px; background-color:#fff; border:2px #555 solid }
				.action_messages { margin:0 0 20px 0 !important; font-size: 12px }
				.log_error { margin: 0 }
				.label { display: none }
				.input { margin:0 0 7px 0 !important }
			');

			if( $params['jquery'] ) require_js( '#jquery#' );

			if( $params['checkboxes'] )
			{
				add_js_headline('
				var allchecked = Array();
				var idprefix;

				function toggleCheckboxes(the_form, the_elements, set_name )
				{
					if( typeof set_name == "undefined" )
					{
						set_name = 0;
					}
					if( allchecked[set_name] ) allchecked[set_name] = false;
					else allchecked[set_name] = true;

					var elems = document.forms[the_form].elements[the_elements];
					if( !elems )
					{
						return;
					}
					var elems_cnt = (typeof(elems.length) != "undefined") ? elems.length : 0;
					if (elems_cnt)
					{
						for (var i = 0; i < elems_cnt; i++)
						{
							elems[i].checked = allchecked[nr];
						} // end for
					}
					else
					{
						elems.checked = allchecked[nr];
					}
					setcheckallspan( set_name );
				}


				function setcheckallspan( set_name, set )
				{
					if( typeof(allchecked[set_name]) == "undefined" || typeof(set) != "undefined" )
					{ // init
						allchecked[set_name] = set;
					}

					if( allchecked[set_name] )
					{
						var replace = document.createTextNode("uncheck all");
					}
					else
					{
						var replace = document.createTextNode("check all");
					}

					if( document.getElementById( idprefix+"_"+String(set_name) ) )
					{
						document.getElementById( idprefix+"_"+String(set_name) ).replaceChild(replace, document.getElementById( idprefix+"_"+String(set_name) ).firstChild);
					}
					//else alert("no element with id "+idprefix+"_"+String(set_name));
				}

				function initcheckall( htmlid, init )
				{
					// initialize array
					allchecked = Array();
					idprefix = typeof(htmlid) == "undefined" ? "checkallspan" : htmlid;

					for( var lform = 0; lform < document.forms.length; lform++ )
					{
						for( var lelem = 0; lelem < document.forms[lform].elements.length; lelem++ )
						{
							if( document.forms[lform].elements[lelem].id.indexOf( idprefix ) == 0 )
							{
								var index = document.forms[lform].elements[lelem].name.substring( idprefix.length+2, document.forms[lform].elements[lelem].name.length );
								if( document.getElementById( idprefix+"_state_"+String(index)) )
								{
									setcheckallspan( index, document.getElementById( idprefix+"_state_"+String(index)).checked );
								}
								else
								{
									setcheckallspan( index, init );
								}
							}
						}
					}
				}');
			}

			include_headlines();
			echo '</head><body><div class="content"><div class="whitebox_center">';

			$out = ob_get_clean();
		}
		elseif( $what == 'footer' )
		{
			$out = '</div></div></body></html>';
		}

		return $out;
	}
}

?>