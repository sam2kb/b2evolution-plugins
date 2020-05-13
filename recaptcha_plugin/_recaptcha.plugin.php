<?php
/**
 * This file implements the reCAPTCHA plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2009 by Cary Mathews - {@link http://epapyr.us/2009/01/recaptcha}.
 * @copyright (c)2011 by Sonorth Corp. - {@link http://b2evo.sonorth.com/}.
 *
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Cary Mathews
 * @author Alex (sam2kb)
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class recaptcha_plugin extends Plugin
{
	var $name = 'reCAPTCHA';
	var $code = 'recaptcha';
	var $priority = 10;
	var $version = '2.0.1';
	var $author = 'Cary Mathews';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=22963';
	var $number_of_installs = 1;

	var $apply_rendering = 'never';
	var $group = 'antispam';


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Protects your site against spam');
		
		$this->long_desc = sprintf( $this->T_('<a %s>reCAPTCHA</a> is a free CAPTCHA service that protects your site against spam, malicious registrations and other forms of attacks where computers try to disguise themselves as a human; a CAPTCHA is a Completely Automated Public Turing test to tell Computers and Human Apart.'), 'href="http://www.google.com/recaptcha"' );
	}


	function GetDefaultSettings()
	{
		global $baseurl, $app_name;
		
		// Load the library
		require_once dirname(__FILE__).'/recaptchalib.php';
		
		$signup_url = recaptcha_get_signup_url( get_base_domain($baseurl), $app_name );
		
		return array(
			'pub_key' => array(
				'label' => $this->T_('Public Key'),
				'type' => 'text',
				'size' => 55,
				'maxlength' => 50,
				'note' => sprintf( $this->T_('<b>Public key</b> recieved when you <a %s>signed up</a>'),
							'href="'.$signup_url.'" target="_blank"' ),
			),

			'priv_key' => array(
				'label' => $this->T_('Private Key'),
				'type' => 'text',
				'size' => 55,
				'maxlength' => 50,
				'note' => sprintf( $this->T_('<b>Private key</b> recieved when you <a %s>signed up</a>'), 	
							'href="'.$signup_url.'" target="_blank"' ),
			),

			'req_to_reg' => array (
				'label' => $this->T_('Registration'),
				'type' => 'checkbox',
				'defaultvalue' => 1,
				'note' => $this->T_('Check to enable on registration form.'),
			),

			'req_to_login' => array (
				'label' => $this->T_('Login'),
				'type' => 'checkbox',
				'defaultvalue' => 0,
				'note' => $this->T_('Check to enable on login form.'),
			),

			'req_to_comment' => array (
				'label' => $this->T_('Comments'),
				'type' => 'checkbox',
				'defaultvalue' => 1,
				'note' => $this->T_('Check to enable on comment forms.'),
			),

			'req_to_msg' => array (
				'label' => $this->T_('Messages'),
				'type' => 'checkbox',
				'defaultvalue' => 1,
				'note' => $this->T_('Check to enable on email message forms.'),
			),

			'reg_user_pass' => array (
				'label' => $this->T_('Exempt Users'),
				'type' => 'checkbox',
				'defaultvalue' => 1,
				'note' => $this->T_('Check to let registered users submit comments and messages without a captcha.'),
			),

			'use_ssl' => array(
				'label' => $this->T_('Enable SSL'),
				'type' => 'checkbox',
				'defaultvalue' => 0,
				'note' => $this->T_('Check to enable reCAPTCHA to query over SSL; only use if you\'re already using SSL'),
			),
			
			'hr' => array(
				'layout' => 'separator',
			),

			'rC_apply_theme' => array(
				'label' => $this->T_('Apply Theme'),
				'type' => 'checkbox',
				'defaultvalue' => 0,
				'note' => $this->T_('Check to apply theme and tabindex changes below.'),
			),

			'rC_tab_index' => array(
				'label' => $this->T_('Tab Index'),
				'type' => 'integer',
				'defaultvalue' => 0,
				'size' => 3,
				'note' => '',
			),
			
			'rC_theme' => array(
				'label' => $this->T_('Theme'),
				'type' => 'select',
				'defaultvalue' => 'red',
				'note' => '',
				'options' => array (
						'red' => $this->T_('Red'),
						'white' => $this->T_('White'),
						'blackglass' => $this->T_('Blackglass'),
						'clean' => $this->T_('Clean')
					),
			),

			'rC_lang' => array(
				'label' => $this->T_('Language'),
				'type' => 'select',
				'defaultvalue' => 'en',
				'note' => '',
				'options' => array (
					'en' => $this->T_('English'),
					'nl' => $this->T_('Dutch'),
					'fr' => $this->T_('French'),
					'de' => $this->T_('German'),
					'pt' => $this->T_('Portuguese'),
					'ru' => $this->T_('Russian'),
					'es' => $this->T_('Spanish'),
					'tr' => $this->T_('Turkish')
				),
			),
		);
	}


	function DisplayCommentFormFieldset( & $params )
	{
		// Disabled on comment forms
		if( ! $this->Settings->get('req_to_comment') ) return;

		// Display reCaptcha iframe
		$this->display_recaptcha( $params );
	}


	function DisplayMessageFormFieldset( & $params )
	{
		// Disabled on message forms
		if( ! $this->Settings->get('req_to_msg') ) return;
		
		// Display reCaptcha iframe
		$this->display_recaptcha( $params );
	}


	function DisplayRegisterFormFieldset ( & $params ) 
	{
		// Disabled on registration form
		if( ! $this->Settings->get('req_to_reg') ) return;
		
		// Display reCaptcha iframe
		$this->display_recaptcha( $params );
	}


	function DisplayLoginFormFieldset ( & $params ) 
	{
		// Disabled on login form
		if( ! $this->Settings->get('req_to_login') ) return;
		
		// Display reCaptcha iframe
		$this->display_recaptcha( $params );
	}


	function CommentFormSent( & $params )
	{
		global $commented_Item;
		
		// Comment preview
		if( $params['action'] == 'preview' ) return;
		
		// Disabled on comment forms
		if( ! $this->Settings->get('req_to_comment') ) return;
		
		// Validate the answer. May return error message
		$this->validate_answer( $commented_Item->Blog );
	}


	function MessageFormSent( & $params )
	{
		// Disabled on message forms
		if( ! $this->Settings->get('req_to_msg') ) return;
		
		// Validate the answer. May return error message
		$this->validate_answer( $params['Blog'] );
	}


	function LoginAttempt( & $params )
	{
		global $login_error;
		
		// Disabled on login forms
		if( ! $this->Settings->get('req_to_login') ) return;
		
		$message = $this->validate_answer( NULL, true );
		
		if( !empty($message) )
		{	// There was en error, display it and exit
			$login_error = $message;
		}
	}


	function RegisterFormSent( & $params )
	{
		// Disabled on registration form
		if( ! $this->Settings->get('req_to_reg') ) return;
		
		$this->validate_answer( NULL );
	}
	
	
	function is_captcha_allowed( $Blog = NULL )
	{
		// Empty public or private key fields
		if( ! $this->Settings->get('pub_key') || ! $this->Settings->get('priv_key') ) return false;
		
		// Pass users
		if( is_logged_in() && $this->Settings->get('reg_user_pass') ) return false;
		
		if( !empty($Blog) )
		{	// No captcha on anonymous AJAX forms
			if( $Blog->get_setting('ajax_form_enabled') && !is_logged_in() ) return false;
			
			// No captcha on user AJAX forms, regardless of 'reg_user_pass' setting
			if( $Blog->get_setting('ajax_form_loggedin_enabled') && is_logged_in() ) return false;
		}
		return true;
	}


	function display_recaptcha( & $params )
	{
		global $Blog; // Blog is not defined on reg & login forms
		
		// See if we want to display recaptcha here
		if( ! $this->is_captcha_allowed($Blog) ) return;
		
		// Load the library
		require_once dirname(__FILE__).'/recaptchalib.php';
		
		$captcha_code = '';
		if( $this->Settings->get( 'rC_apply_theme' ) )
		{
			$captcha_code .= "<script type=\"text/javascript\">\n//<![CDATA[\n";
			$captcha_code .= "var RecaptchaOptions = {";
			$captcha_code .= "   theme : '".$this->Settings->get( 'rC_theme' )."',";
			$captcha_code .= "   tabindex : ".$this->Settings->get( 'rC_tab_index' ).",";
			$captcha_code .= "   lang : '".$this->Settings->get( 'rC_lang' )."'";
			$captcha_code .= "};\n//]]>\n</script>\n";
		}
		$captcha_code .= recaptcha_get_html( $this->Settings->get('pub_key'), NULL, $this->Settings->get('use_ssl') );
		
		if( (! isset($params['form_use_fieldset']) || $params['form_use_fieldset']) && !empty($params['Form']) ) 
		{	// Display fieldset
			$params['Form']->begin_fieldset();
			echo '<div class="label"></div><div class="input reCaptcha-block">'.$captcha_code.'</div>';
			$params['Form']->end_fieldset();
		}
		else
		{	// Other display modes
			echo $captcha_code;
		}
		
		// Do not display recaptcha params with Form::hiddens_by_key()
		if( isset($_REQUEST['recaptcha_challenge_field']) ) unset($_REQUEST['recaptcha_challenge_field']);
		if( isset($_REQUEST['recaptcha_response_field']) ) unset($_REQUEST['recaptcha_response_field']);
	}


	function validate_answer( $Blog = NULL, $return = false )
	{
		global $Hit;
		
		// See if we want to validate recaptcha here
		if( ! $this->is_captcha_allowed($Blog) ) return;
		
		$message = $this->T_('You provided an incorrect response to reCAPTCHA, please try again');
		
		$reC_challenge = param('recaptcha_challenge_field', 'string', NULL);
		$reC_response = param('recaptcha_response_field', 'string', NULL);
		
		if( empty($reC_challenge) || empty($reC_response) )
		{	// Empty recaptcha answer, exit here
			if( $return ) return $message;
			
			$this->msg( $message, 'error' );
			return;
		}
		
		// Load the library
		require_once dirname(__FILE__).'/recaptchalib.php';
		
		// Check the answer
		$resp = recaptcha_check_answer( $this->Settings->get('priv_key'), $Hit->IP, $reC_challenge, $reC_response );
		
		if( ! $resp->is_valid )
		{	// Invalid answer, exit here
			if( $return ) return $message;
			$this->msg( $message, 'error' );
		}
	}
}

?>