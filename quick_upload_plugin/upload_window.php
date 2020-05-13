<?php
/**
 * This file implements the Quick Upload plugin for {@link http://b2evolution.net/}.
 * @version 0.4
 *
 * @copyright (c)2003-2008 by Francois PLANQUE - {@link http://fplanque.net/}
 * Parts of this file are copyright (c)2004-2006 by Daniel HAHLER - {@link http://thequod.de/contact}.
 *
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author blueyed: Daniel HAHLER.
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id: admin.php,v 1.28 2008/01/23 12:51:21 fplanque Exp $
 * @version $Id: upload.ctrl.php,v 1.6 2008/02/04 13:57:50 fplanque Exp $
 * @version $Id: _file_upload.view.php,v 1.7 2008/01/21 09:35:30 fplanque Exp $
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * @var Settings
 */
global $Settings, $DB, $UserSettings, $Messages, $Debuglog, $Plugins;

global $upload_quickmode, $failedFiles, $ads_list_path, $inc_path, $action, $ctrl, $app_version;
global $fm_FileRoot, $current_User, $adminskins_path, $admin_skin, $admin_url, $basepath, $baseurl, $io_charset;

/**
 * Do the MAIN initializations:
 */
require_once $basepath.'/conf/_config.php';


/**
 * @global boolean Is this an admin page? Use {@link is_admin_page()} to query it, because it may change.
 */
$is_admin_page = true;


$login_required = true;
require_once $inc_path.'_main.inc.php';

if( !is_logged_in() )
	return;

// Check global permission:
if( ! $current_User->check_perm( 'admin', 'any' ) )
{	// No permission to access admin...
	require $adminskins_path.'_access_denied.main.php';
}

/*
 * Asynchronous processing options that may be required on any page
 */
require_once $inc_path.'_async.inc.php';


// User specific settings:
load_class('plugins/model/_pluginusersettings.class.php');
$PluginUserSettings = & new PluginUserSettings( get_param('plugin_ID') );

// bookmarklet, upload (upload actually means sth like: select img for post):
param( 'mode', 'string', '', true );


/*
 * Get the Admin skin
 * TODO: Allow setting through GET param (dropdown in backoffice), respecting a checkbox "Use different setting on each computer" (if cookie_state handling is ready)
 */
$admin_skin = $UserSettings->get( 'admin_skin' );
$admin_skin_path = $adminskins_path.'%s/_adminUI.class.php';

if( ! $admin_skin || ! file_exists( sprintf( $admin_skin_path, $admin_skin ) ) )
{ // there's no skin for the user
	if( !$admin_skin )
	{
		$Debuglog->add( 'The user has no admin skin set.', 'skin' );
	}
	else
	{
		$Debuglog->add( 'The admin skin ['.$admin_skin.'] set by the user does not exist.', 'skin' );
	}

	$admin_skin = $Settings->get( 'admin_skin' );

	if( !$admin_skin || !file_exists( sprintf( $admin_skin_path, $admin_skin ) ) )
	{ // even the default skin does not exist!
		if( !$admin_skin )
		{
			$Debuglog->add( 'There is no default admin skin set!', 'skin' );
		}
		else
		{
			$Debuglog->add( 'The default admin skin ['.$admin_skin.'] does not exist!', array('skin','error') );
		}

		if( file_exists(sprintf( $admin_skin_path, 'chicago' )) )
		{ // 'legacy' does exist
			$admin_skin = 'chicago';

			$Debuglog->add( 'Falling back to legacy admin skin.', 'skin' );
		}
		else
		{ // get the first one available one
			$admin_skin_dirs = get_admin_skins();

			if( $admin_skin_dirs === false )
			{
				$Debuglog->add( 'No admin skin found! Check that the path '.$adminskins_path.' exists.', array('skin','error') );
			}
			elseif( empty($admin_skin_dirs) )
			{ // No admin skin directories found
				$Debuglog->add( 'No admin skin found! Check that there are skins in '.$adminskins_path.'.', array('skin','error') );
			}
			else
			{
				$admin_skin = array_shift($admin_skin_dirs);
				$Debuglog->add( 'Falling back to first available skin.', 'skin' );
			}
		}
	}
}
if( ! $admin_skin )
{
	$Debuglog->display( 'No admin skin available!', '', true, 'skin' );
	exit();
}

$Debuglog->add( 'Using admin skin &laquo;'.$admin_skin.'&raquo;', 'skin' );


/**
 * Load the AdminUI class for the skin.
 */
require_once $adminskins_path.$admin_skin.'/_adminUI.class.php';

$AdminUI = & new AdminUI();

load_class('files/model/_filelist.class.php');

// Check global access permissions:
if( ! $Settings->get( 'fm_enabled' ) )
{
	bad_request_die( 'The filemanager is disabled.' );
}

// Check permission:
$current_User->check_perm( 'files', 'add', true );

// Params that may need to be passed through:
$fm_mode = param( 'fm_mode', 'string', NULL, true );
$item_ID = param( 'item_ID', 'integer', NULL, true );

$action = param_action();

// INIT params:
if( param( 'root_and_path', 'string', '', false ) /* not memorized (default) */ && strpos( param( 'root_and_path', 'string' ), '::' ) )
{ // root and path together: decode and override (used by "radio-click-dirtree")
	$root_and_path = param( 'root_and_path', 'string' );
	
	list( $root, $path ) = explode( '::', $root_and_path, 2 );	
	// Memorize new root:
	memorize_param( 'root', 'string', NULL );
	memorize_param( 'path', 'string', NULL );
	
	param( 'root', 'string' );
	param( 'path', 'string' );
	
	//echo "$root<br>$path<br>---<br>".param( 'root', 'string')."<br>".param( 'path', 'string');
}
else
{
	param( 'root', 'string', NULL, true ); // the root directory from the dropdown box (user_X or blog_X; X is ID - 'user' for current user (default))
	param( 'path', 'string', '/', true );  // the path relative to the root dir
	
	$root = param( 'root', 'string');
	$path = param( 'path', 'string');
	
	if( param( 'new_root', 'string', '' )
		&& param( 'new_root', 'string' ) != $root )
	{ // We have changed root in the select list
		$root = param( 'new_root', 'string' );
		$path = '';
	}
}


// Get root:
$ads_list_path = false; // false by default, gets set if we have a valid root
/**
 * @var FileRoot
 */
$fm_FileRoot = NULL;

$FileRootCache = & get_Cache( 'FileRootCache' );
$available_Roots = $FileRootCache->get_available_FileRoots();

if( ! empty($root) )
{ // We have requested a root folder by string:
	$fm_FileRoot = & $FileRootCache->get_by_ID($root, true);

	if( ! $fm_FileRoot || ! isset( $available_Roots[$fm_FileRoot->ID] ) )
	{ // Root not found or not in list of available ones
		$Messages->add( $this->T_('You don\'t have access to the requested root directory.'), 'error' );
		$fm_FileRoot = false;
	}
}

if( ! $fm_FileRoot )
{ // No root requested (or the requested is invalid), get the first one available:
	if( $available_Roots
	    && ( $tmp_keys = array_keys( $available_Roots ) )
	    && $first_Root = & $available_Roots[ $tmp_keys[0] ] )
	{ // get the first one
		$fm_FileRoot = & $first_Root;
	}
	else
	{
		$Messages->add( $this->T_('You don\'t have access to any root directory.'), 'error' );
	}
}

if( $fm_FileRoot )
{ // We have access to a file root:
	if( empty($fm_FileRoot->ads_path) )
	{	// Not sure it's possible to get this far, but just in case...
		$Messages->add( sprintf( $this->T_('The root directory &laquo;%s&raquo; does not exist.'), $fm_FileRoot->ads_path ), 'error' );
	}
	else
	{ // Root exists
		// Let's get into requested list dir...
		$non_canonical_list_path = $fm_FileRoot->ads_path;//.$path;

		// Dereference any /../ just to make sure, and CHECK if directory exists:
		$ads_list_path = get_canonical_path( $non_canonical_list_path );

		if( !is_dir( $ads_list_path ) )
		{ // This should never happen, but just in case the diretory does not exist:
			$Messages->add( sprintf( $this->T_('The directory &laquo;%s&raquo; does not exist.'), $path ), 'error' );
			$path = '';		// fp> added
			$ads_list_path = NULL;
		}
		elseif( ! preg_match( '#^'.preg_quote($fm_FileRoot->ads_path, '#').'#', $ads_list_path ) )
		{ // cwd is OUTSIDE OF root!
			$Messages->add( $this->T_( 'You are not allowed to go outside your root directory!' ), 'error' );
			$path = '';		// fp> added
			$ads_list_path = $fm_FileRoot->ads_path;
		}
		elseif( $ads_list_path != $non_canonical_list_path )
		{	// We have reduced the absolute path, we should also reduce the relative $path (used in urls params)
			$path = get_canonical_path( $path );
		}
	}
}

// If there were errors, display them and exit (especially in case there's no valid FileRoot ($fm_FileRoot)):
// TODO: dh> this prevents users from uploading if _any_ blog media directory is not writable.
//           See http://forums.b2evolution.net/viewtopic.php?p=49001#49001
if( $Messages->count('error') )
{
	// Display <html><head>...</head> section! (Note: should be done early if actions do not redirect)
	$AdminUI->disp_html_head();

	// Display title, menu, messages, etc. (Note: messages MUST be displayed AFTER the actions)
	$AdminUI->disp_body_top();
	$AdminUI->disp_payload_begin();
	$AdminUI->disp_payload_end();

	$AdminUI->disp_global_footer();
	exit();
}

if( empty($ads_list_path) )
{ // We have no Root / list path, there was an error. Unset any action.
	$action = '';
}

// Check permissions:
if( ! $Settings->get('upload_enabled') )
{ // Upload is globally disabled
	$Messages->add( $this->T_('Upload is disabled.'), 'error' );
}

if( ! $current_User->check_perm( 'files', 'add' ) )
{ // We do not have permission to add files
	$Messages->add( $this->T_('You have no permission to add/upload files.'), 'error' );
}


// If there were errors, display them and exit
if( $Messages->count('error') )
{
	$AdminUI->disp_html_head();
	// Display title, menu, messages, etc. (Note: messages MUST be displayed AFTER the actions)
	$AdminUI->disp_body_top();
	$AdminUI->disp_global_footer();
	exit();
}


// Quick mode means "just upload and leave mode when successful"
param( 'upload_quickmode', 'integer', 0 );
$upload_quickmode = param( 'upload_quickmode', 'integer' );

/**
 * Remember failed files (and the error messages)
 * @var array
 */
$failedFiles = array();

// Process uploaded files:
if( isset($_FILES) && count( $_FILES ) )
{ // Some files have been uploaded:
	param( 'uploadfile_title', 'array', array() );
	param( 'uploadfile_alt', 'array', array() );
	param( 'uploadfile_desc', 'array', array() );
	param( 'uploadfile_name', 'array', array() );
	
	$saved_messages = NULL;
	
	foreach( $_FILES['uploadfile']['name'] as $lKey => $lName )
	{
		if( empty( $lName ) )
		{ // No file name
			if( $upload_quickmode
				 || !empty( $uploadfile_title[$lKey] )
				 || !empty( $uploadfile_alt[$lKey] )
				 || !empty( $uploadfile_desc[$lKey] )
				 || !empty( $uploadfile_name[$lKey] ) )
			{ // User specified params but NO file!!!
				// Remember the file as failed when additional info provided.
				$failedFiles[$lKey] = $this->T_( 'Please select a local file to upload.' );
			}
			// Abort upload for this file:
			continue;
		}

		if( $Settings->get( 'upload_maxkb' )
				&& $_FILES['uploadfile']['size'][$lKey] > $Settings->get( 'upload_maxkb' )*1024 )
		{ // bigger than defined by blog
			$failedFiles[$lKey] = sprintf(
					/* TRANS: %s will be replaced by the difference */ $this->T_('The file is too large: %s but the maximum allowed is %s.'),
					bytesreadable( $_FILES['uploadfile']['size'][$lKey] ),
					bytesreadable($Settings->get( 'upload_maxkb' )*1024) );
			// Abort upload for this file:
			continue;
		}

		if( $_FILES['uploadfile']['error'][$lKey] )
		{ // PHP has detected an error!:
			switch( $_FILES['uploadfile']['error'][$lKey] )
			{
				case UPLOAD_ERR_FORM_SIZE:
					// The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the html form.

					// This can easily be changed, so we do not use it.. file size gets checked for real just above.
					break;

				case UPLOAD_ERR_INI_SIZE: // bigger than allowed in php.ini
					$failedFiles[$lKey] = $this->T_('The file exceeds the upload_max_filesize directive in php.ini.');
					// Abort upload for this file:
					continue;

				case UPLOAD_ERR_PARTIAL:
					$failedFiles[$lKey] = $this->T_('The file was only partially uploaded.');
					// Abort upload for this file:
					continue;

				case UPLOAD_ERR_NO_FILE:
					// Is probably the same as empty($lName) before.
					$failedFiles[$lKey] = $this->T_('No file was uploaded.');
					// Abort upload for this file:
					continue;

				case 6: // numerical value of UPLOAD_ERR_NO_TMP_DIR
				# (min_php: 4.3.10, 5.0.3) case UPLOAD_ERR_NO_TMP_DIR:
					// Missing a temporary folder.
					$failedFiles[$lKey] = $this->T_('Missing a temporary folder (upload_tmp_dir in php.ini).');
					// Abort upload for this file:
					continue;

				default:
					$failedFiles[$lKey] = $this->T_('Unknown error.').' #'.$_FILES['uploadfile']['error'][$lKey];
					// Abort upload for this file:
					continue;
			}
		}

		if( !is_uploaded_file( $_FILES['uploadfile']['tmp_name'][$lKey] ) )
		{ // Ensure that a malicious user hasn't tried to trick the script into working on files upon which it should not be working.
			$failedFiles[$lKey] = $this->T_('The file does not seem to be a valid upload! It may exceed the upload_max_filesize directive in php.ini.');
			// Abort upload for this file:
			continue;
		}

		// Use new name on server if specified:
		$newName = !empty( $uploadfile_name[ $lKey ] ) ? $uploadfile_name[ $lKey ] : $lName;

		if( $error_filename = validate_filename( $newName ) )
		{ // Not a file name or not an allowed extension
			$failedFiles[$lKey] = $error_filename;
			// Abort upload for this file:
			continue;
		}
		
		$rel_path = trailing_slash($path);
		if( $this->Settings->get('create_subdir') )
		{	// Create item subdir if not exists
			$rel_path2 = $this->Settings->get('subdir_prefix').$item_ID.'/';
			if( mkdir_r( $ads_list_path.$rel_path2 ) )
			{	// Drectory created or already exists, let's use it
				$rel_path = $rel_path2;
			}
			else
			{
				$failedFiles[$lKey] = sprintf( $this->T_('Unable to create directory: [ %s ]'), $ads_list_path.$rel_path2 );
			}
		}

		// Get File object for requested target location:
		$FileCache = & get_Cache( 'FileCache' );
		$newFile = & $FileCache->get_by_root_and_path( $fm_FileRoot->type, $fm_FileRoot->in_type_ID, $rel_path.$newName, true );

		if( $newFile->exists() )
		{ // The file already exists in the target location!
			$failedFiles[$lKey] = sprintf( $this->T_('The file &laquo;%s&raquo; already exists.'), $newFile->dget('name') );
			// Abort upload for this file:
			continue;
		}

		// Attempt to move the uploaded file to the requested target location:
		if( !move_uploaded_file( $_FILES['uploadfile']['tmp_name'][$lKey], $newFile->get_full_path() ) )
		{
			$failedFiles[$lKey] = $this->T_('An unknown error occurred when moving the uploaded file on the server.');
			// Abort upload for this file:
			continue;
		}

		// change to default chmod settings
		if( $newFile->chmod( NULL ) === false )
		{ // add a note, this is no error!
			$Messages->add( sprintf( $this->T_('Could not change permissions of &laquo;%s&raquo; to default chmod setting.'), $newFile->dget('name') ), 'note' );
		}

		// Refreshes file properties (type, size, perms...)
		$newFile->load_properties();

		// Store extra info about the file into File Object:
		if( isset( $uploadfile_title[$lKey] ) )
		{ // If a title text has been passed... (does not happen in quick upload mode)
			$newFile->set( 'title', trim( strip_tags($uploadfile_title[$lKey])) );
		}
		if( isset( $uploadfile_alt[$lKey] ) )
		{ // If an alt text has been passed... (does not happen in quick upload mode)
			$newFile->set( 'alt', trim( strip_tags($uploadfile_alt[$lKey])) );
		}
		if( isset( $uploadfile_desc[$lKey] ) )
		{ // If a desc text has been passed... (does not happen in quick upload mode)
			$newFile->set( 'desc', trim( strip_tags($uploadfile_desc[$lKey])) );
		}

		$success_msg = sprintf( $this->T_('The file &laquo;%s&raquo; has been successfully uploaded.'), $newFile->dget('name') );
		if( get_param('mode') == 'upload' )
		{	// TODO: Add plugin hook to allow generating JS insert code(s)
			$img_tag = format_to_output( $newFile->get_tag(), 'formvalue' );
			$success_msg .= '<ul>'
					.'<li><input type="text" value="'.$img_tag.'" size="50" /></li>'
					.'<li><a href="#" onclick="if( window.focus && window.opener ){ window.opener.focus(); textarea_wrap_selection( window.opener.document.getElementById(\'itemform_post_content\'), \''.$img_tag.'\', \'\', 1, window.opener.document ); } return false;">'.$this->T_('Add the code to your post !').'</a></li>';
				
			
			/***************  Link ("chain") icon:  **************/
			
			if( !empty($item_ID) )
			{	// Offer option to link the file to an Item (or anything else):
				$link_attribs = array();
				$selected_url = '<a href="'.regenerate_url( '', 'plugin_ID='.get_param('plugin_ID').
							'&amp;method=quick_upload&amp;action=link_quickupload&amp;fm_selected[]='.
							rawurlencode($newFile->get_rdfp_rel_path()) ).'">'.$this->T_('Link this file!').'</a>';
				
				if ( version_compare( $app_version, '2.4.9', '>=' ) )
				{	// For b2evo-2.5 and up
					$link_attribs['target'] = 'attachmentframe';
					$selected_url = action_icon( $this->T_('Link this file!'), 'link',
							$admin_url.'?mode=upload&amp;ctrl=files&amp;root='.get_param('root').
							'&amp;fm_mode=link_item&amp;item_ID='.$item_ID.
							'&amp;action=link_inpost&amp;fm_selected[]='.
							rawurlencode($newFile->get_rdfp_rel_path()), $this->T_('Link this file!'), NULL, 5, $link_attribs );
				}
				
				$success_msg .= '<li style="margin-top:7px">'.$selected_url.'</li>';
			}
			$success_msg .= '</ul>';
		}
		$saved_messages .= $success_msg;
		
		$Messages->add( $success_msg, 'success' );

		// Store File object into DB:
		$newFile->dbsave();

	}
	
	if ( ( version_compare( $app_version, '2.4.9' ) < 0 ) && !empty($saved_messages) )
	{
		$PluginUserSettings->set( 'saved_messages', $saved_messages );
		$PluginUserSettings->dbupdate();
	}
	
	if( $upload_quickmode && !empty($failedFiles) )
	{	// Transmit file error to next page!
		$Messages->add( $failedFiles[0], 'error' );
		unset($failedFiles);
	}
}
elseif( $action == 'link_quickupload' )
{
	// Link files in b2evo - 2.0/2.4
	// Still testing
	if ( version_compare( $app_version, '2.4.9' ) < 0 )
	{
		/*
		 * Load linkable objects:
		 */
		if( param( 'item_ID', 'integer', NULL, true, false, false ) )
		{ // Load Requested iem:
			$ItemCache = & get_Cache( 'ItemCache' );
			if( ($edited_Item = & $ItemCache->get_by_ID( $item_ID, false )) === false )
			{	// We could not find the contact to link:
				$Messages->head = $this->T_('Cannot link Item!');
				$Messages->add( $this->T_('Requested item does not exist any longer.'), 'error' );
				unset( $edited_Item );
				forget_param( 'item_ID' );
				unset( $item_ID );
			}
		}
		
		if( isset($edited_Item) )
		{
			/**
			 * A list of filepaths which are selected in the FM list.
			 *
			 * @todo fp> This could probably be further simpplified by using "fm_sources" for selections.
			 * Note: fm_sources is better because it also handles sources/selections on a different fileroot
			 *
			 * @global array
			 */
			$fm_selected = param( 'fm_selected', 'array', array(), true );
			$Debuglog->add( count($fm_selected).' selected files/directories', 'files' );
			/**
			 * The selected files (must be within current fileroot)
			 *
			 * @global Filelist
			 */
			$selected_Filelist = & new Filelist( $fm_FileRoot, false );
			foreach( $fm_selected as $l_source_path )
			{
				// echo '<br>'.$l_source_path;
				$selected_Filelist->add_by_subpath( urldecode($l_source_path), true );
			}
			
			if( !$selected_Filelist->count() )
			{
				$Messages->add( $this->T_('Nothing selected.'), 'error' );
			}
			
			
			$edit_File = & $selected_Filelist->get_by_idx(0);

			// check item EDIT permissions:
			$current_User->check_perm( 'item', 'edit', true, $edited_Item );
			
			if( !$Messages->count('error') )
			{
				$DB->begin();
	
				// Load meta data AND MAKE SURE IT IS CREATED IN DB:
				$edit_File->load_meta( true );
	
				// Let's make the link!
				$edited_Link = & new Link();
				$edited_Link->set( 'itm_ID', $edited_Item->ID );
				$edited_Link->set( 'file_ID', $edit_File->ID );
				$edited_Link->dbinsert();
				
				$DB->commit();
				
				$Messages->add( $this->T_('Selected file has been linked to item.'), 'success' );
			}
						
			// Restore saved messages
			if( $PluginUserSettings->get('saved_messages') )
				$Messages->add( $PluginUserSettings->get('saved_messages'), 'success' );
			
			// In case the mode had been closed, reopen it:
			$fm_mode = 'upload';
		}
	}
}


// ==========================================================
// Start output

header( 'Content-type: text/html; charset='.$io_charset );

echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xml:lang="'.$current_User->locale.'" lang="'.$current_User->locale.'">
<head>
<title>b2evo &rsaquo; '.$this->T_('Quick upload').'</title>
<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
<link rel="stylesheet" type="text/css" href="'.$baseurl.'skins_adm/chicago/rsc/css/chicago.css" />
<style type="text/css">
div.footer { display:none }
div.skin_wrapper_loggedin { margin-top:0 }
</style>
<script type="text/javascript">'."
		// Paths used by JS functions:
		var imgpath_expand = '".$baseurl."rsc/icons/expand.gif';
		var imgpath_collapse = '".$baseurl."rsc/icons/collapse.gif';
		var htsrv_url = '".$baseurl."htsrv/';".'
</script>
<script type="text/javascript" src="'.$baseurl.'rsc/js/functions.js"></script>
<script type="text/javascript" src="'.$baseurl.'rsc/js/form_extensions.js"></script>
<script type="text/javascript" src="'.$baseurl.'rsc/js/anchorposition.js"></script>
<script type="text/javascript" src="'.$baseurl.'rsc/js/date.js"></script>
<script type="text/javascript" src="'.$baseurl.'rsc/js/popupwindow.js"></script>
<script type="text/javascript" src="'.$baseurl.'rsc/js/calendarpopup.js"></script>
<script type="text/javascript" src="'.$baseurl.'rsc/js/rollovers.js"></script>
<script type="text/javascript" src="'.$baseurl.'rsc/js/extracats.js"></script>
<script type="text/javascript" src="'.$baseurl.'rsc/js/dynamic_select.js"></script>
<script type="text/javascript" src="'.$baseurl.'rsc/js/admin.js"></script>
<script type="text/javascript" src="'.$baseurl.'rsc/js/jquery.min.js"></script>
<script type="text/javascript" src="'.$baseurl.'rsc/js/bozo_validator.js"></script>
</head>';

/**
 * Dsiplay the top of the HTML <body>...
 *
 * Typically includes title, menu, messages, etc.
 */
global $Hit;

// #body_win and .body_firefox (for example) can be used to customize CSS per plaform/browser
echo '<body id="body_'.$Hit->agent_platform.'" class="body_'.$Hit->agent_name.'">'."\n";
echo '<div id="skin_wrapper" class="skin_wrapper_loggedin">';

echo $AdminUI->get_body_top();

?>

<script type="text/javascript">
	<!--
	/**
	 * Mighty cool function to append an input or textarea element onto another element.
	 *
	 * @usedby addAnotherFileInput()
	 */
	function appendLabelAndInputElements( appendTo, labelText, labelBr, inputOrTextarea, inputName,
	                                      inputSizeOrCols, inputMaxLengthOrRows, inputType, inputClass )
	{
		// LABEL:

		// var fileDivLabel = document.createElement("div");
		// fileDivLabel.className = "label";

		var fileLabel = document.createElement('label');
		var fileLabelText = document.createTextNode( labelText );
		fileLabel.appendChild( fileLabelText );

		// fileDivLabel.appendChild( fileLabel )

		appendTo.appendChild( fileLabel );

		if( labelBr )
		{ // We want a BR after the label:
			appendTo.appendChild( document.createElement('br') );
		}
		else
		{
			appendTo.appendChild( document.createTextNode( ' ' ) );
		}

		// INPUT:

		// var fileDivInput = document.createElement("div");
		// fileDivInput.className = "input";

		var fileInput = document.createElement( inputOrTextarea );
		fileInput.name = inputName;
		if( inputOrTextarea == "input" )
		{
			fileInput.type = typeof( inputType ) !== 'undefined' ?
												inputType :
												"text";
			fileInput.size = inputSizeOrCols;
			if( typeof( inputMaxLengthOrRows ) != 'undefined' )
			{
				fileInput.maxlength = inputMaxLengthOrRows;
			}
		}
		else
		{
			fileInput.cols = inputSizeOrCols;
			fileInput.rows = inputMaxLengthOrRows;
		}

		fileInput.className = inputClass;

		// fileDivInput.appendChild( fileInput );

		appendTo.appendChild( fileInput );
		appendTo.appendChild( document.createElement('br') );
	}


	/**
	 * Add a new fileinput area to the upload form.
	 */
	function addAnotherFileInput()
	{
		var uploadfiles = document.getElementById("uploadfileinputs");
		var newLI = document.createElement("li");
		var closeLink = document.createElement("a");
		var closeImage = document.createElement("img");

		uploadfiles.appendChild( newLI );
		newLI.appendChild( closeLink );
		closeLink.appendChild( closeImage );


		newLI.className = "clear";

		closeImage.src = "<?php echo get_icon( 'close', 'url' ) ?>";
		closeImage.alt = "<?php echo get_icon( 'close', 'alt' ) ?>";

		<?php
		$icon_class = get_icon( 'close', 'class' );
		if( $icon_class )
		{
			?>
			closeImage.className = '<?php echo $icon_class ?>';
			<?php
		}

		if( get_icon( 'close', 'rollover' ) )
		{ // handle rollover images ('close' by default is one).
			?>
			closeLink.className = 'rollover';
			if( typeof setupRollovers == 'function' )
			{
				setupRollovers();
			}
			<?php
		}
		?>
		closeImage.setAttribute( 'onclick', "document.getElementById('uploadfileinputs').removeChild(this.parentNode.parentNode);" ); // TODO: setting onclick this way DOES NOT work in IE. (try attachEvent then)
		closeLink.style.cssFloat = 'right';		// standard (not working in IE)
		closeLink.style.styleFloat = 'right'; // IE

		appendLabelAndInputElements( newLI, '<?php echo TS_('Choose a file'); ?>:', false, 'input', 'uploadfile[]', '20', '0', 'file', 'upload_file' );
	}
	// -->
</script>

<?php
	// Begin payload block:
	$AdminUI->disp_payload_begin();
	
	$Quick_upload = & $Plugins->get_by_code( 'quick_upload' );
	
	$Form = & new Form( str_replace( '&amp;', '&', $Quick_upload->get_htsrv_url( 'quick_upload' ) ),
				'fm_upload_checkchanges', 'post', 'none', 'multipart/form-data' );
	
	$Form->begin_form( 'fform', $this->T_('Quick upload') );
	$Form->hidden_ctrl();
	$Form->hidden( 'MAX_FILE_SIZE', $Settings->get( 'upload_maxkb' )*1024 );
	$Form->hidden( 'upload_quickmode', $upload_quickmode );
	$Form->hiddens_by_key( get_memorized() );
		
	if ( isset($_GET['root']) )
	{	
		$Form->hidden( 'root', $_GET['root'] );
		$Form->hidden( 'path', NULL );
	}	
	if ( isset($root_and_path) )
	{
		$Form->hidden( 'root_and_path', $root_and_path );
	}
	$quick_path = $path;
	if ( $path == '/' ) $quick_path = '';
	
?>

<table id="fm_browser" cellspacing="0" cellpadding="0">
  <tbody>
	<tr>
	  <?php
	  if( isset($_GET['roots_select']) && $_GET['roots_select'] == '1' )
	  {
		  echo '<td id="fm_dirtree">';
		  // Version with all roots displayed
		  echo quick_directory_tree( NULL, NULL, $ads_list_path.$quick_path, true );
		  echo '</td>';
	  }			
	  echo '<td id="fm_files">';
	  if( count( $failedFiles ) )
	  {
		  echo '<p class="error">'.$this->T_('Some file uploads failed. Please check the errors below.').'</p>';
	  }
	  ?>

	  <ul id="uploadfileinputs">
		<?php
        if( empty($failedFiles) )
        { // No failed failes, display 2 empty input blocks:
            $displayFiles = array( NULL, NULL );
        }
        else
        { // Display failed files:
            $displayFiles = & $failedFiles;
        }

        foreach( $displayFiles as $lKey => $lMessage )
        { // For each file upload block to display:
            if( $lMessage !== NULL )
            { // This is a failed upload:
                echo '<li class="invalid" title="'.$this->T_('Invalid submission.').'">';
                echo '<p class="error">'.$lMessage.'</p>';
            }
            else
            { // Not a failed upload, display normal block:
                echo '<li>';
            }
            ?>
            <label><?php echo $this->T_('Choose a file'); ?>:</label>
            <input name="uploadfile[]" size="20" type="file" class="upload_file" /><br />
            <?php
            echo '</li>';
        }
        ?>
	  </ul>

	  <p class="uploadfileinputs"><a href="#" onclick="addAnotherFileInput(); return false;" class="small"><?php echo $this->T_('Add another file'); ?></a></p>

	  <div class="upload_foot">
        <input type="submit" value="<?php echo format_to_output( $this->T_('Upload to server now'), 'formvalue' ); ?>" class="ActionButton" >
        <input type="reset" value="<?php echo format_to_output( $this->T_('Reset'), 'formvalue' ); ?>" class="ResetButton">
	  </div>
	  </td>
	</tr>
 </tbody>
</table>

<?php

$Form->end_form();
// End payload block:
$AdminUI->disp_payload_end();
// Display body bottom, debug info and close </html>:
$AdminUI->disp_global_footer();

?>