<?php
/**
 * B2evolution Avatars Plugin
 *
 * The Avatars Plugin allows you to attach avatars (display pictures, gravatars, profile pics, etc) to a user, blog, post and category.
 * You upload avatars within the b2evo adminstration, with either a large or small (or both) avatar images.
 * If a large avatar image is specified then lightbox functionality is seen, as when the user clicks the small avatar, the large avatar will show in a lightbox.
 * It makes use of the GD2 Image and the Gallery Plugin’s Libraries allowing for images to be resized and compressed to specified values.
 *
 * @name B2evolution Avatars Plugin: _avatars.plugin.php
 * @version 2.3.1
 * @package plugins
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }
 * @author balupton: Benjamin Lupton - {@link http://www.balupton.com}
 * @author sam2kb: Russian b2evolution - {@link http://b2evo.sonorth.com/}
 *
 * Built on code originally released by balupton: Benjamin LUPTON - {@link http://www.balupton.com/}
 * @copyright (c) 2006-2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
 * @copyright (c) 2008-2012 by Russian b2evolution - {@link http://b2evo.sonorth.com/}
 *
 * @license GNU General Public License 2 (GPL) - {@link http://www.opensource.org/licenses/gpl-license.php}
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

// Define our class
class avatars_Plugin extends Plugin
{
	var $name = 'Avatars';
	var $code = 'evo_avatars';
	var $priority = 60;
	var $version = '2.3.1';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=15641';

	var $min_app_version = '4.1';
	var $max_app_version = '5';

	var $apply_rendering = 'lazy';
	var $number_of_installs = 1;


	/**
	 *	install_dir - directory containig avatars folder.
	 *  Must be created before installing the plugin.
	 *	e.g. /www/htdocs/blog/[ $install_dir ]avatars/
	 */
	var $install_dir = 'media/';

	var $media_url;
	var $media_path;

	var $avatars_dir = 'avatars/';
	var $avatars_url;
	var $avatars_path;

	var $temp_folder;
	var $avatars = NULL;

	var $avatar_types_size = 4;
	var $avatar_types = array( 'user', 'blog', 'category', 'post' );

	var $image_types_size = 1;
	var $image_types = array( 'gif', 'jpeg', 'jpg', 'png' );

	var $do_form_request_ran = false;
	var $admin_avatars = NULL;
	var $lightbox_plugin = false;


	function PluginInit( & $params )
	{
		// Load the resources
		$dir = dirname(__file__).'/resources/';
		require_once($dir.'functions/_scan_dir.funcs.php');
		scan_dir( $dir, 'inc_php', 'require_once($path);' );

		global $Plugins, $Messages, $baseurl, $admin_url, $basepath, $IMAGE_EXTENSIONS;

		define_image_vars();
		$this->image_types = $IMAGE_EXTENSIONS;
		$this->image_types_size = sizeof($this->image_types);

		$this->short_desc = $this->T_('Let\'s you \'easily\' add avatars/icons to various things.');
		$this->long_desc = $this->T_('Let\'s you \'easily\' add avatars/icons to ').implode(', ',$this->avatar_types).'.';

		$this->media_url = $baseurl.'media/';
		$this->media_path = $basepath.'media/';

		// Install in custom dir
		$this->avatars_url = $baseurl.$this->install_dir.$this->avatars_dir;
		$this->avatars_path = $basepath.$this->install_dir.$this->avatars_dir;

		$this->temp_folder = $this->avatars_path.'_temp/';

		if( ($jqplug = & $Plugins->get_by_code('ADjQLightbox')) !== false )
		{
			$choose_lightbox = $jqplug->Settings->get('choose_lightbox');
			if( $jqplug->status == 'enabled' && !empty($choose_lightbox) )
			{
				if(  $choose_lightbox == 'standard' )
				{	// We're going to use lightbox plugin's libraries
					$this->lightbox_plugin = 'standard';
				}
				else
				{	// We can't use lightbox plugin's libraries and we can't include ours,
					// Display the error
					$this->lightbox_plugin = NULL;

					if( !defined('JQUERYPLUGIN_ERROR') )
					{
						define('JQUERYPLUGIN_ERROR', true);
						$url = $admin_url.'?ctrl=plugins&amp;action=edit_settings&amp;plugin_ID='.$jqplug->ID;
						$Messages->head = 'Avatars plugin';
						$Messages->add( sprintf( $this->T_('In order to display large avatars in lightbox you should select the "standard" lightbox version in %s!'), '<b><a href="'.$url.'">jQuery Lightbox plugin</a></b>'), 'error' );
					}
				}
			}
		}
	}


	/**
	 * We require b2evo 4.1 or above.
	 */
	function GetDependencies()
	{
		return array(
				'requires' => array(
					'app_min' => '4.1',
				),
			);
	}


	# =============================================================================
	# PLUGIN SETTINGS

	/**
	 * Define here default settings that are then available in the backoffice
	 */
	function GetDefaultSettings( & $params )
	{
		$has_gd2 = function_exists('imagejpeg');

		$large_min = 10;
		$large_max = 2048;
		$large_default = 400;

		$small_min = 10;
		$small_max = 640;
		$small_default = 60;

		$r = array(
	// ==============================================
	// Plugin settings

			'avatar_group_ids' => array(
					'label' => $this->T_('User Groups'),
					'note' => '<br />'.$this->T_('Disable avatars for these user groups. Separate by ,'),
					'type' => 'text',
					'defaultvalue' => 'Basic Users, Bloggers',
					'maxlength' => 130,
					'size'	=>	75,
				),

			'avatar_user_level' => array(
					'label' => $this->T_('Minimum User level'),
					'type' => 'integer',
					'note' => '[ 0 - 11 ]<br />[ 0 ] - '.$this->T_('Enable for all users.').'<br />[ 11 ] - '.$this->T_('Disable for all users.'),
					'defaultvalue' => '0',
					'size' => 2,
					'maxlength' => 2,
					'valid_range' => array( 'min' => 0, 'max' => 11 ),
				),

			'avatar_filesize' => array(
					'label' => $this->T_('Maximum file size'),
					'type' => 'integer',
					'note' => $this->T_('KB').'.<br />[ 10 - 9000 ]',
					'defaultvalue' => 1024,
					'size' => 4,
					'maxlength' => 4,
					'valid_range' => array( 'min' => 10, 'max' => 9000 ),
				),

			'display_default_avatar' => array(
					'label' => $this->T_('Display default avatar'),
					'defaultvalue' => 1,
					'type' => 'checkbox',
					'note' => $this->T_('Should the default avatar be displayed if no avatar exists?'),
				),

			'use_gd2' => array(
					'label' => $this->T_('Use image resizing'),
					'disabled' => !$has_gd2,
					'defaultvalue' => 1,
					'type' => 'checkbox',
					'note' => $this->T_('Should we use image resizing?'),
				),

			'area_collapse' => array(
					'label' => $this->T_('Collapsed avatars area'),
					'defaultvalue' => 0,
					'type' => 'checkbox',
					'note' => $this->T_('Should we display the admin avatars upload area collapsed?'),
				),

			'display_next' => array(
					'label' => $this->T_('Display next avatar'),
					'defaultvalue' => 0,
					'type' => 'checkbox',
					'note' => $this->T_('If no <b>post</b> avatar and no default post image exists, display the category avatar, if no category avatar exists then display the blog avatar.'),
				),

			'display_next_default' => array(
					'label' => $this->T_('Display next default image'),
					'defaultvalue' => 0,
					'type' => 'checkbox',
					'note' => $this->T_('If no <b>post</b> avatar and no default post image exists, display the default category image, if no default category image exists then display the default blog image.<br /><br />NOTE: <b>Display next default image</b> has higher priority over <b>Display next avatar</b>.'),
				),

			'plugin_end' => array(
					'layout' => 'end_fieldset',
				),

	// ==============================================
	// Image settings

			'image_start' => array(
					'layout' => 'begin_fieldset',
					'label' => $this->T_('Image settings'),
				),

			'avatar_large_quality' => array(
					'label' => $this->T_('Large avatar JPEG quality'),
					'type' => 'integer',
					'note' => '%.',
					'defaultvalue' => 85,
					'size' => 2,
					'maxlength' => 2,
					'valid_range' => array( 'min' => 5, 'max' => 99 ),
				),

			'avatar_large_mode' => array(
					'label' => $this->T_('Large avatar image re-size mode'),
					'disabled' => !$has_gd2,
					'type' => 'select',
					'defaultvalue' => 'area',
					'options' => array(
						'none'	=> $this->T_('None'),
						'area'	=> $this->T_('Area'),
						//'exact'	=> $this->T_('Exact'),
						'crop-top'	=> $this->T_('Crop to top'),
						'crop-center'	=> $this->T_('Crop to center'),
						'crop-bottom'	=> $this->T_('Crop to bottom')
					),
					'note' =>
						'<br/>'.
						'[ '.$this->T_('None').	' ] - '.$this->T_('No Resizing Occurs').'.<br/>'.
						'[ '.$this->T_('Area').	' ] - '.$this->T_('Image is resized proportionally to fit within the given area').'.<br/>'.
						//'[ '.$this->T_('Exact').' - '.$this->T_('Image is resized to fit the given values exactly').' ]<br/>'.
						'[ '.$this->T_('Crop').' ] - '.$this->T_('The Image will be cropped to fit into the given area.')
				),

			'avatar_small_quality' => array(
					'label' => $this->T_('Small avatar JPEG quality'),
					'type' => 'integer',
					'note' => $this->T_('%').'.',
					'defaultvalue' => 95,
					'size' => 2,
					'maxlength' => 2,
					'valid_range' => array( 'min' => 5, 'max' => 99 ),
				),

			'avatar_small_mode' => array(
					'label' => $this->T_('Small avatar image re-size mode'),
					'disabled' => !$has_gd2,
					'type' => 'select',
					'defaultvalue' => 'area',
					'options' => array(
						'none'	=> $this->T_('None'),
						'area'	=> $this->T_('Area'),
						//'exact'	=> $this->T_('Exact'),
						'crop-top'	=> $this->T_('Crop to top'),
						'crop-center'	=> $this->T_('Crop to center'),
						'crop-bottom'	=> $this->T_('Crop to bottom')
					),
					'note' =>
						'<br/>'.
						'[ '.$this->T_('None').	' ] - '.$this->T_('No Resizing Occurs').'.<br/>'.
						'[ '.$this->T_('Area').	' ] - '.$this->T_('Image is resized proportionally to fit within the given area').'.<br/>'.
						//'[ '.$this->T_('Exact').' - '.$this->T_('Image is resized to fit the given values exactly').' ]<br/>'.
						'[ '.$this->T_('Crop').' ] - '.$this->T_('The Image will be cropped to fit into the given area.')
				),

			'image_end' => array(
					'layout' => 'end_fieldset',
				),

	// ==============================================
	// Sizes

		'sizes_start' => array(
					'layout' => 'begin_fieldset',
					'label' => $this->T_('Avatar sizes (Optional)'),
				),

			'large_start' => array(
					'layout' => 'begin_fieldset',
					'label' => $this->T_('Large avatars'),
				),

			'avatar_user_large_width' => array(
					'label' => $this->T_('User avatar width'),
					'disabled' => !$has_gd2,
					'type' => 'integer',
					'note' => $this->T_('Pixels').'. [ '.$large_min.' - '.$large_max.' ]',
					'defaultvalue' => $large_default,
					'size' => 3,
					'maxlength' => 4,
					'valid_range' => array( 'min' => $large_min, 'max' => $large_max ),
				),

			'avatar_user_large_height' => array(
					'label' => $this->T_('User avatar height'),
					'disabled' => !$has_gd2,
					'type' => 'integer',
					'note' => $this->T_('Pixels').'. [ '.$large_min.' - '.$large_max.' ]',
					'defaultvalue' => $large_default,
					'size' => 3,
					'maxlength' => 4,
					'valid_range' => array( 'min' => $large_min, 'max' => $large_max ),
				),

			'avatar_blog_large_width' => array(
					'label' => $this->T_('Blog avatar width'),
					'disabled' => !$has_gd2,
					'type' => 'integer',
					'note' => $this->T_('Pixels').'. [ '.$large_min.' - '.$large_max.' ]',
					'defaultvalue' => $large_default,
					'size' => 3,
					'maxlength' => 4,
					'valid_range' => array( 'min' => $large_min, 'max' => $large_max ),
				),

			'avatar_blog_large_height' => array(
					'label' => $this->T_('Blog avatar height'),
					'disabled' => !$has_gd2,
					'type' => 'integer',
					'note' => $this->T_('Pixels').'. [ '.$large_min.' - '.$large_max.' ]',
					'defaultvalue' => $large_default,
					'size' => 3,
					'maxlength' => 4,
					'valid_range' => array( 'min' => $large_min, 'max' => $large_max ),
				),

			'avatar_category_large_width' => array(
					'label' => $this->T_('Category avatar width'),
					'disabled' => !$has_gd2,
					'type' => 'integer',
					'note' => $this->T_('Pixels').'. [ '.$large_min.' - '.$large_max.' ]',
					'defaultvalue' => $large_default,
					'size' => 3,
					'maxlength' => 4,
					'valid_range' => array( 'min' => $large_min, 'max' => $large_max ),
				),

			'avatar_category_large_height' => array(
					'label' => $this->T_('Category avatar height'),
					'disabled' => !$has_gd2,
					'type' => 'integer',
					'note' => $this->T_('Pixels').'. [ '.$large_min.' - '.$large_max.' ]',
					'defaultvalue' => $large_default,
					'size' => 3,
					'maxlength' => 4,
					'valid_range' => array( 'min' => $large_min, 'max' => $large_max ),
				),

			'avatar_post_large_width' => array(
					'label' => $this->T_('Post avatar width'),
					'disabled' => !$has_gd2,
					'type' => 'integer',
					'note' => $this->T_('Pixels').'. [ '.$large_min.' - '.$large_max.' ]',
					'defaultvalue' => $large_default,
					'size' => 3,
					'maxlength' => 4,
					'valid_range' => array( 'min' => $large_min, 'max' => $large_max ),
				),

			'avatar_post_large_height' => array(
					'label' => $this->T_('Post avatar height'),
					'disabled' => !$has_gd2,
					'type' => 'integer',
					'note' => $this->T_('Pixels').'. [ '.$large_min.' - '.$large_max.' ]',
					'defaultvalue' => $large_default,
					'size' => 3,
					'maxlength' => 4,
					'valid_range' => array( 'min' => $large_min, 'max' => $large_max ),
				),

			'large_end' => array(
				'layout' => 'end_fieldset',
			),

			'small_start' => array(
					'layout' => 'begin_fieldset',
					'label' => $this->T_('Small avatars'),
				),

			'avatar_user_small_width' => array(
					'label' => $this->T_('User avatar width'),
					'disabled' => !$has_gd2,
					'type' => 'integer',
					'note' => $this->T_('Pixels').'. [ '.$small_min.' - '.$small_max.' ]',
					'defaultvalue' => $small_default,
					'size' => 3,
					'maxlength' => 4,
					'valid_range' => array( 'min' => $small_min, 'max' => $small_max ),
				),

			'avatar_user_small_height' => array(
					'label' => $this->T_('User avatar height'),
					'disabled' => !$has_gd2,
					'type' => 'integer',
					'note' => $this->T_('Pixels').'. [ '.$small_min.' - '.$small_max.' ]',
					'defaultvalue' => $small_default,
					'size' => 3,
					'maxlength' => 4,
					'valid_range' => array( 'min' => $small_min, 'max' => $small_max ),
				),

			'avatar_blog_small_width' => array(
					'label' => $this->T_('Blog avatar width'),
					'disabled' => !$has_gd2,
					'type' => 'integer',
					'note' => $this->T_('Pixels').'. [ '.$small_min.' - '.$small_max.' ]',
					'defaultvalue' => $small_default,
					'size' => 3,
					'maxlength' => 4,
					'valid_range' => array( 'min' => $small_min, 'max' => $small_max ),
				),

			'avatar_blog_small_height' => array(
					'label' => $this->T_('Blog avatar height'),
					'disabled' => !$has_gd2,
					'type' => 'integer',
					'note' => $this->T_('Pixels').'. [ '.$small_min.' - '.$small_max.' ]',
					'defaultvalue' => $small_default,
					'size' => 3,
					'maxlength' => 4,
					'valid_range' => array( 'min' => $small_min, 'max' => $small_max ),
				),

			'avatar_category_small_width' => array(
					'label' => $this->T_('Category avatar width'),
					'disabled' => !$has_gd2,
					'type' => 'integer',
					'note' => $this->T_('Pixels').'. [ '.$small_min.' - '.$small_max.' ]',
					'defaultvalue' => $small_default,
					'size' => 3,
					'maxlength' => 4,
					'valid_range' => array( 'min' => $small_min, 'max' => $small_max ),
				),

			'avatar_category_small_height' => array(
					'label' => $this->T_('Category avatar height'),
					'disabled' => !$has_gd2,
					'type' => 'integer',
					'note' => $this->T_('Pixels').'. [ '.$small_min.' - '.$small_max.' ]',
					'defaultvalue' => $small_default,
					'size' => 3,
					'maxlength' => 4,
					'valid_range' => array( 'min' => $small_min, 'max' => $small_max ),
				),

			'avatar_post_small_width' => array(
					'label' => $this->T_('Post avatar width'),
					'disabled' => !$has_gd2,
					'type' => 'integer',
					'note' => $this->T_('Pixels').'. [ '.$small_min.' - '.$small_max.' ]',
					'defaultvalue' => $small_default,
					'size' => 3,
					'maxlength' => 4,
					'valid_range' => array( 'min' => $small_min, 'max' => $small_max ),
				),

			'avatar_post_small_height' => array(
					'label' => $this->T_('Post avatar height'),
					'disabled' => !$has_gd2,
					'type' => 'integer',
					'note' => $this->T_('Pixels').'. [ '.$small_min.' - '.$small_max.' ]',
					'defaultvalue' => $small_default,
					'size' => 3,
					'maxlength' => 4,
					'valid_range' => array( 'min' => $small_min, 'max' => $small_max ),
				),

			'small_end' => array(
				'layout' => 'end_fieldset',
			),

		);

		return $r;
	}

// ==========================================================================

	function uninstall()
	{
		rmdir_r( $this->avatars_path );
		return !is_dir( $this->avatars_path );
	}

	function check_user_perms()
	{
		global $current_User, $Session;

		$User = & $current_User;
		if( empty($current_User) && $Session->has_User() )
		{
			$User = & $Session->get_User();
		}

		$GroupCache = & get_GroupCache();

		// Check user perms
		if( ! is_object( $User ) || empty($User->ID) || ( is_object($this->Settings) && ($User->get('level') < $this->Settings->get('avatar_user_level')) ) ) return NULL;

		if( is_object($GroupCache) && is_object($this->Settings) && $this->Settings->get('avatar_group_ids') )
		{
			$avatar_group_ids = explode( ',' , $this->Settings->get('avatar_group_ids') );

			foreach ( $avatar_group_ids as $avatar_group_id )
			{
				if( trim($avatar_group_id) )
				{
					$Group = & $GroupCache->get_by_name( trim($avatar_group_id), false, false );

					if( is_object($Group) && $User->get('group_ID') == $Group->ID ) return NULL;
				}
			}
		}

		return true;
	}

// ==========================================================================

	function get_avatars()
	{	// Scan $this->avatars_path for avatars

		// Get the avatars_path contents, including everything
		$avatars = scan_dir( $this->avatars_path, '/^([\\/\\\\\\w \\.])+?\.('.implode('|', $this->image_types).')/', NULL, NULL, 'tree2' );
		// var_export($avatars, false);
		return $avatars;
	}

	function get_avatar_display( $avatar, $style = NULL, $before = NULL, $after = NULL, $none = NULL )
	{
		if( is_array($avatar) && !empty($avatar['avatar']) )
		{	/* we have avatar */	}
		elseif( !empty($avatar) )
		{	// Get avatar
			$avatar = $this->get_avatar($avatar);
		}

		if( empty($avatar) )
			return $none;

		$height = NULL;
		$width = NULL;

		$result = $before;

		if( !empty($avatar['lightbox']) )
		{
			$result .= '<a href="'.$avatar['large_url'].'" title="'.$avatar['title'].'" class="lightbox_avatar" target="_blank">';
		}

		if( !empty($style) )
			$style = 'style="'.$style.'" ';

		if( !empty($avatar['width']) )
			$width = 'width="'.$avatar['width'].'" ';

		if( !empty($avatar['height']) )
			$height = 'height="'.$avatar['height'].'" ';

		$result .=
		'<img '.
			$style.
			'class="avatars_plugin_avatar avatars_plugin_avatar_'.$avatar['type'].'" '.
			'src="'.$avatar['url'].'" '.
			$width.
			$height.
			'title="'.$avatar['title'].'" '.
			'alt="'.$avatar['title'].'" '.
		'/>';

		if( !empty($avatar['lightbox']) )
			$result .=  '</a>';

		$result .= $after;

		return $result;
	}


	function get_avatar( $avatar_type, $avatar_name = NULL, $avatar_skin = '#', $params = array() )
	{	// Get the path and url of a avatar

		if( $avatar_skin == '#' )
			$avatar_skin = 'default';

		if( empty($avatar_name) )
		{
			if( is_array($avatar_type) && !empty($avatar_type['type']) && !empty($avatar_type['name']) )
			{	// We have an array format
				$avatar = $avatar_type;
				$avatar_name = $avatar['name'];
				$avatar_type = $avatar['type'];
				if( !empty($avatar['skin']) )
					$avatar_skin = $avatar['skin'];
				unset($avatar);
			}
			else
			{
				$avatar_name = 'default';
			}
		}

		// ------

		if( is_null($this->avatars) )
			$this->avatars = $this->get_avatars();

		// ------

		//echo $avatar_name.' - '.gettype( $avatar_name );

		$avatar_title = $avatar_name;

		if( is_numeric($avatar_name) || is_object($avatar_name) )
		{
			switch ( $avatar_type )
			{
				case 'user':
					$UserCache = & get_Cache( 'UserCache' );

					if( is_object($avatar_name) && ucfirst(get_class($avatar_name)) === 'Item' )
					{	// Post author
						$User = & $UserCache->get_by_ID( $avatar_name->creator_user_ID, true, false );
					}
					elseif( is_object($avatar_name) && ucfirst(get_class($avatar_name)) === 'Comment' )
					{	// Comment author
						if( !empty($avatar_name->author_user_ID) )
						{
							$User = & $UserCache->get_by_ID( $avatar_name->author_user_ID, true, false );
						}
						else
						{	// Ungeristered user, display visitor avatar ( gravatar, )
							return $this->get_visitor_avatar( $avatar_name, $params );
						}
					}
					elseif( is_object($avatar_name) && ucfirst(get_class($avatar_name)) === 'User' )
					{	// Current user
						$User = $avatar_name;
					}
					else
					{	// Assume the avatar_name is the user id
						$User = & $UserCache->get_by_ID($avatar_name, true, false);
					}

					// Got a user (maybe)
					if( !$User )
					{	// If getting the user class was unsuccessful lets still try with the id
						$avatar_title = 'User no longer exists';
						unset($User);
						break;
					}
					$avatar_name = $User->ID;
					$avatar_title = $User->get('preferredname');
					break;

				case 'blog':
					$BlogCache = & get_Cache( 'BlogCache' );

					if( is_object($avatar_name) && ucfirst(get_class($avatar_name)) === 'Item' )
					{
						$blog_ID = $avatar_name->blog_ID;
						$Blog = & $BlogCache->get_by_ID( $avatar_name );
					}
					elseif( is_object($avatar_name) && ucfirst(get_class($avatar_name)) === 'Blog' )
					{
						$Blog = $avatar_name;
					}
					else
					{
						$Blog = & $BlogCache->get_by_ID( $avatar_name );
					}

					if( empty($Blog) )
					{
						$avatar_title = 'Blog no longer exists';
						unset($Blog);
						break;
					}
					$avatar_name = $Blog->ID;
					$avatar_title = $Blog->name;
					break;

				case 'category':
					if( is_object($avatar_name) && ucfirst(get_class($avatar_name)) === 'Item' )
					{
						$Item = $avatar_name;
						$avatar_name = $Item->main_cat_ID;
					}
					$cat = get_the_category_by_ID( $avatar_name, false );

					if( empty($cat) )
					{
						$avatar_title = 'Category no longer exists';
						break;
					}
					$avatar_title = $cat['cat_name'];
					break;

				case 'post':
					if( is_object($avatar_name) && ucfirst(get_class($avatar_name)) === 'Item' )
					{
						$Item = $avatar_name;
					}
					else
					{
						$ItemCache = & get_Cache( 'ItemCache' );
						$Item = & $ItemCache->get_by_ID( $avatar_name );
					}
					$avatar_name = $Item->ID;
					$avatar_title = $Item->get('title');
					break;
			}
		}

		// ------

		# Blah
		$large_url = $large_path = $avatar_large = $avatar = NULL;

		# Do some preps
		if( !isset($this->avatars[$avatar_skin]) )
			$this->avatars[$avatar_skin] = array();
		if( !isset($this->avatars[$avatar_skin][$avatar_type]) )
			$this->avatars[$avatar_skin][$avatar_type] = array();

		# Check if the avatar name exists
		if( isset($this->avatars[$avatar_skin][$avatar_type][$avatar_name]) )
		{	$exists = true;
			$avatar = $this->avatars[$avatar_skin][$avatar_type][$avatar_name];
			if( isset($this->avatars[$avatar_skin][$avatar_type][$avatar_name.'.large']) )
				$avatar_large = $this->avatars[$avatar_skin][$avatar_type][$avatar_name.'.large'];
		}

		# Check if the avatar name exists in the default directory
		elseif( isset($this->avatars['default'][$avatar_type][$avatar_name]) )
		{
			$exists = true;
			$avatar = $this->avatars['default'][$avatar_type][$avatar_name];
			if( isset($this->avatars['default'][$avatar_type][$avatar_name.'.large']) )
				$avatar_large = $this->avatars['default'][$avatar_type][$avatar_name.'.large'];
		}

		# Check if the default avatar for the type exists
		elseif( isset($this->avatars[$avatar_skin][$avatar_type]['default']) )
		{
			$exists = false;
			$avatar_name = 'default';
			$avatar = $this->avatars[$avatar_skin][$avatar_type][$avatar_name];
			if( isset($this->avatars[$avatar_skin][$avatar_type][$avatar_name.'.large']) )
				$avatar_large = $this->avatars[$avatar_skin][$avatar_type][$avatar_name.'.large'];
		}
		# Check if the default avatar for the type exists
		elseif( isset($this->avatars['default'][$avatar_type]['default']) )
		{
			$exists = false;
			$avatar_name = 'default';
			$avatar = $this->avatars['default'][$avatar_type][$avatar_name];
			if( isset($this->avatars['default'][$avatar_type][$avatar_name.'.large']) )
				$avatar_large = $this->avatars['default'][$avatar_type][$avatar_name.'.large'];
		}

		# Check if the avatar exists in the skin
		elseif( isset($this->avatars[$avatar_skin][$avatar_name]) )
		{
			$exists = true;
			$avatar = $this->avatars[$avatar_skin][$avatar_name];
			if( isset($this->avatars[$avatar_skin][$avatar_name.'.large']) )
				$avatar_large = $this->avatars[$avatar_skin][$avatar_name.'.large'];
		}

		# Check if the default avatar for the skin exists
		elseif( isset($this->avatars[$avatar_skin]['default']) )
		{
			$exists = false;

			// If default post image not found use category image, then blog image
			if( $avatar_type == 'post' && !is_file( $this->avatars_path.'default/post/default.jpg' ) && $this->Settings->get('display_next_default') )
			{
				$avatar_name = 'default';

				if( is_file( $this->avatars_path.'default/category/default.jpg' ) )
				{	// Use default category image
					$avatar = $this->avatars[$avatar_skin]['category'][$avatar_name];
					if( isset($this->avatars[$avatar_skin]['category'][$avatar_name.'.large']) )
						$avatar_large = $this->avatars[$avatar_skin]['category'][$avatar_name.'.large'];
				}
				elseif( is_file( $this->avatars_path.'default/blog/default.jpg' ) )
				{	// Use default blog image
					$avatar = $this->avatars[$avatar_skin]['blog'][$avatar_name];
					if( isset($this->avatars[$avatar_skin]['blog'][$avatar_name.'.large']) )
						$avatar_large = $this->avatars[$avatar_skin]['blog'][$avatar_name.'.large'];
				}
				else
				{	// Use main default image
					$avatar = $this->avatars['default'][$avatar_name];
					if( isset($this->avatars['default'][$avatar_name.'.large']) )
						$avatar_large = $this->avatars['default'][$avatar_name.'.large'];
				}
			}
			elseif( $avatar_type == 'post' && !is_file( $this->avatars_path.'default/post/default.jpg' ) && $this->Settings->get('display_next') )
			{
				global $ItemCache;

				$Item = & $ItemCache->get_by_ID( $avatar_name );
				$avatar_title = $Item->get('title');

				if( is_file( $this->avatars_path.'default/category/'.$Item->main_cat_ID.'.jpg' ) )
				{	// Use default category image
					$avatar_name = $Item->main_cat_ID;
					$avatar = $this->avatars[$avatar_skin]['category'][$avatar_name];
					if( isset($this->avatars[$avatar_skin]['category'][$avatar_name.'.large']) )
						$avatar_large = $this->avatars[$avatar_skin]['category'][$avatar_name.'.large'];
				}
				elseif( is_file( $this->avatars_path.'default/blog/'.$Item->blog_ID.'.jpg' ) )
				{	// Use default blog image
					$avatar_name = $Item->blog_ID;
					$avatar = $this->avatars[$avatar_skin]['blog'][$avatar_name];
					if( isset($this->avatars[$avatar_skin]['blog'][$avatar_name.'.large']) )
						$avatar_large = $this->avatars[$avatar_skin]['blog'][$avatar_name.'.large'];
				}
				else
				{	// Use main default image
					$avatar_name = 'default';
					$avatar = $this->avatars['default'][$avatar_name];
					if( isset($this->avatars['default'][$avatar_name.'.large']) )
						$avatar_large = $this->avatars['default'][$avatar_name.'.large'];
				}
			}
			else
			{
				$avatar_name = 'default';
				$avatar = $this->avatars[$avatar_skin][$avatar_name];
				if( isset($this->avatars[$avatar_skin][$avatar_name.'.large']) )
					$avatar_large = $this->avatars[$avatar_skin][$avatar_name.'.large'];
			}
		}

		# Check if the avatar exists in the default skin
		elseif( isset($this->avatars['default'][$avatar_name]) )
		{
			$exists = true;
			$avatar = $this->avatars['default'][$avatar_name];
			if( isset($this->avatars['default'][$avatar_name.'.large']) )
				$avatar_large = $this->avatars['default'][$avatar_name.'.large'];
		}

		# Check if the default avatar exists in the default skin
		elseif( isset($this->avatars['default']['default']) )
		{
			$exists = false;
			$avatar_name = 'default';
			$avatar = $this->avatars['default'][$avatar_name];
			if( isset($this->avatars['default'][$avatar_name.'.large']) )
				$avatar_large = $this->avatars['default'][$avatar_name.'.large'];
		}
		# Use no avatar
		else
		{
			return NULL;
		}

		# We have found a avatar

		# Check if it is default
		if( $exists === false && !$this->Settings->get('display_default_avatar') )
			return NULL;


		# Large Avatar
		if( $avatar_large )
		{
			$large_url = $this->avatars_url.$avatar_large;
			$large_path = $this->avatars_path.$avatar_large;
			$lightbox = 1;
		}
		else
		{
			$lightbox = '';
		}

		# Avatar
		$avatar = array(
			'avatar'	=>	true,
			'exists'	=>	var_export($exists, true),
			'path'		=>	$this->avatars_path.$avatar,
			'url'		=>	$this->avatars_url.$avatar,
			'title'		=>	$avatar_title,
			'name'		=>	$avatar_name,
			'type'		=>	$avatar_type,
			'skin'		=>	$avatar_skin,
			'large_path'=>	$large_path,
			'large_url'	=>	$large_url,
			'lightbox'	=>	$lightbox,
		);
		return $avatar;
	}


	function display_avatar( $params )
	{
		global $Messages;

		$avatar_name = NULL;
		$opt_params = array();

		if( !isset($params['id']) ) { $params['id'] = ''; }
		if( !isset($params['style']) ) { $params['style'] = ''; }
		if( !isset($params['before']) ) { $params['before'] = '<div style="float:left">'; }
		if( !isset($params['after']) ) { $params['after'] = '</div>'; }
		if( !isset($params['noavatar']) ) { $params['noavatar'] = '<p>No avatar</p>'; }

		if( is_numeric($params['id']) )
		{	// We already have avatar's name (ID)
			$avatar_name = $params['id'];
		}
		else
		{
			switch( $params['type'] )
			{
				case 'user':
					global $current_User;
					if ( is_object($current_User) )
					{	// Current user avatar
						$avatar_name = $current_User;
					}
					break;

				case 'post_author':
					global $Item;
					if ( is_object($Item) )
					{	// Post author avatar
						$avatar_name = $Item;
						$params['type'] = 'user';
					}
					else
					{
						$wrong_place = sprintf( $this->T_('Put this %s avatars code in posts loop, usually in %s, in %s or in %s'),
												'<b>'.$params['type'].'</b>', 'index.main.php', 'posts.main.php', 'single.main.php' );
					}
					break;

				case 'comment_author':
					if ( is_object($params['comment']) )
					{	// Comment author avatar
						$avatar_name = $params['comment'];
						$params['type'] = 'user';
						$opt_params = $params;
					}
					else
					{
						$wrong_place = sprintf( $this->T_('Put this %s avatars code in comments loop, usually in %s'),
												'<b>'.$params['type'].'</b>', '_item_comment.inc.php' );
					}
					break;

				case 'post':
					global $Item;
					if ( is_object($Item) )
					{	// Post avatar
						$avatar_name = $Item;
					}
					else
					{
						$wrong_place = sprintf( $this->T_('Put this %s avatars code in posts loop, usually in %s, in %s or in %s'),
												'<b>'.$params['type'].'</b>', 'index.main.php', 'posts.main.php', 'single.main.php' );
					}
					break;

				case 'category':
					global $Item;
					if ( is_object($Item) )
					{	// Category avatar
						$avatar_name = $Item;
					}
					else
					{
						$wrong_place = sprintf( $this->T_('Put this %s avatars code in posts loop, usually in %s, in %s or in %s'),
												'<b>'.$params['type'].'</b>', 'index.main.php', 'posts.main.php', 'single.main.php' );
					}
					break;

				case 'blog':
					global $Blog;
					if ( is_object($Blog) )
					{	// Blog avatar
						$avatar_name = $Blog->ID;
					}
					break;

				default:
					return;
			}

			if( !empty($wrong_place) )
			{
				echo '<div style="margin:5px; padding: 15px; border:1px solid #d99; background:#fbf2ee; color:#e00; text-align:left">'.$wrong_place.'</div>';
				return;
			}
		}

		if( ($avatar = $this->get_avatar( $params['type'], $avatar_name, '#', $opt_params )) !== NULL )
		{	// Display selected avatar
			echo $this->get_avatar_display($avatar, $params['style'], $params['before'], $params['after'], $params['noavatar'] );
		}
	}

	// ==============================
	// Visitor avatars

	function get_visitor_avatar( $Comment, $params = array() )
	{	// Gravatar
		$visitor_avatar = $this->get_gravatar( $Comment, $params );

		$avatar = array(
			'avatar'	=>	true,
			'exists'	=>	true,
			'url'		=>	$visitor_avatar['url'],
			'title'		=>	$visitor_avatar['title'],
			'name'		=>	NULL,
			'type'		=>	'user',
			'skin'		=>	'default',
			'width'		=>	$this->Settings->get('avatar_user_small_width'),
			'height'	=>	$this->Settings->get('avatar_user_small_height'),
		);
		return $avatar;
	}


	// Display gravatars
	function get_gravatar( $Comment, $params = array() )
	{
		if( empty($Comment->author_email) )
			return false;

		$url = 'http://www.gravatar.com/avatar.php?gravatar_id='.md5($Comment->author_email);

		if( !empty($params['rating']) )
			$url .= '&rating='.$params['rating'];

		if( !empty($params['size']) )
			$url .='&size='.$params['size'];

		if( !empty($params['default']) )
			$url .= '&default='.urlencode($params['default']);

		if( !empty($params['border']) )
			$url .= '&border='.$params['border'];

		return array( 'url' => $url, 'title' => $Comment->author );
	}


	# -----------------------------------------------------------------------------

	function upload_image( $file_var_name )
	{
		global $Messages;

		$result = NULL;

		if( isset($_FILES[$file_var_name]) && !empty($_FILES[$file_var_name]['tmp_name']) )
		{	// We have a new large avatar
			$result = false;

			$avatar_file = $_FILES[$file_var_name];

			$original_name = $avatar_file['name'];
			$original_extension = strtolower(substr($original_name, strrpos($original_name, '.')+1));
			if( !in_array($original_extension, $this->image_types, false) )
			{
				$Messages->add( $this->T_('The image you tried to upload is not of an acceptable extension').' ['.implode(', ',$this->image_types).'].', 'error');
			}
			elseif( $avatar_file['size'] > ($this->Settings->get('avatar_filesize') * 1024) )
			{
				$Messages->add( sprintf($this->T_('The maximum allowed file size is %s KB.'), $this->Settings->get('avatar_filesize')), 'error');
			}
			else
			{	// Upload Avatar
				if( empty($avatar_file['error']) )
				{	// File was uploaded successfully, let's upload it
					$avatar_path = $this->temp_folder.basename($avatar_file['tmp_name']);

					/*
					$ext = basename($avatar_file['name']);
					$ext = substr($ext,strrpos($ext,'.')+1);
					$ext = strtolower($ext);
					$avatar_path .= '.'.$ext;
					*/

					switch( $original_extension )
					{	// types
						case 'png':
							$GLOBALS['IMAGE_EXT'] = 'png';
							break;

						default:
							$GLOBALS['IMAGE_EXT'] = '';
							break;
					}

					if( move_uploaded_file($avatar_file['tmp_name'], $avatar_path) )
					{	// File was moved successfully
						$result = $avatar_path;
						$Messages->add( $this->T_('Succesfully uploaded the image').' ['.$avatar_file['name'].'].', 'success');
					}
					else
					{
						$Messages->add( $this->T_('Failed to upload the image').' ['.$avatar_file['name'].'].', 'error');
					}
				}
				else
				{
					$Messages->add( $this->T_('Failed to upload the image').' ['.$avatar_file['name'].'].', 'error');
				}
			}
		}

		return $result;
	}

	function do_form_request( $params = NULL )
	{
		if( empty($_POST['avatar_name']) )
		{	// Let's see if we have a id instead
			switch ( true )
			{	// types
				case !empty( $params['User'] ) && !empty( $params['User']->ID ):
					$_POST['avatar_name'] = $params['User']->ID;
					break;

				default:
					break;
			}
		}

		if( !empty($_POST['avatar_runonce']) && !empty($_POST['avatar_type']) && !empty($_POST['avatar_name']) )
		{
			// Make it so we don't do this again
			$this->do_form_request_ran = true;
			unset($_POST['avatar_runonce']);

			// Get required variables
			$avatar_type = $_POST['avatar_type'];
			$avatar_name = $_POST['avatar_name'];
			$avatar_skin = !empty($_POST['avatar_skin']) ? $_POST['avatar_skin'] : 'default';

			// Set the paths
			$avatar_small_path = $avatar_small_temp_path = $avatar_large_path = $avatar_large_temp_path = NULL;

			// --------------------------------------------
			// Find out whether we need to remove
			$avatar_small_delete = isset($_REQUEST['avatar_small_delete']) ? true : false;
			$avatar_large_delete = isset($_REQUEST['avatar_large_delete']) ? true : false;

			// Remove the avatars
			if( $avatar_small_delete )
				$avatar_small_delete_result = $this->delete_avatar_image($avatar_type, $avatar_name, $avatar_skin, 'small');

			if( $avatar_large_delete )
				$avatar_large_delete_result = $this->delete_avatar_image($avatar_type, $avatar_name, $avatar_skin, 'large');
			//	if( $avatar_small_delete && $avatar_large_delete )
			//		return $avatar_small_delete_result && $avatar_large_delete_result;

			// --------------------------------------------
			// Upload the images
			$avatar_small_temp_path = $this->upload_image('avatar_small_file');
			$avatar_large_temp_path = $this->upload_image('avatar_large_file');

			if( !$avatar_small_temp_path )
				$avatar_small_temp_path = NULL;

			if( !$avatar_large_temp_path )
				$avatar_large_temp_path = NULL;

			// --------------------------------------------
			// Add the avatars

			$avatar = $this->add_avatar($avatar_small_temp_path, $avatar_large_temp_path, $avatar_type, $avatar_name, $avatar_skin);

			// Invalidate avatars cache
			$this->avatars = NULL;

			return $avatar;
		}

		return NULL;
	}

	# -----------------------------------------------------------------------------

	function avatar_exists ( $avatar_type, $avatar_name, $avatar_skin = 'default' )
	{
		return (
			isset($this->avatars[$avatar_skin])
			&& isset($this->avatars[$avatar_skin][$avatar_type])
			&& isset($this->avatars[$avatar_skin][$avatar_type][$avatar_name])
		);
	}

	function delete_avatar_image ( $avatar_type, $avatar_name, $avatar_skin = 'default', $image_type = NULL )
	{
		global $Messages;

		$result = true;

		// Create avatar paths
		$avatar_folder = $this->avatars_path.$avatar_skin.'/'.$avatar_type.'/';
		$avatar_small_path = $avatar_folder.$avatar_name.'.jpg';
		$avatar_large_path = $avatar_folder.$avatar_name.'.large.jpg';

		// Never delete default avatars
		if( $avatar_small_path == $this->avatars_path.'default/default.jpg' ||
			$avatar_small_path == $this->avatars_path.'default/'.$avatar_type.'/default.jpg' ||
			$avatar_large_path == $this->avatars_path.'default/default.jpg' ||
			$avatar_large_path == $this->avatars_path.'default/'.$avatar_type.'/default.jpg' )
		{
			return $result;
		}

		// Delete small avatar image
		if( ($image_type === NULL || $image_type === 'small') && is_file($avatar_small_path) )
		{
			if( unlink($avatar_small_path) )
			{
				$Messages->add( sprintf($this->T_('Successfully deleted the %s avatar image.'), $image_type), 'success' );
				if( $this->avatar_exists($avatar_type, $avatar_name, $avatar_skin) )
					unset($this->avatars[$avatar_skin][$avatar_type][$avatar_name]);
			}
			else
			{
				$Messages->add( sprintf($this->T_('Failed to delete the %s avatar image.'), $image_type), 'error' );
				$result = false;
			}
		}

		// Delete large avatar image
		if( ($image_type === NULL || $image_type === 'large') && is_file($avatar_large_path) )
		{
			if( unlink($avatar_large_path) )
			{
				$Messages->add( sprintf($this->T_('Successfully deleted the %s avatar image.'), $image_type), 'success' );
				if( $this->avatar_exists($avatar_type, $avatar_name.'.large', $avatar_skin) )
					unset($this->avatars[$avatar_skin][$avatar_type][$avatar_name.'.large']);
			}
			else
			{
				$Messages->add( sprintf($this->T_('Failed to delete the %s avatar image.'), $image_type), 'error' );
				$result = false;
			}
		}

		return $result;
	}

	# -----------------------------------------------------------------------------

	function create_avatar_image ( $avatar_temp_path, $avatar_type, $avatar_name, $avatar_skin = 'default', $image_type = 'small' )
	{
		// ------------------------------------------------------------------------------------
		// Some version comparing stuff for beta versions

		/*
		echo '<!-- create_avatar_image'.
			"\r\n".print_r($avatar_temp_path, true).
			"\r\n".print_r($avatar_type,true).
			"\r\n".print_r($avatar_name,true).
			"\r\n".print_r($avatar_skin,true).
			"\r\n".print_r($image_type,true).
			"\r\n".'-->'."\r\n";
		*/

		$v_new = 9;
		$f_name = 'remake_image';
		$v_name = $f_name.'_version';
		global $$v_name;
		if( isset($$v_name) )
		{
			$v_old = & $$v_name;
			if( floor($v_old) < floor($v_new) )
			{
				return $this->create_avatar_image_old($avatar_temp_path, $avatar_type, $avatar_name, $avatar_skin, $image_type);
			}
		}


		// ------------------------------------------------------------------------------------
		// Set some pre vars

		global $Messages;

		// ------------------------------------------------------------------------------------
		// Create avatar path

		$ext = basename($avatar_temp_path);
		$ext = substr($ext,strrpos($ext,'.')+1);
		$ext = strtolower($ext);
		$ext = 'jpg';

		$avatar_folder = $this->avatars_path.$avatar_skin.'/'.$avatar_type.'/';
		$avatar_path = $avatar_folder.$avatar_name.($image_type === 'large' ? '.large' : '' ).'.'.$ext;

		if( isset($image_type) && $ext == 'png' )
		{
			$GLOBALS['IMAGE_EXT'] = 'png';
		} else {
			$GLOBALS['IMAGE_EXT'] = '';
		}

		// ------------------------------------------------------------------------------------
		// Get onto it

		// Remove the image if it exists
		if( !$this->delete_avatar_image($avatar_type, $avatar_name, $avatar_skin, $image_type) )
		{	// Removing the image failed
			return false;
		}

		// Do we support image resizing?
		if( $this->Settings->get('use_gd2') )
		{	// Yes we do

			// Set the variables to use
			$image = $avatar_temp_path;

			$width_new = $this->Settings->get('avatar_'.$avatar_type.'_'.$image_type.'_width');
			$height_new = $this->Settings->get('avatar_'.$avatar_type.'_'.$image_type.'_height');

			$resize_mode = $this->Settings->get('avatar_'.$image_type.'_mode');
			$max_filesize = 0;

			if( isset($GLOBALS['IMAGE_EXT']) )
			{
				switch ( $GLOBALS['IMAGE_EXT'] )
				{	// types
					case 'png':

						$quality = $image_type === 'large' ? 9 : 9;
						unset($GLOBALS['IMAGE_EXT']);
							break;

					default :
						$quality = $this->Settings->get('avatar_'.$image_type.'_quality');
						break;
				}
			}

			// Get the remade image
			$remake_image = compact(
				'image',
				'width_new', 'height_new',
				'resize_mode', 'max_filesize', 'quality',
				'image_type', 'image_extension'
			);

			/*echo '<!-- create_avatar_image:remake_image'.
			"\r\n".print_r($remake_image, true).
			"\r\n".'-->'."\r\n";*/

			$avatar_image = remake_image($remake_image);

			// Write the image
			if( $avatar_image )
			{	// Image Creation was succcess
				if( ($fp = fopen($avatar_path,'w')) && fwrite($fp, $avatar_image) && fclose($fp) )
				{
					$Messages->add( sprintf($this->T_('Successfully created the %s avatar.'), $image_type), 'success' );
				}
				else
				{
					$Messages->add( sprintf($this->T_('Failed writing the %s avatar.'), $image_type), 'error' );
					return false;
				}
			}
			else
			{
				$Messages->add( sprintf($this->T_('Failed to create the %s avatar.'), $image_type), 'error' );
				return false;
			}

			// Delete the temp file
			if( !unlink($avatar_temp_path) )
			{
				$Messages->add( sprintf($this->T_('Failed to delete the temporary %s avatar image.'), $image_type), 'error' );
				return false;
			}
		}
		else
		{	// We don't support image resizing

			// So let's move the image
			if( rename($avatar_temp_path, $avatar_path) )
			{
				$Messages->add( sprintf($this->T_('Successfully moved the %s avatar to it\'s correct location. [ %d ]'), $image_type, $avatar_path), 'success' );
			}
			else
			{	// Moving failed
				$Messages->add( sprintf($this->T_('Failed to move the %s avatar image to it\'s correct location.'), $image_type), 'error' );
				// Delete the temp file
				if( !unlink($avatar_temp_path) )
				{
					$Messages->add( sprintf($this->T_('Failed to delete the temporary %s avatar image.'), $image_type), 'error' );
				}
				return false;
			}
		}

		return true;
	}

	function create_avatar_image_old ( $avatar_temp_path, $avatar_type, $avatar_name, $avatar_skin = 'default', $image_type = 'small' )
	{
		global $Messages;

		// ------------------------------------------------------------------------------------
		// Create avatar path

		$avatar_folder = $this->avatars_path.$avatar_skin.'/'.$avatar_type.'/';
		$avatar_path = $avatar_folder.$avatar_name.($image_type === 'large' ? '.large' : '' ).'.jpg';

		// ------------------------------------------------------------------------------------
		// Get onto it

		// Remove the image if it exists
		if( !$this->delete_avatar_image($avatar_type, $avatar_name, $avatar_skin, $image_type) )
		{	// Removing the image failed
			return false;
		}

		// Do we support image resizing?
		if( $this->Settings->get('use_gd2') )
		{	// Yes we do

			// Create the large image
			$avatar_image = remake_image(array(
					'image'		=> $avatar_temp_path,
					'width'		=> $this->Settings->get('avatar_'.$avatar_type.'_'.$image_type.'_width'),
					'height'	=> $this->Settings->get('avatar_'.$avatar_type.'_'.$image_type.'_height'),
					'mode'		=> $this->Settings->get('avatar_'.$image_type.'_mode'),
					'size'		=> 0,
					'quality'	=> 95,
					'debug'		=> true
			));

			// Write the large image
			if( $avatar_image )
			{	// Image Creation was succcess
				if( ($fp = fopen($avatar_path,'w')) && fwrite($fp, $avatar_image) && fclose($fp) )
					$Messages->add( sprintf($this->T_('Successfully created the %s avatar.'), $image_type), 'success' );
				else {
					$Messages->add( sprintf($this->T_('Failed writing the %s avatar.'), $image_type), 'error' );
					return false;
				}
			}
			else {
				$Messages->add( sprintf($this->T_('Failed to create the %s avatar.'), $image_type), 'error' );
				return false;
			}

			// Delete the temp file
			if( !unlink($avatar_temp_path) )
			{
				$Messages->add( sprintf($this->T_('Failed to delete the temporary %s avatar image.'), $image_type), 'error' );
				return false;
			}
		}
		else
		{	// We don't support image resizing

			// So let's move the image
			if( rename($avatar_temp_path, $avatar_path) )
			{
				$Messages->add( sprintf($this->T_('Successfully moved the %s avatar to it\'s correct location. [ %d ]'), $image_type, $avatar_path), 'success' );
			}
			else
			{	// Moving failed
				$Messages->add( sprintf($this->T_('Failed to move the %s avatar image to it\'s correct location.'), $image_type), 'error' );
				// Delete the temp file
				if( !unlink($avatar_temp_path) )
				{
					$Messages->add( sprintf($this->T_('Failed to delete the temporary %s avatar image.'), $image_type), 'error' );
				}
				return false;
			}
		}

		return true;
	}

	# -----------------------------------------------------------------------------

	function add_avatar( $avatar_small_temp_path, $avatar_large_temp_path /* = NULL */, $avatar_type, $avatar_name, $avatar_skin = 'default' )
	{
		global $Messages;

		if( empty($avatar_small_temp_path) && empty($avatar_large_temp_path) )
			return NULL;

		// ------------------------------------------------------------------------------------
		// Create avatar paths

		$avatar_folder = $this->avatars_path.$avatar_skin.'/'.$avatar_type.'/';

		$avatar = $this->get_avatar($avatar_type, $avatar_name, $avatar_skin);
		if( !empty($avatar) && !empty($avatar['path']) )
			$avatar_small_path = $avatar['path'];
		else
		{
			$ext = basename($avatar_small_temp_path);
			$ext = substr($ext,strrpos($ext,'.')+1);
			$avatar_small_path = $avatar_folder.$avatar_name.'.'.$ext;
			$this->delete_avatar_image($avatar_type, $avatar_name, $avatar_skin, 'small');
		}

		if( !empty($avatar) && !empty($avatar['large_path']) )
			$avatar_large_path = $avatar['large_path'];
		else
		{
			$ext = basename($avatar_large_temp_path);
			$ext = substr($ext,strrpos($ext,'.')+1);
			$avatar_large_path = $avatar_folder.$avatar_name.'.large.'.$ext;
			$this->delete_avatar_image($avatar_type, $avatar_name, $avatar_skin, 'large');
		}

		// ------------------------------------------------------------------------------------
		// Check if we have a small avatar, and if we don't try copy the large avatar over

		if( !$avatar_small_temp_path && !is_file($avatar_small_path) )
		{	// No small avatar exists

			// Get the path to try and copy
			if( $avatar_large_temp_path )
				$avatar_large_copy_path = $avatar_large_temp_path;
			elseif( is_file($avatar_large_path) )
				$avatar_large_copy_path = $avatar_large_path;
			else
				$avatar_large_copy_path = NULL;

			// Do the work
			if( $avatar_large_copy_path )
			{	// A large avatar exists
				// SO let's copy the large avatar over for use for the small avatar
				$ext = basename($avatar_large_copy_path);
				$ext = substr($ext,strrpos($ext,'.')+1);
				$avatar_small_temp_path = $this->temp_folder.basename($avatar_large_copy_path).'.'.$ext;
				if( copy($avatar_large_copy_path, $avatar_small_temp_path) )
				{	// Copy went well
					$Messages->add( $this->T_('Successfully copied the large avatar image for use for the small avatar image.'), 'success');
				} else
				{	// Copy went bad
					$Messages->add( $this->T_('Unsuccessfully copied the large avatar image for use for the small avatar image.'), 'error');
					return false;
				}
			} else
			{	// Copy went bad
				$Messages->add( $this->T_('No small avatar exists! And no large avatar exists to use for the small avatar either!'), 'error');
				return false;
			}
		}

		// ------------------------------------------------------------------------------------
		// Create the large avatar image
		if( $avatar_large_temp_path )
		{
			if( !$this->create_avatar_image($avatar_large_temp_path, $avatar_type, $avatar_name, $avatar_skin, 'large') )
				return false;
			else
			{	// Add the avatar to the list
				// We do extension stuff, because the $avatar_large_path might be of a old extension
				// and we cannot simply use temp path because that has a cryptic/temp name
				$basename = basename($avatar_large_path);
				$basename = substr($basename, 0, strrpos($basename, '.')+1); // remove extension
				$ext = basename($avatar_large_temp_path);
				$ext = substr($ext,strrpos($ext,'.')+1);
				$basename .= $ext;
				$this->avatars[$avatar_skin][$avatar_type][$avatar_name.'.large'] = $avatar_skin.'/'.$avatar_type.'/'.$basename;
			}
		}

		// Create the small avatar image
		if( $avatar_small_temp_path )
		{
			if( !$this->create_avatar_image($avatar_small_temp_path, $avatar_type, $avatar_name, $avatar_skin, 'small') )
				return false;
			else
			{	// Add the avatar to the list
				// We do extension stuff, because the $avatar_large_path might be of a old extension
				// and we cannot simply use temp path because that has a cryptic/temp name
				$basename = basename($avatar_small_path);
				$basename = substr($basename, 0, strrpos($basename, '.')+1); // remove extension
				$ext = basename($avatar_small_temp_path);
				$ext = substr($ext,strrpos($ext,'.')+1);
				$basename .= $ext;
				$this->avatars[$avatar_skin][$avatar_type][$avatar_name] = $avatar_skin.'/'.$avatar_type.'/'.$basename;
			}
		}

		// ------------------------------------------------------------------------------------

		return $this->get_avatar($avatar_type, $avatar_name, $avatar_skin);
	}

	# -----------------------------------------------------------------------------

	function admin_avatars( & $params, $position = '' )
	{	// Finds out if we are needed

		// ----------------------------------------------------
		// Check to see if we have already found out if we are needed

		if( $this->admin_avatars !== NULL && !empty($this->admin_avatars) && $position != 'footer' )
			return $this->admin_avatars;

		// ----------------------------------------------------
		// Set globals

		global $ctrl, $tab, $action, $user_ID, $cat_ID, $edited_Item, $blog, $user_tab; // find our where we are

		// ----------------------------------------------------
		// If we haven't constructed yet, let's construct

		if( is_null($this->avatars) )
			$this->avatars = $this->get_avatars();

		// ----------------------------------------------------
		// So let's find out what avatar form we should display

		$admin_area = $avatar_name = $avatar_type = NULL;
		switch ( true )
		{
			case $ctrl === 'coll_settings' && $tab === 'general' && !empty($blog):
				$avatar_type = 'blog';
				$avatar_name = $blog;
				$admin_area = 'blog';
				break;

			case $ctrl === 'chapters' && !empty($cat_ID) && $action !== 'delete':
				$avatar_type = 'category';
				$avatar_name = $cat_ID;
				$admin_area = 'category';
				break;

			case $ctrl === 'items' && ( $action === 'edit' || $action === 'edit_switchtab' ):
				// don't display the block twice
				if( $position != 'footer' )
				{
					$avatar_type = 'post';
					$avatar_name = $edited_Item->ID;
					$admin_area = 'post';
				}
				break;

			case $ctrl == 'user' && ($action == 'new' || $user_tab == 'profile'):
				$avatar_type = 'user';
				$avatar_name = $user_ID;
				$admin_area = 'user';
				break;
		}

		// ----------------------------------------------------

		 $this->admin_avatars = array(
			'avatar_type'	=>	$avatar_type,
			'avatar_name'	=>	$avatar_name,
			'admin_area'	=>	$admin_area
		);

		return $this->admin_avatars;
	}

// ==========================================================================

	/**
	 * Event handler: Called before the plugin is going to be installed.
	 *
	 * This is the hook to create any DB tables or the like.
	 *
	 * If you just want to add a note, use {@link Plugin::msg()} (and return true).
	 *
	 * @return true|string True, if the plugin can be enabled/activated,
	 *                     a string with an error/note otherwise.
	 */
	function BeforeInstall()
	{	// So we can check the requirements

		$r = mkdir_r( $this->avatars_path ) &&
				mkdir_r( $this->temp_folder ) &&
				mkdir_r( $this->avatars_path.'default/' ) &&
				mkdir_r( $this->avatars_path.'default/blog/' ) &&
				mkdir_r( $this->avatars_path.'default/category/' ) &&
				mkdir_r( $this->avatars_path.'default/post/' ) &&
				mkdir_r( $this->avatars_path.'default/user/' );

		if( $r )
		{
			@touch($this->avatars_path.'index.html');
			@touch($this->temp_folder.'index.html');
			@touch($this->avatars_path.'default/index.html');
			@touch($this->avatars_path.'default/blog/index.html');
			@touch($this->avatars_path.'default/category/index.html');
			@touch($this->avatars_path.'default/post/index.html');
			@touch($this->avatars_path.'default/user/index.html');
		}
		else
		{
			$dir_list = '<br />'.$this->temp_folder.'<br />'.
						$this->avatars_path.'default/<br />'.
						$this->avatars_path.'default/blog/<br />'.
						$this->avatars_path.'default/category/<br />'.
						$this->avatars_path.'default/post/<br />'.
						$this->avatars_path.'default/user/';

			$this->msg( sprintf( $this->T_('You must create the following directories with write permissions (777):%s'), $dir_list), 'error' );
			return false;
		}

		if( !is_file($this->avatars_path.'default/default.jpg') )
		{
			if( ! @copy( dirname(__FILE__).'/includes/default.jpg', $this->avatars_path.'default/default.jpg' ) )
			{
				$this->msg( sprintf( $this->T_('You must copy the file default.jpg from "avatars_plugin/includes/" to %s'),
							$this->avatars_path.'default/' ), 'error' );

				return false;
			}
		}
		return true;
	}


	function BeforeEnable()
	{
		return $this->BeforeInstall();
	}


	/**
	 * Event handler: Called before the plugin is going to be un-installed.
	 *
	 * This is the hook to remove any files or the like - tables with canonical names
	 * (see {@link Plugin::get_sql_table()}, are handled internally.
	 */
	function BeforeUninstall( & $params )
	{
		if( $params['unattended'] )
		{	// Auto
			$del = true;
		}
		else
		{	// User
			if( isset($_POST[$this->code.'__unlink_dir']) )
			{	// Second step
				$del = $_POST[$this->code.'__unlink_dir'] === '1';
			}
			else
			{	// First step
				return NULL; // display payload
			}
		}

		if( $del )
		{	// Delete Files
			$result = $this->uninstall();
			$this->msg( $result ? $this->T_('Successfully removed all avatars.') : $this->T_('Failed deleting all avatars for this plugin.'), $result ? 'success' : 'error' );
			return $result;
		}
		else
		{	// Keep files
			$this->msg( $this->T_('All avatars were kept.'), 'note');
			return true;
		}
	}

	/**
	 * Event handler: Gets invoked to display the payload before uninstalling the plugin.
	 *
	 * You have to request a call to this during the plugin uninstall procedure by
	 * returning NULL in {@link BeforeUninstall()}.
	 *
	 * @param array Associative array of parameters.
	 *              'Form': The {@link Form} that user_tab the user for confirmation (by reference).
	 *                      If your plugin uses canonical table names (see {@link Plugin::get_sql_table()}),
	 *                      there will be already a list of those tables included in it.
	 *                      Do not end the form, just add own inputs or hidden keys to it.
	 */
	function BeforeUninstallPayload( & $params )
	{
		?>
		<input name="<?php echo $this->code.'__unlink_dir'; ?>" type="hidden" value="0"  />
		<label>
			<input name="<?php echo $this->code.'__unlink_dir'; ?>" type="checkbox" value="1"  />
			<span class="notes"><?php echo $this->T_('Permanently delete any avatars?') ?></span>
		</label>
		<?php
	}

# =============================================================================
# ADMIN AREA FUNCTIONS/EVENTS

	/**
	 * Event handler: Gets invoked when our tab is selected and should get displayed.
	 *
	 * Do your output here.
	 *
	 * @return boolean did we display something?
	 */
	function AdminAfterPageFooter()
	{
		global $ctrl, $blog, $action, $user_ID;

		switch ( true )
		{
			// Posts
			case $ctrl === 'items':
				//<div id="item_70822">...<div class="bContent">
				$regex = '/(<div .*?id="item_([0-9]+)".*?>[\s\S]+?<div .*?class=".*?bContent.*?".*?>)/e';
				$replace =
					'$this->get_avatar_display( '.
						// avatar
						'array("type" => "post", "name" => $2, "skin" => "admin"), '.
						// style
						'"margin:3px; float:right;", '.
						// before
						'str_replace("\\\'", "\'", "$1"), '.
						// after
						'"", '.
						// none
						'str_replace("\\\'", "\'", "$1") '.
					')';
				break;

			// Users
			case $ctrl === 'users':
				$regex = '/<td class="">(<a href="\\?ctrl=users&amp;user_ID=([0-9]+).+?>.+?<\\/a>)<\\/td>/e';
				$replace =
					'"<td class=\"\">".$this->get_avatar_display( '.
						/* avatar */
						'array("type" => "user", "name" => $2, "skin" => "admin"), '.
						/* style */
						'"margin:4px 0", '.
						/* before */
						'"<div style=\"float:left\">", '.
						/* after */
						'"</div><div style=\"margin-top:'.(round($this->Settings->get('avatar_user_small_height')/2)-5).'px; margin-left:'.
						($this->Settings->get('avatar_user_small_width')+15).'px\" >".str_replace("\\\'", "\'", "$1")."</div>", '.
						/* none */
						'str_replace("\\\'", "\'", "$1") '.
					')."</td>"';
				break;

			default:
				break;
		}

		// =======================================================

		// Check user permissions
		if( ! $this->check_user_perms() )
			return NULL;

		// Find out if a avatar group applies for our current area
		extract( $this->admin_avatars($params, 'footer') );

		if( empty($avatar_type) )
		{	// We are not needed
			return NULL;
		}

		// Include our admin area
		require(dirname(__FILE__).'/_admin_area.php');

		return true;
	}

	function DisplayProfileFormFieldset( & $params )
	{
		if( $params['edit_layout'] == 'public' )
		{ // Do nothing in public mode
			return false;
		}

		// Check user permissions
		if( ! $this->check_user_perms() )
			return NULL;

		// ----------------------------------------------------
		// We are needed

		// Include our profile area
		require(dirname(__FILE__).'/_profile_area.php');

		return true;
	}

	function AfterObjectInsert( & $params )
	{
		if( in_array( $params['type'], array('User', 'Blog', 'Chapter', 'Item') ) )
		{
			$this->do_form_request();
		}
	}

	function AfterObjectDelete( & $params )
	{
		switch( $params['type'] )
		{
			case 'Item':
				$name = 'post';
				break;

			case 'Blog':
				$name = 'blog';
				break;

			case 'Chapter':
				$name = 'category';
				break;

			case 'User':
				$name = 'user';
				break;

			default:
				return;
		}

		$this->delete_avatar_image( $name, $params['Object']->ID );
	}


	function SessionLoaded()
	{
		global $Session, $is_admin_page, $login_required;

		if( $is_admin_page && $login_required && $Session->has_User() && $this->check_user_perms() )
		{	// We are in backoffice, let's check if avatars form was submitted
			if( !empty($_POST['avatar_runonce']) && !empty($_POST['avatar_type']) && !empty($_POST['avatar_name'])
			&& param('action', 'string') == 'update'
			&& in_array( param('ctrl', 'string'), array('coll_settings', 'chapters') ) )
			{
				$this->do_form_request();
			}
		}
	}

	# -----------------------------------------------------------------------------

	/**
	 * The AdminDisplayItemFormFieldset Event
	 **
	 * This function displays our little form/area in the 'Write/Edit Post' page.
	 * What it does:
	   * Searches the post with our $search expression
	   * If something is found, then our plugin is needed - return true;
	   * Otherwise our plugin is not needed - return false;
	 *
	 */

	/**
	 * Event handler: Called at the end of the "Edit item" form.
	 *
	 * @param array Associative array of parameters
	 *   - 'Form': the {@link Form} object (by reference)
	 *   - 'Item': the Item which gets edited (by reference)
	 *   - 'edit_layout': "simple", "expert", etc. (users, hackers, plugins, etc. may create their own layouts in addition to these)
	 * NOTE: Please respect the "simple" mode, which should display only the most simple things!
	 * @return boolean did we display something?
	 */
	function AdminDisplayItemFormFieldset( & $params )
	{
		// Comment the below statement out if you want your plugin shown in simple view
		if( $params['edit_layout'] === 'simple' )
			return false;

		// Check user permissions
		if( ! $this->check_user_perms() )
			return NULL;

		// Find out if a avatar group applies for our current area
		extract( $this->admin_avatars($params) );
		if( empty($avatar_type) )
		{	// We are not needed
			return NULL;
		}

		// ----------------------------------------------------
		// We are needed

		// Include our admin area
		require( dirname(__FILE__).'/_admin_area.php' );

		return true;
	}


	/**
	 * Event handler: Called before an existing item gets updated (in the backoffice).
	 *
	 * You could {@link Plugin::msg() add a message} of
	 * category "error" here, to prevent the comment from being inserted.
	 *
	 * @param array Associative array of parameters
	 *              'Item': the Item which gets updated (by reference)
	 */
	function AdminBeforeItemEditUpdate( & $params )
	{
		$this->do_form_request();
	}


	/**
	 * Event handler: called at the end of {@link User::dbupdate() updating
	 * an user account in the database}, which means that it has been changed.
	 *
	 * @since 1.8.1
	 * @param array Associative array of parameters
	 *   - 'User': the related User (by reference)
	 */
	function AfterUserUpdate( & $params )
	{
		$this->do_form_request();
	}


	/**
	 * Event handler: Called when ending the admin html head section.
	 *
	 * @param array Associative array of parameters
	 * @return boolean did we do something?
	 */
	function AdminEndHtmlHead( & $params )
	{
		global $ctrl, $blog, $plugins_url;

		switch ( true )
		{
			// Posts
			case $ctrl === 'items':
			// Users
			case $ctrl === 'users':
			// Blogs
			case $ctrl === 'collections' && $blog === 0:
			// Categories
			case $ctrl === 'chapters':
				break;

			default:
				// Find out if a avatar group applies for our current area
				extract($this->admin_avatars($params));
				if( empty($avatar_type) )
				{	// We are not needed
					return NULL;
				}

				// Find out if this event applies for our current area
				switch ( $admin_area )
				{
					case 'blog':
					case 'category':
					case 'user':
						$this->do_form_request($params);
						if( $admin_area == 'blog' ) break;

						if( $this->do_form_request_ran )
						{	// We do not need this if the form ran
							return NULL;
							break;
						}
						break;
				}
				break;
		}

		// ----------------------------------------------------
		// We are needed

		$this->SkinBeginHtmlHead($params);

		return true;
	}



// =============================================================================
// SKIN FUNCTIONS/EVENTS

	/**
	 * The SkinBeginHtmlHead Event
	 **
	 * This function displays our and HEAD html code we want inside the skin (Eg. Stylsheets, Javascript).
	 *
	 * Event handler: Called during the display of the Skins's Html Head section.
	 *
	 * @param array Associative array of parameters
	 * @return boolean did we do something?
	 */
	function SkinBeginHtmlHead( & $params )
	{
		global $Plugins, $plugins_url;

		// Make it easier for our skinner
		$GLOBALS['avatars_Plugin'] = & $Plugins->get_by_code($this->code);

		// Include Avatar's CSS files
		require_css( $plugins_url.'avatars_plugin/includes/basic.css', false );

        // Set the background avatar if it exists
		$background_avatar = $this->get_avatar( NULL, 'background', 'default' );
		if( !empty($background_avatar) )
		{
			add_css_headline( 'img.avatars_plugin_avatar { background:url("'.$background_avatar['url'].'") no-repeat white center }');
		}

		// =============================
		// Include SCRIPTS

		require_js( '#jqueryUI#' );
		require_js( 'colorbox/jquery.colorbox-min.js' );
		require_css( 'colorbox/colorbox.css' );
		add_js_headline('jQuery(document).ready(function()
			{
				jQuery("a.lightbox_avatar").colorbox({maxWidth:"95%", maxHeight:"90%", slideshow:true, slideshowAuto:false });
			});' );

		return true;
	}

}

?>