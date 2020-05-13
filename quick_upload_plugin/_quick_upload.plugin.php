<?php
/**
 *
 * This file implements the Quick Upload plugin for {@link http://b2evolution.net/}.
 *
 * @author sam2kb: Russian b2evolution - {@link http://ru.b2evo.net/}
 * @copyright (c)2009 by Russian b2evolution - {@link http://ru.b2evo.net/}.
 *
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class quick_upload_plugin extends Plugin
{
	/**
	 * Variables below MUST be overriden by plugin implementations,
	 * either in the subclass declaration or in the subclass constructor.
	 */
	var $name = 'Quick Upload';
	/**
	 * Code, if this is a renderer or pingback plugin.
	 */
	var $code = 'quick_upload';
	var $priority = 30;
	var $version = '0.4';
	var $group = 'ru.b2evo.net';
	var $author = 'Russian b2evolution';
	var $author_url = 'http://ru.b2evo.net';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=15569';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;


	/**
	 * Init
	 *
	 * This gets called after a plugin has been registered/instantiated.
	 */
	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Add a quick upload button');
		$this->long_desc = $this->T_('Add a quick upload button for fast and easy image uploading.');
	}


	/**
	 * Define settings that the plugin uses/provides.
	 */
	function GetDefaultSettings()
	{
		return array(
				'roots_select' => array(
					'label' => $this->T_('Enable directory select'),
					'defaultvalue' => '0',
					'type' => 'checkbox',
					'note' => $this->T_('Check this if you want to display a directory tree in Quick Upload window. This will show a list of all avaliable directories, might be slow.').'<br />'.$this->T_('Note: If disabled files get uploaded in blog\'s media folder.'),
				),
				'create_subdir' => array(
					'label' => $this->T_('Create subdirectories'),
					'defaultvalue' => '0',
					'type' => 'checkbox',
					'note' => $this->T_('Check this to automaticaly create sub directories in blog\'s media folder for each item to store attached files.').'<br />'.$this->T_('Example: each item(post) will have its own folder e.g. <b>/media/blog_a/item_23/</b>. You can select the name prefix below.'),
				),
				'subdir_prefix' => array(
					'label' => $this->T_('Directory name prefix'),
					'defaultvalue' => 'item_',
					'note' => $this->T_('An optional prefix to be added to the directory name.'),
					'valid_pattern' => array( 'pattern' => '~^[a-zA-Z0-9\-_]*$~', 'error' => $this->T_( 'Please enter a valid directory name prefix.' ) ),
				),
			);
	}


	function GetHtsrvMethods()
	{
		return array( 'quick_upload' );
	}



	function htsrv_quick_upload( $params )
	{
		include dirname(__FILE__).'/upload_window.php';
	}


	/**
	 * Event handler: Called when displaying editor buttons.
	 *
	 * @return boolean did we display a button?
	 */
	function AdminDisplayEditorButton( $params )
	{
		global $Settings, $Blog, $edited_Item, $current_User;

		if ( $current_User->check_perm( 'files', 'add' ) && $Settings->get( 'fm_enabled' ) )
		{
			load_class('files/model/_filelist.class.php');

			/**
			 * @var FileRoot
			 */
			$fm_FileRoot = false;
			$upload_root = '';

			$FileRootCache = & get_Cache( 'FileRootCache' );
			$available_Roots = $FileRootCache->get_available_FileRoots();

			if ( !empty($Blog) )
			{	// try to get it for the current Blog
				$fm_FileRoot = & $FileRootCache->get_by_type_and_ID( 'collection', $Blog->ID );
				if ( ! $fm_FileRoot || ! isset( $available_Roots[$fm_FileRoot->ID] ) )
				{ // Root not found or not in list of available ones
					$fm_FileRoot = false;
				}
			}

			if ( $fm_FileRoot )
			{
				$upload_root = '&root='.$fm_FileRoot->ID;
			}
			else
			{	// Root not found, use directory selector
				$this->Settings->set( 'roots_select', '1' );
			}

			switch ( $this->Settings->get('roots_select') )
			{
				case '1':
					$width = '650';
					$height = '500';
					break;

				case '0':
					$width = '540';
					$height = '310';
					break;
			}

			if ( $params['target_type'] == 'Item' )
			{
				$button_url = str_replace( '&amp;', '&', $this->get_htsrv_url( 'quick_upload' ) ).
								'&blog='.$Blog->ID.'&mode=upload&item_ID='.$edited_Item->ID.
								'&roots_select='.$this->Settings->get('roots_select').$upload_root;

				echo '<input type="button" value="'.$this->T_('Quick upload').
								'" onclick="return pop_up_window( \''.$button_url.'\', \'quick_upload_plugin\', '.$width.', '.$height.' )" />';
			}
		}
	}

}

/**
 * Get the directories of the supplied path as a radio button tree.
 *
 * @todo fp> Make a DirTree class (those static hacks suck)
 *
 * @param FileRoot A single root or NULL for all available.
 * @param string the root path to use
 * @param boolean add radio buttons ?
 * @param string used by recursion
 * @return string
 */
function quick_directory_tree( $Root = NULL, $ads_full_path = NULL, $ads_selected_full_path = NULL, $radios = false, $rds_rel_path = NULL, $is_recursing = false )
{
	static $js_closeClickIDs; // clickopen IDs that should get closed
	static $instance_ID = 0;

	if( ! $is_recursing )
	{	// This is not a recursive call (yet):
		// Init:
		$instance_ID++;
		$js_closeClickIDs = array();
		$ret = '<ul class="clicktree">';
	}
	else
	{
		$ret = '';
	}

	// ________________________ Handle Roots ______________________
	if( $Root === NULL )
	{ // We want to list all roots:
		$FileRootCache = & get_Cache( 'FileRootCache' );
		$_roots = $FileRootCache->get_available_FileRoots();

		foreach( $_roots as $l_Root )
		{
			$subR = quick_directory_tree( $l_Root, $l_Root->ads_path, $ads_selected_full_path, $radios, '', true );
			if( !empty( $subR['string'] ) )
			{
				$ret .= '<li>'.$subR['string'].'</li>';
			}
		}
	}
	else
	{
		// We'll go through files in current dir:
		$Nodelist = new Filelist( $Root, trailing_slash($ads_full_path) );
		$Nodelist->load();
		$Nodelist->sort( 'name' );
		$has_sub_dirs = $Nodelist->count_dirs();

		$id_path = 'id_path_'.$instance_ID.md5( $ads_full_path );

		$r['string'] = '<span class="folder_in_tree">';

		// echo '<br />'. $rds_rel_path . ' - '.$ads_full_path;
		if( $ads_full_path == $ads_selected_full_path )
		{	// This is the current open path
	 		$r['opened'] = true;
		}
		else
		{
	 		$r['opened'] = NULL;
		}


		if( $radios )
		{ // Optional radio input to select this path:
			$root_and_path = format_to_output( implode( '::', array($Root->ID, $rds_rel_path) ), 'formvalue' );

			$r['string'] .= '<input type="radio" name="root_and_path" value="'.$root_and_path.'" id="radio_'.$id_path.'"';

			if( $r['opened'] )
			{	// This is the current open path
				$r['string'] .= ' checked="checked"';
			}

			//.( ! $has_sub_dirs ? ' style="margin-right:'.get_icon( 'collapse', 'size', array( 'size' => 'width' ) ).'px"' : '' )
			$r['string'] .= ' /> &nbsp; &nbsp;';
		}

		global $Plugins;
		$Quick_upload = & $Plugins->get_by_code( 'quick_upload' );

		// Folder Icon + Name:
		//$url = regenerate_url( 'root,path', 'root='.$Root->ID );
		$url = substr( $_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '&root=')).'&amp;root='.$Root->ID.'&amp;path='.$rds_rel_path;
		$label = action_icon( T_('Open this directory in the file manager'), 'folder', $url )
			.'<a href="'.$url.'"
			title="'.T_('Open this directory in the file manager').'">'
			.( empty($rds_rel_path) ? $Root->name : basename( $ads_full_path ) )
			.'</a>';

		// Handle potential subdir:
		if( ! $has_sub_dirs )
		{	// No subirs
			$r['string'] .= get_icon( 'expand', 'noimg', array( 'class'=>'' ) ).'&nbsp;'.$label.'</span>';
		}
		else
		{ // Process subdirs
			$r['string'] .= get_icon( 'collapse', 'imgtag', array( 'onclick' => 'toggle_clickopen(\''.$id_path.'\');',
						'id' => 'clickimg_'.$id_path
					) )
				.'&nbsp;'.$label.'</span>'
				.'<ul class="clicktree" id="clickdiv_'.$id_path.'">'."\n";

			while( $l_File = & $Nodelist->get_next( 'dir' ) )
			{
				$rSub = quick_directory_tree( $Root, $l_File->get_full_path(), $ads_selected_full_path, $radios, $l_File->get_rdfs_rel_path(), true );

				if( $rSub['opened'] )
				{ // pass opened status on, if given
					$r['opened'] = $rSub['opened'];
				}

				$r['string'] .= '<li>'.$rSub['string'].'</li>';
			}

			if( !$r['opened'] )
			{
				$js_closeClickIDs[] = $id_path;
			}
			$r['string'] .= '</ul>';
		}

   	if( $is_recursing )
		{
			return $r;
		}
		else
		{
			$ret .= '<li>'.$r['string'].'</li>';
		}
	}

	if( ! $is_recursing )
	{
 		$ret .= '</ul>';

		if( ! empty($js_closeClickIDs) )
		{ // there are IDs of checkboxes that we want to close
			$ret .= "\n".'<script type="text/javascript">toggle_clickopen( \''
						.implode( "' );\ntoggle_clickopen( '", $js_closeClickIDs )
						."' );\n</script>";
		}
	}

	return $ret;
}

?>