<?php
/**
 * This file implements the vCard plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2012 Russian b2evolution - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Alex (sam2kb)
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class vcard_plugin extends Plugin
{
	var $name = 'vCard plugin';
	var $code = 'vcard';
	var $priority = 50;
	var $version = '1.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=';

	var $min_app_version = '5';
	var $max_app_version = '6';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;

	private $dl_url_prefix = 'vcard';


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Creates vCards v3.0 from user data.');
		$this->long_desc = $this->short_desc;
	}


	function GetDependencies()
	{
		return array(
				'requires' => array(
					'app_min' => '4.2',
				),
			);
	}


	function BeforeBlogDisplay()
	{
		global $ReqHost, $ReqURI, $baseurl;

		if( preg_match( '~^(index\.php/)?'.$this->dl_url_prefix.'/([0-9]+)/([a-z0-9.]{3})/?$~i', str_replace( $baseurl, '', $ReqHost.$ReqURI ), $matches ) )
		{
			$user_ID = $matches[2];
			$format = $matches[3];
		}

		// Not a download, we return
		if( empty($format) ) return;

		global $current_User, $current_charset, $current_locale, $locale_from_get, $default_locale;

		return $this->get_vcard( $user_ID, $format );
	}


	function get_vcard( $user_ID, $format )
	{
		global $io_charset;

		// Make sure the async responses are never cached:
		header_nocache();
		header_content_type( 'text/vcard', $io_charset );

		switch( $format )
		{
			case '3.0':
				break;

			default:
				$this->err( $this->T_('Unsupported vCard format') );
		}

		$UserCache = & get_UserCache();
		$User = & $UserCache->get_by_ID( $user_ID, false, false );

		if( empty($User) )
		{
			$this->err( $this->T_('User not found') );
		}

		$dataArray = array(
			'fileName'		=> 'vcardx',

			'vcard_birtda'	=> '1367-05-05',
			'vcard_f_name'	=> 'Behrouz',
			'vcard_s_name'	=> 'IFLashLord',
			'vcard_uri'		=> 'http://wp.iflashlord.com',
			'vcard_nickna'	=> 'IranFLashLord',
			'vcard_note'	=> 'this is a vcard note!',
			'vcard_cellul'	=> '+9891300000',
			'vcard_compan'	=> 'IFLashLord Studio Web Design Company.',
			'vcard_p_pager'	=> 'pagerNumber',

			'vcard_h_addr'	=> 'Address of Live Position',
			'vcard_h_city'	=> 'ShahinShahr',
			'vcard_h_coun'	=> 'Iran',
			'vcard_h_fax'	=> 'Faxnumber',
			'vcard_h_mail'	=> 'HomeMail <me@iflashlord.com>',
			'vcard_h_phon'	=> 'Home Phone 0312 000000',
			'vcard_h_zip'	=> 'ZipCode1234567890',
			'vcard_h_uri'	=> 'Home URi http://www.iflashlord.com',

			'vcard_w_addr'	=> 'Address of Work Position',
			'vcard_w_city'	=> 'Esfahan',
			'vcard_w_coun'	=> 'Iran',
			'vcard_w_fax'	=> 'WorkFax',
			'vcard_w_mail'	=> 'Work Mail <info@iflashlord.com>',
			'vcard_w_phon'	=> '+98311 000000',
			'vcard_w_role'	=> 'Web Designer & Programer',
			'vcard_w_titl'	=> 'IFLashLord [TITLE]',
			'vcard_w_zip'	=> 'WorkZipCode1234567890',
			'vcard_w_uri'	=> 'Work URi http://about.iflashlord.com'
		);

		$User->userfields_load();
	//	var_export($User->userfields);

		$params = array(
			'vcard_birtda'	=> '',
			'vcard_f_name'	=> $User->get('firstname'),
			'vcard_s_name'	=> $User->get('lastname'),
			'vcard_photo'	=> $this->get_user_pic( $User ),
			'vcard_uri'		=> $User->get('url'),
			'vcard_nickna'	=> $User->get('nickname'),
			'vcard_note'	=> $this->get_field( 28, $User ),	// about me
			'vcard_cellul'	=> $this->get_field( 7, $User ),
			'vcard_compan'	=> $this->get_field( 23, $User ),
			'vcard_p_pager'	=> '',

			'vcard_h_addr'	=> $this->get_field( 27, $User ),
			'vcard_h_city'	=> $User->get_city_name(),
			'vcard_h_coun'	=> $User->get_country_name(),
			'vcard_h_fax'	=> $this->get_field( 11, $User ),
			'vcard_h_mail'	=> $User->get('email'),
			'vcard_h_phon'	=> $this->get_field( 9, $User ),
			'vcard_h_zip'	=> '',
			'vcard_h_uri'	=> '',

			'vcard_w_addr'	=> $this->get_field( 26, $User ),	// main address
			'vcard_w_city'	=> '',
			'vcard_w_coun'	=> '',
			'vcard_w_fax'	=> $this->get_field( 10, $User ),
			'vcard_w_mail'	=> '',
			'vcard_w_phon'	=> $this->get_field( 8, $User ),
			'vcard_w_role'	=> $this->get_field( 22, $User ),
			'vcard_w_titl'	=> '',
			'vcard_w_zip'	=> '',
			'vcard_w_uri'	=> '',
		);

		require_once dirname(__FILE__).'/_vcard.class.php';
		$vCard = new vCard( $params );

		$vCard->author = $this->author_url.' ['.$this->author.']';
		$vCard->version = 'vCard for b2evolution v'.$this->version;
		$vCard->filename = $User->login.'-vcard';

		$vCard->create( $format );
		//$vCard->output();
		$vCard->download();

		exit(0); // just in case
	}


	function get_field( $ufdf_ID, $User )
	{	// EXTREMELY SLOW !!!
		$r = array();
		foreach( $User->userfields as $field )
		{
			if( $ufdf_ID == $field->ufdf_ID )
			{	// Combine multiple fields
				$r[] = $field->uf_varchar;
			}
		}

		if( !empty($r) ) return implode( ';', $r );
	}


	function get_user_pic( $User )
	{
		global $thumbnail_sizes;

		$size_name = 'crop-top-64x64';

		if( $File = & $User->get_avatar_File() )
		{
			$Filetype = & $File->get_Filetype();

			$path = $File->get_af_thumb_path( $size_name );
			if( ! @is_file($path) )
			{
				load_funcs( '/files/model/_image.funcs.php' );

				// Set all params for requested size:
				list( $thumb_type, $thumb_width, $thumb_height, $thumb_quality ) = $thumbnail_sizes[$size_name];
				list( $err, $src_imh ) = load_image( $File->get_full_path(), $Filetype->mimetype );

				if( empty( $err ) )
				{
					list( $err, $dest_imh ) = generate_thumb( $src_imh, $thumb_type, $thumb_width, $thumb_height );
					if( empty( $err ) )
					{
						$err = $File->save_thumb_to_cache( $dest_imh, $size_name, $Filetype->mimetype, $thumb_quality );
						if( empty( $err ) )
						{
							$path = $File->get_af_thumb_path( $size_name );
						}
					}
				}
			}

			if( @is_file($path) )
			{
				$content = @file_get_contents($path);
				if( !empty($content) )
				{
					$r[0] = str_ireplace( 'IMAGE/', '', $Filetype->mimetype );
					$r[1] = $content;

					return $r;
				}
			}
		}
	}


	function err( $str )
	{
		echo $str;
		exit(0);
	}
}

?>