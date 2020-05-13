<?php
/**
 *
 * This file implements the Skin switcher widget for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2012 by Sonorth Corp. - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Sonorth Corp.
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class skin_switcher_plugin extends Plugin
{
	var $name = 'Skin switcher';
	var $code = 'skin_switcher';
	var $priority = 30;
	var $version = '0.3';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://www.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=16500';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;


	/**
	 * Init
	 *
	 * This gets called after a plugin has been registered/instantiated.
	 */
	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Users and guests can choose the skin for each blog');
		$this->long_desc = $this->T_('Users and guests can choose the skin for each blog');
	}
	
	
	function GetDefaultSettings( $params )
	{
		return array(
				'memorize_tempskin' => array(
					'label' => $this->T_('Memorize tempskin'),
					'defaultvalue' => 0,
					'type' => 'checkbox',
					'note' => $this->T_('Check this to save "tempskin" in cookies.'),
				),
			);
	}
	
	
	/**
	* Get definitions for widget specific editable params
	*
	* @see Plugin::GetDefaultSettings()
	* @param local params like 'for_editing' => true
	*/
	function get_widget_param_definitions( $params )
	{
		return array(
				'title' => array(
					'label' => $this->T_('Widget title'),
					'defaultvalue' => $this->T_('Choose skin'),
					'type' => 'text',
					'note' => $this->T_('Widget title displayed in skin.'),
				),
				'submit' => array(
					'label' => $this->T_('Submit button'),
					'defaultvalue' => $this->T_('Apply selection'),
					'type' => 'text',
					'note' => $this->T_('Submit button text.'),
				),
				'display' => array(
					'label' => $this->T_('List style'),
					'note' => $this->T_('How do we want to display the list?'),
					'type' => 'select',
					'defaultvalue' => 'list',
					'options' => array( 'list' => $this->T_('Standard links'),
										'form' => $this->T_('Dropdown menu') ),
				),
			);
	}
	
	
	
	/**
	 * Event handler: SkinTag (widget)
	 *
	 * @param array Associative array of parameters.
	 * @return boolean did we display?
	 * 
	 */
	function SkinTag( $params )
	{
		/**
		 * Default params:
		 */
		// This is what will enclose the block in the skin:
		if(!isset($params['block_start'])) $params['block_start'] = '<div class="bSideItem widget_plugin_'.$this->code.'">';
		if(!isset($params['block_end'])) $params['block_end'] = "</div>\n";
		if(!isset($params['block_title_start'])) $params['block_title_start'] = '<h3>';
		if(!isset($params['block_title_end'])) $params['block_title_end'] = '</h3>';
		
		if(!isset($params['title'])) $params['title'] = $this->T_('Choose skin');
		if(!isset($params['display'])) $params['display'] = 'list';
		if(!isset($params['submit'])) $params['submit'] = $this->T_('Apply selection');
		
		$this->disp_skin_selector( $params );
	}
	
	
	
	/**
	 * Event handler: Called before a blog gets displayed (in _blog_main.inc.php).
	 */
	function BeforeBlogDisplay( & $params )
	{
		global $Blog, $tempskin;
		
		$key = 'user_skin_'.$Blog->ID;
		
		if( !empty($tempskin) )
		{
			if( ! $this->Settings->get('memorize_tempskin') )
			{	// Do not memorize tempskin
				return;
			}
			elseif( ! $this->check_skin($tempskin) )
			{	// Do not owerride tempskin (feeds)
				return;
			}
			else
			{	// Memorize and display
				$_GET[$key] = $tempskin;
			}
		}
		
		if( array_key_exists( $key, $_GET ) )
		{
			$new_skin = $_GET[$key];
			
			if( $new_skin == 'RESET' )
			{	// Reset the skin to default one
				$this->set_cookie( $key, '', time() - 3600 );
				return;
			}
			elseif( $this->check_skin($new_skin) )
			{	// Check if the skin exists
				$this->use_skin($new_skin);
				return;
			}
		}
		// Use saved skin
		if( array_key_exists( $key, $_COOKIE ) && $this->check_skin($_COOKIE[$key]) )
		{
			$this->use_skin($_COOKIE[$key]);
		}
	}
	
	
	
	function disp_skin_selector( $params )
	{
		global $Blog, $skin;
		
		// Get skins list
		$SkinCache = & get_Cache( 'SkinCache' );
		$SkinCache->load_by_type( 'normal' );
		
		echo $params['block_start'];
		
		echo $params['block_title_start'];
		echo $params['title'];
		echo $params['block_title_end'];
		
		if( $params['display'] == 'list' )
		{
			echo '<ul>';
			// Reset the skin to default one
			echo "\n".'<li><a href="'.$Blog->gen_blogurl().'?user_skin_'.$Blog->ID.'=RESET" rel="nofollow">'.$this->T_('Use default skin').'</a></li>';
			
			foreach ( $SkinCache->cache as $Skin )
			{
				if( $Skin->type != 'normal' )
				{	// This skin cannot be used here...
					continue;
				}
				echo "\n".'<li><a href="'.$Blog->gen_blogurl().'?user_skin_'.$Blog->ID.'='.$Skin->folder.'" rel="nofollow">'.$Skin->name.'</a></li>';
			}		
			echo '</ul>';
		}
		elseif( $params['display'] == 'form' )
		{
			echo '<form style="margin-top:10px" action="'.$Blog->gen_blogurl().'" method="get"><select name="user_skin_'.$Blog->ID.'">';
			
			echo '<option value="RESET">'.$this->T_('Use default skin').'</option>';

			foreach ( $SkinCache->cache as $Skin )
			{
				if( $Skin->type != 'normal' )
				{	// This skin cannot be used here...
					continue;
				}
				echo "\n".'<option value="'.$Skin->folder.'">'.$Skin->name.'</option>';
			}
			echo '</select><br /><input style="margin-top:10px" type="submit" value="'.$params['submit'].'" /></form>';
		}
		echo $params['block_end'];
	}
	
	
	function set_cookie( $name, $value, $time = '#' )
	{
		global $cookie_path, $cookie_domain;
		
		if( $time == '#' ) $time = time() + 315360000;
		if( setcookie( $name, $value, $time, $cookie_path, $cookie_domain ) ) return true;
		
		return false;
	}
	
	
	function use_skin( $skin )
	{
		global $Blog;
		
		if( !empty($skin) && $this->set_cookie( 'user_skin_'.$Blog->ID, $skin ) )
		{	// Override the skin
			memorize_param( 'skin', 'string', '', $skin );
		}
	}
	
	
	
	function check_skin( $skin )
	{
		// Check if the skin exists
		if( skin_exists($skin) )
		{	// Skip XML skin
			if( @preg_match( '~^_~', $skin ) ) return false;
			
			return true;
		}
		return false;
	}
	
}

?>