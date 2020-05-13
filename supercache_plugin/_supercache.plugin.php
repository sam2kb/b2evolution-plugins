<?php
/**
 *
 * This file implements the Super Cache plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2010 by Sonorth Corp. - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Sonorth Corp.
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class supercache_plugin extends Plugin
{
	var $name = 'Super Cache';
	var $code = 'supercache';
	var $priority = 30;
	var $version = '0.1';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=';
	
	var $apply_rendering = 'never';
	var $number_of_installs = 1;
	

	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Super Cache');
		$this->long_desc = $this->short_desc;
	}
	
	
	function AfterLoginAnonymousUser( $params )
	{
		global $instance_name, $cookie_path, $cookie_domain;
		
		if( ! empty($_COOKIE['cookie'.$instance_name.'nocache']) )
		{	// Remove nocache cookie
			setcookie( 'cookie'.$instance_name.'nocache', '', time()-172800, $cookie_path, $cookie_domain );
		}
		
		//$this->erase_form_cookies();
	}
	
	
	function AfterLoginRegisteredUser( $params )
	{
		$this->nocache();
	}
	
	
	function CommentFormSent( & $params )
	{
		$this->nocache( 60 );
		
		// Never save cookies
		$params['anon_cookies'] = NULL;
		
		if( $params['action'] != 'preview' )
		{
			$this->erase_form_cookies();
		}
	}
	
	
	function AfterCommentFormInsert( & $params )
	{
		global $Hit, $Session, $Messages, $instance_name, $cookie_path, $cookie_domain;
		
		$redirect_to = $params['Comment']->Item->get_permanent_url();
		
		$params['Comment']->send_email_notifications();
		
		$meta_redirect = '<meta http-equiv="refresh" content="2;url='.$redirect_to.'" />';
		
		if( !is_logged_in() )
		{
			// Set nocache cookie to 60 sec
			setcookie( 'cookie'.$instance_name.'nocache', '1', time() + 60, $cookie_path, $cookie_domain );
			
			$this->erase_form_cookies();
			
			load_class( '_core/model/_pagecache.class.php', 'PageCache' );
			$PageCache = new PageCache( $params['Comment']->Item->Blog );
			$PageCache->invalidate( $params['Comment']->Item->get_single_url() );
		}
		
		if( $Hit->is_opera() || $Hit->is_gecko() || is_logged_in() )
		{
			if( $params['Comment']->status == 'published' )
			{
				$Messages->add( T_('Your comment has been submitted.'), 'success' );
			}
			else
			{
				$Messages->add( T_('Your comment has been submitted. It will appear once it has been approved.'), 'success' );
			}
			
			// Set Messages into user's session, so they get restored on the next page (after redirect):
			$Session->set( 'Messages', $Messages );
			$Session->dbsave();
			
			//$meta_redirect = '';
		}
		
		$this->headers_content_mightcache( 'text/html', 0 );
		
		echo '<html>
			  <header>
				<title>'.T_('Your comment has been submitted.').'</title>
				'.$meta_redirect.'
				<meta name="robots" content="NOINDEX NOFOLLOW" />
			  </header>
			  <body>
			  <div style="margin:30px">
				<h2>'.T_('Your comment has been submitted. It will appear once it has been approved.').'</h2>
				<p>'.sprintf( T_('Click <a %s>here</a> to return to the website.'), 'href="'.$redirect_to.'"' ).'</p>
			  </div>
			  </body>
			  </html>';
		
		exit(0);
	}
	
	
	function nocache( $time_nocache = 315360000 )
	{
		global $instance_name, $cookie_path, $cookie_domain;
		
		if( empty($_COOKIE['cookie'.$instance_name.'nocache']) )
		{	// Set nocache cookie
			setcookie( 'cookie'.$instance_name.'nocache', '1', time()+$time_nocache, $cookie_path, $cookie_domain );
		}
		header('X-Accel-Expires: 0');
		header_nocache();
	}
	
	
	function erase_form_cookies()
	{
		global $cookie_name, $cookie_email, $cookie_url, $cookie_expired, $cookie_path, $cookie_domain;
		
		// Erase cookies:
		if( !empty($_COOKIE[$cookie_name]) )
		{
			setcookie( $cookie_name, '', $cookie_expired, $cookie_path, $cookie_domain);
		}
		if( !empty($_COOKIE[$cookie_email]) )
		{
			setcookie( $cookie_email, '', $cookie_expired, $cookie_path, $cookie_domain);
		}
		if( !empty($_COOKIE[$cookie_url]) )
		{
			setcookie( $cookie_url, '', $cookie_expired, $cookie_path, $cookie_domain);
		}
	}
	
	
	/**
	 * This is a placeholder for future development.
	 *
	 * @param string content-type; override for RSS feeds
	 * @param integer seconds
	 */
	function headers_content_mightcache( $type = 'text/html', $max_age = '#', $charset = '#' )
	{
		global $is_admin_page;

		header_content_type( $type, $charset );

		if( empty($max_age) || $is_admin_page || is_logged_in() )
		{	// Don't cache if no max_age given + NEVER EVER allow admin pages to cache + NEVER EVER allow logged in data to be cached:
			header_nocache();
			return;
		}
		
		// Do cache
	}
}

?>