<?php
/**
 * This file implements the Website Thumbshots plugin
 *
 * Author: Sonorth Corp. - {@link http://www.sonorth.com/}
 * License: GPL version 3 or any later version
 * License info: {@link http://www.gnu.org/licenses/gpl.txt}
 *
 * Version: 1.4.5
 * Date: 14-Sep-2012
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


// Load common functions
require_once dirname(__FILE__).'/inc/_common.funcs.php';


class thumbshots_plugin extends Plugin
{
	var $priority = 60;
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth CORP.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;
	var $displayed_item_ids = array();

	var $name = 'Website Thumbshots';
	var $code = 'thumbshots_plugin';
	var $version = '1.4.5';
	var $help_url = 'http://www.thumbshots.ru/en/website-thumbshots-wordpress-plugin';

	var $debug = 0;
	var $debug_IP = '';
	var $cache_dirname = 'thumbs_cache';

	// Internal
	var $thumbshots_class = 'inc/_thumbshots.class.php';
	var $thumbnails_path;
	var $display_preview = '#';		// fallback to plugin setting
	var $link_to_exit_page = '#';	// fallback to plugin setting
	var $_service_images;
	var $_head_scripts = array();


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Replace special tags in posts with website screenshots');
		$this->long_desc = $this->T_('This plugin allows any user to add previews of websites right in the content of their posts using a simple [thumb]http://domain.com[/thumb] format. Users may also "optionally" turn on mouseover previews. No purchase or registration is required to use the plugin. Optional upgrade enables PRO features such as "Full-length Captures", "Free Width Captures" and "Refresh on Demand".');

		$this->thumbnails_path = $GLOBALS['media_path'].$this->cache_dirname.'/';
		$this->thumbnails_url = $GLOBALS['media_url'].$this->cache_dirname.'/';

		// Register shortcode tags
		$this->add_shortcode( 'thumb', array($this, 'parse_shortcode') );
		$this->add_shortcode( 'thumbshot', array($this, 'parse_shortcode') );
	}


	function GetDefaultSettings()
	{
		$locale = $GLOBALS['current_locale'];
		if( $locale != 'ru_RU' )
		{
			$locale = 'en_US';
		}

		$register_url = 'http://my.thumbshots.ru/auth/register.php?locale='.str_replace('_', '-', $locale);
		$error_codes_url = 'http://www.thumbshots.ru/error-codes';

		$max_w = 1280;
		$onclick = 'onclick="Javascript:jQuery.get(this.href); jQuery(this).replaceWith(\'<span style=\\\'color:red\\\'>done</span>\'); return false;"';

		$r = array(
			'access_key' => array(
				'label' => $this->T_('Access Key'),
				'size' => 50,
				'note' => sprintf( $this->T_('Enter your access key here.<br /><a %s>Get your FREE account now</a>!'), 'href="'.$register_url.'" target="_blank"' ),
				'defaultvalue' => 'DEMOKEY002PMK1CERDMUI5PP5R4SPCYO',
			),
			'link' => array(
				'label' => $this->T_('Link images'),
				'defaultvalue' => true,
				'type' => 'checkbox',
				'note' => $this->T_('Check this to display clickable images.'),
			),
			'link_noindex' => array(
				'label' => $this->T_('Add rel="noindex" to links'),
				'defaultvalue' => false,
				'type' => 'checkbox',
				'note' => $this->T_('Check this to add rel="noindex" attribute to image links.'),
			),
			'link_nofollow' => array(
				'label' => $this->T_('Add rel="nofollow" to links'),
				'defaultvalue' => false,
				'type' => 'checkbox',
				'note' => $this->T_('Check this to add rel="nofollow" attribute to image links.'),
			),
			'sep0' => array(
				'layout' => 'separator',
			),
			'render_linktourl' => array(
				'label' => $this->T_('Render "Link to URL"'),
				'type' => 'checkbox',
				'defaultvalue' => 1,
				'note' => $this->T_('Check this if you want to render URLs from "Link to URL" field.'),
			),
			'allow_reloads' => array(
				'label' => $this->T_('Allow thumbshot reloads'),
				'type' => 'checkbox',
				'defaultvalue' => 1,
				'note' => $this->T_('Check this if you want to allow admins to reload/refresh individual thumbshots. Reload button pops-up when you hover over a thumbshot.'),
			),
			'link_to_exit_page' => array(
				'label' => $this->T_('Link to exit page'),
				'type' => 'checkbox',
				'defaultvalue' => 0,
				'note' => $this->T_('Check this if you want to link external URLs to an exit "goodbye" page.'),
			),
			'thumb_popups' => array(
				'label' => $this->T_('Display website preview'),
				'type' => 'checkbox',
				'defaultvalue' => 1,
				'note' => $this->T_('Display website previews when you hover over external post link.'),
			),
			'sep1' => array(
				'layout' => 'separator',
			),
			'quality' => array(
				'label' => $this->T_('Thumbshot quality'),
				'size' => 3,
				'defaultvalue' => 95,
				'type' => 'integer',
				'valid_range' => array( 'min' => 1, 'max' => 100 ),
				'note' => $this->T_('JPEG quality [1-100].'),
			),
			'width' => array(
				'label' => $this->T_('Default thumbshot width'),
				'size' => 3,
				'defaultvalue' => 120,
				'type' => 'integer',
				'valid_range' => array( 'max' => $max_w ),
				'note' => $this->T_('px.'),
			),
			'height' => array(
				'label' => $this->T_('Default thumbshot height'),
				'size' => 3,
				'defaultvalue' => 90,
				'type' => 'integer',
				'note' => $this->T_('px. Enter 0 to get full-length thumbshots.'),
			),
			'sep2' => array(
				'layout' => 'separator',
			),
			/*'original_image_q' => array(
				'label' => $this->T_('Original image quality'),
				'size' => 3,
				'defaultvalue' => 98,
				'type' => 'integer',
				'valid_range' => array( 'min' => 1, 'max' => 100 ),
				'note' => $this->T_('JPEG quality [1-100].'),
			),*/
			'original_image_w' => array(
				'label' => $this->T_('Original image width'),
				'size' => 3,
				'defaultvalue' => 640,
				'type' => 'integer',
				'valid_range' => array( 'max' => $max_w ),
				'note' => $this->T_('px. '),
			),
			'original_image_h' => array(
				'label' => $this->T_('Original image height'),
				'size' => 3,
				'defaultvalue' => 0,
				'type' => 'integer',
				'note' => $this->T_('px. Enter 0 to request full-length images from server.'),
			),
			'sep3' => array(
				'layout' => 'separator',
			),
			'display_preview' => array(
				'label' => $this->T_('Display header preview'),
				'type' => 'checkbox',
				'defaultvalue' => 1,
				'note' => $this->T_('Check this if you want to display website header preview on image hover (tooltip).'),
			),
			'preview_width' => array(
				'label' => $this->T_('Preview image width'),
				'size' => 3,
				'defaultvalue' => 320,
				'type' => 'integer',
				'note' => $this->T_('px.'),
			),
			'preview_height' => array(
				'label' => $this->T_('Preview image height'),
				'size' => 3,
				'defaultvalue' => 90,
				'type' => 'integer',
				'note' => $this->T_('px. Enter 0 to get full-length preview (not recommended).'),
			),
			'sep4' => array(
				'layout' => 'separator',
			),
			'cache_days' => array(
				'label' => $this->T_('Cache images'),
				'size' => 3,
				'defaultvalue' => 7,
				'valid_range' => array( 'min' => 0 ),
				'type' => 'integer',
				'note' => sprintf( $this->T_('days. How many days do you want to store image cache. Clear cache: <a %s>files</a> | <a %s>files and folders</a>.'),
					'href="'.$this->get_url('clear').'files" '.$onclick,
					'href="'.$this->get_url('clear').'everything" '.$onclick ),
			),
			'queued_cache_days' => array(
				'label' => $this->T_('Cache "queued" images'),
				'size' => 3,
				'defaultvalue' => 0,
				'valid_range' => array( 'min' => 0 ),
				'type' => 'integer',
				'note' => $this->T_('days. How many days do you want to store "queued" image cache.'),
			),
			'err_cache_days' => array(
				'label' => $this->T_('Cache "error" images'),
				'size' => 3,
				'defaultvalue' => 3,
				'valid_range' => array( 'min' => 0 ),
				'type' => 'integer',
				'note' => $this->T_('days. How many days do you want to store "error" image cache.'),
			),
			'sep5' => array(
				'layout' => 'separator',
			),
			'service_images_enabled' => array(
				'label' => $this->T_('Custom service images'),
				'type' => 'checkbox',
				'defaultvalue' => false,
				'note' => $this->T_('Check this to enable custom service images defined below.'),
			),
			'service_images' => array(
				'label' => $this->T_('Image definitions'),
				'note' => sprintf( $this->T_('[Error code = URL to JPEG image]<br />Here you can define custom service images displayed when a thumbshot cannot be loaded from our server.<br />See <a %s>this page</a> for a complete list of error codes you can use.'), 'href="'.$error_codes_url.'" target="_blank"' ),
				'type' => 'html_textarea',
				'rows' => 5,
				'cols' => 70,
				'defaultvalue' => '
all = http://domain.tld/image-general.jpg
0x0 = http://domain.tld/image-queued.jpg
0x12 = http://domain.tld/image-bad-host.jpg
',
			),
			'sep6' => array(
				'layout' => 'separator',
			),
			'debug' => array(
				'label' => $this->T_('Enable debug mode'),
				'type' => 'checkbox',
				'defaultvalue' => false,
				'note' => $this->T_('Display debug information during thumbshots processing. Warning: this will break your website layout!'),
			),
			'debug_ip' => array(
				'label' => $this->T_('Debug for selected IP'),
				'size' => 20,
				'note' => '[255.255.255.255]<br />'.$this->T_('Display debug information for this IP address only. Warning: this will break your website layout!'),
				'defaultvalue' => '',
			),
		);

		return $r;
	}


	function BeforeInstall()
	{
		if( ! function_exists('gd_info') )
		{
			$this->msg( $this->T_('You will not be able to automatically generate thumbnails for images. Enable the gd2 extension in your php.ini file or ask your hosting provider about it.'), 'error' );
			return false;
		}

		// Create cache directory
		snr_mkdir_r( $this->thumbnails_path );

		if( is_writable($this->thumbnails_path) )
		{	// Hide directory listing
			@touch( $this->thumbnails_path.'index.html' );
		}
		else
		{
			$this->msg( sprintf( $this->T_('You must create the following directory with write permissions (777):%s'), '<br />'.$this->thumbnails_path ), 'error' );
			return false;
		}
		return true;
	}


	function get_thumbshot( $params )
	{
		if( is_string($params) )
		{
			$params = array('url' => $params);
		}

		// Set defaults
		$params = array_merge( array(
				'url'		=> '',
				'width'		=> false,
				'height'	=> false,
				'display'	=> false,
				'exit_page' => '',
				'noindex'	=> NULL,
				'nofollow'	=> NULL,
			), $params );

		// Get thumbshot image
		$r = $this->get_image( $params['url'], $params['width'], $params['height'], $params['exit_page'], $params['noindex'], $params['nofollow'] );

		if( $params['display'] ) echo $r;

		return $r;
	}


	function init_thumbshot_class()
	{
		if( defined('THUMBSHOT_INIT') ) return;

		define('THUMBSHOT_INIT', true);

		require_once dirname(__FILE__).'/'.$this->thumbshots_class;

		$Thumbshot = new Thumbshot();

		if( $this->get_option('access_key') )
		{	// The class may use it's own preset key
			$Thumbshot->access_key = $this->get_option('access_key');
		}

		$Thumbshot->quality = $this->get_option('quality');
		$Thumbshot->create_link = $this->get_option('link');
		$Thumbshot->link_noindex = $this->get_option('link_noindex');
		$Thumbshot->link_nofollow = $this->get_option('link_nofollow');

		$Thumbshot->original_image_w = $this->get_option('original_image_w');
		$Thumbshot->original_image_h = $this->get_option('original_image_h');
		//$Thumbshot->original_image_q = $this->get_option('original_image_q');

		$Thumbshot->cache_days = $this->get_option('cache_days');
		$Thumbshot->err_cache_days = $this->get_option('err_cache_days');
		$Thumbshot->queued_cache_days = $this->get_option('queued_cache_days');

		// Use custom service images
		$Thumbshot->service_images = $this->get_service_images();

		if( $this->display_preview == '#' )
		{	// Global override setting
			$Thumbshot->preview_width = $this->get_option('preview_width');
			$Thumbshot->preview_height = $this->get_option('preview_height');
			$Thumbshot->display_preview = $this->get_option('display_preview');
		}

		if( $this->is_reload_allowed() )
		{	// Display a link to reload/refresh cached thumbshot image
			$Thumbshot->display_reload_link = true;
			$Thumbshot->reload_link_url = $this->get_url('reload');
		}

		$Thumbshot->debug = ( $this->debug || $this->get_option('debug') );
		$Thumbshot->debug_IP = ( $this->debug_IP ? $this->debug_IP : $this->get_option('debug_ip') );

		$Thumbshot->image_class = 'thumbshots_plugin';
		$Thumbshot->thumbnails_url = $this->thumbnails_url;
		$Thumbshot->thumbnails_path = $this->thumbnails_path;

		//set_param( 'Thumbshot', $Thumbshot );
		$GLOBALS['Thumbshot'] = $Thumbshot;
	}


	function get_image( $url, $w = false, $h = false, $exit_page = '', $noindex = NULL, $nofollow = NULL )
	{
		global $Thumbshot;

		if( empty($url) )
		{
			return;
		}

		if( ! function_exists('gd_info') )
		{	// GD is not installed
			return;
		}

		if( empty($Thumbshot) )
		{	// Initialize Thumbshot class and set defaults
			$this->init_thumbshot_class();
		}

		if( strstr( $url, '|http' ) )
		{
			$tmpurl = @explode( '|http', $url );
			$url = $tmpurl[0];
		}

		if( preg_match( '~[^(\x00-\x7F)]~', $url ) && function_exists('idna_encode') )
		{	// Non ASCII URL, let's convert it to IDN:
			$idna_url = idna_encode($url);
		}

		$Thumbshot->url = $url;
		$Thumbshot->link_url = isset($tmpurl[1]) ? 'http'.$tmpurl[1] : '';
		$Thumbshot->idna_url = isset($idna_url) ? $idna_url : '';

		$Thumbshot->width = ($w === false) ? $this->get_option('width') : $w;
		$Thumbshot->height = ($h === false) ? $this->get_option('height') : $h;

		$Thumbshot->display_preview = ($this->display_preview != '#') ? $this->display_preview : $this->get_option('display_preview');

		if( $exit_page == '' )
		{
			$exit_page = $this->get_option('link_to_exit_page');
		}

		if( $exit_page == 1 )
		{	// Link thumbshot to an exit "goodbye" page
			$Thumbshot->link_to_exit_page = true;
			$Thumbshot->exit_page_url = $this->get_url('exit');
		}
		else
		{
			$Thumbshot->link_to_exit_page = false;
			$Thumbshot->exit_page_url = '';
		}

		if( is_null($noindex) )
		{
			$noindex = $this->get_option('link_noindex');
		}
		$Thumbshot->link_noindex = $noindex;

		if( is_null($nofollow) )
		{
			$nofollow = $this->get_option('link_nofollow');
		}
		$Thumbshot->link_nofollow = $nofollow;

		// Get the thumbshot
		return $Thumbshot->get();
	}


	function parse_shortcode( $p, $url )
	{
		$p = $this->shortcode_atts( array('w'=>false, 'h'=>false, 'e'=>'', 'nofollow'=>NULL, 'noindex'=>NULL), $p );
		return $this->get_image( $url, $p['w'], $p['h'], $p['e'], $p['noindex'], $p['nofollow'] );
	}


	function get_service_images()
	{
		if( is_null($this->_service_images) )
		{
			$this->_service_images = array();
			if( $this->get_option('service_images_enabled') && $this->get_option('service_images') )
			{
				$service_images = array();
				$ims = $this->get_option('service_images');
				$ims = explode( "\n", trim($ims) );

				foreach( $ims as $img )
				{
					list($k,$v) = explode( '=', $img );

					$k = trim($k);
					$v = trim($v);

					if( preg_match( '~^((.+x\d+)|all)$~', $k ) && preg_match( '~^https?://.{3}~', $v ) )
					{	// It looks like a valid image definition
						$service_images[$k] = $v;
					}
				}
				$this->_service_images = $service_images;
			}
		}

		return $this->_service_images;
	}


	function BeforeBlogDisplay()
	{
		set_param( 'THUMBplugin', $this );

		$this->reload_thumbshot();

		// Display an exit page if requested, then exit
		$this->display_exit_page();

		global $plugins_url;
		require_css( $plugins_url.'thumbshots_plugin/thumbshots.css', true );

		if( $this->is_reload_allowed() )
		{	// Add jQuery for reload links
			require_js('#jquery#');
		}

		if( $this->display_preview && $this->get_option('display_preview') )
		{	// Add internal preview javascript
			$this->_head_scripts[] = 'ThumbshotPreview("ThumbshotPreview");';
			require_js('#jquery#');
			require_js($plugins_url.'thumbshots_plugin/thumbshots.js', true);
		}
		if( $this->get_option('thumb_popups') )
		{	// Add external javascript
			$this->_head_scripts[] = 'ThumbshotExt("ThumbshotExt");';
			require_js('#jquery#');
			require_js($plugins_url.'thumbshots_plugin/thumbshots.js', true);
		}
	}


	function SkinBeginHtmlHead()
	{
		if( $this->_head_scripts )
		{
			add_js_headline('jQuery(function() { '.implode( ' ', $this->_head_scripts ).' })');
		}
	}


	function AdminDisplayToolbar( & $params )
	{
		echo '<script type="text/javascript">
			//<![CDATA[
			jQuery(function() {
				var thumbshots_plugin_button = "<input type=\"button\" name=\"thumbshots\" class=\"quicktags thumbshots-plugin-button\" value=\"'.$this->T_('Add thumbshot').'\" />";
				jQuery( thumbshots_plugin_button ).prependTo( jQuery("div.edit_actions") );

				jQuery(".thumbshots-plugin-button").click(function(event) {
					event.preventDefault();

					var t_url = prompt( "'.$this->T_('Site URL').'", "http://" );

					if( t_url == null || t_url.length < 8 ) return;

					var t_width = prompt( "'.$this->T_('Thumbshot width').'", "'.$this->get_option('width').'" );
					var t_height = prompt( "'.$this->T_('Thumbshot height').'", "'.$this->get_option('height').'" );
					var t_url2 = prompt( "'.$this->T_('Link thumbshot to URL (optional)').'", "http://" );
					var t_ext = confirm( "'.$this->T_('Display an exit page if thumbshot is linked to URL').'?" );
					var t_noindex = confirm( "'.$this->T_('Add rel=\"noindex\" to thumbshot link').'?" );
					var t_nofollow = confirm( "'.$this->T_('Add rel=\"nofollow\" to thumbshot link').'?" );

					if( t_url2 !== null && t_url2.length > 7) t_url = t_url + "|" + t_url2;

					if(t_ext) t_ext = 1;
					else t_ext = 0;

					if(t_noindex) t_noindex = 1;
					else t_noindex = 0;

					if(t_nofollow) t_nofollow = 1;
					else t_nofollow = 0;

					var code = "[thumb";
					if(t_width) {
						t_width = t_width.replace(/[^0-9]*/g, "");
						if( t_width !="" ) code += " w=\"" + t_width + "\"";
					}
					if(t_height) {
						t_height = t_height.replace(/[^0-9]*/g, "");
						if( t_height !="" ) code += " h=\"" + t_height + "\"";
					}
					code += " e=\"" + t_ext + "\"";
					code += " noindex=\"" + t_noindex + "\"";
					code += " nofollow=\"" + t_nofollow + "\"";
					code += "]" + t_url + "[/thumb]";

					textarea_wrap_selection( b2evoCanvas, code, "", 1 );
				});

			});
			//]]>
		</script>';
	}


	function get_url( $type = 'reload' )
	{
		global $baseurl, $current_locale;

		switch( $type )
		{
			case 'reload':
				return $baseurl.'?thumb-reload/#md5#/#url#';
				break;

			case 'clear':
				return regenerate_url( 'action,plugin_ID,clear_thumb_cache', 'action=edit_settings&amp;plugin_ID='.$this->ID.'&amp;clear_thumb_cache=');
				break;

			case 'exit':
				return $baseurl.'?thumb-exit/#md5#/#url#&amp;redirect_to='.rawurlencode(snr_get_request('uri')).'&amp;lang='.substr( $current_locale, 0, 2 );
				break;
		}
		return false;
	}


	function is_reload_allowed()
	{
		if( $this->get_option('allow_reloads') && is_logged_in() && $GLOBALS['current_User']->check_perm( 'options', 'edit', false ) )
		{
			return true;
		}
		return false;
	}


	function AdminAfterMenuInit()
	{
		if( $GLOBALS['ctrl'] != 'plugins' || param( 'plugin_ID', 'integer' ) != $this->ID ) return;

		// Let's clear thumbnails cache
		switch( param( 'clear_thumb_cache', 'string' ) )
		{
			case 'files':
				snr_cleardir_r( $this->thumbnails_path, true );
				$this->msg( sprintf( $this->T_('Thumbnails cache has been cleared (%s)'), $this->T_('files') ), 'success' );
				break;

			case 'everything':
				snr_cleardir_r( $this->thumbnails_path, false );
				$this->msg( sprintf( $this->T_('Thumbnails cache has been cleared (%s)'), $this->T_('files and folders') ), 'success' );
				break;
		}

		$this->BeforeInstall();
	}


	function reload_thumbshot()
	{
		global $Thumbshot, $baseurl;

		if( ! $this->is_reload_allowed() ) return;

		if( preg_match( '~^\?thumb-reload/([a-z0-9]{32})/(aHR0c.*?)$~', str_replace( $baseurl, '', snr_get_request('url') ), $matches ) )
		{
			if( empty($Thumbshot) )
			{	// Initialize Thumbshot class and set defaults
				$this->init_thumbshot_class();
			}

			// Stage 1: request thumbshot reload
			$Thumbshot->args['refresh'] = 1;

			$url = @base64_decode($matches[2]);
			$md5 = md5($url.'+'.$Thumbshot->dispatcher);

			if( $md5 != $matches[1] )
			{
				echo 'Bad URL'; die;
			}

			$r = $Thumbshot->get_data( $Thumbshot->get_request_url($url) );

			// Stage 2: invalidate local cache
			if( $Thumbshot->cache_days > 1 )
			{
				$dir = $this->thumbnails_path.substr( $md5, 0, 3 ).'/';

				if( is_dir($dir) )
				{
					$scan = glob(rtrim($dir,'/').'/*');
					foreach( $scan as $index=>$path )
					{
						if( is_file($path) && strstr( $path, $dir.$md5 ) )
						{	// Change modification time so cache expires in ~ 1h 20m
							@touch( $path, time() - 3600 * 24 * ($Thumbshot->cache_days - 0.05) );
						}
					}
				}
			}
			exit();
		}
	}


	function display_exit_page()
	{
		global $Thumbshot, $baseurl;

		if( preg_match( '~^\?thumb-exit/([a-z0-9]{32})/(aHR0c.*?)&~i', str_replace( $baseurl, '', snr_get_request('url') ), $matches ) )
		{
			if( empty($Thumbshot) )
			{	// Initialize Thumbshot class and set defaults
				$this->init_thumbshot_class();
			}

			$url = @base64_decode($matches[2]);
			$md5 = md5($url.'+'.$Thumbshot->dispatcher);

			if( $md5 != $matches[1] )
			{
				echo 'Bad URL'; die;
			}

			if( ($cookie = @$_COOKIE['thumb_skip_exit_page']) && $cookie = 1 )
			{	// We found a cookie, let's redirect without asking
				header('Location: '.$url);
				exit;
			}

			$exit_template = 'exit_page';
			if( $lang = param('lang', 'string') )
			{
				$lang = strtolower( substr( $lang, 0, 2 ) );
				if( file_exists(dirname(__FILE__).'/inc/'.$exit_template.'-'.$lang.'.tpl') )
				{
					$exit_template .= '-'.$lang;
				}
			}

			if( $content = @file_get_contents( dirname(__FILE__).'/inc/'.$exit_template.'.tpl' ) )
			{
				$redirect_to = '/';
				if( $redirect_to = param('redirect_to', 'string') )
				{
					// Don't allow absolute URLs
					if( preg_match( '~^https?://~i', $redirect_to ) ) $redirect_to = '/';
				}

				echo str_replace( array('{LEAVING_HOST}', '{LEAVING_URL}', '{TARGET_HOST}', '{TARGET_URL}'),
								  array(snr_get_hostname(snr_get_request('host')), $redirect_to, snr_get_hostname($url), $url),
								  $content );
			}
			else
			{
				echo $this->T_('Template file not found');
			}
			exit();
		}
	}


	// =====================================


	function AdminEndHtmlHead( & $params )
	{
		$this->BeforeBlogDisplay();
		$this->SkinBeginHtmlHead( $params );
	}


	function GetExtraEvents()
	{
		return array(
				'get_thumbshot'	=> 'Get a default-sized thumbshot (selected in plugin settings)',
			);
	}


	function RenderItemAsHtml( & $params )
	{
		// This is not actually a renderer,
		// we're just using the checkbox
		// to see if we should process the given post.
	}


	function DisplayItemAsXml( & $params )
	{
		return $this->RenderItemAsHtml( $params );
	}


	/**
	 * Perform rendering
	 */
	function DisplayItemAsHtml( & $params )
	{
		global $Plugins;

		$content = & $params['data'];

		// If the renderer checkbox is unchecked, then display nothing
		$renders = $Plugins->validate_list( $params['Item']->get_renderers() );
		if( !in_array($this->code, $renders) ) return false;

		// See if we already displayed the auto-thumbnail
		if( ! in_array($params['Item']->ID, $this->displayed_item_ids) )
		{	// Remember this Item
			$this->displayed_item_ids[] = $params['Item']->ID;

			// Display thumbshot for post link
			if( @$params['Item']->url && $this->get_option('render_linktourl') )
			{
				$content = $this->get_thumbshot( $params['Item']->url ).$content;
			}
		}

		// OBSOLETE
			// <!--thumbshot:120|90|1|http://screenshot.tld|http://alternative.tld-->
			// <!--thumbshot:||http://www.domain.com-->
			$content = preg_replace( '~<\!--(thumbshot|webshot):([0-9]*)\|([0-9]*)\|(([0-1])\|)?(.*?)-->~ie', '$this->get_image( "\\6", "\\2", "\\3", "\\4" )', $content );

			// Default image
			// <!--thumbshot:http://www.domain.com-->
			$content = preg_replace( '~<\!--(thumbshot|webshot):(.*?)-->~ie', '$this->get_image( "\\2" )', $content );

		// Do the shortcode
		$content = $this->do_shortcode( $content );


		return true;
	}


	function get_option( $opt )
	{
		return $this->Settings->get($opt);
	}


	/**
	 * Add hook for shortcode tag.
	 *
	 * @param string $tag Shortcode tag to be searched in post content.
	 * @param callable $func Hook to run when shortcode is found.
	 */
	function add_shortcode($tag, $func)
	{
		global $shortcode_tags;

		if( is_callable($func) )
		{
			$shortcode_tags[$tag] = $func;
		}
	}


	/**
	 * Retrieve the shortcode regular expression for searching.
	 *
	 * The regular expression combines the shortcode tags in the regular expression
	 * in a regex class.
	 *
	 * The regular expression contains 6 different sub matches to help with parsing.
	 *
	 * 1 - An extra [ to allow for escaping shortcodes with double [[]]
	 * 2 - The shortcode name
	 * 3 - The shortcode argument list
	 * 4 - The self closing /
	 * 5 - The content of a shortcode when it wraps some content.
	 * 6 - An extra ] to allow for escaping shortcodes with double [[]]
	 *
	 * @return string The shortcode search regular expression
	 */
	function get_shortcode_regex( $tagnames = array() )
	{
		global $shortcode_tags;

		$tagnames = array_keys($shortcode_tags);
		$tagregexp = join( '|', array_map('preg_quote', $tagnames) );

		// WARNING! Do not change this regex without changing do_shortcode_tag() and strip_shortcode_tag()
		return
			  '\\['                              // Opening bracket
			. '(\\[?)'                           // 1: Optional second opening bracket for escaping shortcodes: [[tag]]
			. "($tagregexp)"                     // 2: Shortcode name
			. '\\b'                              // Word boundary
			. '('                                // 3: Unroll the loop: Inside the opening shortcode tag
			.     '[^\\]\\/]*'                   // Not a closing bracket or forward slash
			.     '(?:'
			.         '\\/(?!\\])'               // A forward slash not followed by a closing bracket
			.         '[^\\]\\/]*'               // Not a closing bracket or forward slash
			.     ')*?'
			. ')'
			. '(?:'
			.     '(\\/)'                        // 4: Self closing tag ...
			.     '\\]'                          // ... and closing bracket
			. '|'
			.     '\\]'                          // Closing bracket
			.     '(?:'
			.         '('                        // 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags
			.             '[^\\[]*+'             // Not an opening bracket
			.             '(?:'
			.                 '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag
			.                 '[^\\[]*+'         // Not an opening bracket
			.             ')*+'
			.         ')'
			.         '\\[\\/\\2\\]'             // Closing shortcode tag
			.     ')?'
			. ')'
			. '(\\]?)';                          // 6: Optional second closing brocket for escaping shortcodes: [[tag]]
	}


	/**
	 * Regular Expression callable for do_shortcode() for calling shortcode hook.
	 * @see get_shortcode_regex for details of the match array contents.
	 *
	 * @access private
	 * @uses $shortcode_tags
	 *
	 * @param array $m Regular expression match array
	 * @return mixed False on failure.
	 */
	function do_shortcode_tag( $m )
	{
		global $shortcode_tags;

		// allow [[foo]] syntax for escaping a tag
		if( $m[1] == '[' && $m[6] == ']' )
		{
			return substr($m[0], 1, -1);
		}

		$tag = $m[2];
		$attr = $this->shortcode_parse_atts( $m[3] );

		if ( isset( $m[5] ) )
		{	// enclosing tag - extra parameter
			return $m[1].call_user_func( $shortcode_tags[$tag], $attr, $m[5], $tag ).$m[6];
		}
		else
		{	// self-closing tag
			return $m[1].call_user_func( $shortcode_tags[$tag], $attr, NULL,  $tag ).$m[6];
		}
	}


	/**
	 * Retrieve all attributes from the shortcodes tag.
	 *
	 * The attributes list has the attribute name as the key and the value of the
	 * attribute as the value in the key/value pair. This allows for easier
	 * retrieval of the attributes, since all attributes have to be known.
	 *
	 * @param string $text
	 * @return array List of attributes and their value.
	 */
	function shortcode_parse_atts( $text )
	{
		$atts = array();

		$pattern = '/(\w+)\s*=\s*"([^"]*)"(?:\s|$)|(\w+)\s*=\s*\'([^\']*)\'(?:\s|$)|(\w+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/';
		$text = preg_replace("/[\x{00a0}\x{200b}]+/u", " ", $text);

		if( preg_match_all($pattern, $text, $match, PREG_SET_ORDER) )
		{
			foreach( $match as $m )
			{
				if( !empty($m[1]) )
				{
					$atts[evo_strtolower($m[1])] = stripcslashes($m[2]);
				}
				elseif( !empty($m[3]) )
				{
					$atts[evo_strtolower($m[3])] = stripcslashes($m[4]);
				}
				elseif( !empty($m[5]) )
				{
					$atts[evo_strtolower($m[5])] = stripcslashes($m[6]);
				}
				elseif( isset($m[7]) and strlen($m[7]) )
				{
					$atts[] = stripcslashes($m[7]);
				}
				elseif( isset($m[8]) )
				{
					$atts[] = stripcslashes($m[8]);
				}
			}
		}
		else
		{
			$atts = ltrim($text);
		}
		return $atts;
	}


	/**
	 * Combine user attributes with known attributes and fill in defaults when needed.
	 *
	 * The pairs should be considered to be all of the attributes which are
	 * supported by the caller and given as a list. The returned attributes will
	 * only contain the attributes in the $pairs list.
	 *
	 * If the $atts list has unsupported attributes, then they will be ignored and
	 * removed from the final returned list.
	 *
	 * @param array $pairs Entire list of supported attributes and their defaults.
	 * @param array $atts User defined attributes in shortcode tag.
	 * @return array Combined and filtered attribute list.
	 */
	function shortcode_atts( $pairs, $atts )
	{
		$atts = (array)$atts;
		$out = array();

		foreach( $pairs as $name => $default )
		{
			$out[$name] = array_key_exists($name, $atts) ? $atts[$name] : $default;
		}
		return $out;
	}

	/**
	 * Search content for shortcodes and filter shortcodes through their hooks.
	 *
	 * If there are no shortcode tags defined, then the content will be returned
	 * without any filtering. This might cause issues when plugins are disabled but
	 * the shortcode will still show up in the post or content.
	 *
	 * @param string $content Content to search for shortcodes
	 * @return string Content with shortcodes filtered out.
	 */
	function do_shortcode( $content )
	{
		global $shortcode_tags;

		if( empty($shortcode_tags) || !is_array($shortcode_tags) )
		{
			return $content;
		}

		$pattern = $this->get_shortcode_regex();
		return preg_replace_callback( "/$pattern/s", array($this, 'do_shortcode_tag'), $content );
	}

}

?>