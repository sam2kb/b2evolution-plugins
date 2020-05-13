<?php
/**
 * This file implements the Translit URLs Plugin for {@link http://b2evolution.net/}.
 * 
 * @copyright (c)2008-2012 Russian b2evolution - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Russian b2evolution
 */

if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class translit_urls_plugin extends Plugin
{
	var $name = 'Translit URLs';
	var $code = 'translit_urls';
	var $priority = 40;
	var $version = '1.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=15416';
	
	var $apply_rendering = 'never';
	var $number_of_installs = 1;
	
	// Define your own characters. To replace "U" with "you" use 'U'=>'you'
	var $char_map = array(
			'А'=>'A', 'Б'=>'B', 'В'=>'V', 'Г'=>'G', 'Д'=>'D', 'Е'=>'E', 'Ё'=>'YO',
			'Ж'=>'ZH', 'З'=>'Z', 'И'=>'I', 'Й'=>'J', 'К'=>'K', 'Л'=>'L', 'М'=>'M',
			'Н'=>'N', 'О'=>'O', 'П'=>'P', 'Р'=>'R', 'С'=>'S', 'Т'=>'T', 'У'=>'U',
			'Ф'=>'F', 'Х'=>'X', 'Ц'=>'C', 'Ч'=>'CH', 'Ш'=>'SH', 'Щ'=>'SHH',
			'Ъ'=>'', 'Ы'=>'Y', 'Ь'=>'', 'Э'=>'E', 'Ю'=>'YU', 'Я'=>'YA',
			'а'=>'a', 'б'=>'b', 'в'=>'v', 'г'=>'g', 'д'=>'d', 'е'=>'e', 'ё'=>'yo',
			'ж'=>'zh', 'з'=>'z', 'и'=>'i', 'й'=>'j', 'к'=>'k', 'л'=>'l', 'м'=>'m',
			'н'=>'n', 'о'=>'o', 'п'=>'p', 'р'=>'r', 'с'=>'s', 'т'=>'t', 'у'=>'u',
			'ф'=>'f', 'х'=>'x', 'ц'=>'c', 'ч'=>'ch', 'ш'=>'sh', 'щ'=>'shh',
			'ъ'=>'', 'ы'=>'y', 'ь'=>'', 'э'=>'e', 'ю'=>'yu', 'я'=>'ya',
			'Є'=>'YE', 'І'=>'I', 'Ѓ'=>'G',
			'і'=>'i', 'ї'=>'ji', '№'=>'#', 'є'=>'ye', 'ѓ'=>'g',
			'«'=>'', '»'=>'', '—'=>'-'
		);
	  
			  
	function PluginInit()
	{
		$this->short_desc = $this->T_('Transliterate item\'s URL');
		$this->long_desc = $this->T_('Transliterate Russian/Ukrainian characters in item\'s URL to Latin');
	}
	
	
	# =============================================================================
	# PLUGIN SETTINGS
	
	function GetDefaultSettings()
	{
		global $app_version;
		// Check b2evo version		
		if ( version_compare( $app_version, '2.4.9', '>' ) )
		{
			$s = '200';
			$maxlength = 3;
		}
		else
		{
			$s = '40';
			$maxlength = 2;
		}
		
		return array(
			'use_translit' => array(
					'label' => $this->T_('Enable transliteration'),
					'defaultvalue' => 1,
					'type' => 'checkbox',
					'note' => $this->T_('Do we need to transliterate url titles?'),
				),

			'crop_at' => array(
					'label' => $this->T_('Url title length'),
					'defaultvalue' => $s,
					'size' => 2,
					'maxlength' => $maxlength,
					'valid_range' => array( 'min' => 10, 'max' => $s ),
					'note' => $this->T_('Crop url titles to this number of characters.').'<br />[ 10 - '.$s.' ] '.$this->T_('characters').'.',
				),
			
			'item_insert' => array(
					'label' => $this->T_('Apply to new'),
					'defaultvalue' => 1,
					'type' => 'checkbox',
					'note' => $this->T_('Apply URL transliteration to new items.'),
				),
			
			'item_update' => array(
					'label' => $this->T_('Apply to existing'),
					'defaultvalue' => 0,
					'type' => 'checkbox',
					'note' => $this->T_('Apply URL transliteration to existing items.<br>Warning: this will update item\'s URL every time when you change the title. Don\'t enable it unless you know what you are doing!'),
				),
			);
	}
	
	
	function TranslitUrl( $params )
	{
		global $Messages, $app_version;
		
		$urltitle = $params['Item']->get('title');
		
		if ( $this->Settings->get('use_translit') )
		{	// We need to transliterate
			$urltitle = strtr( $params['Item']->get('title'), $this->char_map );
		}
		
		if ( $this->Settings->get('crop_at') )
		{	// We need to crop
			$urltitle = $this->CropUrl( $urltitle, $this->Settings->get('crop_at') );
		}
		
		if ( version_compare( $app_version, '4.1', '<' ) )
		{	// This bug is fixed in 4.1, we don't need to play with messages
			$msg_before = count($Messages->messages['error']);
		}
		
		$urltitle = urltitle_validate( $urltitle, $params['Item']->get('title'), $params['Item']->ID );
		
		if ( version_compare( $app_version, '4.1', '<' ) )
		{
			if( $msg_before < count($Messages->messages['error']) )
			{	// Clear the last error message
				$Messages->_count['error']--;
				array_pop($Messages->messages['error']);
			}
		}
		
		// Set urltitle
		$params['Item']->set( 'urltitle', $urltitle );
		
		return $params['Item'];
	}
	
	
	function CropUrl( $url, $length, $save_words = false )
	{
		if( strlen($url) > $length )
        {
			$url = substr( $url, 0, $length );
        }        
        return $url;
	}


	function AdminBeforeItemEditCreate( & $params )
	{
		// Do not overwrite urltitle if already set
		if( $params['Item']->get('urltitle') ) return;
		
		if( $this->Settings->get('item_insert') ) $this->TranslitUrl($params);
	}
	
	
	function AdminBeforeItemEditUpdate( & $params )
	{
		if( $this->Settings->get('item_update') ) $this->TranslitUrl($params);			
	}
}

?>