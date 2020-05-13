<?php
//This file implements the Janrain Engage plugin for b2evolution
//@copyright (c)2012 by Sonorth Corp. - {@link http://www.sonorth.com/}.
// (\t| )*// \s*.+
//\n\n\n
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class janrain_engage_plugin extends Plugin
{
	var $name = 'Janrain Engage';
	var $code = 'sn_jrengage';
	var $priority = 10;
	var $version = '1.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://www.sonorth.com';
	var $help_url = 'http://b2evo.sonorth.com/show.php/janrain-engage-plugin';

	var $licensed_to = array('name'=>'', 'email'=>'');

	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;

	// Internal
	var $temp_email = 'temporary@email';
	var $salt = 'XXX';
	var $_widget_js = NULL;
	var $_comments_form = false;

	var $copyright = 'THIS COMPUTER PROGRAM IS PROTECTED BY COPYRIGHT LAW AND INTERNATIONAL TREATIES. UNAUTHORIZED REPRODUCTION OR DISTRIBUTION OF %N PLUGIN, OR ANY PORTION OF IT THAT IS OWNED BY %C, MAY RESULT IN SEVERE CIVIL AND CRIMINAL PENALTIES, AND WILL BE PROSECUTED TO THE MAXIMUM EXTENT POSSIBLE UNDER THE LAW.<br /><br />THE %N PLUGIN FOR B2EVOLUTION CONTAINED HEREIN IS PROVIDED "AS IS." %C MAKES NO WARRANTIES OF ANY KIND WHATSOEVER WITH RESPECT TO THE %N PLUGIN FOR B2EVOLUTION. ALL EXPRESS OR IMPLIED CONDITIONS, REPRESENTATIONS AND WARRANTIES, INCLUDING ANY WARRANTY OF NON-INFRINGEMENT OR IMPLIED WARRANTY OF MERCHANTABILITY OR FITNESS FOR A PARTICULAR PURPOSE, ARE HEREBY DISCLAIMED AND EXCLUDED TO THE EXTENT ALLOWED BY APPLICABLE LAW.<br /><br />IN NO EVENT WILL %C BE LIABLE FOR ANY LOST REVENUE, PROFIT OR DATA, OR FOR DIRECT, SPECIAL, INDIRECT, CONSEQUENTIAL, INCIDENTAL, OR PUNITIVE DAMAGES HOWEVER CAUSED AND REGARDLESS OF THE THEORY OF LIABILITY ARISING OUT OF THE USE OF OR INABILITY TO USE THE %N PLUGIN FOR B2EVOLUTION, EVEN IF %C HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.';


	function PluginInit( & $params )
	{
		// load_class('plugins/model/_plugins_admin.class.php', 'Plugins_admin');
		// $Plugins_admin = new Plugins_admin();
		// if( $methods = $Plugins_admin->get_registered_events( $this ) )
		// {
			// echo "<pre>\n\tfunction ".implode( "(){}\n\tfunction ", $methods )."(){}\n</pre>"; die;
		// }

		$this->short_desc = $this->T_('Social Login and Social Sharing with multiple providers');
		$this->long_desc = 'This product is licensed to: [<em>'.$this->licensed_to['name'].'</em>]<br /><br /><br />'.
							str_replace( array('%N', '%C'), array( strtoupper($this->name), strtoupper($this->author) ), $this->copyright );
	}


	function BeforeInstall()
	{
		if( ! function_exists('curl_init') )
		{
			$this->msg('CURL extension is not loaded', 'error');
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

		load_funcs('users/model/_user.funcs.php');

		$GroupCache = & get_GroupCache();
		$url = 'https://rpxnow.com/account';

		if( !empty($this->Settings) )
		{
			$appId = $this->Settings->get('appId');
			$signinProviders = $this->Settings->get('signinProviders');
		}

		$r['api_key'] = array(
				'label' => $this->T_('Engage API Key'),
				'defaultvalue' => '',
				'size' => 50,
				'note' => '<ol><li>Enter your <a href="'.$url.'" target="_blank">Engage API Key</a> in the field above.</li><li>Setup Sign-In widget.</li><li>Add your website domain to the whitelist.</li><li>Click "Save" to apply changes and refresh settings from Janrain website.</li></ol>',
			);

		if( !empty($appId) && !empty($signinProviders) )
		{
			$r['info1'] = array(
					'label' => 'App ID',
					'type' => 'info',
					'info' => $appId,
				);
			$r['info2'] = array(
					'label' => $this->T_('Providers'),
					'type' => 'info',
					'info' => ucwords(str_replace(',', ', ', $signinProviders)),
				);
			$r['info3'] = array(
					'label' => $this->T_('Sign-in button layout'),
					'type' => 'info',
					'info' => $this->get_widget_buttons(false),
				);
		}

		$r['layout1'] = array(
				'layout' => 'separator',
			);

		$r['widget_type'] = array(
				'label' => $this->T_('Widget display type'),
				'defaultvalue' => 'modal',
				'type' => 'select',
				'options' => array(
						'modal' => $this->T_('Modal (displayed on click)'),
						'embedded' => $this->T_('Embedded')
					),
				'note' => $this->T_('Select widget layout.'),
			);

		$r['icon_size'] = array(
				'label' => $this->T_('Icons size'),
				'defaultvalue' => 'medium',
				'type' => 'select',
				'options' => array(
						'small' => $this->T_('Small icons 16x16 pixels.'),
						'medium' => $this->T_('Medium icons 32x32 pixels.')
					),
				'note' => $this->T_('Select widget button icons size (only for "Modal" widget layout).'),
			);

		$r['maxtime_noemail'] = array(
				'label' => $this->T_('Email wait time frame'),
				'defaultvalue' => 1,
				'type' => 'integer',
				'size' => 4,
				'maxlength' => 3,
				'note' => $this->T_('[hours]. The time after which we should delete accounts that don\'t have email address. Note that some provides including Twitter, Blogger and LinkedIn do not provide email addresses. If a user signs-in with such account, he/she is required to enter valid email address within a reasonable time frame.'),
			);

		$r['layout2'] = array(
				'layout' => 'separator',
			);

		$r['newusers_mustvalidate'] = array(
				'label' => $this->T_('New users must activate by email'),
				'type' => 'checkbox',
				'defaultvalue' => false,
				'note' => $this->T_('Check to require users to activate their account by clicking a link sent to them via email.'),
			);

		$r['newusers_grp_ID'] = array(
				'label' => $this->T_('Group for new users'),
				'defaultvalue' => $Settings->get('newusers_grp_ID'),
				'type' => 'select',
				'options' => $GroupCache->get_option_array(),
				'note' => $this->T_('Select a group for new users.'),
			);

		$r['user_status'] = array(
				'label' => $this->T_('Account status'),
				'defaultvalue' => 'autoactivated',
				'type' => 'select',
				'options' => get_user_statuses(),
				'note' => $this->T_('Select a status assigned to new users.'),
			);

		return $r;
	}


	function PluginSettingsUpdateAction()
	{
		$data = array();
		if( $key = param( 'edit_plugin_'.$this->ID.'_set_api_key', 'string' ) )
		{
			if( $valid_data = $this->lookup_api_key($key) )
			{
				$data = $valid_data;
				$this->msg( 'JR Engage: '.$this->T_('Your API Key is valid. Social providers updated.'), 'success' );
			}
		}

		$data = array_merge( array(
				'appId'				=> '',
				'realm'				=> '',
				'realmScheme'		=> '',
				'signinProviders'	=> '',
				'capabilities'		=> '',
			), $data );

		$this->Settings->set('appId', $data['appId']);
		$this->Settings->set('realmScheme', $data['realmScheme']);
		$this->Settings->set('realm', str_replace('.rpxnow.com', '', $data['realm']));
		$this->Settings->set('signinProviders', $data['signinProviders']);
		$this->Settings->set('capabilities', $data['capabilities']);

		if( empty($data) )
		{
			$this->msg( 'JR Engage: '.sprintf( $this->T_('Invalid API Key [%s]'), $key ), 'error' );
			return false;
		}
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
		}

		//$this->register_menu_entry( $this->name );
	}


	function GetHtsrvMethods()
	{
		return array('connect');
	}


	function DisplayCommentFormFieldset( & $params )
	{
		global $Blog;

		$this->disp_widget_tag( true );

		if( $Blog->get_setting('ajax_form_enabled') )
		{	// Move the trigger link to our placeholder in feedback form
			$selector = '#janrainEngageEmbed';
			if( $this->Settings->get('widget_type') == 'modal' )
			{
				$selector = '.janrainEngage';
			}

			echo '<script type="text/javascript">
				jQuery("'.$selector.'").appendTo(".janrainEngageEmbed-placeholder");
				jQuery("'.$selector.'").show();
				jQuery("#AuthFormPlaceholder").show();
				</script>';
		}
		else
		{
			$this->disp_widget('auth');
		}
	}


	function DisplayLoginFormFieldset( & $params )
	{
		$this->disp_widget('auth');
		$this->disp_widget_tag();
	}


	function DisplayRegisterFormFieldset( & $params )
	{
		$this->disp_widget('reg');
		$this->disp_widget_tag();
	}


	function SessionLoaded()
	{
		global $ReqURI, $htsrv_subdir, $blog;

		$search = '~(('.preg_quote($htsrv_subdir, '~').'(login|register))|admin)\.php~i';

		if( empty($blog) && preg_match( $search, $ReqURI ) )
		{	// Login and registration forms
			load_funcs('_core/_template.funcs.php');
			load_funcs('_core/_url.funcs.php');
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
			if( $time = $UserSettings->get('janrain_noemail') )
			{	// Display warning to users with temp email addresses
				$this->disp_noemail_message('Janrain Engage');
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
			{	// Registration disabled, let's exit
				return;
			}
		}


		require_css( $this->get_plugin_url().'rsc/'.$this->classname.'.css', true );
	}


	function SkinEndHtmlBody()
	{
		global $Settings, $Blog, $ReqURI;

		if( is_logged_in() || ! $Settings->get('newusers_canregister') ) return;

		if( $this->_comments_form && $Blog->get_setting('ajax_form_enabled') )
		{	// Commetns are allowed, let's load widget javascript
			if( $this->Settings->get('widget_type') == 'modal' )
			{
				//echo '<div class="janrainEngage" style="display:none">'.$this->get_widget_buttons().'</div>';
				echo '<a class="janrainEngage" href="'.format_to_output($ReqURI, 'htmlattr').'" style="display:none">'.$this->get_widget_buttons().'</a>';
			}
			else
			{
				echo '<div id="janrainEngageEmbed" style="display:none"></div>';
			}
			$this->disp_widget('auth');
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


	function htsrv_connect( & $opts )
	{
		global $DB, $Session, $Settings, $UserSettings, $current_User, $baseurl, $redirect_to;

		if( ! function_exists('curl_init') )
		{
			$this->err('CURL extension is not loaded');
		}

		if( empty($_GET['params']) )
		{
			$this->err('Bad params passed');
		}
		$params = trim($_GET['params']);

		if( ! $params = @base64_decode($params) )
		{
			$this->err('Bad params passed');
		}
		if( ! $params = @unserialize($params) )
		{
			$this->err('Bad params passed');
		}
		$this->check_received_crumb( 'jrconnect', $params );

		$redirect_to = isset($params['redirect_to']) ? $params['redirect_to'] : ''; // defaults to $_SERVER['HTTP_REFERER']

		if( empty($params['action']) )
		{
			$this->err('Unknown action');
		}

		$action = $params['action'];
		if( $action != 'auth' && $action != 'reg' )
		{
			$this->err('Unknown action');
		}

		$token = param('token', 'string', '', true);
		if( strlen($token) != 40 )
		{
			$this->err('Authentication canceled');
		}

		// STEP 2: Use the token to make the auth_info API call
		$post_data = array('token' => $token, 'apiKey' => $this->Settings->get('api_key'), 'format' => 'json');

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_URL, 'https://rpxnow.com/api/v2/auth_info');
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_FAILONERROR, true);

		if( ($result = curl_exec($curl)) === false )
		{
			$this->err( 'CURL error: '.curl_error($curl) );
		}
		curl_close($curl);

		// STEP 3: Parse the JSON auth_info response
		$auth_info = json_decode($result, true);

		if( $auth_info['stat'] != 'ok' )
		{
			$this->err( $auth_info['err']['msg'] );
		}

		$U = $auth_info['profile'];
		//var_export($U);

		// Hash user identifier
		$U['jr_ident'] = md5( $U['identifier'].$this->salt );

		$U['jr_email'] = '';
		if( !empty($U['verifiedEmail']) )
		{
			$U['jr_email'] = $U['verifiedEmail'];
		}
		elseif( !empty($U['email']) )
		{
			$U['jr_email'] = $U['email'];
		}

		$U['jr_username'] = '';
		if( empty($U['preferredUsername']) )
		{	// Get the first part of email before the @ sign.
			$U['jr_username'] = preg_replace( '~@.+$~', '', $U['jr_email'] );
		}
		else
		{
			$U['jr_username'] = $U['preferredUsername'];
		}

		// First attempt
		$check = is_valid_login($U['jr_username']);
		if( ! $check || $check === 'usr' )
		{
			// Bad username, trying to get one from email
			$U['jr_username'] = preg_replace( '~@.+$~', '', $U['jr_email'] );

			// Second attempt
			$check = is_valid_login($U['jr_username']);
			if( ! $check || $check === 'usr' )
			{
				if( $check === 'usr' )
				{	// Special case, the login is valid however we forbid it's usage.
					$msg = $this->T_('Logins cannot start with "usr_", this prefix is reserved for system use.');
				}
				elseif( $Settings->get('strict_logins') )
				{
					$msg = $this->T_('Logins can only contain letters, digits and the following characters: _ .');
				}
				else
				{
					$msg = sprintf( $this->T_('Logins cannot contain whitespace and the following characters: %s'), '\', ", >, <, @' );
				}

				$this->err($msg);
			}
		}

		$login = evo_substr( $U['jr_username'], 0, 20 );
		$login = evo_strtolower( $login );

		$UserCache = & get_UserCache();

		if( ($User = & $UserCache->get_by_login($login)) === false )
		{	// Not a registered yet
			$this->register_new_user($login, $U, $redirect_to, $params);
		}
		else
		{
			// CASE 1: User is authorizing with account used during registration
			// Let's check user identifier
			if( $UserSettings->get( 'janrain_identifier', $User->ID ) == $U['jr_ident'] )
			{	// Identifier matched, let's autologin the user
				$valid_user = $User->ID;
			}
			else
			{
				// CASE 2: User is authorizing with different account
				// See if we can find existing user by its identifier
				$SQL = 'SELECT uset_user_ID FROM T_users__usersettings
						WHERE uset_name = "janrain_identifier"
						AND uset_value = "'.$DB->escape($U['jr_ident']).'"';

				if( $user_ID = $DB->get_var($SQL) )
				{	// Found valid user
					$valid_user = $user_ID;
				}
				else
				{	// Let's edit login and register new user
					$n = 0;
					while( user_exists($login) )
					{
						$n++;
						$login = $login.'_'.$n;
					}
					$this->register_new_user($login, $U, $redirect_to, $params);
				}
			}

			if( !empty($valid_user) )
			{
				$current_User = & $UserCache->get_by_ID($valid_user);

				if( $current_User->check_status( 'is_closed' ) )
				{	// Check and don't login if current user account was closed
					unset( $current_User );
					$this->err( $this->T_('This account is closed. You cannot log in.'), false );
				}
				else
				{ // save the user for later hits
					$Session->set_User( $current_User );
					$this->msg( sprintf( $this->T_('Successfully authorized with <b>%s</b> account!'), $U['providerName'] ), 'success' );
				}
			}
		}

		if( empty( $redirect_to ) )
		{ // redirect_to param was not set
			if( !empty($params['inskin']) && !empty($params['blog']) )
			{
				if( $params['blog'] > 0 )
				{
					$BlogCache = & get_BlogCache();
					if( $Blog = $BlogCache->get_by_ID( $params['blog'], false, false ) )
					{
						$redirect_to = $Blog->gen_blogurl();
					}
				}
			}
			else
			{
				$redirect_to = $baseurl;
			}
		}

		header_redirect( $redirect_to );
	}


	function disp_widget( $action = 'auth' )
	{
		global $Settings;

		if( is_logged_in() || ! $Settings->get('newusers_canregister') ) return;

		echo $this->get_widget( $action );
	}


	function get_widget( $action = 'auth' )
	{
		global $Session, $ReqURI, $blog;

		if( ! $redirect_to = param( 'redirect_to', 'string' ) )
		{
			$redirect_to = $ReqURI;
		}

		$options = array(
			'action'			=> $action,
			'crumb_jrconnect'	=> $Session->create_crumb('jrconnect'),
			'redirect_to'		=> $redirect_to,
			'inskin'			=> isset($blog),
			'blog'				=> ( !empty($blog) ? intval($blog) : 0 ),
		);

		return $this->get_widget_js( $options );
	}


	function disp_widget_tag( $in_comments = false )
	{
		global $Settings, $Blog, $ReqURI;

		if( is_logged_in() || ! $Settings->get('newusers_canregister') ) return;

		if( $in_comments )
		{
			$style = 'style="display:none"';
			if( $Blog->get_setting('ajax_form_enabled') )
			{	// Should we rely on Javascript?
				$r = '<div class="janrainEngageEmbed-placeholder"></div>';
			}
		}
		else
		{
			$style = '';
			$r = '<div id="janrainEngageEmbed"></div>';
			if( $this->Settings->get('widget_type') == 'modal' )
			{
				//$r = '<div class="janrainEngage">'.$this->get_widget_buttons().'</div>';
				$r = '<a class="janrainEngage" href="'.format_to_output($ReqURI, 'htmlattr').'">'.$this->get_widget_buttons().'</a>';
			}
		}

		echo '<fieldset id="AuthFormPlaceholder"'.$style.'>
				<table width="100%" cellspacing="0" border="0"><tr><td align="center">'.$r.'</td></tr></table>
			  </fieldset>';
	}


	function get_widget_buttons( $with_text = true )
	{
		global $ReqURI;

		$r = '';
		if( $with_text )
		{
			$r .= '<span>'.$this->T_('Or log in with').'</span>';
		}

		if( $providers = $this->Settings->get('signinProviders') )
		{
			$size = $this->Settings->get('icon_size');
			$width = ($size == 'small') ? '108px' : '192px';

			$providers = explode( ',', $providers );
			foreach( $providers as $provider )
			{
				$list[] = '<div class="janrain-icon-'.$size.' janrain-'.$provider.'-'.$size.'" title="'.ucfirst($provider).'"></div>';
			}
			$r .= '<div style="width:'.$width.'">'.implode( "\n", $list ).'</div>';
		}
		return '<div class="janrain-button">'.$r.'</div>';
	}


	function get_widget_js( $params )
	{
		if( ! $realm = $this->Settings->get('realm') ) return;

		return '<script type="text/javascript">
			/* <![CDATA[ */
			(function() {
				if (typeof window.janrain !== "object") window.janrain = {};
				if (typeof window.janrain.settings !== "object") window.janrain.settings = {};

				janrain.settings.tokenUrl = "'.url_add_param( $this->get_htsrv_url('connect', '', '&', true), 'params='.base64_encode(serialize($params)), '&' ).'";

				function isReady() {
					janrain.ready = true;
					var authform = document.getElementById("AuthFormPlaceholder");
					if( authform !== null ) { authform.style.display = "block"; }
				}
				if (document.addEventListener) {
				  document.addEventListener("DOMContentLoaded", isReady, false);
				} else {
				  window.attachEvent("onload", isReady);
				}

				var e = document.createElement("script");
				e.type = "text/javascript";
				e.id = "janrainAuthWidget";

				if (document.location.protocol === "https:") {
				  e.src = "https://rpxnow.com/js/lib/'.$realm.'/engage.js";
				} else {
				  e.src = "http://widget-cdn.rpxnow.com/js/lib/'.$realm.'/engage.js";
				}

				var s = document.getElementsByTagName("script")[0];
				s.parentNode.insertBefore(e, s);
			})();
			/* ]]> */
			</script>';
	}


	function register_new_user( $login, $U, & $redirect_to, $params )
	{
		global $UserCache, $Plugins, $Session, $Settings, $UserSettings, $DB, $Hit;
		global $localtimenow, $secure_htsrv_url;

		if( ! $Settings->get('newusers_canregister') )
		{
			$this->err( $this->T_('User registration is currently not allowed.'), false );
		}

		if( empty($U['jr_email']) )
		{
			$U['jr_email'] = $this->temp_email;
		}

		if( $UserCache->get_by_login( $login ) )
		{ // The login is already registered
			$this->err( sprintf( $this->T_('The login &laquo;%s&raquo; is already registered, please choose another one.'), $login ), false );
		}

		/*
			$U['birthday'];
			$U['utcOffset'];
			$U['googleUserId'];
		*/

		$email = $U['jr_email'];
		$url = empty($U['url']) ? '' : $U['url'];
		$source = 'JR: '.$U['providerName'];

		$gender = empty($U['gender']) ? '' : strtoupper($U['gender']);
		if( $gender != 'F' && $gender != 'M' ) $gender = '';

		$firstname = $lastname = $nickname = '';
		if( !empty($U['displayName']) ) $nickname = $U['displayName'];
		if( !empty($U['name']) )
		{
			if( !empty($U['name']['formatted']) ) $firstname = $U['name']['formatted'];
			if( !empty($U['name']['givenName']) ) $firstname = $U['name']['givenName']; // Override
			if( !empty($U['name']['familyName']) ) $lastname = $U['name']['familyName'];
		}

		// Save trigger page
		$session_registration_trigger_url = $Session->get( 'registration_trigger_url' );
		if( empty( $session_registration_trigger_url ) && isset( $_SERVER['HTTP_REFERER'] ) )
		{	// Trigger page still is not defined
			$session_registration_trigger_url = $redirect_to;
			$Session->set( 'registration_trigger_url', $session_registration_trigger_url );
		}

		$DB->begin();

		$new_User = new User();
		$new_User->set( 'login', $login );
		$new_User->set( 'pass', md5( generate_random_passwd() ) ); // encrypted
		$new_User->set( 'firstname', $firstname );
		$new_User->set( 'lastname', $lastname );
		$new_User->set( 'nickname', $nickname );
		$new_User->set( 'gender', $gender );
		$new_User->set( 'url', $url );
		$new_User->set( 'source', $source );
		$new_User->set( 'status', $this->Settings->get('user_status') );
		$new_User->set_email( $email );
		$new_User->set_datecreated( $localtimenow );
		$GroupCache = & get_GroupCache();
		$new_user_Group = & $GroupCache->get_by_ID( $this->Settings->get('newusers_grp_ID') );
		$new_User->set_Group( $new_user_Group );

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

		// Memorize JR "identifier" hash so we can later identify this user easily
		$UserSettings->set( 'janrain_identifier', $U['jr_ident'], $new_User->ID );

		if( $email == $this->temp_email )
		{	// Save the time when temp account created
			$UserSettings->set( 'janrain_noemail', time(), $new_User->ID );
		}

		// TODO: fill social network account into a userfield
		$UserSettings->dbupdate();

		// Send email to admin (using his locale):
		/**
		 * @var User
		 */
		$AdminUser = & $UserCache->get_by_ID( 1 ); // fp> TODO: make this less arbitrary
		locale_temp_switch( $AdminUser->get( 'locale' ) );

		$email_template_params = array(
				'firstname'   => $firstname,
				'lastname'    => $lastname,
				'source'      => $source,
				'trigger_url' => $session_registration_trigger_url,
				'initial_hit' => $initial_hit,
				'login'       => $login,
				'email'       => $email,
				'new_user_ID' => $new_User->ID,
			);
		send_mail_to_User( $AdminUser->ID, $this->T_('New user registration').': '.$login, 'registration', $email_template_params );

		locale_restore_previous();

		$Plugins->trigger_event( 'AfterUserRegistration', array( 'User' => & $new_User ) );


		if( $this->Settings->get('newusers_mustvalidate') )
		{ // We want that the user validates his email address:
			$inskin_blog = NULL;
			if( !empty($params['inskin']) && !empty($params['blog']) )
			{
				if( $params['blog'] > 0 )
				{
					$inskin_blog = $params['blog'];
				}
			}

			if( $new_User->send_validate_email( $redirect_to, $inskin_blog ) )
			{
				$activateinfo_link = 'href="'.$secure_htsrv_url.'login.php?action=req_validatemail'.'"';

				if( $inskin_blog )
				{
					$BlogCache = & get_BlogCache();
					if( $Blog = $BlogCache->get_by_ID( $inskin_blog, false, false ) )
					{
						$redirect_to = $Blog->gen_blogurl();
						$activateinfo_link = 'href="'.url_add_param( $redirect_to, 'disp=activateinfo' ).'"';
					}
				}
				$this->msg( sprintf( $this->T_('An email has been sent to your email address. Please click on the link therein to activate your account. <a %s>More info &raquo;</a>'), $activateinfo_link ), 'success' );
			}
			else
			{
				$this->msg( $this->T_('Sorry, the email with the link to activate your account could not be sent.')
					.'<br />'.$this->T_('Possible reason: the PHP mail() function may have been disabled on the server.'), 'error' );
				// fp> TODO: allow to enter a different email address (just in case it's that kind of problem)
			}
		}
		else
		{	// TODO: Autovalidate the user
			//$UserSettings->set( '', '',$new_User->ID );
			//$UserSettings->dbupdate();
		}

		// Autologin the user. This is more comfortable for the user and avoids
		// extra confusion when account validation is required.
		$Session->set_User( $new_User );
		$this->msg( sprintf( $this->T_('Successfully registered with <b>%s</b> account!'), $U['providerName'] ), 'success' );

		// Set redirect_to pending from after_registration setting
		$after_registration = $Settings->get( 'after_registration' );
		if( $after_registration != 'return_to_original' )
		{	// Return to the specific URL which is set in the registration settings form
			$redirect_to = $after_registration;
		}
	}


	// TODO: add_domain_patterns
	function lookup_api_key( $key )
	{
		if( empty($key) )
		{
			$key = $this->Settings->get('api_key');
		}
		$post_data = array('apiKey' => $key, 'format' => 'json');

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_URL, 'https://rpxnow.com/plugin/lookup_rp');
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_FAILONERROR, true);

		if( ($result = curl_exec($curl)) === false )
		{
			$this->msg( 'JR Engage: CURL error ('.curl_error($curl).')', 'error' );
			curl_close($curl);
			return false;
		}
		curl_close($curl);

		if( $result == 'No RP found' )
		{
			$this->msg( 'JR Engage: No RP found', 'error' );
			return false;
		}

		$data = @json_decode($result, true);
		if( empty($data) || !is_array($data) )
		{
			$this->msg( 'JR Engage: Invalid response ['.var_export($data, true).']', 'error' );
			return false;
		}
		return $data;
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

		if( $add_prefix ) $str = 'Janrain Engage: '.$str;
		$this->msg( $str, 'note' );

		header_redirect($redirect_to);

		debug_die($str);
	}


	function check_received_crumb( $crumb_name, $params, $die = true )
	{
		global $Session, $servertimenow, $crumb_expires, $debug;

		if( empty($params['crumb_'.$crumb_name]) )
		{ // We did not receive a crumb!
			if( $die )
			{
				bad_request_die( 'Missing crumb ['.$crumb_name.'] -- It looks like this request is not legit.' );
			}
			return false;
		}

		$crumb_received = $params['crumb_'.$crumb_name];

		// Retrieve latest saved crumb:
		$crumb_recalled = $Session->get( 'crumb_latest_'.$crumb_name, '-0' );
		list( $crumb_value, $crumb_time ) = explode( '-', $crumb_recalled );
		if( $crumb_received == $crumb_value && $servertimenow - $crumb_time <= $crumb_expires )
		{	// Crumb is valid
			// echo '<p>-<p>-<p>A';
			return true;
		}

		$crumb_valid_latest = $crumb_value;

		// Retrieve previous saved crumb:
		$crumb_recalled = $Session->get( 'crumb_prev_'.$crumb_name, '-0' );
		list( $crumb_value, $crumb_time ) = explode( '-', $crumb_recalled );
		if( $crumb_received == $crumb_value && $servertimenow - $crumb_time <= $crumb_expires )
		{	// Crumb is valid
			// echo '<p>-<p>-<p>B';
			return true;
		}

		if( ! $die )
		{
			return false;
		}

		// ERROR MESSAGE, with form/button to bypass and enough warning hopefully.
		// TODO: dh> please review carefully!
		echo '<div style="background-color: #fdd; padding: 1ex; margin-bottom: 1ex;">';
		echo '<h3 style="color:#f00;">'.T_('Incorrect crumb received!').' ['.$crumb_name.']</h3>';
		echo '<p>'.T_('Your request was stopped for security reasons.').'</p>';
		echo '<p>'.sprintf( T_('Have you waited more than %d minutes before submitting your request?'), floor($crumb_expires/60) ).'</p>';
		echo '<p>'.T_('Please go back to the previous page and refresh it before submitting the form again.').'</p>';
		echo '</div>';

		if( $debug > 0 )
		{
			echo '<div>';
			echo '<p>Received crumb:'.$crumb_received.'</p>';
			echo '<p>Latest saved crumb:'.$crumb_valid_latest.'</p>';
			echo '<p>Previous saved crumb:'.$crumb_value.'</p>';
			echo '</div>';
		}

		echo '<div>';
		echo '<p class="warning">'.T_('Alternatively, you can try to resubmit your request with a refreshed crumb:').'</p>';
		$Form = new Form( '', 'evo_session_crumb_resend', $_SERVER['REQUEST_METHOD'] );
		$Form->begin_form( 'inline' );
		$Form->add_crumb( $crumb_name );
		$Form->hiddens_by_key( remove_magic_quotes($_REQUEST) );
		$Form->button( array( 'submit', '', T_('Resubmit now!'), 'ActionButton' ) );
		$Form->end_form();
		echo '</div>';

		die();
	}
}

?>