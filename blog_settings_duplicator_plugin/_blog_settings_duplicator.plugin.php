<?php
/**
 * This file implements the Skin Settings Duplicator plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2012 Russian b2evolution - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Emin Özlem
 * @author Alex (sam2kb)
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class blog_settings_duplicator_plugin extends Plugin
{
	var $name = 'Blog settings duplicator';
	var $code = 'blog_settings_dup';
	var $priority = 50;
	var $version = '1.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=21895';

	var $min_app_version = '4';
	var $max_app_version = '5';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;
	var $hide_tab = 0;


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Copies blog settings from one blog to another.');
		$this->long_desc = $this->T_('This plugin copies skins and plugins settings between blogs.');
	}


	/**
	 * We require b2evo 4.0 or above.
	 */
	function GetDependencies()
	{
		return array(
				'requires' => array(
					'app_min' => '4.0',
				),
			);
	}


	function AdminAfterMenuInit()
	{
		$this->register_menu_entry($this->name);
	}


	function AdminTabPayload()
	{
		if( $this->hide_tab == 1 ) return;

		// Dropdown list
		$BlogCache = & get_BlogCache();
		$BlogCache->load_all();

		$Form = new Form( 'admin.php', '', 'post' );
		$Form->begin_form( 'fform' );
		$Form->hidden_ctrl();
		$Form->hiddens_by_key( get_memorized() );

		$Form->begin_fieldset($this->name);
		echo '<p class="red center"><b>Use with caution, this cannot be undone!</b></p>';
		$Form->select_object( $this->get_class_id('blog_ID_FROM'), NULL, $BlogCache, $this->T_('From'), $this->T_('Source blog') );
		$Form->select_object( $this->get_class_id('blog_ID_TO'), NULL, $BlogCache, $this->T_('To'), $this->T_('Destination blog') );
		$Form->checkbox( $this->get_class_id('skin_settings'), '', $this->T_('Skin settings'), $this->T_('Check this to copy skin settings (current skin).') );
		$Form->checkbox( $this->get_class_id('plugin_settings'), '', $this->T_('Plugin settings'), $this->T_('Check this to copy plugin settings (all installed plugins).') );
		$Form->end_fieldset();

		$Form->end_form( array( array( 'value' => $this->T_('Copy settings!'), 'onclick' => 'return confirm(\''.$this->T_('Do you really want to continue?').'\')' ) ) );
	}


	function AdminTabAction()
	{
		global $DB;

		$this->check_perms();
		if( $this->hide_tab == 1 ) return;

		if( ! $blog_ID_FROM = param( $this->get_class_id('blog_ID_FROM'), 'integer' ) ) return;
		if( ! $blog_ID_TO = param( $this->get_class_id('blog_ID_TO'), 'integer' ) ) return;

		if( $blog_ID_FROM == $blog_ID_TO )
		{
			$this->msg( $this->T_('Select another target blog!'), 'error' );
			return;
		}

		$BlogCache = & get_BlogCache();

		$Blog_FROM = & $BlogCache->get_by_ID( $blog_ID_FROM );
		$Blog_TO = & $BlogCache->get_by_ID( $blog_ID_TO );

		$regexp = array();
		if( param( $this->get_class_id('skin_settings'), 'boolean' ) )
		{
			$regexp[] = 'skin'.$Blog_FROM->skin_ID.'_';
		}
		if( param( $this->get_class_id('plugin_settings'), 'boolean' ) )
		{
			$regexp[] = 'plugin[0-9]+_';
		}

		if( count($regexp) > 0 )
		{
			$regexp = '('.implode( '|', $regexp ).')';
		}
		else
		{
			$this->msg( $this->T_('Select settings to copy!'), 'error' );
			return;
		}

		// Skin settings
		$SQL = 'SELECT cset_name, cset_value FROM T_coll_settings
				WHERE cset_coll_ID = '.$Blog_FROM->ID.'
				AND cset_name REGEXP "^'.$regexp.'"';

		if( $settings = $DB->get_results($SQL) )
		{
			$msg = array();
			foreach( $settings as $param )
			{
				$Blog_TO->set_setting( $param->cset_name, $param->cset_value );
				$msg[] = $param->cset_name;
			}
			$Blog_TO->dbupdate();
			$this->msg( $this->T_('The following settings were copied:').'<br /><ol><li>'.implode( '</li><li>', $msg ).'</li></ol>', 'note' );
			$this->msg( $this->T_('Settings have been copied'), 'success' );
		}
		else
		{
			$this->msg( $this->T_('The source blog does not have any settings to copy'), 'note' );
		}
	}


	function check_perms()
	{
		global $current_User;

		$msg = $this->T_('You\'re not allowed to view this page!');

		$this->hide_tab = 1;
		if( !is_logged_in() )
		{	// Not logged in
			$this->msg( $msg, 'error' );
			return false;
		}
		if( ! $current_User->check_perm('options', 'edit') )
		{
			$this->msg( $msg, 'error' );
			return false;
		}
		$this->hide_tab = 0;
		return true;
	}
}

?>