<?php
/**
 * This file implements the UserBlog plugin for {@link http://b2evolution.net/}.
 *
 * Below is a list of authors who have contributed to design/coding of this file:
 * @author sam2kb: Russian b2evolution - {@link http://ru.b2evo.net/}
 * @author fralenuvol: Francesco CASTRONOVO - {@link http://www.fralenuvol.com/}
 *
 * @copyright (c)2008-2009 by Sonorth Corp. - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @TODO: double check post statuses
 * Plugin & Skin settings tabs
 * chmod($config_file, 0777); on line 1360
 * chmod($file, 0755); in line 1374
 * chmod($config_file, 0777); on line 1412
 */

if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );
	global $show_statuses;
	$show_statuses = array();

/**
 * UserBlog Plugin
 * This plugin creates a new Blog for each new user registration
 */
class userblog_plugin extends Plugin
{
	/**
	 * Variables below MUST be overriden by plugin implementations,
	 * either in the subclass declaration or in the subclass constructor.
	 */
	var $name = 'UserBlog';
	var $code = 'evo_userblog';
	var $priority = 60;
	var $version = '2.2.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=15842';

	/*
	 * These variables MAY be overriden.
	 */
	var $apply_rendering = 'never';
	var $number_of_installs = 1;

	var $Userblog_Group = 'Userblog group';

	/**
	 * Init
	 *
	 * This gets called after a plugin has been registered/instantiated.
	 */
	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Create and manage user blogs.');
		$this->long_desc = $this->T_('This plugin creates a new blog for each registered user.');
	}


	// ==========================================================================
	// Plugin settings

	/**
	 * Get the settings that the plugin can use.
	 *
	 * Those settings are transfered into a Settings member object of the plugin
	 * and can be edited in the backoffice (Settings / Plugins).
	 *
	 * @see Plugin::GetDefaultSettings()
	 * @see PluginSettings
	 * @see Plugin::PluginSettingsValidateSet()
	 * @return array
	 */
	function GetDefaultSettings()
	{
		global $GroupCache, $conf_path;

		// Get skins list
		$s = array();
		$SkinCache = & get_Cache( 'SkinCache' );
		$SkinCache->load_by_type( 'normal' );

		foreach ( $SkinCache->cache as $Skin )
		{
			$s[$Skin->ID] = $Skin->name;
		}

		// Get host name
		$host = $_SERVER['HTTP_HOST'];

		$r = array(

	// =====================================================
	// Plugin settings

			'userblog_group' => array(
					'label' => $this->T_('Userblog group name'),
					'defaultvalue' => $this->Userblog_Group,
					'size' => '30',
				),
			'userblog_frontend' => array(
					'label' => $this->T_('Create user blog'),
					'defaultvalue' => '1',
					'type' => 'checkbox',
					'note' => $this->T_('Check this if you want').' '.$this->T_('to enable user blog creation.'),
				),
			'admin_create_userblog' => array(
					'label' => $this->T_('Admin user blog'),
					'defaultvalue' => '0',
					'type' => 'checkbox',
					'note' => $this->T_('Check this if you want').' '.$this->T_('to create a blog when admin register a new user.'),
				),
			'blog_upon_registration' => array(
					'label' => $this->T_('Blog upon registration'),
					'defaultvalue' => '0',
					'type' => 'checkbox',
					'note' => $this->T_('Check this if you want').' '.$this->T_('to create a blog upon user registration.<br />Note: users must accept terms and conditions in order to get a blog (see Messages section).'),
				),
			'delete_stub_folder' => array(
					'label' => $this->T_('Delete stub folder'),
					'defaultvalue' => 0,
					'type' => 'checkbox',
					'note' => $this->T_('Check this if you want').' '.$this->T_('to automatically delete user\'s folder when the blog is deleted.<br />EXAMPLE: If you use a storage folder "u/" and user\'s login is mike -  <strong>"u/mike/" folder and all content</strong> will be removed.'),
				),
			'blog_in_bloglist' => array(
					'label' => $this->T_('Include in public blog list'),
					'defaultvalue' => 0,
					'type' => 'checkbox',
					'note' => $this->T_('Check this if you want').' '.$this->T_('to add a new user blog to the public blogs list.'),
				),
			'blog_skin' => array(
					'label' => $this->T_('Default skin'),
					'type' => 'select',
					'options' => $s,
					'defaultvalue' => 'custom',
					'note' => $this->T_('This is the default skin that will be used to display new user blogs.'),
				),
			'blog_allowblogcss' => array(
					'label' => $this->T_('Custom user CSS'),
					'defaultvalue' => 0,
					'type' => 'checkbox',
					'note' => $this->T_('Create blank "style.css" in blog media folder. This file may be edited by users to override default skin styles.<br />ATTENTION: Make sure you set File manager permissions to "Edit" below.'),
				),
			'clean_urls' => array(
					'label' => $this->T_('Use extra path URLs'),
					'defaultvalue' => true,
					'type' => 'checkbox',
					'note' => $this->T_('Allows you to use nice formatted links, removing additional parameters from URLs.<br /><br />EXAMPLE: URLs like <strong>"www.yourdomain.com/u/username/index.php?title=post-title&more=1"</strong> will look like <strong>"www.yourdomain.com/u/username/index.php/2008/12/31/post-title"</strong>.<br /><br />ATTENTION: In order to make this feature work you need enabled mod_rewrite Apache module (already enabled on most servers).'),/*<br /><br />If you want to remove "index.php" from URLs, copy the content of cleanurls.htaccess provided in Userblog Plugin folder in your own .htaccess file.*/
				),
			'use_subdom' => array(
					'label' => $this->T_('EXPERIMENTAL: Use sub-domains'),
					'defaultvalue' => 0,
					'type' => 'checkbox',
					'note' => sprintf( $this->T_('If checked, new blogs will be in format %s'), '<code>blog1.example.com</code>.<br />' ). $this->T_('NOTE: you have to configure wildcard DNS first.'),
				),
			'options_end' => array(
					'layout' => 'end_fieldset',
				),

	// =====================================================
	// User Permissions

			'perm_start' => array(
					'layout' => 'begin_fieldset',
					'label' => $this->T_('User permissions'),
				),
			'post_now' => array(
					'label' => $this->T_('Enable posts'),
					//'onchange' => 'document.getElementById("'.$this->classname.'_post_perm").disabled = ( this.value == "false" );',
					'defaultvalue' =>  'true',
					'type' => 'select',
					'options' => array( 'true' => $this->T_('yes'), 'false' => $this->T_('no') ),
					'note' => $this->T_('Are new users allowed to post after registration?<br />REMEMBER: If you disable posting, admin has to set user login in "Login of this blog\'s owner" field in Blog settings -> Genearal.'),
				),
			'file_manager' => array(
					'label' => $this->T_('File manager'),
					'defaultvalue' =>  'none',
					'type' => 'select',
					'options' => array( 'edit' => $this->T_('Edit'), 'add' => $this->T_('Add'), 'none' => $this->T_('None') ),
					'note' => $this->T_('Select File manager permissions for new users.<br />[ Edit ] - upload/edit/delete files.<br />[ Add ] - upload only.<br />[ None ] - disabled.'),
				),
			'perm_end' => array(
					'layout' => 'end_fieldset',
				),

	// =====================================================
	// Stub settings

			'stub_start' => array(
					'layout' => 'begin_fieldset',
					'label' => $this->T_('Stub settings'),
				),
			'use_stub' => array(
					'label' => $this->T_('Use stub file'),
					'defaultvalue' =>  'true',
					'type' => 'select',
					'options' => array( 'true' => $this->T_('yes'), 'false' => $this->T_('no') ),
				),
			'stub_ext' => array(
					'label' => $this->T_('Stub file extension'),
					'id' => $this->classname.'_stub_ext',
					'defaultvalue' => '.php',
					'disabled' => false, // this can be useful if you detect that something cannot be changed. You probably want to add a 'note' then, too.
					'note' => $this->T_('OPTIONAL. Set extension for new stub files. Leave empty if you want no extension.'),
					'size' => '5',
				),
			'stub_folder' => array(
					'label' => $this->T_('Use stub folder'),
					'id' => $this->classname.'_stub_folder',
					'defaultvalue' =>  'true',
					'type' => 'select',
					'options' => array( 'true' => $this->T_('yes'), 'false' => $this->T_('no') ),
					'disabled' => false,
					'note' => $this->T_('OPTIONAL. Separate folders will be created for each user and index.php stub file will be dropped in (example: root/username/index.php). If disabled, stub files will be created as usual, with the same name as login (example: root/username.php).'),
				),
			'media_in_user' => array(
					'label' => $this->T_('Media in stub folder'),
					'defaultvalue' => false,
					'type' => 'checkbox',
					'id' => $this->classname.'_media_in_user',
					'disabled' => false,
					'note' => $this->T_('OPTIONAL. If set, the directory username/media/ folder will be created. Useful if you want to store all user stuff in one single directory.'),
				),
			'storage_folder' => array(
					'label' => $this->T_('Use storage folder'),
					'id' => $this->classname.'_storage_folder',
					'defaultvalue' => 'u/',
					'disabled' => false,
					'note' => $this->T_('OPTIONAL. Store all users stub files in one single directory. Example: if your root is /blogs/ and you want to store all your stub files in /blogs/subdir/ you will set "subdir/". Leave empty to disable.<br /><br />If this settings don\'t work on your server, check if storage folder was created, if not do it manually and chmod to 777. Otherwise deactivate the "Use stub file" option.'),
				),
			'userblog_config_file' => array(
					'id' => $this->classname.'_userblog_config_file',
					'label' => $this->T_('Common config file for user blogs'),
					'defaultvalue' => '$show_statuses = array();

$linkblog_cat = "";
$linkblog_catsel = array();
$timestamp_min = "";
$timestamp_max = "";

require_once "'.$conf_path.'_config.php";
require $inc_path."_blog_main.inc.php";',
					'note' => $this->T_('OPTIONAL. This is the common config file included in all user blog stub files which lets you override the global settings for each blog.<br />Don\'t add PHP brackets (<strong>&lt;?php</strong> and <strong>?&gt;</strong>) in this form.<br /><br />ATTENTION: Take care to chmod "userblog_config.php" in  userblog_plugin folder to 777 if you use stub files.'),
					'type' => 'html_textarea',
					'rows' => '10',
				),
			'stub_end' => array(
					'layout' => 'end_fieldset',
				),

	// =====================================================
	// Blog description

			'strings_start' => array(
					'layout' => 'begin_fieldset',
					'label' => $this->T_('Blog description'),
				),
			'blog_name' => array(
					'label' => $this->T_('Full Name'),
					'defaultvalue' => $this->T_('New blog for %s'),
					'note' => $this->T_('Will be displayed on top of the blog.').$this->T_('<br />(example: if login name= "mike", the blog name will be: "New blog for mike").'),
					'size' => '42',
				),
			'blog_tagline' => array(
					'label' => $this->T_('Tagline'),
					'defaultvalue' => $this->T_('This is the blog\'s tagline'),
					'note' => $this->T_('This is diplayed under the blog name on the blog template.'),
					'size' => '42',
				),
			'blog_long_description' => array(
					'label' => $this->T_('Long Description'),
					'defaultvalue' => $this->T_('This is the blog\'s long description.'),
					'note' => $this->T_('This is displayed on the blog template.'),
					'type' => 'textarea',
					'rows' => '3',
				),
			'blog_description' => array(
					'label' => $this->T_('Short Description'),
					'defaultvalue' => $this->T_('This is the blog\'s description.'),
					'note' => $this->T_('This is is used in meta tag description and RSS feeds. NO HTML!'),
					'size' => '42',
				),
			'blog_keywords' => array(
					'label' => $this->T_('Keywords'),
					'defaultvalue' => $this->T_('%s, personal, blog'),
					'note' => $this->T_('This is used in meta tag keywords. NO HTML!').$this->T_('<br />Placeholder stands here for user\'s login name.'),
					'size' => '42',
				),
			'strings_end' => array(
					'layout' => 'end_fieldset',
				),

	// =====================================================
	// Messages

			'messages_start' => array(
					'layout' => 'begin_fieldset',
					'label' => $this->T_('Messages'),
				),
			'link_to_create' => array(
					'label' => $this->T_('Link to create a blog'),
					'defaultvalue' => $this->T_('Create my personal blog!'),
					'note' => $this->T_('This is a text link to create a new blog. Appears in user profile and after user is registered (see below).'),
					'size' => '60',
				),
			'blog_welcome_msg' => array(
					'label' => '1. ' . $this->T_('Welcome message'),
					'defaultvalue' => $this->T_('You have successfully registered in').' '.$host.$this->T_('!%sIf you don\'t need a blog now you can create it later from your profile page.'),
					'note' => $this->T_('Welcome message will be displayed when the user is registered. This message introduces the link to create a new blog.<br />%s will be replaced with a link to create a blog (see above).'),
					'type' => 'html_textarea',
					'rows' => '3',
				),
			'blog_welcome_msg1' => array(
					'label' => '2. ' . $this->T_('Welcome message'),
					'defaultvalue' => $this->T_('Your personal blog was created! Take note of your new blog address:'),
					'note' => $this->T_('Welcome message will be displayed to the user after his new blog is created. This message introduces the new blog URL.'),
					'type' => 'html_textarea',
					'rows' => '3',
				),
			'blog_welcome_msg2' => array(
					'label' => '3. ' . $this->T_('Welcome message'),
					'defaultvalue' => $this->T_('At this address you can access the Administration of your blog:'),
					'note' => $this->T_('This message will be appended to the previous message. It introduces the admin backend URL.'),
					'type' => 'html_textarea',
					'rows' => '3',
				),
			'tos' => array(
					'label' => $this->T_('Terms of Service'),
					'defaultvalue' => $this->T_('Write your Terms of Service or site policy that needs to be accepted by user in order to create a new blog.'),
					'note' => $this->T_('This message will be displayed on registration page.'),
					'type' => 'html_textarea',
					'rows' => '10',
				),
			'tos_checkbox' => array(
					'label' => $this->T_('Terms checkbox'),
					'defaultvalue' => $this->T_('Do you accept the above Terms of Service?'),
					'note' => $this->T_('This message will be displayed on registration page.'),
					'size' => '60',
				),
			'messages_end' => array(
					'layout' => 'end_fieldset',
				),
			);

		if( isset($this->Settings) )
		{
			if( $this->Settings->get('use_stub') == 'false' )
			{
				$r['stub_folder']['disabled'] = true;
				$r['stub_folder']['note'] = '';

				$r['storage_folder']['disabled'] = true;
				$r['storage_folder']['note'] = '';

				$r['stub_ext']['disabled'] = true;
				$r['stub_ext']['note'] = '';

				$r['media_in_user']['disabled'] = true;
				$r['media_in_user']['note'] = '';

				$r['userblog_config_file']['disabled'] = true;
				$r['userblog_config_file']['note'] = '';
			}

			if( $this->Settings->get('stub_folder') == 'false' )
			{
				$r['media_in_user']['disabled'] = true;
			}
		}
		return $r;
	}

	// ==========================================================================
	// Create/Update UserBlog group

	/**
	 * Event handler: Called when the plugin has been installed.
	 * @see Plugin::AfterInstall()
	 */
	function AfterInstall()
	{
		$this->Create_Userblog_Group();
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
		global $PluginSettings, $GroupCache;

		// Get Userblog group
		$Userblog_Group = & $GroupCache->get_by_ID( $this->UserblogGroupID() );
		if( isset($Userblog_Group) )
		{
			$grpname = $Userblog_Group->name;
		}
		else
		{
			$grpname = $this->$Userblog_Group;
		}
		$this->Settings->set( 'userblog_group', $grpname );
		$this->Settings->dbupdate();
	}


	// Update group settings if changed
	function PluginSettingsUpdateAction()
	{
		$this->Update_Userblog_Group();
	}


	function Create_Userblog_Group()
	{
		global $Group, $GroupCache, $DB, $Settings;

		$Userblog_Group_ID = false;
		$Userblog_Group = & $GroupCache->get_by_name( $this->Settings->get('userblog_group'), false );

		if( isset($Userblog_Group) )
		{
			$Userblog_Group_ID = $Userblog_Group->ID;
		}

		// Check if Userblog Group exists.
		if( ! $Userblog_Group_ID )
		{
			// Create Userblog Group
			$Userblog_Group = new Group();
			$Userblog_Group->set( 'name', $this->Settings->get('userblog_group') );
			$Userblog_Group->set( 'perm_admin', 'visible' );
			$Userblog_Group->set( 'perm_blogs', 'user' );
			$Userblog_Group->set( 'perm_stats', 'user' );
			$Userblog_Group->set( 'perm_spamblacklist', 'view' );
			$Userblog_Group->set( 'perm_files', $this->Settings->get('file_manager') );
			$Userblog_Group->set( 'perm_options', 'none' );
			$Userblog_Group->set( 'perm_templates', 0 );
			$Userblog_Group->set( 'perm_users', 'none' );
			$Userblog_Group->dbinsert();

			// Remember Userblog Group ID in settings
			$Settings->set( 'userblog_grp_ID', $Userblog_Group->ID );
			$Settings->dbupdate();

			$Userblog_Group_ID = $Userblog_Group->ID;
		}
		$this->Settings->dbupdate();

		return $Userblog_Group;
	}


	function Update_Userblog_Group()
	{
		global $GroupCache;

		$Userblog_Group = & $GroupCache->get_by_ID( $this->UserblogGroupID() );

		if( isset($Userblog_Group) )
		{	// Update group settings
			$Userblog_Group->set( 'name', $this->Settings->get('userblog_group') );
			$Userblog_Group->set( 'perm_files', $this->Settings->get('file_manager') );
			$Userblog_Group->set( 'perm_spamblacklist', 'view' );
			$Userblog_Group->dbupdate();
		}
	}

	// ==========================================================================
	// On user change

	/*
	 * Event handler: called at the end of {@link User::dbinsert() inserting
	 * an user account into the database}, which means it has been created.
	 */
	function AfterUserInsert( $params )
	{
		global $UserSettings, $Settings, $Messages, $admin_url, $current_User;

		$inserted_User = $params['User'];

		// Change user level
		//$inserted_User->set( 'level', 5 );
		//$inserted_User->dbupdate();

		// Check if user blog is enabled in plugin settings and user is in userblog group
		if( $this->Settings->get('userblog_frontend') && ($this->UserblogGroupID() == $inserted_User->Group->ID) )
		{
			if( param( 'tos_accepted', 'bool', '' ) && $this->Settings->get('blog_upon_registration') )
			{
				$UserSettings->set('userblog_tos_accepted', '1', $inserted_User->ID);
				$UserSettings->set('userblog_requested_blog_name', param( 'requested_blog_name', 'string', '' ), $inserted_User->ID);
			}
			$UserSettings->set('userblog_created', 'wanted', $inserted_User->ID);
			$UserSettings->dbupdate();

			if( isset($current_User->Group->ID) && $current_User->Group->ID == 1 )
			{	// Admin registers a new user and user blog is needed - create it
				if( $this->Settings->get('admin_create_userblog') )
				{
					$created = $this->CreateUserBlog( $params );
					if( $created === false )
					{
						$Messages->add( $this->T_('User blog was not created!') );
					}
					elseif( $created === NULL )
					{
						$Messages->add( $this->T_('User blog was not properly created! You must delete this blog and try again.') );
					}
				}
			}
			elseif( param( 'tos_accepted', 'bool', '' ) && $this->Settings->get('blog_upon_registration') )
			{	// User requested a blog upon registration
				// We'll create it later
			}
			elseif( $Settings->get('newusers_mustvalidate') )
			{	// No message if validation is required
				// We'll show the message when user gets validated
				$UserSettings->set('userblog_welcome', 1, $inserted_User->ID);
				$UserSettings->dbupdate();
			}
			else
			{	// If regular user and no validation required
				$s = '<p><a href="'.$admin_url.'?ctrl=users&amp;userblog=new">'.$this->Settings->get('link_to_create').'</a></p>';
				$Messages->add( sprintf( $this->Settings->get('blog_welcome_msg'), $s ), 'success' );
			}
		}
	}

	/**
	 * Event handler: called at the end of {@link User::dbdelete() deleting
	 * an user from the database}.
	 */
	function AfterUserDelete( $params )
	{
		global $UserSettings;

		$deleted_User = $params['User'];

		$UserSettings->delete('userblog_created', $deleted_User->ID);
		$UserSettings->dbupdate();
	}


	// ==========================================================================
	// Register form events

	/**
	 * Event handler: Called at the end of the "Register as new user" form.
	 *
	 * You might want to use this to inject antispam payload to use
	 * in {@link Plugin::RegisterFormSent()}.
	 *
	 * @param array Associative array of parameters
	 *   - 'Form': the comment form generating object (by reference)
	 */
	function DisplayRegisterFormFieldset( & $params )
	{
		global $Settings;

		$Form = $params['Form'];
		if( $this->UserblogGroupID() == $Settings->get('newusers_grp_ID') && $this->Settings->get('blog_upon_registration') )
		{
			echo '<fieldset><div class="input">'.$this->Settings->get('tos').'</div></fieldset>';
			$Form->checkbox_input( 'tos_accepted', false, $this->T_('I accept'), array( 'note' => $this->Settings->get('tos_checkbox') ) );
			$Form->text_input( 'requested_blog_name', '', 49, $this->T_('Blog name'), '', array( 'maxlength' => 49 ) );
		}
	}


	/**
	 * Event handler: Called when a "Register as new user" form has been submitted.
	 *
	 * You can cancel the registration process by {@link Plugin::msg() adding a message}
	 * of type "error".
	 *
	 * @param array Associative array of parameters
	 *   - 'login': Login name (by reference) (since 1.10.0)
	 *   - 'email': E-Mail value (by reference) (since 1.10.0)
	 *   - 'locale': Locale value (by reference) (since 1.10.0)
	 *   - 'pass1': Password (by reference) (since 1.10.0)
	 *   - 'pass2': Confirmed password (by reference) (since 1.10.0)
	 */
	function RegisterFormSent( & $params )
	{
		global $Messages, $Settings;

		if( !param( 'tos_accepted', 'bool', '' )
			&& $this->UserblogGroupID() == $Settings->get('newusers_grp_ID')
			&& $this->Settings->get('blog_upon_registration') )
		{
			$Messages->add( $this->T_('You must read and accept our Terms of Service in order to complete your registration.'), 'error' );
		}
	}


	// ==========================================================================
	// Show "Welcome" and "Create user blog" messages

	function AfterLoginRegisteredUser()
	{
		global $Messages, $UserSettings, $Session, $current_User, $admin_url;

		// Show messages in user profiles
		if( ($current_User->group_ID == $this->UserblogGroupID()) &&
			((isset($_GET['disp']) && $_GET['disp'] == 'profile') || (isset($_GET['ctrl']) && $_GET['ctrl'] == 'users')) )
		{
			if( $UserSettings->get('userblog_created', $current_User->ID) == 'wanted' )
			{	// Show the message to create user blog
				$msg = '<a href="'.$admin_url.'?ctrl=users&amp;userblog=new">'.$this->Settings->get('link_to_create').'</a>';
				if( ! $Messages->count('note') && ! $UserSettings->get( 'userblog_welcome' ) )
				{
					$Messages->add( $msg, 'note' );
				}
			}
		}


		if( $current_User->validated && $UserSettings->get( 'userblog_tos_accepted' ) )
		{	// Create a blog after registration
			$current_User->set('requested_blog_name', $UserSettings->get( 'userblog_requested_blog_name' ) );

			$UserSettings->delete( 'userblog_tos_accepted' );
			$UserSettings->delete( 'userblog_requested_blog_name' );
			$UserSettings->dbupdate();

			$created = $this->CreateUserBlog( $current_User );
			if( $created === false )
			{
				$Messages->add( $this->T_('User blog was not created!') );
			}
			elseif( $created === NULL )
			{
				$Messages->add( $this->T_('User blog was not properly created! You must delete this blog and try again.') );
			}
		}
		elseif( $current_User->validated && $UserSettings->get( 'userblog_welcome' ) )
		{	// Show welcome message to validated user
			$s = '<p><a href="'.$admin_url.'?ctrl=users&amp;userblog=new">'.$this->Settings->get('link_to_create').'</a></p>';
			$Messages->add( sprintf( $this->Settings->get('blog_welcome_msg'), $s ), 'success' );
			$UserSettings->delete( 'userblog_welcome' );
			$UserSettings->dbupdate();
		}

		// Set user settings 'userblog_created = wanted' if user belongs to Userblog group and not set yet
		// This is needed if admin deleted user blog
		if( $current_User->group_ID == $this->UserblogGroupID() && ! $UserSettings->get('userblog_created') )
		{
			$UserSettings->set('userblog_created', 'wanted', $current_User->ID);
			$UserSettings->dbupdate();

			header_redirect( regenerate_url() );
		}
	}

	// ==========================================================================
	// Main control

	/**
	 * Event handler: Gets invoked in /admin/_header.php for every backoffice page after
	 * the menu structure is build.
	 */
	function AdminAfterMenuInit()
	{
		global $BlogCache, $GroupCache, $UserCache, $Settings, $UserSettings, $AdminUI, $Messages, $DB;
		global $current_User, $demo_mode, $blog, $basepath;

		$ctrl = param( 'ctrl', 'string' );
		$tab = param( 'tab', 'string' );

		// Switch to demo mode only for demouser
		if( $current_User->login == 'demouser' ) $demo_mode = true;

		// Do the following only for UserBlog group members
		if( $current_User->Group->ID == $this->UserblogGroupID() )
        {
			// Show stats only for User's blog
			if( $ctrl == 'stats' )
			{
				$blog = autoselect_blog( 'stats', 'view' );
			}

			// Hide backoffice menu entries from all users
			if( !empty( $AdminUI->_menus['entries']['blogs']['entries']['features'] ) )
				unset( $AdminUI->_menus['entries']['blogs']['entries']['features'] );

			if( !empty( $AdminUI->_menus['entries']['blogs']['entries']['urls'] ) )
				unset( $AdminUI->_menus['entries']['blogs']['entries']['urls'] );

			if( !empty( $AdminUI->_menus['entries']['blogs']['entries']['seo'] ) )
				unset( $AdminUI->_menus['entries']['blogs']['entries']['seo'] );

			if( !empty( $AdminUI->_menus['entries']['blogs']['entries']['advanced'] ) )
				unset( $AdminUI->_menus['entries']['blogs']['entries']['advanced'] );

			if( !empty( $AdminUI->_menus['entries']['blogs']['entries']['perm'] ) )
				unset( $AdminUI->_menus['entries']['blogs']['entries']['perm'] );

			if( !empty( $AdminUI->_menus['entries']['blogs']['entries']['permgroup'] ) )
				unset( $AdminUI->_menus['entries']['blogs']['entries']['permgroup'] );


			if( $UserSettings->get('userblog_created', $current_User->ID) == 'wanted' )
			{	// No user blog yet

				// Redirect users to My Profile if no blog yet
				if( ( $ctrl != 'users' ) && !isset($_POST['edited_user_login']) )
				{
					$Messages->add( $this->T_('You have no permission to view the requested page!'), 'error' );
					header_redirect( $_SERVER['PHP_SELF'].'?ctrl=users' );
				}

				// Create user blog if requested
				if( isset($_GET['userblog']) && $_GET['userblog'] == 'new' )
				{
					$created = $this->CreateUserBlog( $current_User );

					if( $created === false )
					{
						$Messages->add( $this->T_('User blog was not created!') );
					}
					elseif( $created === NULL )
					{
						$Messages->add( $this->T_('User blog was not properly created! You must delete this blog and try again.') );
					}
					header_redirect( $_SERVER['PHP_SELF'].'?ctrl=items' );
				}

				// Hide menu entries if no user blog yet
				if( !empty( $AdminUI->_menus['entries']['files'] ) )
					unset( $AdminUI->_menus['entries']['files'] );

				if( !empty( $AdminUI->_menus['entries']['tools'] ) )
					unset( $AdminUI->_menus['entries']['tools'] );

				if( !empty( $AdminUI->_menus['entries']['stats'] ) )
					unset( $AdminUI->_menus['entries']['stats'] );

				if( !empty( $AdminUI->_menus['entries']['blogs'] ) )
					unset( $AdminUI->_menus['entries']['blogs'] );

				if( !empty( $AdminUI->_menus['entries']['cats'] ) )
					unset( $AdminUI->_menus['entries']['cats'] );

				if( !empty( $AdminUI->_menus['entries']['dashboard'] ) )
					unset( $AdminUI->_menus['entries']['dashboard'] );

				if( !empty( $AdminUI->_menus['entries']['items'] ) )
					unset( $AdminUI->_menus['entries']['items'] );
			}
			elseif( is_numeric($UserSettings->get('userblog_created', $current_User->ID)) )
			{	// User blog is already created

				// Update user blog media perms
				switch ( $this->Settings->get('file_manager') )
				{
					case 'edit':
					case 'add':
						$perm_media_upload = 1;
						$perm_media_browse = 1;
						$perm_media_change = 1;
						break;

					case 'none':
						$perm_media_upload = 0;
						$perm_media_browse = 0;
						$perm_media_change = 0;
						break;
				}

				$DB->query(" UPDATE T_coll_user_perms
							SET bloguser_perm_media_upload = $perm_media_upload,
								bloguser_perm_media_browse = $perm_media_browse,
								bloguser_perm_media_change = $perm_media_change
							WHERE bloguser_blog_ID = ".$UserSettings->get('userblog_created', $current_User->ID)."
							AND bloguser_user_ID = ".$current_User->ID );
			}

			// Redirect all smart guys ;)
			if( $ctrl == 'coll_settings' && $tab != 'general' && $tab != 'skin' )
			{
				$Messages->add( $this->T_('You have no permission to view the requested page!'), 'error' );
				header_redirect( $_SERVER['PHP_SELF'].'?ctrl=coll_settings&amp;blog='.$blog );
			}

			// Redirect from Sessions tab, I don't think we need it in 2.x
			if( ($ctrl == 'stats' && $tab == 'sessions') || ($ctrl == 'set_antispam') )
			{
				$Messages->add( $this->T_('You have no permission to view the requested page!'), 'error' );
				header_redirect( $_SERVER['PHP_SELF'].'?ctrl=stats&blog='.$blog );
			}
        }
		elseif( $current_User->Group->ID == 1 )
		{	// Admin
			if( isset($_GET['userblog'], $_GET['userID']) && $_GET['userblog'] == 'new' )
			{	// Create user blog if requested
				$created = $this->CreateUserBlog( $_GET['userID'] );

				if( $created === false )
				{
					$Messages->add( $this->T_('User blog was not created!') );
				}
				elseif( $created === NULL )
				{
					$Messages->add( $this->T_('User blog was not properly created! You must delete this blog and try again.') );
				}
			}
			elseif( isset($_GET['userblog'], $_GET['userID']) && $_GET['userblog'] == 'wanted' &&
						$UserSettings->get('userblog_created', $_GET['userID']) != 'wanted' )
			{	// Fix user blog settings
				$UserSettings->set('userblog_created', 'wanted', $_GET['userID']);
				$UserSettings->dbupdate();

				$fixed_User = & $UserCache->get_by_ID( $_GET['userID'], true, false );
				$Messages->add( sprintf( $this->T_('Userblog settings set to &quot;wanted&quot; for user &laquo;%s&raquo;.'), $fixed_User->get('preferredname') ), 'success' );
			}
		}

		// Delete Userblog settings when BLOG is deleted
		if( isset($_GET['action'], $_GET['blog'], $_GET['confirm']) &&
				$_GET['action'] == 'delete' &&
				$_GET['blog'] &&
				$_GET['confirm'] == 1 )
		{
			$deleted_Blog = & $BlogCache->get_by_ID( $_GET['blog'], false );

			// Delete stub directory
			$stub_folder = $basepath.$this->Settings->get('storage_folder').$deleted_Blog->shortname.'/';
			//die($stub_folder);
			if( is_dir( $stub_folder ) && $deleted_Blog->get_setting('userblog_owner_id') && $this->Settings->get('delete_stub_folder') )
			{
				rmdir_r( $stub_folder );
				$Messages->add( $this->T_('Deleted blog\'s stub folder'), 'success' );
			}

			// Delete stub file
			$stub_file = $stub_folder.basename($deleted_Blog->siteurl);
			//die($stub_file);
			if( is_file( $stub_file ) && $deleted_Blog->get_setting('userblog_owner_id') )
			{
				if( unlink( $stub_file ) )
					$Messages->add( $this->T_('Deleted blog\'s stub file'), 'success' );
			}

			// Delete userblog_owner_id settings
			$DB->query('
					DELETE FROM T_coll_settings
					WHERE cset_coll_ID = '.$deleted_Blog->ID.'
					AND cset_name = "userblog_owner_id" ');

			// Delete Userblog settings
			$UserCache->load_blogmembers( $_GET['blog'] );
			foreach( $UserCache->cache as $loop_Obj )
			{
				$UserSettings->delete('userblog_created', $loop_Obj->ID);
			}

			$UserSettings->dbupdate();
			$Messages->add( $this->T_('Deleted Userblog settings for blog members'), 'success' );
		}

		// Delete Userblog group ID from settings when GROUP is deleted
		if( isset($_GET['blog'], $_GET['ctrl'],$_GET['grp_ID'], $_GET['action'], $_GET['confirm']) &&
				$_GET['action'] == 'delete_group' && $_GET['confirm'] == 1 )
		{
			// Check if it's a Userblog group
			if( $this->UserblogGroupID() == $_GET['grp_ID'] )
			{
				$Settings->delete( 'userblog_grp_ID' );
				$Settings->dbupdate();
				$Messages->add( $this->T_('Deleted Userblog group ID'), 'success' );
			}
		}
	}

	// ==========================================================================
	// Create User blog

	/*
	 * This is the function that creates a new user blog.
	 * Returns the new blog ID on success.
	 */
	function CreateUserBlog( $params )
	{
		global $Blog, $Group, $DB, $UserSettings, $Settings, $Session, $Messages;
		global $current_User, $is_admin_page, $locales, $basepath, $baseurl, $admin_url, $plugins_path, $notify_from, $app_name, $app_version, $evo_charset;

		$BlogCache = & get_Cache('BlogCache');
		$UserCache = & get_Cache('UserCache');
		$GroupCache = & get_Cache('GroupCache');

		$Messages->clear('all');

		// =====================================================
		// Get New user params
		if( isset($current_User->Group->ID) && $current_User->Group->ID == 1 )
		{	// Admin
			if( is_numeric($params) )
			{	// Create a blog from user ID
				$new_User = & $UserCache->get_by_ID( $params, true, false );

				// [ERROR] No user blog if Group != Userblog_group
				if( $new_User->group_ID !== $this->UserblogGroupID() )
				{
					$error_userblog_create = $this->T_('Error: ').sprintf( $this->T_('the user is not a member of &laquo;%s&raquo; group.'), $this->Settings->get('userblog_group') );

					if( $current_User->Group->ID != 1 )
					{	// If a regular user, append the following
						$error_userblog_create .= "<br />".$this->T_('Ask the admin to create a blog for you!');
					}

					$Messages->add( $error_userblog_create, 'error' );
					return;
				}
			}
			else
			{	// Automatic creation when new user added
				$new_User = $params['User'];
			}
		}
		else
		{	// User
			$new_User = $params;
		}

		// =====================================================
		// Check if we can create a blog

		// If login has non-ASCII characters use "blog + userID" as blog urlname
		if( @preg_match('/^['.chr(32).'-'.chr(126).']+$/', $new_User->login) )
		{
			$new_User_login = $new_User->login;
		}
		else
		{
			$new_User_login = 'blog'.$new_User->ID;
		}

		// Unique names for new blog: shortname & urlname & category & staticfilename
		$new_blog_shortname = '';
		// Replace HTML entities
		$new_blog_shortname = htmlentities( $new_User_login, ENT_NOQUOTES );
		// Keep only one char in entities!
		$new_blog_shortname = preg_replace( '/&(.).+?;/', '$1', $new_blog_shortname );
		// Remove non acceptable chars
		$new_blog_shortname = preg_replace( '/[^A-Za-z0-9]+/', '_', $new_blog_shortname );
		// Remove '_' at start and end:
		$new_blog_shortname = preg_replace( '/^_+/', '', $new_blog_shortname );
		$new_blog_shortname = preg_replace( '/_+$/', '', $new_blog_shortname );
		// Lowercase all characters
		$new_blog_shortname = strtolower( $new_blog_shortname );

		// [ERROR] No user blog if we have no name for it
		if( empty($new_blog_shortname) )
		{
			$error_userblog_create = $this->T_('Error: ').$this->T_('we cannot create a valid blog name from your login.');

			if( $current_User->Group->ID != 1 )
			{	// If a regular user, append the following
				$error_userblog_create .= "<br />".$this->T_('Ask the admin to create a blog for you!');
			}

			$Messages->add( $error_userblog_create, 'error' );
			return false;
		}

		$new_blog_urlname = $new_blog_shortname;
		$new_blog_cat = $new_blog_shortname;
		$new_blog_stub = '';
		$new_blog_siteurl = '';
		$new_blog_access_type = 'index.php';

		// [ERROR] No user blog if already existing with this name
		$blog_exists = & $BlogCache->get_by_urlname( $new_blog_urlname, false );

		if( $blog_exists )
		{
			$error_userblog_create = $this->T_('Error: ').$this->T_('a blog with the same &quot;URL blog name&quot; is already exist in our database.');

			if( $current_User->Group->ID != 1 )
			{	// If a regular user, append the following
				$error_userblog_create .= "<br />".$this->T_('Ask the admin to create a blog for you!');
			}

			$Messages->add( $error_userblog_create, 'error' );
			return false;
		}

		// [ERROR] No user blog if userblog_created != wanted (DEBUG for admin)
		$is_userblog = $UserSettings->get('userblog_created', $new_User->ID);

		if( isset($is_userblog) && $is_userblog != 'wanted' )
		{
			$error_userblog_create = $this->T_('Error: ').$this->T_('failed to create a new blog.');

			if( $current_User->Group->ID != 1 )
			{	// If a regular user, append the following
				$error_userblog_create .= "<br />".$this->T_('Ask the admin to create a blog for you!');
			}
			else
			{	// Debug message for admin
				$error_userblog_create .= "<br />".'[DEBUG] UserSettings->userblog_created != wanted';
			}

			$Messages->add( $error_userblog_create, 'error' );
			return false;
		}

		// [ERROR] No user blog if disabled in Plugin settings
		if( $this->Settings->get('userblog_frontend' ) != 1 )
		{
			$error_userblog_create = $this->T_('Error: ').$this->T_('user blog creation is currently disabled.');

			if( $current_User->Group->ID != 1 )
			{	// If a regular user, append the following
				$error_userblog_create .= "<br />".$this->T_('Ask the admin to create a blog for you!');
			}

			$Messages->add( $error_userblog_create, 'error' );
			return false;
		}

		// =====================================================
		// Blog URLs handling

		if( $this->Settings->get('use_stub') != 'false' )
		{
			// Set url according to storage folder
			if( $this->Settings->get('storage_folder') )
			{
				$new_blog_siteurl = $this->Settings->get('storage_folder');
			}

			// Set url according to stub folder
			// If personal folder is used, it will have the same name as login and stub name will be 'index'
			if( $this->Settings->get('stub_folder') != 'false' )
			{
				$new_blog_stub = 'index';
				$new_blog_siteurl .= $new_blog_shortname.'/'.$new_blog_stub.$this->Settings->get('stub_ext');
			}
			else
			{
				$new_blog_stub = $new_blog_shortname;
				$new_blog_siteurl .= $new_blog_shortname.$this->Settings->get('stub_ext');
			}

			// Append stub extension
			$new_blog_stub .= $this->Settings->get('stub_ext');
			// Set access type for new blog
			$new_blog_access_type = 'relative';
		}

		// Set user media folder
		if( $this->Settings->get('use_stub') != 'false' && $this->Settings->get('stub_folder') != 'false' && $this->Settings->get('media_in_user') )
		{
			$new_blog_media_location = 'custom';
			$new_blog_media_subdir = '';
			$new_blog_media_fullpath = $basepath.$this->Settings->get('storage_folder').$new_blog_shortname.'/media/';
			$new_blog_media_url = $baseurl.$this->Settings->get('storage_folder').$new_blog_shortname.'/media/';
		}
		else
		{
			$new_blog_media_location = 'subdir';
			$new_blog_media_subdir = 'blogs/'.$new_blog_shortname.'/';
			$new_blog_media_fullpath = '';
			$new_blog_media_url = '';
		}

		// =====================================================
		// Set permissions

		// User permissions
		$new_blog_advanced_perms = 1;
		$new_blog_owner_user_ID = ( $this->Settings->get('post_now') == 'true' ) ? $new_User->ID : 1;

		// Media permissions
		switch ( $this->Settings->get('file_manager') )
		{
			case 'edit':
				$new_blog_media_perm = '1, 1, 1';
				break;

			case 'add':
				$new_blog_media_perm = '1, 1, 0';
				break;

			case 'none':
				$new_blog_media_perm = '0, 0, 0';
				break;
		}

		// =====================================================
		// Default blog params

		if( !empty($new_User->requested_blog_name) )
		{	// Use blog name requested upon reginstration
			$new_blog_name = $new_User->requested_blog_name;
		}
		else
		{	// Use the one from plugin settings
			$new_blog_name = sprintf($this->Settings->get('blog_name'), $new_User->login);
		}

		if( $this->Settings->get('use_subdom') )
		{
			$new_blog_access_type = 'subdom';
			$new_blog_siteurl = '';
		}
		$new_blog_tagline = $this->Settings->get('blog_tagline');
		$new_blog_description = $this->Settings->get('blog_description');
		$new_blog_long_description = $this->Settings->get('blog_long_description');
		$new_blog_keywords = sprintf($this->Settings->get('blog_keywords'), $new_User->login);
		$new_blog_locale = $new_User->locale;

		$new_blog_skin = $this->Settings->get('blog_skin');
		$new_blog_allowblogcss = $this->Settings->get('blog_allowblogcss');
		$new_blog_in_bloglist = $this->Settings->get('blog_in_bloglist');

		if( empty($evo_charset) )
		{	// This is needed when we create a blog upon user registration
			// At that moment $evo_charset is undefined
			// We must activate locale and define $evo_charset
			locale_activate( $new_blog_locale );
			init_charsets($GLOBALS['current_charset']);
		}

		// =====================================================
		// Creating new blog

		$edited_Blog = new Blog( NULL );

		// Set default values for new blog
		$edited_Blog->set( 'shortname', param( 'blog_shortname', 'string',$new_blog_shortname ) );
		$edited_Blog->set( 'name', param( 'blog_name', 'string', $new_blog_name ) );
		$edited_Blog->set( 'owner_user_ID', $new_blog_owner_user_ID );
		$edited_Blog->set( 'advanced_perms', $new_blog_advanced_perms );
		$edited_Blog->set( 'tagline', format_to_post( param( 'blog_tagline', 'html', $new_blog_tagline ), 0, 0 ) );
		$edited_Blog->set( 'description', param( 'blog_description', 'string', $new_blog_description ) );
		$edited_Blog->set( 'longdesc', format_to_post( param( 'blog_longdesc', 'html',$new_blog_long_description ), 0, 0 ) );
		$edited_Blog->set( 'locale', param( 'blog_locale', 'string', $new_blog_locale ) );
		$edited_Blog->set( 'access_type',  param( 'blog_access_type', 'string', $new_blog_access_type ) );
		$edited_Blog->set( 'siteurl', $new_blog_siteurl );
		$edited_Blog->set( 'urlname', param( 'blog_urlname', 'string', $new_blog_shortname ) );
		$edited_Blog->set( 'keywords', param( 'blog_keywords', 'string', $new_blog_keywords ) );

		$edited_Blog->set( 'allowblogcss', param( 'blog_allowblogcss', 'integer', $new_blog_allowblogcss ) );
		//$edited_Blog->set( 'allowusercss', param( 'blog_allowusercss', 'integer', 0 ) );
		$edited_Blog->set( 'skin_ID', $new_blog_skin );
		$edited_Blog->set( 'in_bloglist', param( 'blog_in_bloglist',   'integer', $new_blog_in_bloglist ) );

		$edited_Blog->set( 'media_location', $new_blog_media_location );
		$edited_Blog->set( 'media_subdir', $new_blog_media_subdir );
		$edited_Blog->set( 'media_fullpath', $new_blog_media_fullpath );
		$edited_Blog->set( 'media_url', $new_blog_media_url );
		$edited_Blog->set_setting( 'userblog_owner_id', $new_User->ID );

		// If clean urls are disabled set correct values for urls
		if( ! $this->Settings->get('clean_urls') )
		{
			// Set blog settings
			$edited_Blog->set_setting( 'archive_links', 'param' );
			$edited_Blog->set_setting( 'chapter_links', 'param_num' );
			$edited_Blog->set_setting( 'tag_links', 'param' );
			$edited_Blog->set_setting( 'single_links', 'param_title' );
		}

		// DB INSERT
		$edited_Blog->dbinsert();
		$new_blog_ID = $edited_Blog->get('ID');

		// Remember this user blog creation in UserSettings
		$UserSettings->set('userblog_created', $new_blog_ID, $new_User->ID);
		$UserSettings->dbupdate();

		// Create category for the new blog
		cat_create( $new_blog_cat, 'NULL', $new_blog_ID );

		// Set default user permissions for this blog (also to post immediatly if it is enabled )
		$query = "INSERT INTO T_coll_user_perms( bloguser_blog_ID, bloguser_user_ID, bloguser_ismember,
							bloguser_perm_poststatuses, bloguser_perm_edit,
							bloguser_perm_delpost, bloguser_perm_comments,
							bloguser_perm_cats, bloguser_perm_properties,
							bloguser_perm_media_upload, bloguser_perm_media_browse,
							bloguser_perm_media_change )";

		if( $new_blog_owner_user_ID != 1 )
		{
			$query.= "VALUES ( $new_blog_ID, $new_User->ID,
							1, 'published,deprecated,protected,private,draft',
							'all', 1, 1, 1, 1, $new_blog_media_perm )";
		}
		else
		{
			$query.= "VALUES ( $new_blog_ID, $new_User->ID,
							1, 0, 'no', 0, 0, 1, 1, $new_blog_media_perm )";
		}

		$DB->query( $query );

		// ADD DEFAULT WIDGETS:
		// Add public global navigation list:
		$DB->query( 'INSERT INTO T_widget( wi_coll_ID, wi_sco_name, wi_order, wi_type, wi_code )
						VALUES( '.$edited_Blog->ID.', "Page Top", 1, "core", "colls_list_public" )' );

		// Add title to all blog Headers:
		$DB->query( 'INSERT INTO T_widget( wi_coll_ID, wi_sco_name, wi_order, wi_type, wi_code )
						VALUES( '.$edited_Blog->ID.', "Header", 1, "core", "coll_title" )' );
		// Add tagline to all blogs Headers:
		$DB->query( 'INSERT INTO T_widget( wi_coll_ID, wi_sco_name, wi_order, wi_type, wi_code )
						VALUES( '.$edited_Blog->ID.', "Header", 2, "core", "coll_tagline" )' );

		// Add home link to all blogs Menus:
		$DB->query( 'INSERT INTO T_widget( wi_coll_ID, wi_sco_name, wi_order, wi_type, wi_code, wi_params )
						VALUES( '.$edited_Blog->ID.', "Menu", 1, "core", "menu_link", "'.$DB->escape(serialize(array('link_type'=>'home'))).'" )' );
		// Add info pages to all blogs Menus:
		$DB->query( 'INSERT INTO T_widget( wi_coll_ID, wi_sco_name, wi_order, wi_type, wi_code )
						VALUES( '.$edited_Blog->ID.', "Menu", 2, "core", "coll_page_list" )' );
		// Add contact link to all blogs Menus:
		$DB->query( 'INSERT INTO T_widget( wi_coll_ID, wi_sco_name, wi_order, wi_type, wi_code, wi_params )
						VALUES( '.$edited_Blog->ID.', "Menu", 3, "core", "menu_link", "'.$DB->escape(serialize(array('link_type'=>'ownercontact'))).'" )' );
		// Add login link to all blogs Menus:
		$DB->query( 'INSERT INTO T_widget( wi_coll_ID, wi_sco_name, wi_order, wi_type, wi_code, wi_params )
						VALUES( '.$edited_Blog->ID.', "Menu", 4, "core", "menu_link", "'.$DB->escape(serialize(array('link_type'=>'login'))).'" )' );

		// Add Calendar plugin to all blog Sidebars:
		$DB->query( 'INSERT INTO T_widget( wi_coll_ID, wi_sco_name, wi_order, wi_type, wi_code )
						VALUES( '.$edited_Blog->ID.', "Sidebar", 1, "plugin", "evo_Calr" )' );
		// Add title to all blog Sidebars:
		$DB->query( 'INSERT INTO T_widget( wi_coll_ID, wi_sco_name, wi_order, wi_type, wi_code )
						VALUES( '.$edited_Blog->ID.', "Sidebar", 2, "core", "coll_title" )' );
		// Add longdesc to all blogs Sidebars:
		$DB->query( 'INSERT INTO T_widget( wi_coll_ID, wi_sco_name, wi_order, wi_type, wi_code )
						VALUES( '.$edited_Blog->ID.', "Sidebar", 3, "core", "coll_longdesc" )' );
		// Add common links to all blogs Sidebars:
		$DB->query( 'INSERT INTO T_widget( wi_coll_ID, wi_sco_name, wi_order, wi_type, wi_code )
						VALUES( '.$edited_Blog->ID.', "Sidebar", 4, "core", "coll_common_links" )' );
		// Add search form to all blogs Sidebars:
		$DB->query( 'INSERT INTO T_widget( wi_coll_ID, wi_sco_name, wi_order, wi_type, wi_code )
						VALUES( '.$edited_Blog->ID.', "Sidebar", 5, "core", "coll_search_form" )' );
		// Add category links to all blog Sidebars:
		$DB->query( 'INSERT INTO T_widget( wi_coll_ID, wi_sco_name, wi_order, wi_type, wi_code )
						VALUES( '.$edited_Blog->ID.', "Sidebar", 6, "core", "coll_category_list" )' );
		// Add XML feeds to all blogs Sidebars:
		$DB->query( 'INSERT INTO T_widget( wi_coll_ID, wi_sco_name, wi_order, wi_type, wi_code )
						VALUES( '.$edited_Blog->ID.', "Sidebar", 7, "core", "coll_xml_feeds" )' );

		$DB->commit();

		// Commit changes in cache:
		$BlogCache = & get_Cache( 'BlogCache' );
		$BlogCache->add( $edited_Blog );

		// =====================================================
		// Creating Stub files

		// If stub file: eventually creates storage dir & user dir, and write stub
		if( $this->Settings->get('use_stub') != 'false' )
		{
			$users_base_dir = $basepath;
			// Set users storage directory
			if( $this->Settings->get('storage_folder'))
			{
				 $users_base_dir .= $this->Settings->get('storage_folder');
				 // Create subfolder for all users, if still not existing
				 if( ! mkdir_r( $users_base_dir ) )
				 {
				 	$Messages->add( sprintf( $this->T_('You must create the following directory with write permissions (777): %s'), $users_base_dir), 'error' );
					return;
				}
			}

			$new_blog_dir = $users_base_dir;
			// Set new user personal directory
			if( $this->Settings->get('stub_folder') == 'true' )
			{
				$new_blog_dir .= $new_blog_shortname.'/';
				// Create subfolder for new user if still not existing
				if( ! mkdir_r( $new_blog_dir ) )
				{
					$Messages->add( sprintf( $this->T_('You must create the following directory with write permissions (777): %s'), $new_blog_dir), 'error' );
					return;
				}
				$file = $new_blog_dir.'index.php';
			}
			else
			{
				$file = $new_blog_dir. $new_blog_stub;
			}

			// Common config file for stubs
			$config_file = $plugins_path.$this->classname.'/'."_userblog_config.php";
			// Content of common config file
			$config_data = "<?php\n".$this->Settings->get('userblog_config_file')."\n?>";

			// Create common config file
			if( file_exists($config_file) )
			{
				@unlink($config_file);
			}

			$file_handle = @fopen($config_file,"a");
			@fwrite($file_handle, $config_data);
			@fclose($file_handle);

			// Content of stub file
			$data = "<?php\n\$blog = ".$new_blog_ID.";\n\nrequire_once '".$config_file."';\n?>";

			// Create stub file
			if( file_exists($file) )
			{
				@unlink($file);
			}

			$file_handle = @fopen($file,"a");
			@fwrite($file_handle, $data);
			@fclose($file_handle);

			if( !is_file($file) )
			{
				$Messages->add( sprintf( $this->T_('Cannot create blog stub file [%s]<br />Make sure the directory [%s] has write permissions (777)'), $file, $new_blog_dir), 'error' );
				return;
			}
		}

		// =====================================================
		// Copy style.css in media folder

		// Copy default style.css file to user media folder if set
		if( $this->Settings->get('blog_allowblogcss') )
		{
			if( empty($new_blog_media_fullpath) )
			{
				$custom_css_dir = $basepath.'media/'.$new_blog_media_subdir;
			}
			else
			{
				$custom_css_dir = $new_blog_media_fullpath;
			}

			// Create user media folder
			mkdir_r( $custom_css_dir );

			$custom_css_file = $custom_css_dir.'style.css';

			// Create a blank style.css to override default styles
			if( !file_exists($custom_css_file) )
			{
				$file_handle = @fopen($custom_css_file,"a");

				// Content of css file
				$cssdata = "/* This custom style.css will override the default skin styles. The styles you define here will be ADDED (not replaced!) to the default styles.\nExample:\nbody { background-color:#555555 } */";

				@fwrite($file_handle, $cssdata);
				@fclose($file_handle);
			}
		}

		// =====================================================
		// Notification messages

		// Set full http url for new blog
		$new_blog_full_url = $edited_Blog->gen_blogurl();

		// Get current user group
		if( ! empty($current_User) && $Session->has_User() )
		{
			$current_User_Group = $current_User->Group->ID;
		}

		// Show messages
		if( isset($current_User_Group) && $current_User_Group == 1 )
		{	// Notify the admin
			$new_blog_msg = '['.$this->name.' '.$this->T_('Plugin').']: '.$this->T_('New user blog').
					' <a target="_blank" href="'.$new_blog_full_url.'">'.$new_blog_shortname.
					'</a> '.$this->T_('created.').' [<a href="'.$admin_url.'?ctrl=coll_settings&action=edit&blog='.$new_blog_ID.'">'.$this->T_('Blog settings').'</a>]';

			$Messages->add( $new_blog_msg, 'success' );
		}
		else
		{	// Show welcome message to the new blogger
			$new_welcome_msg = $this->Settings->get('blog_welcome_msg1').
					' <a href="'.$new_blog_full_url.'">'.$new_blog_full_url.'</a><br /><br />';
			$new_welcome_msg .= $this->Settings->get('blog_welcome_msg2').
					' <a href="'.$admin_url.'">'.$admin_url.'</a><br />';

			$Messages->add( $new_welcome_msg, 'success' );
		}

		// Send notification email to the user
		$email_subj = sprintf( $this->T_('Your personal blog "%s"'), $new_blog_shortname);
		$email_msg = $this->Settings->get('blog_welcome_msg1')."\n\n".$new_blog_full_url;
		$email_msg .= "\n\n".$this->Settings->get('blog_welcome_msg2').' '.$admin_url;
		$email_msg .= "\n\n".$app_name;

		// Check b2evo version and send email
		if( version_compare( $app_version, '2.4.9', '>=' ) )
		{	// For b2evo-2.5 and up
			send_mail( $new_User->email, $new_User->login, $email_subj, $email_msg, $notify_from );
		}
		else
		{
			send_mail( $new_User->email, $email_subj, $email_msg, $notify_from );
		}
		return $new_blog_ID;
	}

	// ==========================================================================
	// Additional functions

	// Returns user group
	function getUserGroup( $params )
	{
		$User = $params['User'];
		$group_ID = false;

		if( isset($User->group_ID) )
		{
		  $group_ID = $User->group_ID;
		}
		elseif( $User->Group )
		{
		  $group_ID = $User->Group->ID;
		}

		return $group_ID;
	}

	// Returns Userblog group ID
	function UserblogGroupID()
	{
		global $Settings;

		$UserblogGroupID = false;

		if( $Settings->get('userblog_grp_ID') )
		{
			$UserblogGroupID = $Settings->get('userblog_grp_ID');
		}
		return $UserblogGroupID;
	}
}

?>