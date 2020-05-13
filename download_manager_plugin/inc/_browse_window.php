<?php

if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * @var Settings
 */
global $Settings, $DB, $UserSettings, $Messages, $Debuglog, $Plugins;

global $ads_list_path, $inc_path, $evo_charset;
global $current_User, $adminskins_url, $adminskins_path, $admin_skin, $admin_url, $basepath, $baseurl, $io_charset;

$is_admin_page = true;
$login_required = true;

if( !is_logged_in() )
{
	echo 'You must be logged in!';
	return false;
}

// Check global permission:
if( ! $current_User->check_perm( 'admin', 'any' ) )
{	// No permission to access admin...
	require $adminskins_path.'_access_denied.main.php';
}

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

$AdminUI = new AdminUI();

memorize_param( 'plugin_ID', 'integer', '', $this->ID );
memorize_param( 'method', 'string', '', 'browse_downloads' );
$checkall = param( 'checkall', 'integer', 0 );  // Non-Javascript-CheckAll

$item_ID = param( 'item_ID', 'integer', '', true );
$fm_selected = param( 'fm_selected', 'array', array(), true );

if( !empty($fm_selected) )
{	// Generate the code
	
	$keys = array_unique($fm_selected);
	$cats = array();
	
	foreach( $keys as $lfile )
	{	// Delete file keys if their category selected
		if( preg_match( '~^cat_([0-9]+)$~', $lfile, $matches ) )
		{
			$SQL = 'SELECT file_key FROM '.$this->get_sql_table('files').'
					WHERE file_user_ID = '.$DB->quote($current_User->ID).'
					AND file_cat_ID = '.$DB->quote($matches[1]).'
					AND file_status = 1';
			
			if( $col = $DB->get_col($SQL) )
			{
				$cats[] = $matches[1];
				$keys = array_diff( $keys, $col, array( $lfile ) );
			}
		}
	}
	
	$inc_cats = $inc_keys = $desc = '';
	
	if( count($cats) > 0 )
	{
		$inc_cats = ' cats="'.implode( ', ', $cats ).'"';
	}
	
	if( count($keys) > 0 )
	{
		$inc_keys = ' files="'.implode( ', ', $keys ).'"';
	}
	
	if( !empty($inc_cats) || !empty($inc_keys) )
	{
		if( param( 'no_desc', 'bool' ) )
		{
			$desc = ' config="nodesc"';
		}
		$embed_code = format_to_output( '<!-- dl_manager:'.$desc.$inc_cats.$inc_keys.' -->', 'formvalue' );
	}
}

$limit = 10;
$body = '';

$SQL = 'SELECT * FROM '.$this->get_sql_table('files').'
		INNER JOIN '.$this->get_sql_table('cats').'
			ON file_cat_ID = cat_ID
		WHERE file_user_ID = '.$current_User->ID.'
		AND file_status = 1
		ORDER BY cat_name ASC';

$Results = new Results( $SQL, 'dl_', '' );
$Results->title = $this->T_('Files list');
$Results->group_by = 'cat_ID';

function dl_results_td_cat( $Category )
{
	global $Plugins, $checkall;
	$DLM = $Plugins->get_by_code('dl_manager');
	
	$r = '<span name="surround_check" class="checkbox_surround_init">';
	$r .= '<input title="'.$DLM->T_('Select this category').'" type="checkbox" class="checkbox"
				name="fm_selected[]" value="cat_'.$Category->cat_ID.'" id="cb_filename_cat_'.$Category->cat_ID.'"';
	
	if( $checkall )
	{
		$r .= ' checked="checked"';
	}
	$r .= ' />';
	$r .= '</span>';
	
	return $r.' <span style="color:#000">'.$Category->cat_name.'</span>';
}
$Results->grp_cols[] = array(
		'td_colspan' => 4,  // nb_cols
		'td' => '% dl_results_td_cat( {row} ) %',
	);

function dl_results_td_box( $File )
{
	global $Plugins, $checkall;
	$DLM = $Plugins->get_by_code('dl_manager');
	
	// Checkbox
	$r = '<span name="surround_check" class="checkbox_surround_init">';
	$r .= '<input title="'.$DLM->T_('Select this file').'" type="checkbox" class="checkbox"
				name="fm_selected[]" value="'.$File->file_key.'" id="cb_filename_'.$File->file_ID.'"';
	
	if( $checkall )
	{
		$r .= ' checked="checked"';
	}
	$r .= ' />';
	$r .= '</span>';
	
	return $r;
}
$Results->cols[] = array(
		'th' => '',
		'td' => '% dl_results_td_box( {row} ) %',
		'td_class' => 'checkbox firstcol shrinkwrap',
	);

function dl_results_td_icon( $File )
{
	global $Plugins;
	$DLM = $Plugins->get_by_code('dl_manager');
	
	return $DLM->get_extension_icon( $File->file_name, $File->file_type );
}
$Results->cols[] = array(
		'th' => '',
		'td' => '% dl_results_td_icon( {row} ) %',
		'td_class' => 'shrinkwrap center',
	);

function dl_results_td_name( $File )
{
	$file_title = $File->file_title;
	
	if( evo_strlen($file_title) > 55 )
	{
		$file_title = @evo_substr( $file_title, 0, 55).'&hellip;';
	}
	
	if( empty($file_title) )
	{
		$r = $File->file_name;
	}
	else
	{
		$r = '<div><b style="color:#333">'.$file_title.'</b><br /><span class="note">&rsaquo;&rsaquo; <i>'.$File->file_name.'</i></span></div>';
	}
	
	return $r;
}
$Results->cols[] = array(
		'th' => $this->T_('File'),
		'td' => '% dl_results_td_name( {row} ) %',
	);

function dl_results_td_size( $file_size )
{
	return '<span title="'.$file_size.' bytes">'.bytesreadable( $file_size ).'</span>';
}
$Results->cols[] = array(
		'th' => $this->T_('Size'),
		'td' => '% dl_results_td_size( #file_size# ) %',
		'th_class' => 'shrinkwrap',
		'td_class' => 'nowrap',
	);

// Start output
if( true )
{
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset='.$evo_charset.'" />
	<meta http-equiv="Expires" content="Sun, 15 Jan 1998 17:38:00 GMT" />
	<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
	<title>'.$this->T_('Browse downloads').'</title>
	<link rel="stylesheet" type="text/css" href="'.$adminskins_url.'chicago/rsc/css/chicago.css" />
	<style type="text/css">
	fieldset { margin:0 60px; padding:0; border:none }
	.embed { text-align:center }
	table.grouped tr.group td { color:#000 }
	.whitebox_center { margin:10px; padding:15px; background-color:#fff; border:2px #555 solid }
	</style>';
	
	require_js( 'functions.js');
	require_js( 'form_extensions.js');
	require_js( 'rollovers.js' );
	require_js( 'dynamic_select.js' );
	require_js( 'admin.js' );
	require_js( '#jquery#' );
	
	include_headlines();
	
	?>
	
	<script type="text/javascript">
	<!--
	  var allchecked = Array();
	  var idprefix;

			function toggleCheckboxes(the_form, the_elements, set_name )
			{
				if( typeof set_name == 'undefined' )
				{
					set_name = 0;
				}
				if( allchecked[set_name] ) allchecked[set_name] = false;
				else allchecked[set_name] = true;

				var elems = document.forms[the_form].elements[the_elements];
				if( !elems )
				{
					return;
				}
				var elems_cnt = (typeof(elems.length) != 'undefined') ? elems.length : 0;
				if (elems_cnt)
				{
					for (var i = 0; i < elems_cnt; i++)
					{
						elems[i].checked = allchecked[nr];
					} // end for
				}
				else
				{
					elems.checked = allchecked[nr];
				}
				setcheckallspan( set_name );
			}


		function setcheckallspan( set_name, set )
		{
			if( typeof(allchecked[set_name]) == 'undefined' || typeof(set) != 'undefined' )
			{ // init
				allchecked[set_name] = set;
			}

			if( allchecked[set_name] )
			{
				var replace = document.createTextNode('uncheck all');
			}
			else
			{
				var replace = document.createTextNode('check all');
			}

			if( document.getElementById( idprefix+'_'+String(set_name) ) )
			{
				document.getElementById( idprefix+'_'+String(set_name) ).replaceChild(replace, document.getElementById( idprefix+'_'+String(set_name) ).firstChild);
			}
			//else alert('no element with id '+idprefix+'_'+String(set_name));
		}

			function initcheckall( htmlid, init )
		{
			// initialize array
			allchecked = Array();
			idprefix = typeof(htmlid) == 'undefined' ? 'checkallspan' : htmlid;

			for( var lform = 0; lform < document.forms.length; lform++ )
			{
				for( var lelem = 0; lelem < document.forms[lform].elements.length; lelem++ )
				{
					if( document.forms[lform].elements[lelem].id.indexOf( idprefix ) == 0 )
					{
						var index = document.forms[lform].elements[lelem].name.substring( idprefix.length+2, document.forms[lform].elements[lelem].name.length );
						if( document.getElementById( idprefix+'_state_'+String(index)) )
						{
							setcheckallspan( index, document.getElementById( idprefix+'_state_'+String(index)).checked );
						}
						else
						{
							setcheckallspan( index, init );
						}
					}
				}
			}
		}
		//-->
	</script>
	
	<?php
	
	echo '</head><body>';
	
	$Form = new Form( regenerate_url( 'fm_selected,checkall,no_desc', '', '', '&' ), 'BrowseItems', 'post' );
	$Form->begin_form();
	
	if( !empty($embed_code) )
	{
		echo '<div class="action_messages center"><div class="log_success">'.$embed_code.'</div>';
		echo '<a href="#" onclick="if( window.focus && window.opener ){ window.opener.focus(); textarea_wrap_selection( window.opener.document.getElementById(\'itemform_post_content\'), \''.$embed_code.'\', \'\', 1, window.opener.document ); } return false;">'.$this->T_('Add the code to your post !').'</a></div>';
	}
	
	echo '<div class="whitebox_center">';
	echo '<div class="notes" style="margin-bottom:5px">'.$this->T_('Select files and/or categories and click "Generate the code" to get the embedding code for your post.').'</div>';
	echo '<div style="height:380px; overflow-y:scroll">';
	
	$Results->display( $AdminUI->get_template('Results') );
	
	echo '</div>';
	echo '<div class="notes" style="margin-top:5px">'.$Form->check_all().' '.$this->T_('Check/Uncheck all').' | <input name="no_desc" value="1" type="checkbox"> '.$this->T_('Do not include file description').'</div>';
	echo '</div>';
	
	$Form->end_form( array( array( 'submit', 'submit', $this->T_('Generate the code') ) ) );
	
	// Include JS
	?>
	<script type="text/javascript">
		<!--
		/**
		 * Check if files are selected.
		 *
		 * This should be used as "onclick" handler for "With selected" actions (onclick="return check_if_selected_files();").
		 * @return boolean true, if something is selected, false if not.
		 */
		function check_if_selected_files()
		{
			elems = document.getElementsByName( 'fm_selected[]' );
			var checked = 0;
			for( i = 0; i < elems.length; i++ )
			{
				if( elems[i].checked )
				{
					checked++;
				}
			}
			if( !checked )
			{
				alert( '<?php echo TS_('Nothing selected.') ?>' );
				return false;
			}
			else
			{
				return true;
			}
		}
		// -->
	</script>
	<?php
	
	echo '<br /><hr /><div style="padding:0 10px; font-size:12px"><a href="'.$this->help_url.'">'.$this->name.' plugin</a></div></body></html>';
}

debug_info(); // output debug info if requested

?>