<?php
/**
 *
 * This file implements the Protected posts plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2008-2012 Russian b2evolution - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Russian b2evolution
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class protected_posts_plugin extends Plugin
{
	var $name = 'Protected posts';
	var $code = 'protected_posts';
	var $priority = 30;
	var $version = '1.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=16830';

	var $min_app_version = '2';
	var $max_app_version = '5';

	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Allows users to password protect their posts');
		$this->long_desc = $this->short_desc;
	}


	function GetDefaultSettings()
	{
		return array(
				'cookies' => array(
					'label' => $this->T_('Memorize passwords'),
					'defaultvalue' => 1,
					'type' => 'checkbox',
					'note' => $this->T_('Do we want to memorize passwords in user cookies for future use.'),
				),
			);
	}


	function GetDbLayout()
	{
		return array(
			'CREATE TABLE IF NOT EXISTS '.$this->get_sql_table('pass').' (
					post_ID int(11) NOT NULL,
					pass_hash varchar(32) NOT NULL,
					pass varchar(255) NOT NULL,
					PRIMARY KEY post_ID ( post_ID ),
					UNIQUE ( pass_hash )
				)',
		);
	}


	/**
	 * Event handler: Called when we detect a version change (in {@link Plugins::register()}).
	 *
	 * Use this for your upgrade needs.
	 *
	 * @param array Associative array of parameters.
	 *              'old_version': The old version of your plugin as stored in DB.
	 *              'db_row': an array with the columns of the plugin DB entry (in T_plugins).
	 *                        The key 'plug_version' is the same as the 'old_version' key.
	 * @return boolean If this method returns false, the Plugin's status gets changed to "needs_config" and
	 *                 it gets unregistered for the current request.
	 */
	function PluginVersionChanged( & $params )
	{
		global $app_version;

		if( version_compare( $params['old_version'], '1.0.0', '<' ) )
		{	// Add pass_hash column
			global $DB;

			if( version_compare( $app_version, '4.1', '>' ) )
			{	// Automatically register new events
				$admin_Plugins = & get_Plugins_admin();
				$admin_Plugins->restart();
				if( $admin_Plugins->save_events( $this, NULL ) )
				{
					invalidate_pagecaches();
					$this->load_events();
				}
			}

			$DB->query('ALTER TABLE '.$this->get_sql_table('pass').' CHANGE COLUMN pass pass varchar(255) NOT NULL');
			$DB->query('ALTER TABLE '.$this->get_sql_table('pass').' ADD COLUMN pass_hash varchar(32) NOT NULL');
			$DB->query('UPDATE '.$this->get_sql_table('pass').' SET pass_hash = MD5( CONCAT("'.$this->code.'", pass, "'.$this->code.'") )');
			$DB->query('ALTER TABLE '.$this->get_sql_table('pass').' ADD UNIQUE ( pass_hash )');
		}
		return true; // leave plugin enabled
	}


	function SkinBeginHtmlHead()
	{
		global $plugins_url;
		require_css( $plugins_url.'protected_posts_plugin/protected_posts.css' );
	}


	function AdminEndHtmlHead()
	{
		$this->SkinBeginHtmlHead();
	}


	function BeforeBlogDisplay( & $params )
	{
		$prpost_id = param( 'prpost_id', 'integer' );
		$prpost_pwd = param( 'prpost_pwd', 'string' );

		if( !empty($prpost_pwd) && !empty($prpost_id) && $this->Settings->get('cookies') )
		{	// Save pass in cookies
			$this->set_cookie( 'prpost_pwd_'.$prpost_id, $this->hash($prpost_pwd) );
		}
	}


	function DisplayItemAsHtml( & $params )
	{
		if( $params['preview'] || !$this->get_post_pass( $params['Item']->ID, true ) ) return;
		if( $this->check_post_pass( $params['Item']->ID ) ) return;

		if( $this->form_displayed && $this->form_displayed == $params['Item']->ID )
		{	// Don't display the form again in "follow up" section
			$params['data'] = NULL;
			return;
		}
		$params['data'] = $this->disp_pass_form( $params['Item'] );

		// Hide comments
		// WARNING: Visitors can still view comments on index.php?disp=comments page!
		$params['Item']->comment_status = 'disabled';
	}

	function DisplayItemAsXml( & $params )
	{
		return $this->DisplayItemAsHtml( $params );
	}


	function DisplayItemAsText( & $params )
	{
		return $this->DisplayItemAsHtml( $params );
	}


	function AdminDisplayItemFormFieldset( & $params )
	{
		if( $params['edit_layout'] == 'simple' ) return;

		$params['Form']->begin_fieldset( $this->T_('Password protect this post'), array( 'class' => 'fieldset' ) );
		echo '<table class="compose_layout" cellspacing="0"><tr>
				<td class="label"><label for="prpost_pwd" title="&quot;slug&quot; to be used in permalinks"><strong>'.$this->T_('Set password').':</strong></label></td>
				<td class="input" width="97%"><div class="tile"><div class="label"></div>
					<div class="input"><input style="width: 100%;" value="'.$this->get_post_pass( $params['Item']->ID, 'pass' ).'" size="50" class="form_text_input" name="prpost_pwd" id="prpost_pwd" type="text"></div>
				</div></td>
				<td width="1"><!-- for IE7 --></td></tr>
			  </table>';
		$params['Form']->end_fieldset();
	}


	function AfterItemInsert( & $params )
	{
		global $DB;

		$action = 'create';

		$prpost_pwd = param( 'prpost_pwd', 'string' );

		if( $prpost_pwd === NULL ) return;
		if( $this->get_post_pass($params['Item']->ID) ) $action = 'update';
		if( $prpost_pwd == '' ) $action = 'delete';
		if( evo_strlen($prpost_pwd) > 255 )
		{
			$this->msg( $this->T_('The password is too long (max 255 chars).'), 'error' );
			return false;
		}

		switch( $action )
		{
			case 'create':
				$SQL = 'INSERT INTO '.$this->get_sql_table('pass').' (post_id, pass, pass_hash)
						VALUES ('.$DB->quote($params['Item']->ID).', "'.$DB->escape($prpost_pwd).'", "'.$DB->escape( $this->hash($prpost_pwd) ).'")';

				if( $DB->query($SQL) )
					$this->msg( $this->T_('Post password has been created.'), 'success' );

				break;

			case 'update':
				$SQL = 'UPDATE '.$this->get_sql_table('pass').'
						SET pass = "'.$DB->escape($prpost_pwd).'",
						pass_hash = "'.$DB->escape( $this->hash($prpost_pwd) ).'"
						WHERE post_ID = '.$DB->quote($params['Item']->ID);

				if( $DB->query($SQL) )
					$this->msg( $this->T_('Post password has been updated.'), 'success' );
				break;

			case 'delete':
				$this->delete_post_pass( $params['Item']->ID );
				$this->msg( $this->T_('Post password has been deleted.'), 'success' );
				return;
		}
	}


	function AfterItemUpdate( & $params )
	{
		return $this->AfterItemInsert( $params );
	}


	function AfterItemDelete( & $params )
	{
		$this->delete_post_pass( $params['Item']->ID );
	}


	function check_post_pass( $post_ID )
	{
		$key = 'prpost_pwd_'.$post_ID;
		$post_pass = $this->get_post_pass( $post_ID );

		// Check cookies if enabled in plugin settings
		if( $this->Settings->get('cookies') && array_key_exists( $key, $_COOKIE ) && $_COOKIE[$key] == $post_pass ) return true;

		// Check POST
		$prpost_pwd = param( 'prpost_pwd', 'string' );
		if( $prpost_pwd != '' && $this->hash($prpost_pwd) == $post_pass ) return true;

		return false;
	}


	function delete_post_pass( $post_ID )
	{
		global $DB;
		$DB->query('DELETE FROM '.$this->get_sql_table('pass').' WHERE post_ID = '.$post_ID );
	}


	function get_post_pass( $post_ID, $what = 'pass_hash' )
	{
		global $DB;
		return $DB->get_var('SELECT '.$what.' FROM '.$this->get_sql_table('pass').' WHERE post_ID = '.$DB->quote($post_ID) );
	}


	function disp_pass_form( $Item )
	{
		echo '<div class="protected_posts_container">'.$this->T_('To view this post please enter the password below.');
		$Form = new Form( $Item->get_permanent_url(), 'protected_posts', 'post' );
		$Form->begin_form( 'protected_posts_form error' );
			$Form->hidden( 'prpost_id', $Item->ID );
			echo '<label for="prpost_pwd"><b>'.$this->T_('Password').':</b></label><br />';
			echo '<input id="prpost_pwd" name="prpost_pwd" type="password" size="30" />';
			echo '<input type="submit" />';
		$Form->end_form();
		echo '</div>';

		$this->form_displayed = $Item->ID;
	}


	function set_cookie( $name, $value, $time = '#' )
	{
		global $cookie_path, $cookie_domain;

		if( $time == '#' ) $time = time() + 315360000;
		if( setcookie( $name, $value, $time, $cookie_path, $cookie_domain ) ) return true;

		return false;
	}


	function hash( $str )
	{	// Salt the pass
		return md5( $this->code.$str.$this->code );
	}
}
?>