<?php
/**
 *
 * This file implements the SAPE plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2008-2012 Russian b2evolution - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Russian b2evolution
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class sape_plugin extends Plugin
{
	var $name = 'SAPE';
	var $code = 'sape';
	var $priority = 30;
	var $version = '1.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://b2evo.sonorth.com/show.php/sape?page=2';

	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;

	var $sape_class = '_sape.class.inc'; // Sape class filename

	// Internal
	var $path;
	var $purl = 'http://www.sape.ru/';


	function PluginInit( & $params )
	{
		global $plugins_path;

		$this->short_desc = $this->T_('Allows you to sell links from your blog');
		$this->long_desc = sprintf( $this->T_('Allows you to sell links from your blog pages on <a %s>Sape.ru</a>, one of the leading link exchange services in Russia.'), 'href="'.$this->purl.'" target="_blank"' );

		$this->path = $plugins_path.$this->classname.'/data/';
	}


	function AfterInstall()
	{
		global $admin_url, $current_User;

		// Create sape directory if not exists (silent)
		if( !is_dir($this->path) )
		{
			mkdir_r( $this->path, 777 );
			$this->msg( sprintf( $this->T_('Sape plugin is not configured yet. <a %s>Configure now!</a>'), 'href="'.$admin_url.'?ctrl=plugins&amp;action=edit_settings&amp;plugin_ID='.$this->ID ), 'note' );
		}

		if( !is_writable($this->path) )
		{
			$this->msg( sprintf( $this->T_('You must create the following directory with write permissions (777):%s'), '<br />'.$this->path), 'error' );
		}

		// Create index.html file
		$file = $this->path.'index.html';
		if( !file_exists($file) )
		{
			$data = ' ';
			if( !$this->save_to_file( $data, $file ) ) return false;

			if( !file_exists($file) )
			{
				$this->msg( sprintf( $this->T_('Make sure the directory [%s] has write permissions (777)'), $user_path ), 'error' );
			}
		}
	}


	function PluginSettingsUpdateAction()
	{
		// Create user directory if not exists
		$this->check_dir();
	}


	function GetDefaultSettings()
	{
		return array(
				'sape' => array(
					'label' => $this->T_('Enable Sape'),
					'defaultvalue' => 1,
					'type' => 'checkbox',
				),
				'sape_context' => array(
					'label' => $this->T_('Enable Sape Context'),
					'defaultvalue' => 1,
					'type' => 'checkbox',
				),
				'sape_articles' => array(
					'label' => $this->T_('Enable Sape Articles'),
					'defaultvalue' => 0,
					'type' => 'checkbox',
				),
				'sape_uid' => array(
					'label' => $this->T_('Sape UID'),
					'type' => 'text',
					'size' => 35,
					'note' => sprintf( $this->T_('Enter your Sape UID. You can <a %s>register</a> if you don\'t have an account yet.'), 'href="'.$this->purl.'" target="_blank"' ),
				),
				'link_as_block' => array(
					'label' => $this->T_('Display links in blocks'),
					'defaultvalue' => '',
					'type' => 'select',
					'options' => array(
							''		=> $this->T_('Default (website settings)'),
							'true'	=> $this->T_('Yes'),
							'false'	=> $this->T_('No'),
						),
					'note' => $this->T_('Fetch remote page method.'),
				),
				'verbose' => array(
					'label' => $this->T_('Display errors'),
					'defaultvalue' => 0,
					'note' => $this->T_('Check this to display Sape errors if any.'),
					'type' => 'checkbox',
				),
				'debug' => array(
					'label' => $this->T_('Display debug info'),
					'defaultvalue' => 0,
					'note' => $this->T_('Check this to display debug info for SAPE Context.'),
					'type' => 'checkbox',
				),
				'show_code' => array(
					'label' => $this->T_('Display check code'),
					'defaultvalue' => 0,
					'note' => $this->T_('Check this to always display Sape check code.'),
					'type' => 'checkbox',
				),
				'charset' => array(
					'label' => $this->T_('Blog charset'),
					'type' => 'text',
					'note' => $this->T_('Leave empty to autodetect charset (recommended).'),
				),
				'fetch_remote_type' => array(
					'label' => $this->T_('Fetch page method'),
					'type' => 'select',
					'options' => array(
							''					=> $this->T_('Default (recommended)'),
							'file_get_contents' => 'file_get_contents',
							'curl'				=> 'Curl',
							'socket'			=> 'Socket',
						),
					'note' => $this->T_('Fetch remote page method.'),
				),
				'socket_timeout' => array(
					'label' => $this->T_('Socket timeout'),
					'type' => 'integer',
					'size' => 4,
					'defaultvalue' => 6,
					'note' => $this->T_('sec.').' '.$this->T_('Longer timeout may increase page load time.'),
				),
				'cache_lifetime' => array(
					'label' => $this->T_('Cache life time'),
					'type' => 'integer',
					'size' => 4,
					'defaultvalue' => 3600,
					'note' => $this->T_('sec.'),
				),
				'cache_reloadtime' => array(
					'label' => $this->T_('Cache reload time'),
					'type' => 'integer',
					'size' => 4,
					'defaultvalue' => 600,
					'note' => $this->T_('sec.'),
				),
			);
	}


	function get_widget_param_definitions( $params )
	{
		return array(
				'title' => array(
					'label' => $this->T_('Widget title'),
					'defaultvalue' => $this->T_('Advertisement'),
					'type' => 'text',
					'note' => $this->T_('Widget title displayed in skin.'),
				),
				'num' => array(
					'label' => $this->T_('Number of links'),
					'note' => $this->T_('Number of links to display'),
					'type' => 'select',
					'defaultvalue' => 15,
					'options' => array( '1'=>1, 2,3,4,5,6,7,8,9,10,11,12,13,14,15 ),
				),
			);
	}


	function SkinBeginHtmlHead()
	{
		global $plugins_url;
		// Add our css
		require_css( $plugins_url.$this->classname.'/'.$this->code.'.css', true );
	}


	function SkinTag( $params )
	{
		// Init default params
		$params = $this->init_display($params);

		if(!isset($params['link_opts'])) $params['link_opts'] = array();

		// Define default block options
		$params['link_opts'] = array_merge( array(
				'offset'	=> 0,
			), $params['link_opts'] );

		if( ($link_as_block = $this->Settings->get('link_as_block')) != '' )
		{
			$params['link_opts']['as_block'] = $link_as_block;
		}

		$r  = '<div class="widget_plugin_'.$this->code.'">';
		if( !empty($params['title']) )
		{	// Widget title
			$r .= $params['block_title_start'];
			$r .= $params['title'];
			$r .= $params['block_title_end'];
		}
		$content = (is_object($this->bSape)) ? $this->bSape->return_links($params['num'], $params['link_opts']['offset'], $params['link_opts']) : '';
		$r .= $content;
		$r .= '</div>';

		if( !empty($content) ) echo $r;
	}


	function BeforeBlogDisplay( & $params )
	{
		global $Blog, $ReqURI, $current_charset;

		if( !$sape_uid = $this->Settings->get('sape_uid') )
		{	// Plugin is not configured
			return;
		}
		if( !$this->Settings->get('sape') && !$this->Settings->get('sape_context') )
		{	// Sape is disabled
			return;
		}

		$this->check_dir();  // Make sure user directory exists

		if( file_exists($this->path.$this->sape_class) )
		{
			if (!defined('_SAPE_USER')) define('_SAPE_USER', $sape_uid );
			require_once $this->path.$this->sape_class;

			// Detect charset
			switch( strtolower($current_charset) )
			{
				case 'cp1251':
				case 'windows-1251':
				case 'koi8-r':
				case 'koi8-u':
					$charset = strtolower($current_charset);
					break;

				default:
					$charset = 'utf-8';
			}
			if( $this->Settings->get('charset') ) $charset = $this->Settings->get('charset');

			$SapeURI = $ReqURI;
			switch( substr( $ReqURI, -1 ) )
			{
				case '?':
				case '&':
					$SapeURI = substr( $ReqURI, 0 , -1 );
					break;
			}

			// Sape params
			$sape_params = array(
					'multi_site'		=> true,
					'charset'			=> strtolower($charset),
					'request_uri'		=> $SapeURI,
					'fetch_remote_type'	=> $this->Settings->get('fetch_remote_type'),
					'socket_timeout'	=> $this->Settings->get('socket_timeout'),
					'verbose'			=> $this->Settings->get('verbose'),
					'debug'				=> $this->Settings->get('debug'),
					'force_show_code'	=> $this->Settings->get('show_code'),
					'cache_lifetime'	=> $this->Settings->get('cache_lifetime'),
					'cache_reloadtime'	=> $this->Settings->get('cache_reloadtime'),
				);

			if( $this->Settings->get('sape') && class_exists('SAPE_client') )
			{	// Activate Sape
				$this->bSape = new SAPE_client( $sape_params );
			}
			if( $this->Settings->get('sape_context') && class_exists('SAPE_context') )
			{	// Activate Sape Context
				$this->bSape_context = new SAPE_context( $sape_params );
			}
			if( $this->Settings->get('sape_articles') && class_exists('SAPE_articles') )
			{	// Activate Sape Articles
				$this->bSape_article = new SAPE_articles( $sape_params );
			}
		}
	}


	function DisplayItemAsHtml( & $params )
	{
		if( $params['preview'] ) return;

		if( is_object($this->bSape_context) )
		{	// Add Sape Context
			$params['data'] = $this->bSape_context->replace_in_text_segment($params['data']);
		}
		if( is_object($this->bSape_article) )
		{	// Add Sape Article
			$params['data'] = preg_replace( '~<\!--sape_article-->~ie', '$this->bSape_article->return_announcements()', $params['data'] );
		}
		return true;
	}


	function check_dir( $msg = true )
	{
		if( !$sape_uid = $this->Settings->get('sape_uid') ) return false;

		$user_path = $this->path.$sape_uid.'/';

		// Create sape user directory
		mkdir_r( $user_path, 777 );
		if( !is_writable($user_path) )
		{
			if( $msg )
			{
				$this->msg( sprintf( $this->T_('You must create the following directory with write permissions (777):%s'), '<br />'.$user_path), 'error' );
			}
			return false;
		}

		// Create index.html file
		$filename = 'index.html';
		if( !file_exists($user_path.$filename) )
		{
			if( !$this->save_to_file( ' ', $user_path.$filename ) )
			{
				if( $msg )
				{
					$this->msg( sprintf( $this->T_('Cannot create %s file!'), '<i>'.$filename.'</i>' ), 'error' );
				}
				return false;
			}
		}
		return true;
	}


	/**
	 * Save data in file
	 *
	 * @param string File content
	 * @param string Filename with full path
	 * @param string fopen mode
	 */
	function save_to_file( $content, $filename, $mode = 'w' )
	{
		$f = @fopen($filename, $mode);
		@fwrite($f, $content);
		@fclose($f);

		if( md5($this->get_data($filename)) != md5($content) )
		{
			$this->msg( $this->T_('Data integrity error') .': '.basename($filename), 'error' );
			return false;
		}
		return true;
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

	/**
	 * Sets all the display parameters
	 * These will either be the default display params or the widget display params if it's in a container
	 *
	 * @param array $params
	 */
 	function init_display( $params = array() )
 	{
		$temp = $this->get_widget_param_definitions( array() );

		$full_params = array();
		foreach( $temp as $setting => $values )
		{
			$full_params[$setting] = ( isset($params[$setting]) ? $params[$setting] : $this->Settings->get($setting) );
		}

		foreach( $params as $param => $value )
		{
			if( !isset($full_params[$param]) ) $full_params[$param] = $value;
		}
		return $full_params;
	}
}

?>