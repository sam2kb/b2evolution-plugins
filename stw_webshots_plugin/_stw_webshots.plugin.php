<?php
/**
 *
 * This file implements the STW Webshots plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2008 by Russian b2evolution - {@link http://ru.b2evo.net/}.
 *
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 *
 * @author Russian b2evolution
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class stw_webshots_plugin extends Plugin
{
	/**
	 * Variables below MUST be overriden by plugin implementations,
	 * either in the subclass declaration or in the subclass constructor.
	 */
	var $name = 'STW Webshots';
	/**
	 * Code, if this is a renderer or pingback plugin.
	 */
	var $code = 'stw_webshots';
	var $priority = 65;
	var $version = '0.1';
	var $group = 'ru.b2evo.net';
	var $author = 'Russian b2evolution';
	var $author_url = 'http://ru.b2evo.net';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=18099';
	
	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;
	
	var $free = true;
	var $debug = 0;	// 1 to display debug info
	var $xino = 'http://www.shrinktheweb.com/xino.php?';
	
	var $thumbnails_dir;
	var $thumbnails_path;

	/**
	 * Init
	 *
	 * This gets called after a plugin has been registered/instantiated.
	 */
	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Displays website thumbnails');
		$this->long_desc = $this->T_('Displays website thumbnails using the <a href="http://www.shrinktheweb.com">ShrinkTheWeb</a> service.');
		
		$this->thumbnails_dir = $GLOBALS['media_url'].$this->code.'/';
		$this->thumbnails_path = $GLOBALS['media_path'].$this->code.'/';
	}
	
	function BeforeInstall()
	{
		if( !mkdir_r( $this->thumbnails_path ) )
		{
			$this->msg( sprintf( $this->T_('You must create the following directory with write permissions (777):%s'), '<br />'.$this->thumbnails_path ), 'error' );
			return false;
		}
		return true;
	}
	
	
	/**
	 * Define settings that the plugin uses/provides.
	 */
	function GetDefaultSettings()
	{
		$max_w = 3000;
		$max_h = 3000;
		$limit_w = $limit_h = NULL;
			
		if( $this->free )
		{
			$max_w = 320;
			$limit_w = sprintf( $this->T_('<br />Cannot exceed %spx on free accounts.'), $max_w );
			
			$max_h = 240;
			$limit_h = sprintf( $this->T_('<br />Cannot exceed %spx on free accounts.'), $max_h );			
		}
		
		return array(
				'free_account' => array(
					'label' => $this->T_('STW Account type'),
					'type' => 'checkbox',
					'defaultvalue' => 1,
					'note' => $this->T_('Check this if you have a <b>FREE</b> STW account.'),
				),
				'secret_key' => array(
					'label' => $this->T_('STW Access Key ID'),
					'size' => 20,
					'note' => sprintf( $this->T_('Create an account on %s to use webshots.'), '<a href="http://www.shrinktheweb.com/index.php?view=join" target="_blank">Shrink The Web</a>' ),
				),
				'key_id' => array(
					'label' => $this->T_('STW Secret Key'),
					'size' => 6,
				),
				'cache_days' => array(
					'label' => $this->T_('Cache images'),
					'size' => 3,
					'defaultvalue' => 5,
					'note' => $this->T_('days<br />How many days do you want to store image cache.'),
				),
				'width' => array(
					'label' => $this->T_('Default webshot width'),
					'size' => 3,
					'defaultvalue' => 120,
					'valid_range' => array( 'max' => $max_w ),
					'note' => $this->T_('px.').$limit_w,
				),
				'height' => array(
					'label' => $this->T_('Default webshot height'),
					'size' => 3,
					'defaultvalue' => 90,
					'valid_range' => array( 'max' => $max_h ),
					'note' => $this->T_('px.').$limit_h,
				),
			);
	}
	
	
	/**
	 * Event handler: Called as action before displaying the "Edit plugin" form,
	 * which includes the display of the {@link Plugin::$Settings plugin's settings}.
	 *
	 * You may want to use this to check existing settings or display notes about
	 * something.
	 */
	function PluginSettingsEditAction()
	{
		if( $this->Settings->get( 'free_account' ) == 0 )
			$this->free = false;
	}
	
	
	function GetExtraEvents()
	{
		return array(
				'stw_webshot_s'			=> 'Get a small (120x90) webshot',
				'stw_webshot_l'			=> 'Get a large (200x150) webshot',
				'stw_webshot_xl'		=> 'Get an extra large (320x240) webshot',
				'stw_webshot_default'	=> 'Get a default-sized webshot (selected in plugin settings)',
			);
	}
	
	function stw_webshot_s( $url )
	{
		$params = array(
			'url'		=> $url,
			'width'		=> 120,
			'height'	=> 90,
			);		
		echo $this->render_content( $params );
	}
	
	function stw_webshot_l( $url )
	{
		$params = array(
			'url'		=> $url,
			'width'		=> 200,
			'height'	=> 150,
			);		
		echo $this->render_content( $params );
	}
	
	function stw_webshot_xl( $url )
	{
		$params = array(
			'url'		=> $url,
			'width'		=> 320,
			'height'	=> 240,
			);		
		echo $this->render_content( $params );
	}
	
	function stw_webshot_default( $url )
	{
		$params = array(
			'url'		=> $url,
			);		
		echo $this->render_content( $params );
	}
		
	
	function RenderItemAsHtml( & $params )
	{
		// This is not actually a renderer, 
		// we're just using the checkbox
		// to see if we should process the given post.
	}
	
	
	function SkinBeginHtmlHead()
	{
		global $plugins_url;
		
		require_css( $plugins_url.'stw_webshots_plugin/webshots.css', true );	// Add our css
	}
	
	
	/**
	 * Perform rendering for HTML feeds
	 */
	function DisplayItemAsHtml( & $params )
	{
		global $Plugins;
		
		$content = & $params['data'];

		// Display webshot for post link
		if( !empty($params['Item']->url) )
		{
			$content = $this->stw_webshot_default( $params['Item']->url ).'<br />'.$content;
		}		
		$content = $this->render_content($content);
		return true;
	}



	/**
	 * Perform rendering for XML feeds
	 */
	function DisplayItemAsXml( & $params )
	{
		return $this->DisplayItemAsHtml( $params );
	}
	
	
	// Render content
	function render_content( $content )
	{
		if( is_array($content) )
		{	// Direct call
			if(!isset($content['width'])) $content['width'] = NULL;
			if(!isset($content['height'])) $content['height'] = NULL;
			if(!isset($content['args'])) $content['args'] = NULL;
			if(!isset($content['force'])) $content['force'] = false;
												  
			return $this->disp_image( $content['url'], $content['width'], $content['height'], $content['args'], $content['force'] );
		}
		
		// Scaled image
		// <!--webshot:120|90|http://www.domain.com-->
		// <!--webshot:||http://www.domain.com-->
		$content = preg_replace( '~<\!--webshot:([0-9]*)\|([0-9]*)\|(.*?)-->~ie', '$this->disp_image( "\\3", "\\1", "\\2" )', $content );
		
		// Default image
		// <!--webshot:http://www.domain.com-->
		$content = preg_replace( '~<\!--webshot:(.*?)-->~ie', '$this->disp_image( "\\1" )', $content );
		
		return $content;
	}
	
	
	function AdminDisplayToolbar( & $params )
	{	
		global $Blog;
		?>
		<script type="text/javascript">
		//<![CDATA[
			function stw_webshots () {
			var webshots_w = '<?php echo $this->T_('Webshot width ?') ?>';
			var width = prompt( webshots_w, '<?php echo $this->Settings->get('width') ?>' );
			var webshots_h = '<?php echo $this->T_('Webshot height ?') ?>';
			var height = prompt( webshots_h, '<?php echo $this->Settings->get('height') ?>' );
			var webshots_url = '<?php echo $this->T_('Site URL ?'); ?>';
			var url = prompt( webshots_url, 'http://' );
			
			code = '<!--webshot:'+width+'|'+height+"|"+url+'-->';
			textarea_wrap_selection( b2evoCanvas, code, '', 1 );
		}
		//]]>
		</script>
		<div class="edit_toolbar">
		 <input type="button" name="stwwebshots" class="quicktags" onclick="stw_webshots();" value="<?php echo $this->T_('Webshot') ?>" />
		</div>
		<?php
	}
	
	
	// Display resized image
	function disp_image( $url = NULL, $width = NULL, $height = NULL, $args = NULL, $force = false )
	{
		$url = trim($url);
		$r = '';
		
		if( !empty($url) )
		{
			if( !is_numeric($width) ) $width = $this->Settings->get('width');
			if( !is_numeric($height) ) $height = $this->Settings->get('height');

			if( $src = $this->getScaledThumbnail( $url, $width, $height, $args, $force ) )
			{	// Got an image, let's display it
				if( $this->is_url($url) )
					$r .= '<a class="stw_webshot" href="'.$url.'" target="_blank">';
				
				$r .= '<img class="stw_webshot" src="'.$src.'" alt="" />';
				
				if( $this->is_url($url) ) $r .= '</a>';
				
				return $r;
			}
		}
		return NULL;
	}
	
		
	/*
     * @param string $url URL to get thumbnail for
     * @param array $args Array of parameters to use
     * @return string full remote URL to the thumbnail
     */
	function queryRemoteThumbnail( $url, $args = NULL )
	{
        $args = is_array($args) ? $args : array();
        $args['Url'] = $url;
		
		$args = array_merge( array(
					'Service'			=> 'ShrinkWebUrlThumbnail',
					'Action'			=> 'Thumbnail',
					'STWAccessKeyId'	=> $this->Settings->get('secret_key'),
					'u'					=> $this->Settings->get('key_id'),
				), $args );
				
		$arr = array();
		foreach( $args as $k => $v ) $arr[] = $k.'='.$v;
		$query = implode( '&', $arr );
		
		$request_url = $this->xino.$query;
		
		if($this->debug)
		{	// Debug
			echo 'Requesting a new image from server<br />';
			pre_dump($request_url);
		}
		
		$lines = file($request_url);
		$line = implode( '', $lines );

        if($this->debug)
		{	// Debug
			if(isset($args['STWAccessKeyId'])) unset($args['STWAccessKeyId']);
			if(isset($args['u'])) unset($args['u']);
			
			pre_dump($args);
			pre_dump($line);
        }

		$regex = '/<[^:]*:Thumbnail\\s*(?:Exists=\"((?:true)|(?:false))\")?[^>]*>([^<]*)<\//';
		if( @preg_match( $regex, $line, $matches ) == 1 && $matches[1] == "true" )
		{	// Got an image
			return $matches[2];
		}
		
		// Second request, trying to get an error image
		$error_image = $this->xino.'embed=1&'.$query;
		if( $this->is_image($error_image) )
		{
			$this->error_detected = 1;
			return $error_image;
		}
		
		$err = array();
		$regex_err = '/ResponseStatus>\\s*<[^:]*:StatusCode\\s*?[^>]*>([^<]*)<\//';
		if( @preg_match( $regex_err, $line, $matches_err ) )
		{
			$err[] = '<b>ResponseStatus:</b><br />'.$matches_err[1];
		}
		$regex_err = '/CategoryCode>\\s*<[^:]*:StatusCode\\s*?[^>]*>([^<]*)<\//';
		if( @preg_match( $regex_err, $line, $matches_err ) )
		{
			$err[] = '<b>CategoryCode:</b><br />'.$matches_err[1];
		}
		
		if( !empty($err) )
		{
			echo '<div class="error" style="width:120px; height:90px; font-size:10px; color:red">';
			echo implode( '<br /><br />', $err );
			echo '</div>';
		}
		return NULL;
    }
	
	

	function getThumbnail( $url, $args = NULL, $force = false )
	{		
		$cutoff = time() - 3600 * 24 * $this->Settings->get('cache_days');
        $name = md5( $url.serialize($args) ).'.jpg';
        $src = $this->thumbnails_dir.$name;
        $file = $this->thumbnails_path.$name;

        if( $force || !file_exists($file) || filemtime($file) <= $cutoff )
        {
			if( $jpgurl = $this->queryRemoteThumbnail($url, $args) )
            {
				if( $im = @imagecreatefromjpeg($jpgurl) )
				{
                	@imagejpeg($im, $file, 98);
				}
				if( $this->error_detected )
				{	// Cache error images for 6 hours only
					@touch( $file, $cutoff + 21600 );
				}
			}
		}
		if( @file_exists($file) ) return $src;
		
        return NULL;
    }
	
	
	// Get scaled image
	function getScaledThumbnail( $url, $width, $height, $args = NULL, $force = false )
	{
		// Edit the following string if you have a PRO account with custom image sizes
		// Example: ? 'xlg' : '800';
		$size = $this->Settings->get( 'free_account' ) ? 'xlg' : 'xlg';
		
		$args = $args ? $args : array( 'Size' => $size );
		$cutoff = time() - 3600 * 24 * $this->Settings->get('cache_days');
        $name = md5( $url.serialize($args) ).'-'.$width.'_'.$height.'.jpg';
        $src = $this->thumbnails_dir.$name;
        $file = $this->thumbnails_path.$name;
		
		$name_orig = md5( $url.serialize($args) ).'.jpg';
		$file_orig = $this->thumbnails_path.$name_orig;
        		
        if( $force || !file_exists($file) || filemtime($file) <= $cutoff )
		{
			if( false && file_exists($file_orig) ) // more testing
			{	// Use saved original image
				// Debug
				if($this->debug) echo 'Using saved original image<br />';
				$xlg = $file_orig;
			}
			else
			{	// Retrive a new one
				// Debug
				if($this->debug) echo 'Retriving a new image<br />';
				$xlg = $this->getThumbnail($url, $args, $force);
			}
			
			if( !empty($xlg) && $im = @imagecreatefromjpeg($xlg) )
			{
				list( $xw, $xh ) = @getimagesize($xlg );
				$scaled = @imagecreatetruecolor( $width, $height );
				if( @imagecopyresampled( $scaled, $im, 0, 0, 0, 0, $width, $height, $xw, $xh ) )
				{
					@imagejpeg($scaled, $file, 98);
				}
				if( $this->error_detected )
				{	// Cache error images for 6 hours only
					@touch( $file, $cutoff + 21600 );
					$this->error_detected = 0;
				}
			}
		}
		else
		{	// Debug
			if($this->debug) echo 'Using cached image<br />';
		}
		if( file_exists($file) ) return $src;
		
        return false;
    }
	
	
	function is_url( $url )
	{
		if( $parts = @parse_url( $url ) )
		{
			if( !empty($parts['scheme']) && ($parts['scheme'] == 'http' || $parts['scheme'] == 'https') ) return true;
		}		
		return false;
	}
	
	
	function is_image( $file )
	{
		if( function_exists( 'exif_imagetype' ) )
		{
			if( @exif_imagetype($file) ) return true;
		}
		else
		{
			if( @getimagesize($file) ) return true;
		}
		return false;
	}
}

?>