<?php
/**
 * This file implements the Mobile plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2008 by Sonorth Corp. - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Sonorth Corp.
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class mobile_plugin extends Plugin
{
	var $name = 'Mobile';
	var $code = 'mobile';
	var $priority = 10;
	var $version = '0.0.1-dev';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';

	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;

	var $test_user_agent = ''; // Enter user agent to test (admin only)
	var $is_mobile = false;
	var $skin_path = false;

	/**
	 * Init
	 *
	 * This gets called after a plugin has been registered/instantiated.
	 */
	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Mobile');
		$this->long_desc = $this->T_('Mobile');
	}


	function GetDefaultSettings()
	{
		return array(
				'limit_posts' => array(
					'label' => $this->T_('Posts per page'),
					'type' => 'integer',
					'size' => 2,
					'defaultvalue' => 3,
					'note' => $this->T_('The number of posts displayed in "post list" mode.'),
				),
				'limit_comments' => array(
					'label' => $this->T_('Comments per page'),
					'type' => 'integer',
					'size' => 2,
					'defaultvalue' => 3,
					'note' => $this->T_('The number of latest comments displayed in "single post" mode.'),
				),
				'disp_comment_form' => array(
					'label' => $this->T_('Display comments form'),
					'type' => 'checkbox',
					'defaultvalue' => 0,
					'note' => $this->T_('Check this if you want to display the comment form.'),
				),
				'android' => array(
					'label' => $this->T_('Android'),
					'type' => 'checkbox',
					'defaultvalue' => 1,
					'note' => $this->T_('Check this if you want to treat Android handsets as mobiles.'),
				),
				'blackberry' => array(
					'label' => $this->T_('Blackberry'),
					'type' => 'checkbox',
					'defaultvalue' => 1,
					'note' => $this->T_('Check this if you want to treat Blackberry like a mobile.'),
				),
				'iphone' => array(
					'label' => $this->T_('iPhone/iPod'),
					'type' => 'checkbox',
					'defaultvalue' => 1,
					'note' => $this->T_('Check this if you want to treat iPhones and iPods as mobiles.'),
				),
				'opera' => array(
					'label' => $this->T_('Opera Mini'),
					'type' => 'checkbox',
					'defaultvalue' => 1,
					'note' => $this->T_('Check this if you want to treat Opera Mini like a mobile.'),
				),
				'palm' => array(
					'label' => $this->T_('Palm OS'),
					'type' => 'checkbox',
					'defaultvalue' => 1,
					'note' => $this->T_('Check this if you want to treat Palm OS like a mobile.'),
				),
				'windows' => array(
					'label' => $this->T_('Windows Mobiles'),
					'type' => 'checkbox',
					'defaultvalue' => 1,
					'note' => $this->T_('Check this if you want to treat Windows Mobiles like a mobile.'),
				),
			);
	}


	function SessionLoaded()
	{
		global $Hit, $Session, $skin, $force_io_charset_if_accepted, $use_strict;

		// Test user agent
		if( $this->test_user_agent && $Session->has_User() && $Session->user_ID == 1 )
		{	// Admin only
			$Hit->user_agent = $this->test_user_agent;
		}

		if( array_key_exists( 'mobi', $_GET ) )
		{
			switch( $_GET['mobi'] )
			{
				case 'on':
					$this->set_cookie( 'mobi', 'on' );
					$this->is_mobile = true;
					$_COOKIE['mobi'] = 'on';
					break;

				case 'off':
					$this->set_cookie( 'mobi', 'off' );
					$this->is_mobile = false;
					return; // Exit
					break;
			}
		}
		if( array_key_exists( 'mobi', $_COOKIE ) )
		{
			switch( $_COOKIE['mobi'] )
			{
				case 'on':
					$this->is_mobile = true;
					$browser = $this->mobile_device_detect( 'iphone', 'android', 'opera', 'blackberry', 'palm', 'windows' );
					break;

				case 'off':
					$this->is_mobile = false;
					$this->link_to_mobi = 'href="'.regenerate_url( 'tempskin,mobi', 'mobi=on' ).'"';
					break;
			}
		}

		if( $this->is_mobile || $browser = $this->mobile_device_detect( 'iphone', 'android', 'opera', 'blackberry', 'palm', 'windows' ) )
		{
			$use_strict = true;
			$force_io_charset_if_accepted = 'utf-8';

			if( empty($browser) )
			{
				$Hit->is_mobile = true;
				$this->is_mobile = true;
				$skin = 'mobile';
			}
			elseif( $this->Settings->get( $browser ) == 1 )
			{
				$Hit->is_mobile = true;
				$this->is_mobile = $browser;
				$skin = 'mobile';
			}
			if( !$this->link_to_mobi ) memorize_param( 'skin', 'string', '', $skin );
		}
	}


	function BeforeBlogDisplay( & $params )
	{
		global $Blog, $skin, $posts;

		if( $this->link_to_mobi )
		{
			$this->msg( sprintf( $this->T_('Your browser is detected as mobile. Click <a %s>here</a> if you want to switch to our mobile site.'), $this->link_to_mobi ), 'note' );
		}

		if( $this->is_mobile && preg_match( '~mobile~', $skin ) )
		{	// Use mobile skin
			if( !file_exists( dirname(__FILE__).'/skins/'.$skin.'/index.main.php' ) )
			{	// Provide the default skin
				$skin = 'mobile';
			}
			// Set posts per page limit
			$posts = $this->Settings->get('limit_posts');
			$Blog->set_setting( 'posts_per_page', $posts );
		}
		$this->skin_path = dirname(__FILE__).'/skins/'.$skin.'/';

		// Not a mobile device or no skin provided
		if( !file_exists( $this->skin_path.'index.main.php' ) )
		{	// Use default blog skin
			global $SkinCache, $Blog, $admin_url;

			$SkinCache = & get_cache( 'SkinCache' );
			$Skin = & $SkinCache->get_by_ID( $Blog->skin_ID );
			$skin = $Skin->folder;

			if( !empty( $skin ) && !skin_exists( $skin ) )
			{ // We want to use a skin, but it doesn't exist!
				$err_msg = sprintf( T_('The skin [%s] set for blog [%s] does not exist. It must be properly set in the <a %s>blog properties</a> or properly overriden in a stub file.'),
					htmlspecialchars($skin),
					$Blog->dget('shortname'),
					'href="'.$admin_url.'?ctrl=coll_settings&amp;tab=skin&amp;blog='.$Blog->ID.'"' );
				debug_die( $err_msg );
			}
		}
	}


	function DisplayItemAsHtml( & $params )
	{
		global $disp;

		if( $disp == 'posts' )
		{	// Post excerpt
			$read_more = '<p class="bMore"><a href="'.$params['Item']->get_permanent_url().'">'.$this->T_('Read more').' &raquo;</a></p>';
			$params['data'] = $this->cut_text( $params['data'], 150, '', $read_more );
		}
		// Filter content. Scale images
		$params['data'] = callback_on_non_matching_blocks( $params['data'], '~<pre>(.*?)</pre>~is', array(&$this, 'filter_content') );

		return true;
	}


	function DisplayItemAsXml( & $params )
	{
		return $this->DisplayItemAsHtml( $params );
	}


	function GetProvidedSkins()
	{
		return array(
				'mobile',
				'mobile_ext',
			);
	}


	function DisplaySkin( & $params, $compress = true )
	{
		global $skins_url, $ads_current_skin_path, $plugins_url;

		// All needed globals must be set here
		global $baseurl, $app_version, $skin_links, $francois_links, $skinfaktory_links;
		global $disp, $Plugins, $Item, $Blog, $Hit, $s;

		$skins_url = $plugins_url.$this->classname.'/skins/';
		$ads_current_skin_path = $this->skin_path;

		$MobilePlugin = & $this;

		$disp_handlers = array(
				'arcdir'         => 'arcdir.main.php',
				'catdir'         => 'catdir.main.php',
				'comments'       => 'comments.main.php',
				'feedback-popup' => 'feedback_popup.main.php',
				'mediaidx'       => 'mediaidx.main.php',
				'msgform'        => 'msgform.main.php',
				'page'           => 'page.main.php',
				'posts'          => 'posts.main.php',
				'profile'        => 'profile.main.php',
				'single'         => 'single.main.php',
				'subs'           => 'subs.main.php',
			);

		if( $compress )
		{
			ob_start();
		}

		header('Pragma: Public');
  		header('Cache-Control: no-cache, must-revalidate, no-transform');
    	header('Vary: User-Agent, Accept');
		header('Expires: '.date('D, d M Y H:i:s', time() + 60 * 60).' GMT');
		header('Content-type: text/html; charset=utf-8');

		if( !empty($disp_handlers[$disp]) && file_exists( $disp_handler = $this->skin_path.$disp_handlers[$disp] ) )
		{	// The skin has a customized page handler for this display:
			require $disp_handler;
		}
		else
		{	// Use the default handler from the skins dir:
			require $this->skin_path.'index.main.php';
		}

		if( $compress )
		{
			echo $this->compress( ob_get_clean() );
		}
	}


	function compress( $content )
	{	// Filter content. Don't touch images
		$content = callback_on_non_matching_blocks( $content, '~<pre>(.*?)</pre>~is', array(&$this, 'filter_content') , array(false) );

		return $content;
	}


	function filter_content( & $content, $fix_images = true )
	{
		$content = str_replace(array("\n", "\r", "\t", ' align="center"'), '', $content);
		$content = preg_replace( array(
						'~\s{2,}~',				// Remove extra whitespace
						'~</?[o|u]l[^>]*>~i',	// <ul>, </ul>, <ol>, </ol> to <br>
						'~<li>~is',				// <li>
						'~<li\s+[^>]*>~is',		// <li ...>
						'~</li>~is',			// </li> to <br />
						'~(<br[^>]*>){3,}~is',	// 3 and more <br> to 1 <br>
						'~<!--(.*?)-->~is',		// Remove HTML comments
					), array(
						' ',
						'<br />',
						'&bull; ',
						'&bull; ',
						'<br />',
						'<br />',
						'',
					), $content );

		if( $fix_images )
		{	// Scale images to 80x80
			$content = preg_replace( '/(<\s*img[^>]+>)/ise', '$this->scale_image( "\\1" )', $content );
		}
		return $content;
	}


	// Resize all images to 80x80
	function scale_image( $img )
	{
		if( empty($img) ) return '';

		global $current_charset;

		preg_match_all('~([a-z]([a-z0-9]*)?)=("|\')(.*?)("|\')~is', $img, $pairs);

		if( !is_array($pairs[1]) || !is_array($pairs[4]) ) return $img;
		if( !$prop = @array_combine($pairs[1], $pairs[4]) ) return $img;

		$p = array_merge( array(
				'alt'		=> '',
				'title'		=> '',
				'class'		=> '',
			), $prop );

		if( empty($p['src']) ) return $img;

		switch( true )
		{	// Filter smiles & feedburner's crap
			case $p['class'] == 'middle':
			case stristr( $p['src'], 'feedburner.com' ):
			case stristr( $p['src'], 'doubleclick.net' ):
				return $img;
		}

		return '<img src="'.$p['src'].'" width="80" heigth="80" title="'.$p['title'].'" alt="'.$p['alt'].'" />';
	}


	/**
	 * List of items by category
	 *
	 * @param array MUST contain at least the basic display params
	 */
	function disp_cat_item_list( $params = array() )
	{
		global $Blog;
		global $timestamp_min, $timestamp_max;

		$BlogCache = & get_Cache( 'BlogCache' );

		$cat = '';
		$catsel = array();

		// Compile cat array stuff:
		$cat_array = array();
		$cat_modifier = '';
		compile_cat_array( $cat, $catsel, /* by ref */ $cat_array, /* by ref */  $cat_modifier, $Blog->ID );

		$limit = 1000;

		$lp_BlogList = new ItemListLight( $Blog, $timestamp_min, $timestamp_max, $limit );

		$lp_BlogList->set_filters( array(
				'cat_array' => $cat_array,
				'cat_modifier' => $cat_modifier,
				'orderby' => 'main_cat_ID datecreated',
				'order' => 'DESC',
				'unit' => 'posts',
			), false ); // we don't want to memorise these params

		// Run the query:
		$lp_BlogList->query();

		if( ! $lp_BlogList->get_num_rows() )
		{ // empty list:
			return;
		}

		echo '<table>';

		$nb_cols = $params['cols'];
		$count = 0;
		$prev_post_ID = 0;
		while( $Item = & $lp_BlogList->get_category_group() )
		{
			if( $count % $nb_cols == 0 )
			{
				echo "\n<tr>";
			}
			if( $Item->ID != $prev_post_ID )
			{
				$prev_post_ID = $Item->ID;
				$count++;
			}

			// Open new cat:
			echo "\n\t".'<td valign="top" style="padding-bottom:10px">';

			$Chapter = & $Item->get_main_Chapter();
			$cat_name = $Chapter->get( 'name', 'htmlhead' );

			// ======================================
			echo '<h3><a href="'.$Chapter->get_permanent_url().'">'.$cat_name.'</a></h3>';
			$i = 0;
			while( $Item = & $lp_BlogList->get_item() )
			{
				if( $i >= $params['limit'] )
					continue;

				$Item->title( array(
						'before' => '&bull;&nbsp;',
						'after'  => '<br />',
						'format' => 'htmlhead',
					) );

				$i++;
			}
			// ======================================

			echo '</td>';
			if( $count % $nb_cols == 0 )
			{
				echo '</tr>';
			}
		}
		if( $count && ( $count % $nb_cols != 0 ) )
		{
			echo '</tr>';
		}
		echo '</table>';
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
		if( strlen($string) < $cut ) return $string;

		global $current_charset;

		$pattern = (strtolower($current_charset) == 'utf-8') ? '/[\s]+/iu' : '/[\s]+/i';
		$string = trim( preg_replace( $pattern, ' ', strip_tags( $string, '<p><pre><br><ul><li><b><strong><u><i>' ) ) );
		$length = abs($cut);

		if( function_exists('mb_strlen') && function_exists('mb_substr') )
		{
			if( $length < mb_strlen($string) && $cut > 0 )
			{
				while ( $string{$length} != $delimeter && $length > 0 )
				{
					$length--;
				}
				return balance_tags( mb_substr($string, 0, $length).'...' ).$after_text;
			}
			elseif( $length < mb_strlen($string) && $cut < 0 )
			{
				$string = strrev($string);

				while ( $string{$length} != $delimeter && $length > 0 )
				{
					$length--;
				}
				return strrev( balance_tags( mb_substr($string, 0, $length).'...' ).$before_text );
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
				return balance_tags( substr($string, 0, $length).'...' ).$after_text;
			}
			elseif( $length < strlen($string) && $cut < 0 )
			{
				$string = strrev($string);

				while ( $string{$length} != $delimeter && $length > 0 )
				{
					$length--;
				}
				return strrev( balance_tags( substr($string, 0, $length).'...' ).$before_text );
			}
			else
			{
				return $string;
			}
		}
	}


	function set_cookie( $name, $value, $time = '#' )
	{
		global $cookie_path, $cookie_domain;

		if( $time == '#' ) $time = time() + 604800; // 7 days
		if( @setcookie( $name, $value, $time, $cookie_path, $cookie_domain ) ) return true;

		return false;
	}



	/*
	This code is from http://detectmobilebrowsers.mobi/ - please do not republish it without due credit and hyperlink to http://detectmobilebrowsers.mobi

	For help generating the function call visit http://detectmobilebrowsers.mobi/ and use the function generator.

	Published by Andy Moore - .mobi certified mobile web developer - http://andymoore.info/

	This code is free to download and use on non-profit websites, if your website makes a profit or you require support using this code please upgrade.

	Upgrade for use on commercial websites and support: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=1064282

	To submit a support request please forward your PayPal receipt with your questions to the email address you sent the money to and I will endeavour to get back to you. It might take me a few days but I reply to all support issues with as much helpful info as I can provide.

	Change Log

	  * 25.11.08 - Added Amazon's Kindle to the pipe seperated array
	  * 27.11.08 - Added support for Blackberry options
	  * 27.01.09 - Added usage samples & help with PHP in HTML
	  * 09.03.09 - Added support for Windows Mobile options
	  * 09.03.09 - Removed 'ppc;'=>'ppc;', from array to reduce false positives
	  * 09.03.09 - Added support for Palm OS options
	  * 09.03.09 - Added sample .htaccess html.html and help.html files to download

	*/

	function mobile_device_detect($iphone=true,$android=true,$opera=true,$blackberry=true,$palm=true,$windows=true,$mobileredirect=false,$desktopredirect=false){

	  global $Hit;

	  $mobile_browser   = false; // set mobile browser as false till we can prove otherwise
	  $user_agent       = strtolower($Hit->get_user_agent()); // get the user agent value - this should be cleaned to ensure no nefarious input gets executed
	  $accept           = (isset($_SERVER['HTTP_ACCEPT'])) ? $_SERVER['HTTP_ACCEPT'] : ''; // get the content accept value - this should be cleaned to ensure no nefarious input gets executed
	//var_export($user_agent);
	  switch(true){ // using a switch against the following statements which could return true is more efficient than the previous method of using if statements

		case (eregi('ipod',$user_agent)||eregi('iphone',$user_agent)); // we find the words iphone or ipod in the user agent
		  $mobile_browser = $iphone; // mobile browser is either true or false depending on the setting of iphone when calling the function
		  if(substr($iphone,0,4)=='http'){ // does the value of iphone resemble a url
			$mobileredirect = $iphone; // set the mobile redirect url to the url value stored in the iphone value
		  } // ends the if for iphone being a url
		break; // break out and skip the rest if we've had a match on the iphone or ipod

		case (eregi('android',$user_agent));  // we find android in the user agent
		  $mobile_browser = $android; // mobile browser is either true or false depending on the setting of android when calling the function
		  if(substr($android,0,4)=='http'){ // does the value of android resemble a url
			$mobileredirect = $android; // set the mobile redirect url to the url value stored in the android value
		  } // ends the if for android being a url
		break; // break out and skip the rest if we've had a match on android

		case (eregi('opera mini',$user_agent)); // we find opera mini in the user agent
		  $mobile_browser = $opera; // mobile browser is either true or false depending on the setting of opera when calling the function
		  if(substr($opera,0,4)=='http'){ // does the value of opera resemble a rul
			$mobileredirect = $opera; // set the mobile redirect url to the url value stored in the opera value
		  } // ends the if for opera being a url
		break; // break out and skip the rest if we've had a match on opera

		case (eregi('blackberry',$user_agent)); // we find blackberry in the user agent
		  $mobile_browser = $blackberry; // mobile browser is either true or false depending on the setting of blackberry when calling the function
		  if(substr($blackberry,0,4)=='http'){ // does the value of blackberry resemble a rul
			$mobileredirect = $blackberry; // set the mobile redirect url to the url value stored in the blackberry value
		  } // ends the if for blackberry being a url
		break; // break out and skip the rest if we've had a match on blackberry

		case (preg_match('/(palm os|palm|hiptop|avantgo|plucker|xiino|blazer|elaine)/i',$user_agent)); // we find palm os in the user agent - the i at the end makes it case insensitive
		  $mobile_browser = $palm; // mobile browser is either true or false depending on the setting of palm when calling the function
		  if(substr($palm,0,4)=='http'){ // does the value of palm resemble a rul
			$mobileredirect = $palm; // set the mobile redirect url to the url value stored in the palm value
		  } // ends the if for palm being a url
		break; // break out and skip the rest if we've had a match on palm os

		case (preg_match('/(windows ce; ppc;|windows ce; smartphone;|windows ce; iemobile)/i',$user_agent)); // we find windows mobile in the user agent - the i at the end makes it case insensitive
		  $mobile_browser = $windows; // mobile browser is either true or false depending on the setting of windows when calling the function
		  if(substr($windows,0,4)=='http'){ // does the value of windows resemble a rul
			$mobileredirect = $windows; // set the mobile redirect url to the url value stored in the windows value
		  } // ends the if for windows being a url
		break; // break out and skip the rest if we've had a match on windows

		case (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|vodafone|o2|pocket|kindle|mobile|pda|psp|treo)/i',$user_agent)); // check if any of the values listed create a match on the user agent - these are some of the most common terms used in agents to identify them as being mobile devices - the i at the end makes it case insensitive
		  $mobile_browser = true; // set mobile browser to true
		break; // break out and skip the rest if we've preg_match on the user agent returned true

		case ((strpos($accept,'text/vnd.wap.wml')>0)||(strpos($accept,'application/vnd.wap.xhtml+xml')>0)); // is the device showing signs of support for text/vnd.wap.wml or application/vnd.wap.xhtml+xml
		  $mobile_browser = true; // set mobile browser to true
		break; // break out and skip the rest if we've had a match on the content accept headers

		case (isset($_SERVER['HTTP_X_WAP_PROFILE'])||isset($_SERVER['HTTP_PROFILE'])); // is the device giving us a HTTP_X_WAP_PROFILE or HTTP_PROFILE header - only mobile devices would do this
		  $mobile_browser = true; // set mobile browser to true
		break; // break out and skip the final step if we've had a return true on the mobile specfic headers

		case (in_array(substr($user_agent,0,4),array('1207'=>'1207','3gso'=>'3gso','4thp'=>'4thp','501i'=>'501i','502i'=>'502i','503i'=>'503i','504i'=>'504i','505i'=>'505i','506i'=>'506i','6310'=>'6310','6590'=>'6590','770s'=>'770s','802s'=>'802s','a wa'=>'a wa','acer'=>'acer','acs-'=>'acs-','airn'=>'airn','alav'=>'alav','asus'=>'asus','attw'=>'attw','au-m'=>'au-m','aur '=>'aur ','aus '=>'aus ','abac'=>'abac','acoo'=>'acoo','aiko'=>'aiko','alco'=>'alco','alca'=>'alca','amoi'=>'amoi','anex'=>'anex','anny'=>'anny','anyw'=>'anyw','aptu'=>'aptu','arch'=>'arch','argo'=>'argo','bell'=>'bell','bird'=>'bird','bw-n'=>'bw-n','bw-u'=>'bw-u','beck'=>'beck','benq'=>'benq','bilb'=>'bilb','blac'=>'blac','c55/'=>'c55/','cdm-'=>'cdm-','chtm'=>'chtm','capi'=>'capi','comp'=>'comp','cond'=>'cond','craw'=>'craw','dall'=>'dall','dbte'=>'dbte','dc-s'=>'dc-s','dica'=>'dica','ds-d'=>'ds-d','ds12'=>'ds12','dait'=>'dait','devi'=>'devi','dmob'=>'dmob','doco'=>'doco','dopo'=>'dopo','el49'=>'el49','erk0'=>'erk0','esl8'=>'esl8','ez40'=>'ez40','ez60'=>'ez60','ez70'=>'ez70','ezos'=>'ezos','ezze'=>'ezze','elai'=>'elai','emul'=>'emul','eric'=>'eric','ezwa'=>'ezwa','fake'=>'fake','fly-'=>'fly-','fly_'=>'fly_','g-mo'=>'g-mo','g1 u'=>'g1 u','g560'=>'g560','gf-5'=>'gf-5','grun'=>'grun','gene'=>'gene','go.w'=>'go.w','good'=>'good','grad'=>'grad','hcit'=>'hcit','hd-m'=>'hd-m','hd-p'=>'hd-p','hd-t'=>'hd-t','hei-'=>'hei-','hp i'=>'hp i','hpip'=>'hpip','hs-c'=>'hs-c','htc '=>'htc ','htc-'=>'htc-','htca'=>'htca','htcg'=>'htcg','htcp'=>'htcp','htcs'=>'htcs','htct'=>'htct','htc_'=>'htc_','haie'=>'haie','hita'=>'hita','huaw'=>'huaw','hutc'=>'hutc','i-20'=>'i-20','i-go'=>'i-go','i-ma'=>'i-ma','i230'=>'i230','iac'=>'iac','iac-'=>'iac-','iac/'=>'iac/','ig01'=>'ig01','im1k'=>'im1k','inno'=>'inno','iris'=>'iris','jata'=>'jata','java'=>'java','kddi'=>'kddi','kgt'=>'kgt','kgt/'=>'kgt/','kpt '=>'kpt ','kwc-'=>'kwc-','klon'=>'klon','lexi'=>'lexi','lg g'=>'lg g','lg-a'=>'lg-a','lg-b'=>'lg-b','lg-c'=>'lg-c','lg-d'=>'lg-d','lg-f'=>'lg-f','lg-g'=>'lg-g','lg-k'=>'lg-k','lg-l'=>'lg-l','lg-m'=>'lg-m','lg-o'=>'lg-o','lg-p'=>'lg-p','lg-s'=>'lg-s','lg-t'=>'lg-t','lg-u'=>'lg-u','lg-w'=>'lg-w','lg/k'=>'lg/k','lg/l'=>'lg/l','lg/u'=>'lg/u','lg50'=>'lg50','lg54'=>'lg54','lge-'=>'lge-','lge/'=>'lge/','lynx'=>'lynx','leno'=>'leno','m1-w'=>'m1-w','m3ga'=>'m3ga','m50/'=>'m50/','maui'=>'maui','mc01'=>'mc01','mc21'=>'mc21','mcca'=>'mcca','medi'=>'medi','meri'=>'meri','mio8'=>'mio8','mioa'=>'mioa','mo01'=>'mo01','mo02'=>'mo02','mode'=>'mode','modo'=>'modo','mot '=>'mot ','mot-'=>'mot-','mt50'=>'mt50','mtp1'=>'mtp1','mtv '=>'mtv ','mate'=>'mate','maxo'=>'maxo','merc'=>'merc','mits'=>'mits','mobi'=>'mobi','motv'=>'motv','mozz'=>'mozz','n100'=>'n100','n101'=>'n101','n102'=>'n102','n202'=>'n202','n203'=>'n203','n300'=>'n300','n302'=>'n302','n500'=>'n500','n502'=>'n502','n505'=>'n505','n700'=>'n700','n701'=>'n701','n710'=>'n710','nec-'=>'nec-','nem-'=>'nem-','newg'=>'newg','neon'=>'neon','netf'=>'netf','noki'=>'noki','nzph'=>'nzph','o2 x'=>'o2 x','o2-x'=>'o2-x','opwv'=>'opwv','owg1'=>'owg1','opti'=>'opti','oran'=>'oran','p800'=>'p800','pand'=>'pand','pg-1'=>'pg-1','pg-2'=>'pg-2','pg-3'=>'pg-3','pg-6'=>'pg-6','pg-8'=>'pg-8','pg-c'=>'pg-c','pg13'=>'pg13','phil'=>'phil','pn-2'=>'pn-2','pt-g'=>'pt-g','palm'=>'palm','pana'=>'pana','pire'=>'pire','pock'=>'pock','pose'=>'pose','psio'=>'psio','qa-a'=>'qa-a','qc-2'=>'qc-2','qc-3'=>'qc-3','qc-5'=>'qc-5','qc-7'=>'qc-7','qc07'=>'qc07','qc12'=>'qc12','qc21'=>'qc21','qc32'=>'qc32','qc60'=>'qc60','qci-'=>'qci-','qwap'=>'qwap','qtek'=>'qtek','r380'=>'r380','r600'=>'r600','raks'=>'raks','rim9'=>'rim9','rove'=>'rove','s55/'=>'s55/','sage'=>'sage','sams'=>'sams','sc01'=>'sc01','sch-'=>'sch-','scp-'=>'scp-','sdk/'=>'sdk/','se47'=>'se47','sec-'=>'sec-','sec0'=>'sec0','sec1'=>'sec1','semc'=>'semc','sgh-'=>'sgh-','shar'=>'shar','sie-'=>'sie-','sk-0'=>'sk-0','sl45'=>'sl45','slid'=>'slid','smb3'=>'smb3','smt5'=>'smt5','sp01'=>'sp01','sph-'=>'sph-','spv '=>'spv ','spv-'=>'spv-','sy01'=>'sy01','samm'=>'samm','sany'=>'sany','sava'=>'sava','scoo'=>'scoo','send'=>'send','siem'=>'siem','smar'=>'smar','smit'=>'smit','soft'=>'soft','sony'=>'sony','t-mo'=>'t-mo','t218'=>'t218','t250'=>'t250','t600'=>'t600','t610'=>'t610','t618'=>'t618','tcl-'=>'tcl-','tdg-'=>'tdg-','telm'=>'telm','tim-'=>'tim-','ts70'=>'ts70','tsm-'=>'tsm-','tsm3'=>'tsm3','tsm5'=>'tsm5','tx-9'=>'tx-9','tagt'=>'tagt','talk'=>'talk','teli'=>'teli','topl'=>'topl','tosh'=>'tosh','up.b'=>'up.b','upg1'=>'upg1','utst'=>'utst','v400'=>'v400','v750'=>'v750','veri'=>'veri','vk-v'=>'vk-v','vk40'=>'vk40','vk50'=>'vk50','vk52'=>'vk52','vk53'=>'vk53','vm40'=>'vm40','vx98'=>'vx98','virg'=>'virg','vite'=>'vite','voda'=>'voda','vulc'=>'vulc','w3c '=>'w3c ','w3c-'=>'w3c-','wapj'=>'wapj','wapp'=>'wapp','wapu'=>'wapu','wapm'=>'wapm','wig '=>'wig ','wapi'=>'wapi','wapr'=>'wapr','wapv'=>'wapv','wapy'=>'wapy','wapa'=>'wapa','waps'=>'waps','wapt'=>'wapt','winc'=>'winc','winw'=>'winw','wonu'=>'wonu','x700'=>'x700','xda2'=>'xda2','xdag'=>'xdag','yas-'=>'yas-','your'=>'your','zte-'=>'zte-','zeto'=>'zeto','acs-'=>'acs-','alav'=>'alav','alca'=>'alca','amoi'=>'amoi','aste'=>'aste','audi'=>'audi','avan'=>'avan','benq'=>'benq','bird'=>'bird','blac'=>'blac','blaz'=>'blaz','brew'=>'brew','brvw'=>'brvw','bumb'=>'bumb','ccwa'=>'ccwa','cell'=>'cell','cldc'=>'cldc','cmd-'=>'cmd-','dang'=>'dang','doco'=>'doco','eml2'=>'eml2','eric'=>'eric','fetc'=>'fetc','hipt'=>'hipt','http'=>'http','ibro'=>'ibro','idea'=>'idea','ikom'=>'ikom','inno'=>'inno','ipaq'=>'ipaq','jbro'=>'jbro','jemu'=>'jemu','java'=>'java','jigs'=>'jigs','kddi'=>'kddi','keji'=>'keji','kyoc'=>'kyoc','kyok'=>'kyok','leno'=>'leno','lg-c'=>'lg-c','lg-d'=>'lg-d','lg-g'=>'lg-g','lge-'=>'lge-','libw'=>'libw','m-cr'=>'m-cr','maui'=>'maui','maxo'=>'maxo','midp'=>'midp','mits'=>'mits','mmef'=>'mmef','mobi'=>'mobi','mot-'=>'mot-','moto'=>'moto','mwbp'=>'mwbp','mywa'=>'mywa','nec-'=>'nec-','newt'=>'newt','nok6'=>'nok6','noki'=>'noki','o2im'=>'o2im','opwv'=>'opwv','palm'=>'palm','pana'=>'pana','pant'=>'pant','pdxg'=>'pdxg','phil'=>'phil','play'=>'play','pluc'=>'pluc','port'=>'port','prox'=>'prox','qtek'=>'qtek','qwap'=>'qwap','rozo'=>'rozo','sage'=>'sage','sama'=>'sama','sams'=>'sams','sany'=>'sany','sch-'=>'sch-','sec-'=>'sec-','send'=>'send','seri'=>'seri','sgh-'=>'sgh-','shar'=>'shar','sie-'=>'sie-','siem'=>'siem','smal'=>'smal','smar'=>'smar','sony'=>'sony','sph-'=>'sph-','symb'=>'symb','t-mo'=>'t-mo','teli'=>'teli','tim-'=>'tim-','tosh'=>'tosh','treo'=>'treo','tsm-'=>'tsm-','upg1'=>'upg1','upsi'=>'upsi','vk-v'=>'vk-v','voda'=>'voda','vx52'=>'vx52','vx53'=>'vx53','vx60'=>'vx60','vx61'=>'vx61','vx70'=>'vx70','vx80'=>'vx80','vx81'=>'vx81','vx83'=>'vx83','vx85'=>'vx85','wap-'=>'wap-','wapa'=>'wapa','wapi'=>'wapi','wapp'=>'wapp','wapr'=>'wapr','webc'=>'webc','whit'=>'whit','winw'=>'winw','wmlb'=>'wmlb','xda-'=>'xda-',))); // check against a list of trimmed user agents to see if we find a match
		  $mobile_browser = true; // set mobile browser to true
		break; // break even though it's the last statement in the switch so there's nothing to break away from but it seems better to include it than exclude it

	  } // ends the switch

	  // tell adaptation services (transcoders and proxies) to not alter the content based on user agent as it's already being managed by this script
	  //header('Cache-Control: no-transform'); // http://mobiforge.com/developing/story/setting-http-headers-advise-transcoding-proxies
	  //header('Vary: User-Agent, Accept'); // http://mobiforge.com/developing/story/setting-http-headers-advise-transcoding-proxies

	  // if redirect (either the value of the mobile or desktop redirect depending on the value of $mobile_browser) is true redirect else we return the status of $mobile_browser
	  if($redirect = ($mobile_browser==true) ? $mobileredirect : $desktopredirect){
		header('Location: '.$redirect); // redirect to the right url for this device
		exit;
	  }else{
		return $mobile_browser; // will return either true or false
	  }

	}
}


?>