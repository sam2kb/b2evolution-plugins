<?php
//This file implements the Coupon Manager plugin for b2evolution
//@copyright (c)2013 by Sonorth Corp. - {@link http://www.sonorth.com/}.
// (\t| )*// \s*.+
//\n\n\n
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class coupon_manager_plugin extends Plugin
{
	var $name = 'Coupon Manager';
	var $code = 'sn_coupon';
	var $priority = 10;
	var $version = '1.1.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://www.sonorth.com';
	var $help_url = 'http://b2evo.sonorth.com/show.php/coupon-manager-plugin';

	var $licensed_to = array('name'=>'Licensed User', 'email'=>'and@their.email');

	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;

	// Internal
	var $_categories = array();
	var $_is_our_hit = false;

	var $copyright = 'THIS COMPUTER PROGRAM IS PROTECTED BY COPYRIGHT LAW AND INTERNATIONAL TREATIES. UNAUTHORIZED REPRODUCTION OR DISTRIBUTION OF %N PLUGIN, OR ANY PORTION OF IT THAT IS OWNED BY %C, MAY RESULT IN SEVERE CIVIL AND CRIMINAL PENALTIES, AND WILL BE PROSECUTED TO THE MAXIMUM EXTENT POSSIBLE UNDER THE LAW.<br /><br />THE %N PLUGIN FOR B2EVOLUTION CONTAINED HEREIN IS PROVIDED "AS IS." %C MAKES NO WARRANTIES OF ANY KIND WHATSOEVER WITH RESPECT TO THE %N PLUGIN FOR B2EVOLUTION. ALL EXPRESS OR IMPLIED CONDITIONS, REPRESENTATIONS AND WARRANTIES, INCLUDING ANY WARRANTY OF NON-INFRINGEMENT OR IMPLIED WARRANTY OF MERCHANTABILITY OR FITNESS FOR A PARTICULAR PURPOSE, ARE HEREBY DISCLAIMED AND EXCLUDED TO THE EXTENT ALLOWED BY APPLICABLE LAW.<br /><br />IN NO EVENT WILL %C BE LIABLE FOR ANY LOST REVENUE, PROFIT OR DATA, OR FOR DIRECT, SPECIAL, INDIRECT, CONSEQUENTIAL, INCIDENTAL, OR PUNITIVE DAMAGES HOWEVER CAUSED AND REGARDLESS OF THE THEORY OF LIABILITY ARISING OUT OF THE USE OF OR INABILITY TO USE THE %N PLUGIN FOR B2EVOLUTION, EVEN IF %C HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.';


	function PluginInit( & $params )
	{
		global $app_version;

		if( version_compare( $app_version, '5' ) > 0 )
		{
			$this->group = 'rendering';
		}

		// load_class('plugins/model/_plugins_admin.class.php', 'Plugins_admin');
		// $Plugins_admin = new Plugins_admin();
		// if( $methods = $Plugins_admin->get_registered_events( $this ) )
		// {
			// echo "<pre>\n\tfunction ".implode( "(){}\n\tfunction ", $methods )."(){}\n</pre>"; die;
		// }

		$this->short_desc = $this->T_('Create and manage stylized affiliate links (coupons)');
		$this->long_desc = 'This product is licensed to: [<em>'.$this->licensed_to['name'].'</em>]<br /><br /><br />'.
							str_replace( array('%N', '%C'), array( strtoupper($this->name), strtoupper($this->author) ), $this->copyright );

		// Settings
		$this->url_prefix = 'coupon';		// This prefix is needed for nice permalinks
		$this->template_styles = array(
					'default' => array(
						'name' => $this->T_('= Default ='),
						'template' => '$link-title$<br />$description$',
						'size' => 'normal',
					),
					'large' => array(
						'name' => $this->T_('Large'),
						'template' => '$link-title$<br />$description$',
						'size' => 'large',
					),
					'small' => array(
						'name' => $this->T_('Small'),
						'template' => '$link-title$<br />$description$',
						'size' => 'small',
					),
				);
	}


	function GetDbLayout()
	{
		return array(
			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('data')." (
					link_ID INT(11) NOT NULL auto_increment,
					link_user_ID INT(11) NOT NULL,
					link_cat_ID INT(11) DEFAULT '0' NOT NULL,
					link_status INT(1) NOT NULL default 1,
					link_mask_url INT(1) NOT NULL default 1,
					link_key VARCHAR(32) NOT NULL,
					link_code VARCHAR(255) NOT NULL,
					link_title VARCHAR(255) NOT NULL,
					link_description VARCHAR(10000) NOT NULL,
					link_template VARCHAR(10000) NOT NULL,
					link_url VARCHAR(1000) NOT NULL,
					link_click_limit INT(11) NOT NULL,
					link_clicks INT(11) NOT NULL,
					link_views INT(11) NOT NULL,
					link_date_start DATETIME NOT NULL default '2000-01-01 00:00:00',
					link_date_end VARCHAR(255) NOT NULL,
					PRIMARY KEY (link_ID),
					UNIQUE (link_key),
					INDEX (link_user_ID),
					INDEX (link_clicks),
					INDEX (link_views)
				)",

			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('cats')." (
					cat_ID INT(11) NOT NULL auto_increment,
					cat_user_ID INT(11) NOT NULL,
					cat_name VARCHAR(255) NOT NULL,
					cat_description MEDIUMTEXT NOT NULL,
					PRIMARY KEY (cat_ID),
					INDEX (cat_user_ID)
				)",

			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('stats')." (
					stat_ID INT(11) NOT NULL auto_increment,
					stat_link_ID INT(11) NOT NULL,
					stat_reg_user_ID INT(11) NOT NULL,
					stat_remote_addr VARCHAR(40) NOT NULL,
					stat_referer VARCHAR(255) NOT NULL,
					stat_agnt_type VARCHAR(50) DEFAULT 'unknown' NOT NULL,
					stat_user_agnt VARCHAR(255) NOT NULL,
					stat_datetime DATETIME NOT NULL default '2000-01-01 00:00:00',
					PRIMARY KEY (stat_ID),
					INDEX (stat_link_ID)
				)",
		);
	}


	function AfterInstall()
	{
		global $DB, $current_User, $UserSettings;

		$cat_name = $this->T_('Dafault category');
		$cat_description = $this->T_('This is the default category. You can rename it or change this description, but you can\'t delete this category.');

		// Create default category
		$SQL = 'INSERT INTO '.$this->get_sql_table('cats').'
					( cat_user_ID, cat_name, cat_description )
				VALUES (
					'.$DB->quote($current_User->ID).',
					"'.$DB->escape( $cat_name ).'",
					"'.$DB->escape( $cat_description ).'"
					)';

		if( $ID = $DB->query($SQL) )
		{	// Save cad_ID in user settings
			$UserSettings->set( $this->code.'_default_cat_ID', $ID );
			$UserSettings->dbupdate();
		}
	}


// =========================
// Plugin settings

	function GetDefaultSettings()
	{
		global $baseurl;

		$test_url = $baseurl.$this->url_prefix.'/XXXXX';
		$faq_url = 'http://forums.b2evolution.net/viewtopic.php?t=17685';

		return array(
				'nice_permalinks' => array(
					'label' => $this->T_('Nice links'),
					'type' => 'checkbox',
					'defaultvalue' => 0,
					'note' => sprintf( $this->T_('Check this if you want to use nice links e.g. %s, where XXXXX is your coupon key.'), '<b><a href="'.$test_url.'" target="_blank">'.$test_url.'</a></b>' ).'<br /><br />'.
					sprintf( $this->T_('Make sure you get the %s error message when you click on the test link above. If you don\'t see it, please <a %s>read this FAQ</a>.'), '<b>"This coupon is temporarily unavailable"</b>', 'href="'.$faq_url.'" target="_blank"' ),
				),
			);
	}


	function GetDefaultUserSettings()
	{
		global $UserSettings;

		return array(
				'notify_user' => array(
					'label' => $this->T_('Enable notifications'),
					'type' => 'checkbox',
					'defaultvalue' => 1,
					'note' => $this->T_('Check this if you want to receive email notifications about broken links.<br />Messages generated automatically, your email will not be displayed.'),
				),
				'key_length' => array(
					'label' => $this->T_('Link key length'),
					'type' => 'integer',
					'defaultvalue' => 5,
					'size' => 2,
					'valid_range' => array( 'min'=>5, 'max'=>32 ),
					'note' => $this->T_('5-32 characters.'),
				),
				'disabled_v' => array(
					'label' => $this->T_('Members only'),
					'type' => 'checkbox',
					'defaultvalue' => 0,
					'note' => $this->T_('Check this if you want to disable links for visitors. If chosen, only logged in users will be able to follow affiliate links.'),
				),
				'user_level' => array(
					'label' => $this->T_('User level'),
					'type' => 'integer',
					'defaultvalue' => 1,
					'size' => 2,
					'valid_range' => array( 'min'=>0, 'max'=>10 ),
					'note' => $this->T_('Minimum user level required to follow links. "Members only" option must be checked!'),
				),
			);
	}


	function PluginUserSettingsEditDisplayAfter( & $params )
	{
		if( !$mode = $this->UserSettings->get('auto_prune_stats_mode') )
		{
			$mode = 'off';
		}

		$params['Form']->radio_input( 'auto_prune_stats_mode', $mode, array(
				array(
					'value'=>'off',
					'label'=>$this->T_('Off'),
					'note'=>$this->T_('Not recommended! Your database will grow very large!'),
					'suffix' => '<br />',
					'onclick'=>'jQuery("#auto_prune_stats_container").hide();' ),
				array(
					'value'=>'page',
					'label'=>$this->T_('With every click'),
					'note'=>$this->T_('This is guaranteed to work but uses extra resources with every coupon link click.'), 'suffix' => '<br />',
					'onclick'=>'jQuery("#auto_prune_stats_container").show();' ),
				array(
					'value'=>'cron',
					'label'=>$this->T_('With a scheduled job'),
					'note'=>$this->T_('Recommended if you have your scheduled jobs properly set up.'), 'onclick'=>'jQuery("#auto_prune_stats_container").show();' ) ),
			$this->T_('Auto pruning hits') );

		echo '<div id="auto_prune_stats_container">';
		$params['Form']->text_input( 'auto_prune_stats', $this->UserSettings->get('auto_prune_stats'), 5, $this->T_('Prune after'), $this->T_('days. How many days of hits do you want to keep in the database for stats?') );
		echo '</div>';

		if( $this->UserSettings->get('auto_prune_stats_mode') == 'off' )
		{ // hide the "days" input field, if mode set to off:
			echo '<script type="text/javascript">jQuery("#auto_prune_stats_container").hide();</script>';
		}
	}


	function PluginUserSettingsUpdateAction()
	{
		global $Settings, $UserSettings;

		if( !param_integer_range( 'edit_plugin_'.$this->ID.'_set_user_level', 0, 10, $this->T_('User level must be between 0 and 10.') ) )
		{
			return false;
		}

		// Get our custom fields
		$this->UserSettings->set( 'auto_prune_stats_mode', param('auto_prune_stats_mode', 'string') );
		$this->UserSettings->set( 'auto_prune_stats', param('auto_prune_stats', 'string') );
	}


	function get_widget_param_definitions( $params )
	{
		$r = array(
			'title' => array(
				'label' => $this->T_('Title'),
				'note' => $this->T_('Widget title displayed in skin'),
				'type' => 'html_input',
				'size' => 40
			),
			'key' => array(
				'label' => $this->T_('Coupon key'),
				'note' => $this->T_('Enter coupon key here.').' [<a href="'.$this->get_plugin_tab_url().'" target="_blank">'.$this->name.'</a>]',
				'type' => 'text',
				'size' => 40,
			),
			'style' => array(
				'label' => $this->T_('Coupon style'),
				'note' => $this->T_('Select coupon style.'),
				'type' => 'select',
				'options' => $this->get_template_params('names'),
			),
			'coupon_class' => array(
				'label' => $this->T_('Coupon class'),
				'note' => sprintf( $this->T_('Enter your own CSS class name to customize this coupon. Read notes in %s file.'), '<b>'.$this->code.'.css</b>' ),
				'type' => 'text',
				'size' => 10,
				'valid_pattern' => array( 'pattern' => '~^([a-z][a-z0-9_\-]*)?$~i', 'error' => $this->T_('Please enter a valid CSS "Coupon class" value') ),
			),
			'template' => array(
				'label' => $this->T_('Coupon template'),
				'defaultvalue' => $this->get_template_params('default', 'template'),
				'note' => sprintf( $this->T_('Enter coupon template code here, you can use any combination of replacement variables listed below.<br /><br />Replacement values: %s.'), '$code$, $link-code$, $title$, $link-title$, $description$, $link-description$, $clicks$, $link-clicks$, $click-limit$, $link-click-limit$, $views$, $link-views$, $url$' ).'<br /><br />',
				'type' => 'html_textarea',
				'rows' => 5,
			),
		);

		return $r;
	}


	function BeforeBlogDisplay( $params )
	{
		global $ReqHost, $ReqURI, $baseurl;

		// Get coupon key
		if( $key = param( $this->url_prefix, 'string' ) )
		{
			if( !preg_match( '~^[A-Za-z0-9]{5,32}$~', $key ) ) return;
		}
		elseif( preg_match( '~^'.$this->url_prefix.'/([A-Za-z0-9]{5,32})$~', str_replace( $baseurl, '', $ReqHost.$ReqURI ),$matches ) )
		{	// Got coupon key
			$key = $matches[1];
		}

		// Not a coupon, we return
		if( empty($key) ) return;

		$this->count_the_hit( $key );
	}

// =========================
// Admin functions

	function GetHtsrvMethods()
	{
		return array('browse');
	}


	function AdminAfterMenuInit()
	{
		$this->register_menu_entry( $this->name );
	}


	function GetCronJobs( & $params )
	{
		return array(
			array(
				'name' => $this->T_('Prune old hits').' ('.$this->name.')',
				'ctrl' => 'prune_old_hits',
			),
		);
	}


	function ExecCronJob( & $params )
	{	// We provide only one cron job, so no need to check $params['ctrl'] here.
		global $UserSettings, $current_User, $DB, $localtimenow;

		if( $this->UserSettings->get('auto_prune_stats_mode') != 'cron' )
		{	// Cron job is disabled
			$message = $this->T_('Scheduled auto pruning is disabled in plugin settings.');
			return array( 'code' => 0, 'message' => $message );
		}

		$last_prune = $UserSettings->get( $this->code.'_auto_prune' );
		if( $last_prune >= date('Y-m-d', $localtimenow) )
		{ // Already pruned today
			$message = $this->T_('Pruning has already been done today');
			return array( 'code' => 0, 'message' => $message );
		}

		$time_prune_before = ($localtimenow - ($this->UserSettings->get('auto_prune_stats') * 86400)); // 1 day

		$SQL = 'DELETE FROM '.$this->get_sql_table('stats').'
				USING '.$this->get_sql_table('stats').'
				INNER JOIN '.$this->get_sql_table('data').' ON stat_link_ID = link_ID
				WHERE link_user_ID = '.$current_User->ID.'
				AND stat_datetime < "'.date('Y-m-d', $time_prune_before).'"';

		$pruned = $DB->query( $SQL, 'Autopruning stats ('.$this->name.')' );
		if( $pruned > 0 )
		{
			$code = 1;
			$message = sprintf( $this->T_('Successfully pruned %d records!'), $pruned );
		}
		else
		{
			$code = -1;
			$message = $this->T_('Nothing to prune.');
		}
		$UserSettings->set( $this->code.'_auto_prune', date('Y-m-d H:i:s', $localtimenow) );
		$UserSettings->dbupdate();

		return array( 'code' => $code, 'message' => $message );
	}


	function AdminTabPayload()
	{
		if( $this->hide_tab == 1 ) return;

		global $DB, $current_User;

		$Coupon = $this->get_Coupon_from_param(); // Get the coupon if requested
		$Category = $this->get_Category_from_param(); // Get the category if requested
		$this->get_cats(); // Get coupon categories

		$GLOBALS['CouponPlugin'] = & $this;

		$action = param( 'action', 'string' );
		$action_url = regenerate_url( 'action' ).'&amp;';

		$action_links = array(
				'<a href="{url}">'.$this->T_('View coupons').'</a>',
				'<a href="{url}action=create">'.$this->T_('Create new coupon').'</a>',
				'<a href="{url}action=view_cats">'.$this->T_('Manage categories').'</a>',
			);

		echo '<div style="margin:0 0 10px 0; font-size:16px">'.
				str_replace( '{url}', $action_url, implode( ' | ', $action_links ) ).'</div>';

		$Form = new Form('admin.php');
		$Form->begin_form('fform');
		$Form->hidden_ctrl();
		$Form->hiddens_by_key( get_memorized() );

		if( empty($Coupon) && empty($Category) && $action == 'view_cats' )
		{	// Categories list mode
			$Form->begin_fieldset( $this->T_('Create new category') );
			$Form->hidden( 'action', 'create_cat' );

			$Form->text_input( $this->get_class_id().'_cat_name', '', 50, $this->T_('Name'), '', array( 'maxlength'=>255 ) );
			$Form->textarea( $this->get_class_id().'_cat_description', '', 4, $this->T_('Description'), '', 60 );

			$Form->end_fieldset();
			$Form->end_form( array( array( 'submit', 'submit', $this->T_('Create category'), 'SaveButton' ) ) );


			$SQL = 'SELECT * FROM '.$this->get_sql_table('cats').'
					WHERE cat_user_ID = '.$DB->quote($current_User->ID);

			$Results = new Results( $SQL, 'dlcats_', '--A' );
			$Results->title = $this->T_('Categories list');

			$Results->cols[] = array(
					'th' => $this->T_('ID'),
					'td' => '$cat_ID$',
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
				);

			function td_items( $Category )
			{
				global $DB, $current_User;

				$SQL = 'SELECT COUNT(link_ID) FROM '.$GLOBALS['CouponPlugin']->get_sql_table('data').'
						WHERE link_cat_ID = '.$DB->quote($Category->cat_ID).'
						AND link_user_ID = '.$DB->quote($current_User->ID);

				$total = $DB->get_var($SQL);
				return $total;
			}
			$Results->cols[] = array(
					'th' => $this->T_('Coupons'),
					'td' => '% td_items( {row} ) %',
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
				);

			$Results->cols[] = array(
					'th' => $this->T_('Name'),
					'td' => '$cat_name$',
				);

			function td_description( $cat_description )
			{
				return evo_substr( $cat_description, 0, 120 );
			}
			$Results->cols[] = array(
					'th' => $this->T_('Description'),
					'td' => '% td_description( #cat_description# ) %',
				);

			function td_actions( $cat_ID )
			{
				$r = '';

				// Edit
				$r .= action_icon( $GLOBALS['CouponPlugin']->T_('Edit'), 'edit',
							regenerate_url( 'action', $GLOBALS['CouponPlugin']->code.'_cat_ID='.$cat_ID.'&amp;action=edit_cat' ), '', 5 );

				if( $cat_ID != 1 )
				{	// Delete
					$r .= action_icon( $GLOBALS['CouponPlugin']->T_('Delete'), 'delete',
								regenerate_url( 'action', $GLOBALS['CouponPlugin']->code.'_cat_ID='.$cat_ID.'&amp;action=delete_cat' ), '', 5, 1, array( 'onclick' => 'return confirm(\''.$GLOBALS['CouponPlugin']->T_('Do you really want to delete this category?').'\')' ) );
				}
				return $r;
			}
			$Results->cols[] = array(
					'th' => $this->T_('Actions'),
					'td' => '% td_actions( #cat_ID# ) %',
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
				);

			$highlight_fadeout = array();
			if( $updated_ID = param( $this->code.'_updated_cat_ID', 'integer' ) )
			{	// If there happened something with a link_ID, apply fadeout to the row
				$highlight_fadeout = array( 'cat_ID' => array($updated_ID) );
			}

			$Results->display( NULL, $highlight_fadeout );
		}
		elseif( empty($Coupon) && !empty($Category) && $action == 'edit_cat' )
		{	// Edit Category mode
			$Form->begin_fieldset( $this->T_('Editing category:').' &laquo;'.$Category->cat_name.'&raquo;' );
			$Form->hidden( $this->code.'_cat_ID', $Category->cat_ID );
			$Form->hidden( 'action', 'update_cat' );

			$Form->text_input( $this->get_class_id('cat_name'), $Category->cat_name, 50, $this->T_('Name'), '', array( 'maxlength'=>255 ) );
			$Form->textarea( $this->get_class_id('cat_description'), $Category->cat_description, 4, $this->T_('Description'), '', 60 );

			$Form->end_fieldset();
			$Form->end_form( array( array( 'submit', 'submit', $this->T_('Update category'), 'SaveButton' ) ) );
		}
		elseif( !empty($Coupon) && $action == 'info' )
		{	// Stats mode
			param( $this->code.'_link_ID', 'integer', '', true );
			param( 'action', 'string', '', true );

			$SQL = 'SELECT * FROM '.$this->get_sql_table('stats').'
					WHERE stat_link_ID = '.$Coupon->link_ID;

			$Results = new Results( $SQL, 'sn_', '-----D' );
			$Results->title = sprintf( $this->T_('Click statistics for %s'), '&laquo;'.$Coupon->link_title.'&raquo;' );

			if( $current_User->check_perm( 'users', 'view' ) )
			{	// User has permissions to view user profiles
				function td_results_reg_user_ID( $user_ID )
				{
					global $admin_url, $UserCache;

					// We could not find the User
					if( empty($user_ID) || ($User = $UserCache->get_by_ID( $user_ID, false )) === false ) return '-';

					if( method_exists($User, 'get_identity_link') )
					{	// b2evo 4
						$r = $User->get_identity_link();
					}
					else
					{
						$r = '<a href="?ctrl=users&amp;user_ID='.$User->ID.'">'.$User->get_preferred_name().'</a>';
					}
					return $r;
				}
				// Agent signature
				$Results->cols[] = array(
							'th' => $this->T_('User'),
							'td' => '% td_results_reg_user_ID( #stat_reg_user_ID# ) %',
							'th_class' => 'shrinkwrap',
							'td_class' => 'shrinkwrap',
							'order' => 'stat_reg_user_ID',
						);
			}

			function td_results_referer( $hit_referer )
			{
				if( empty($hit_referer) )
				{
					return 'direct';
				}
				else
				{
					$ref = parse_url($hit_referer);

					return '<a href="'.$hit_referer.'" target="_blank" title="'.
								$GLOBALS['CouponPlugin']->T_('Open in a new window').'">'.$ref['host'].'</a>';
				}

			}
			// Referer:
			$Results->cols[] = array(
					'th' => $this->T_('Referer'),
					'td' => '% td_results_referer( #stat_referer# ) %',
					'th_class' => 'shrinkwrap',
					'order' => 'stat_referer',
				);

			// Agent type:
			$Results->cols[] = array(
					'th' => $this->T_('Agent type'),
					'td' => '$stat_agnt_type$',
					'th_class' => 'shrinkwrap',
					'td_class' => 'center',
					'order' => 'stat_agnt_type',
				);

			function td_results_agnt_sig( $agnt_signature )
			{
				return '<span title="'.$agnt_signature.'">'.strmaxlen($agnt_signature, 50).'</span>';
			}
			// Agent signature
			$Results->cols[] = array(
						'th' => $this->T_('Agent signature'),
						'td' => '% td_results_agnt_sig( #stat_user_agnt# ) %',
						'order' => 'stat_user_agnt',
					);

			// Remote address (IP):
			$Results->cols[] = array(
					'th' => $this->T_('Remote IP'),
					'td' => '$stat_remote_addr$',
					'th_class' => 'shrinkwrap',
					'order' => 'stat_remote_addr',
				);

			// datetime:
			$Results->cols[] = array(
					'th' => $this->T_('Date Time'),
					'td' => '%mysql2localedatetime_spans( #stat_datetime# )%',
					'th_class' => 'shrinkwrap',
					'td_class' => 'timestamp',
					'order' => 'stat_datetime',
				);
			$Results->display();
		}
		elseif( !empty($Coupon) && $action == 'edit' )
		{	// Edit item
			$Form->hidden( 'action', 'update_item' );

			$Form->begin_fieldset( '<div style="float:right">'.action_icon( $this->T_('Delete'), 'delete',
						regenerate_url( '', $this->code.'_link_ID='.$Coupon->link_ID.'&amp;action=delete' ), ' '.$this->T_('Delete this coupon'), 5, 5,
						array( 'onclick' => 'return confirm(\''.$this->T_('Do you really want to delete this item?').'\')' ) )
							.'</div><div style="float:left">'.$this->T_('Editing:').' &laquo;'.$Coupon->link_title.'&raquo;</div>' );

			$Form->hidden( $this->code.'_link_ID', $Coupon->link_ID );
			$Form->hidden( $this->get_class_id('link_key'), $Coupon->link_key );
			$Form->hidden( $this->get_class_id('link_clicks'), $Coupon->link_clicks );

			// $Form->text_input( $this->get_class_id().'_link_clicks', $Coupon->link_clicks, 11, $this->T_('Clicks'), '' );

			echo '<fieldset><div class="label"><label>'.$this->T_('Views').':</label></div>';
			echo '<div class="input" style="padding-top:3px">'.$Coupon->link_views.'</div></fieldset>';

			echo '<fieldset><div class="label"><label>'.$this->T_('Clicks').':</label></div>';
			echo '<div class="input" style="padding-top:3px">'.$Coupon->link_clicks.'</div></fieldset>';

			$this->disp_item_fields( $Form, $Coupon );

			$Form->end_fieldset();
			$Form->end_form( array( array( 'submit', 'submit', $this->T_('Update coupon'), 'SaveButton' ) ) );
		}
		elseif( empty($Coupon) && empty($Category) && $action == 'create' )
		{	// Create item
			$Form->begin_fieldset( $this->T_('Create new coupon') );
			$Form->hidden( 'action', 'create_item' );

			$this->disp_item_fields( $Form );

			$Form->end_fieldset();
			$Form->end_form( array( array( 'submit', 'submit', $this->T_('Create coupon'), 'SaveButton' ) ) );
		}
		elseif( ! empty($Coupon) && empty($Category) && $action == 'copy' )
		{	// Copy item
			$Form->begin_fieldset( $this->T_('Create new coupon') );
			$Form->hidden( 'action', 'create_item' );

			$this->disp_item_fields( $Form, $Coupon );

			$Form->end_fieldset();
			$Form->end_form( array( array( 'submit', 'submit', $this->T_('Create coupon'), 'SaveButton' ) ) );
		}
		else
		{	// List mode
			$keywords = param( 'keywords', 'string', '', true );
			$filter = param( 'filter', 'string', '', true );

			$where_clause = '';

			switch( $filter )
			{
				case 'enabled':
					$where_clause .= ' AND link_status = 1';
					break;

				case 'disabled':
					$where_clause .= ' AND link_status <> 1';
					break;
			}

			if( !empty( $keywords ) )
			{
				$kw_array = preg_split( '~ ~', $keywords );
				foreach( $kw_array as $kw )
				{
					$where_clause .= ' AND (link_title LIKE "%'.$DB->escape($kw).'%" OR link_title LIKE "%'.$DB->escape($kw).'%")';
				}
			}

			// Get total
			$Total = $DB->get_row( 'SELECT SUM(link_views) as views, SUM(link_clicks) as clicks
								    FROM '.$this->get_sql_table('data').'
									INNER JOIN '.$this->get_sql_table('cats').'
										ON link_cat_ID = cat_ID
								    WHERE link_user_ID = '.$current_User->ID.'
								    '.$where_clause );

			$SQL = 'SELECT '.$this->get_sql_table('data').'.*, '.$this->get_sql_table('cats').'.*, link_clicks / link_views AS ctr
					FROM '.$this->get_sql_table('data').'
					INNER JOIN '.$this->get_sql_table('cats').'
						ON link_cat_ID = cat_ID
					WHERE link_user_ID = '.$current_User->ID.'
					'.$where_clause.'
					ORDER BY cat_name ASC';

			$Results = new Results( $SQL, 'dl_', '------D' );
			$Results->title = $this->T_('Coupons list');
			$Results->group_by = 'cat_ID';

			function td_filter( & $Form )
			{
				$Form->text( 'keywords', get_param('keywords'), 20, $GLOBALS['CouponPlugin']->T_('Keywords'),
						$GLOBALS['CouponPlugin']->T_('Separate with space'), 50 );
			}

			$admin_tab_url = $this->get_plugin_tab_url('&amp;');

			$Results->filter_area = array(
				'callback' => 'td_filter',
				'url_ignore' => 'keywords',
				'presets' => array(
					'all' => array($this->T_('All'), $admin_tab_url ),
					'enabled' => array($this->T_('Enabled'), $admin_tab_url.'&amp;filter=enabled' ),
					'disabled' => array($this->T_('Disabled'), $admin_tab_url.'&amp;filter=disabled' ),
					)
				);

			function td_results_cat( $Category )
			{
				$r = action_icon( $GLOBALS['CouponPlugin']->T_('Edit category'), 'edit',
						regenerate_url( 'action', $GLOBALS['CouponPlugin']->code.'_cat_ID='.$Category->cat_ID.'&amp;action=edit_cat' ), '', 5 );

				return $r.' <span style="color:#000">'.$Category->cat_name.'</span>';
			}
			$Results->grp_cols[] = array(
					'td_colspan' => 9,  // nb_cols
					'td' => '% td_results_cat( {row} ) %',
				);

			function td_results_status( $link_status )
			{
				if( empty($link_status) )
				{
					return get_icon('disabled', 'imgtag', array('title'=> $GLOBALS['CouponPlugin']->T_('The item is disabled.')) );
				}
				else
				{
					return get_icon('enabled', 'imgtag', array('title'=> $GLOBALS['CouponPlugin']->T_('The item is enabled.')) );
				}
			}
			$Results->cols[] = array(
					'th' => $this->T_('En'),
					'td' => '% td_results_status( #link_status# ) %',
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
				);

			function td_results_key( $row )
			{
				$r = $row->link_key;
				if( $row->link_url )
				{
					$r = '<a href="'.$GLOBALS['CouponPlugin']->get_url($row).'" target="_blank">'.$row->link_key.'</a>';
				}
				return $r;
			}
			$Results->cols[] = array(
					'th' => $this->T_('Key'),
					'td' => '%td_results_key( {row} )%',
					'th_class' => 'shrinkwrap',
					'order' => 'link_key',
				);
			$Results->cols[] = array(
					'th' => '<span title="'.$this->T_('Mask URL').'" style="padding:0">'.$this->T_('Mask').'</span>',
					'td' => '% (#link_mask_url#) ? "<span class=\"note\">[<span class=\"green\">Yes</span>]</span>" : "<span class=\"note\">[<span class=\"red\">No</span>]</span>" %',
					'th_class' => 'shrinkwrap',
					'td_class' => 'center',
					'order' => 'link_mask_url',
				);
			$Results->cols[] = array(
					'th' => $this->T_('Code'),
					'td' => '$link_code$',
					'th_class' => 'shrinkwrap',
					'order' => 'link_code',
				);

			function td_results_title( $row )
			{
				$r = '<div><b style="color:#333" title="'.format_to_output( $row->link_url, 'htmlattr' ).'">'.$row->link_title.'</b>';
				if( $row->link_description )
				{
					$r .= '<br /><span class="note">&rsaquo;&rsaquo; <i>'.$row->link_description.'</i></span>';
				}
				$r .= '</div>';
				return $r;
			}
			$Results->cols[] = array(
					'th' => $this->T_('Coupon text'),
					'td' => '%td_results_title( {row} )%',
					// 'order' => 'link_title',
				);

			function td_results_clicks( $row )
			{
				$r = $row->link_clicks;
				if( $row->link_click_limit > 0 )
				{
					$r = '<span title="'.sprintf( $GLOBALS['CouponPlugin']->T_('%d out of %d clicks'),
							$row->link_clicks, $row->link_click_limit ).'">'.$row->link_clicks.'/'.$row->link_click_limit.'</span>';
				}
				return $r;
			}
			$Results->cols[] = array(
					'th' => $this->T_('Clicks'),
					'td' => '%td_results_clicks( {row} )%',
					'th_class' => 'shrinkwrap',
					'td_class' => 'center',
					'order' => 'link_clicks',
					'total' => $Total->clicks,
					'total_class' => 'shrinkwrap',
				);
			$Results->cols[] = array(
					'th' => $this->T_('Views'),
					'td' => '$link_views$',
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
					'order' => 'link_views',
					'total' => $Total->views,
					'total_class' => 'shrinkwrap',
				);
			$Results->cols[] = array(
					'th' => $this->T_('CTR'),
					'td' => '<span title="The number of coupon clicks divided by the number of views">%round(#ctr#,2)%%</span>',
					'th_class' => 'shrinkwrap',
					'td_class' => 'center',
					'order' => 'ctr',
				);

			function td_actions( $link_ID, $link_status, $link_key )
			{
				$r = '';

				$url = regenerate_url( '', $GLOBALS['CouponPlugin']->code.'_link_ID='.$link_ID.'&amp;action=' );

				if( empty($link_status) )
				{	// Enable
					$r .= action_icon( $GLOBALS['CouponPlugin']->T_('Enable this item'), 'activate', $url.'enable', '', 5 );
				}
				else
				{	// Disable
					$r .= action_icon( $GLOBALS['CouponPlugin']->T_('Disable this item'), 'deactivate', $url.'disable', '', 5 );
				}

				// Stats
				$r .= action_icon( $GLOBALS['CouponPlugin']->T_('View stats'), 'info', $url.'info', '', 5 );

				// Prune hits
				$r .= action_icon( $GLOBALS['CouponPlugin']->T_('Prune stats'), 'unlink', $url.'prune_hits', '', 5, 1, array( 'onclick' => 'return confirm(\''.$GLOBALS['CouponPlugin']->T_('Do you really want to prune stats for this coupon?').'\')' ) );

				// Edit
				$r .= action_icon( $GLOBALS['CouponPlugin']->T_('Edit'), 'edit', $url.'edit', '', 5 );

				// Copy
				$r .= action_icon( $GLOBALS['CouponPlugin']->T_('Copy'), 'copy', $url.'copy', '', 5 );

				// Delete
				$r .= action_icon( $GLOBALS['CouponPlugin']->T_('Delete'), 'delete', $url.'delete', '', 5, 1, array( 'onclick' => 'return confirm(\''.$GLOBALS['CouponPlugin']->T_('Do you really want to delete this item?').'\')' ) );

				return $r;
			}
			function td_global_actions()
			{
				$url = regenerate_url( '', 'action=' );

				// Enable all
				$r = action_icon( $GLOBALS['CouponPlugin']->T_('Enable all items'), 'activate', $url.'enable_all', '', 5, 1, array( 'onclick' => 'return confirm(\''.$GLOBALS['CouponPlugin']->T_('Do you really want to enable all items?').'\')' ) );

				// Disable all
				$r .= action_icon( $GLOBALS['CouponPlugin']->T_('Disable all items'), 'deactivate', $url.'disable_all', '', 5, 1, array( 'onclick' => 'return confirm(\''.$GLOBALS['CouponPlugin']->T_('Do you really want to disable all items?').'\')' ) );

				// Prune all hits
				$r .= action_icon( $GLOBALS['CouponPlugin']->T_('Prune all stats'), 'unlink', $url.'prune_all_hits', '', 5, 1, array( 'onclick' => 'return confirm(\''.$GLOBALS['CouponPlugin']->T_('Do you really want to prune all stats?').'\')' ) );

				// Delete all
				$r .= action_icon( $GLOBALS['CouponPlugin']->T_('Delete all items'), 'delete', $url.'delete_all', '', 5, 1, array( 'onclick' => 'return confirm(\''.$GLOBALS['CouponPlugin']->T_('Do you really want to delete all items?').'\')' ) );

				return $r;
			}
			$Results->cols[] = array(
					'th' => $this->T_('Actions'),
					'td' => '% td_actions( #link_ID#, #link_status#, #link_key# ) %',
					'th_class' => 'shrinkwrap',
					'td_class' => 'shrinkwrap',
					'total' => '% td_global_actions() %',
				);

			$highlight_fadeout = array();
			if( $updated_ID = param( $this->code.'_updated_link_ID', 'integer' ) )
			{	// If there happened something with a link_ID, apply fadeout to the row
				$highlight_fadeout = array( 'link_ID' => array($updated_ID) );
			}

			$Results->display( NULL, $highlight_fadeout );
		}
	}



	function AdminTabAction()
	{
		if( !$this->check_perms() ) $this->hide_tab = 1;
		if( $this->hide_tab == 1 ) return;

		global $DB, $current_User, $localtimenow;

		$action = param( 'action', 'string', 'list' );

		if( $Category = $this->get_Category_from_param() )
		{	// We have perms to manage the requested category
			switch($action)
			{
				case 'delete_cat':
					if( param( 'confirm', 'integer', 0 ) )
					{	// Confirmed, Delete and move items
						$this->delete_category( $Category, true );
					}
					else
					{	// Trying to delete category
						$this->delete_category( $Category );
					}

					set_param( $this->code.'_cat_ID', 0 ); // Return to the right view
					set_param('action', 'view_cats' ); // Return to the right view
					break;

				case 'edit_cat':
					// Do nothing...
					break;

				case 'update_item':
					$cat_name = param( $this->get_class_id('cat_name'), 'string' );
					$cat_description = param( $this->get_class_id('cat_description'), 'html' );

					$SQL = 'UPDATE '.$this->get_sql_table('cats').' SET
								cat_name = "'.$DB->escape( $cat_name ).'",
								cat_description = "'.$DB->escape( $cat_description ).'"
							WHERE cat_ID = '.$Category->cat_ID;

					if( $DB->query($SQL) )
					{	// Category saved in DB
						$this->msg( $this->T_('Category settings have been updated'), 'success' );
					}
					set_param( $this->code.'_cat_ID', 0 ); // Return to the right view
					set_param( 'action', 'view_cats' ); // Return to the right view
					set_param( $this->code.'_updated_cat_ID', $Category->cat_ID ); // Save ID for fadeout
			}
			// Exit now
			return true;
		}

		if( $Coupon = $this->get_Coupon_from_param() )
		{	// We have perms to manage
			switch($action)
			{
				case 'copy':
					break;

				case 'enable':
					$SQL = 'UPDATE '.$this->get_sql_table('data').'
							SET link_status = 1
							WHERE link_ID = '.$Coupon->link_ID;
					if( $DB->query($SQL) ) $this->msg( $this->T_('Selected item has been enabled!'), 'success' );

					set_param( $this->code.'_updated_link_ID', $Coupon->link_ID ); // Save ID for fadeout
					break;

				case 'disable':
					$SQL = 'UPDATE '.$this->get_sql_table('data').'
							SET link_status = 0
							WHERE link_ID = '.$Coupon->link_ID;
					if( $DB->query($SQL) ) $this->msg( $this->T_('Selected item has been disabled!'), 'success' );

					set_param( $this->code.'_updated_link_ID', $Coupon->link_ID ); // Save ID for fadeout
					break;

				case 'delete':
					$this->delete_item( $Coupon );
					break;

				case 'info':
					$SQL = 'SELECT stat_ID FROM '.$this->get_sql_table('stats').'
							WHERE stat_link_ID = '.$Coupon->link_ID.'
							LIMIT 0,1';

					if( $DB->get_results($SQL) )
					{
						set_param( $this->code.'_link_ID', $Coupon->link_ID );
						set_param( 'action', 'info' );
					}
					else
					{
						$this->msg( sprintf( $this->T_('The coupon %s has no clicks yet.'), '&laquo;'.$Coupon->link_title.'&raquo;' ) );
						set_param( $this->code.'_link_ID', 0 ); // Return to the right view
					}
					break;

				case 'prune_hits':
					// Prune hits for requested item
					$SQL = 'DELETE FROM '.$this->get_sql_table('stats').'
							WHERE stat_link_ID = '.$Coupon->link_ID;

					if( $DB->query($SQL) )
					{
						$this->msg( $this->T_('Coupon stats has been pruned!'), 'success' );
					}
					else
					{
						$this->msg( $this->T_('Unable to prune coupon stats.'), 'error' );
					}

					set_param( $this->code.'_updated_link_ID', $Coupon->link_ID ); // Save ID for fadeout
					break;
			}
		}

		switch($action)
		{
			// Create actions
			case 'create_cat':
				$cat_name = param( $this->get_class_id('cat_name'), 'string' );
				$cat_description = param( $this->get_class_id('cat_description'), 'html' );

				$SQL = 'INSERT INTO '.$this->get_sql_table('cats').'
							( cat_user_ID, cat_name, cat_description )
						VALUES (
							'.$DB->quote($current_User->ID).',
							"'.$DB->escape( $cat_name ).'",
							"'.$DB->escape( $cat_description ).'"
							)';

				if( $DB->query($SQL) )
				{	// Category saved in DB
					$this->msg( sprintf( $this->T_('The category &laquo;%s&raquo; has been created.'), $cat_name ), 'success' );

					set_param( $this->code.'_updated_cat_ID', $DB->insert_id ); // Save ID for fadeout
				}

				set_param( $this->code.'_cat_ID', 0 ); // Return to the right view
				set_param('action', 'view_cats' ); // Return to the right view
				break;

			case 'create_item':
			case 'update_item':
				$link_clicks = abs( (int) param( $this->get_class_id('link_clicks'), 'integer' ) );
				$link_click_limit = abs( (int) param( $this->get_class_id('link_click_limit'), 'integer' ) );
				$link_cat_ID = abs( (int) param( $this->get_class_id('link_cat_ID'), 'integer' ) );
				$link_code = param( $this->get_class_id('link_code'), 'string' );
				$link_title = param( $this->get_class_id('link_title'), 'string' );
				$link_description = param( $this->get_class_id('link_description'), 'html' );
				$link_template = param( $this->get_class_id('link_template'), 'html' );
				$link_url = param( $this->get_class_id('link_url'), 'html' );
				$link_mask_url = param( $this->get_class_id('link_mask_url'), 'boolean' );
				$link_date_start = param( $this->get_class_id('link_date_start'), 'integer' );
				$link_date_end = param( $this->get_class_id('link_date_end'), 'string' );

				if( $action == 'update_item' )
				{	// Update action
					$SQL = 'UPDATE '.$this->get_sql_table('data').' SET
								link_clicks = '.$DB->quote( $link_clicks ).',
								link_click_limit = '.$DB->quote( $link_click_limit ).',
								link_cat_ID = '.$DB->quote( $link_cat_ID ).',
								link_code = "'.$DB->escape( $link_code ).'",
								link_title = "'.$DB->escape( $link_title ).'",
								link_description = "'.$DB->escape( $link_description ).'",
								link_template = "'.$DB->escape( $link_template ).'",
								link_url = "'.$DB->escape( $link_url ).'",
								link_mask_url = '.$DB->quote( $link_mask_url ).',
								link_date_start = '.$DB->quote( $link_date_start ).',
								link_date_end = '.$DB->quote( $link_date_end ).'
							WHERE link_ID = '.$Coupon->link_ID.'
							AND link_user_ID = '.$DB->quote($current_User->ID);

					set_param( $this->code.'_link_ID', 0 ); // Return to the right view
				}
				else
				{	// Insert action
					$link_key = $this->gen_key( $this->UserSettings->get('key_length') ); // Generate unique key

					$SQL = 'INSERT INTO '.$this->get_sql_table('data').'
								( link_user_ID, link_clicks, link_click_limit, link_cat_ID, link_key, link_code,
									link_title, link_description, link_template, link_url, link_mask_url, link_date_start, link_date_end )
							VALUES (
								'.$DB->quote($current_User->ID).',
								'.$DB->quote( $link_clicks ).',
								'.$DB->quote( $link_click_limit ).',
								'.$DB->quote( $link_cat_ID ).',
								'.$DB->quote( $link_key ).',
								"'.$DB->escape( $link_code ).'",
								"'.$DB->escape( $link_title ).'",
								"'.$DB->escape( $link_description ).'",
								"'.$DB->escape( $link_template ).'",
								"'.$DB->escape( $link_url ).'",
								'.$DB->quote( $link_mask_url ).',
								'.$DB->quote( date('Y-m-d H:i:s', $localtimenow) ).',
								'.$DB->quote( $link_date_end ).'
							)';
				}

				$link_ID = 0;
				if( $link_ID = $DB->query($SQL) )
				{	// Saved in DB
					$this->msg( sprintf( $this->T_('The settings have been saved for &laquo;%s&raquo;.'), $link_title ), 'success' );
				}
				elseif( !empty($Coupon) )
				{	// Update action
					$link_ID = $Coupon->link_ID;
				}
				set_param( $this->code.'_updated_link_ID', $link_ID ); // Save ID for fadeout
				break;

			// Global actions
			case 'prune_all_hits':
				$msg = $this->prune_hit_stats( $current_User->ID );
				$this->msg( $msg[1], $msg[0] );
				break;

			case 'enable_all':
				$SQL = 'UPDATE '.$this->get_sql_table('data').'
						SET link_status = 1
						WHERE link_user_ID = '.$current_User->ID;
				if( $DB->query($SQL) ) $this->msg( $this->T_('All items have been enabled!'), 'success' );
				break;

			case 'disable_all':
				$SQL = 'UPDATE '.$this->get_sql_table('data').'
						SET link_status = 0
						WHERE link_user_ID = '.$current_User->ID;
				if( $DB->query($SQL) ) $this->msg( $this->T_('All items have been disabled!'), 'success' );
				break;

			case 'delete_all':
				$SQL = 'SELECT link_ID, link_key, link_title FROM '.$this->get_sql_table('data').'
						WHERE link_user_ID = '.$current_User->ID;

				if( $rows = $DB->get_results($SQL) )
				{
					$total_rows = count($rows);
					for( $i=0; $i < $total_rows; $i++ )
					{
						$this->delete_item( $rows[$i] );
					}
				}
				break;
		}
	}


// =========================
// Request and display

	function AdminEndHtmlHead()
	{
		$this->SkinBeginHtmlHead();
	}

	function SkinBeginHtmlHead()
	{
		$rsc = $GLOBALS['plugins_url'].$this->classname.'/rsc/';

		require_js('#jquery#');
		//require_js( $rsc.'jquery.zclip.js' );
/*
		add_js_headline('jQuery( function(){
			jQuery(".'.$this->code.'").mouseover( function() {
				var target_coupon_url = jQuery(this).find("a").attr("href");
				var coupon_code = jQuery(this).find(".'.$this->code.'-h-code").text();

				jQuery(this).zclip({
					path: "'.$rsc.'ZeroClipboard.swf",
					copy: coupon_code,
					mouseOver: function(e){ jQuery(this).find(".'.$this->code.'-tooltip").show() },
					mouseOut: function(e){ jQuery(this).find(".'.$this->code.'-tooltip").hide() },
					beforeCopy: function(e){ jQuery(this).find(".'.$this->code.'-tooltip").hide() },
					afterCopy: function(e){
						if( target_coupon_url ) { // Open target link if there is one
							window.open(target_coupon_url,"'.$this->code.'");
						} else { // Send an AJAX request to count the hit
							jQuery.get( "'.$this->get_ajax_url().'" + jQuery(this).find(".'.$this->code.'-h-key").text() );
						}
						jQuery(this).html("<span class=\"'.$this->code.'-code '.$this->code.'-code-revealed\">" + coupon_code + "</span>");
						jQuery(this).css("background-image", "none");
					}
				})
			});
		});');
*/

		add_js_headline('jQuery( function(){
			jQuery(".'.$this->code.'").click( function() {
				var target_coupon_url = jQuery(this).find("a").attr("href");
				var coupon_code = jQuery(this).find(".'.$this->code.'-h-code").text();

				if( target_coupon_url ) { // Open target link if there is one
					window.open(target_coupon_url,"'.$this->code.'");
				} else { // Send an AJAX request to count the hit
					jQuery.get( "'.$this->get_ajax_url().'" + jQuery(this).find(".'.$this->code.'-h-key").text() );
				}
				jQuery(this).html("<span class=\"'.$this->code.'-code '.$this->code.'-code-revealed\">" + coupon_code + "</span>");
				jQuery(this).css("background-image", "none");
			});
		});');

		require_css( $rsc.$this->code.'.css' );	// Add our css
	}


	function DisplayItemAsHtml( & $params )
	{
		$params['data'] = $this->replace_shortcode( $params['data'] );
		return true;
	}


	function DisplayItemAsXml( & $params )
	{
		return $this->DisplayItemAsHtml( $params );
	}


	function AdminDisplayEditorButton( $params )
	{
		global $edited_Item;

		$button_url = str_replace( '&amp;', '&', $this->get_htsrv_url('browse') ).'&item_ID='.$edited_Item->ID;

		echo '<input type="button" value="'.$this->T_('Add coupons').
				'" onclick="return pop_up_window( \''.$button_url.'\', \'add_coupons\', 750, 560, \'scrollbars=yes, status=yes, resizable=yes, menubar=no\' )" />';
	}


	// Sets all the display parameters
 	function init_display( $params = array() )
 	{
		$temp = $this->get_widget_param_definitions( array() );

		$full_params = array();
		foreach( $temp as $setting => $values )
		{
			$full_params[ $setting ] = ( isset( $params[ $setting ] ) ? $params[ $setting ] : $this->Settings->get( $setting ) );
		}

		foreach( $params as $param => $value )
		{
			if( !isset( $full_params[ $param ] ) ) $full_params[ $param ] = $value;
		}
		// return $this->disp_params;
		return $full_params;
	}


	function SkinTag( & $params )
	{
		// If the widget params aren't set up, using the plugin defaults
		$params = $this->init_display($params);
		$this->show_widget( $params );
		return true;
	}


	function show_widget( & $params )
	{
		global $Blog;

		$params = array_merge( array(
				'block_start'		=> '<div class="$wi_class$">',
				'block_end'			=> '</div>',
				'block_title_start'	=> '<h3>',
				'block_title_end'	=> '</h3>',
			), $params );

		$r  = $params['block_start']."\n";
		if( !empty($params['title']) )
		{
			$r .= $params['block_title_start'].$params['title'].$params['block_title_end']."\n";
		}
		$r .= $this->get_coupon( $params['key'], $params['style'], $params['coupon_class'], $params['template'] );
		$r .= $params['block_end']."\n";

		echo $r;
	}


	function htsrv_browse()
	{
		global $DB, $Session, $current_User, $app_name, $adminskins_path;

		echo $this->get_chicago_page( 'header', array(
						'checkboxes'	=> true,
						'title'			=> $this->name,
					) );

		if( ! is_logged_in() )
		{
			echo $this->T_('You\'re not allowed to view this page!');
			echo $this->get_chicago_page('footer');
			return;
		}

		memorize_param( 'plugin_ID', 'integer', '', $this->ID );
		memorize_param( 'method', 'string', '', 'browse' );
		$checkall = param( 'checkall', 'integer', 0 );  // Non-Javascript-CheckAll
		$item_ID = param( 'item_ID', 'integer', '', true );

		$selected_items = param( 'selected_items', 'array', array(), true );
		$option1 = param( 'option1', 'string' );
		$option2 = param( 'option2', 'string' );



		if( !empty($selected_items) )
		{	// Generate the code
			$keys = array_unique($selected_items);
			$cats = array();
			/*
			foreach( $keys as $l_key )
			{	// Delete keys if their category selected
				if( preg_match( '~^cat_([0-9]+)$~', $l_key, $matches ) )
				{
					$SQL = 'SELECT link_key FROM '.$this->get_sql_table('data').'
							WHERE link_user_ID = '.$DB->quote($current_User->ID).'
							AND link_cat_ID = '.$DB->quote($matches[1]).'
							AND link_status = 1';

					if( $col = $DB->get_col($SQL) )
					{
						$cats[] = $matches[1];
						$keys = array_diff( $keys, $col, array( $l_key ) );
					}
				}
			}

			if( count($cats) > 0 )
			{
				foreach($cats as $cat )
				{
					$embed_code = '[coupon: '.$cat."]\n";
				}
			}
			*/

			$opt2_match = preg_match('~^[a-z][a-z0-9_\-]+$~i', $option2);

			$opt = '';
			$options = array();
			if( $option1 != 'default' || $opt2_match )
			{
				$options[] = $option1;
			}
			if( $opt2_match )
			{
				$options[] = $option2;
			}
			if( !empty($options) )
			{	// Build options list
				$opt = ' '.implode( ' ', $options );
			}

			if( count($keys) > 0 )
			{

				foreach($keys as $key )
				{
					$embed_code[] = '[coupon: '.$key.$opt.']';
				}
			}

			if( !empty($embed_code) )
			{	// Format the code
				$embed_code_pre = implode("\n", $embed_code);
				$embed_code_js = format_to_output( implode("\\n", $embed_code), 'formvalue' );
			}
		}

		$Form = new Form( regenerate_url( 'selected_items,checkall,option1,option2', '', '', '&' ), 'BrowseItems', 'post' );
		$Form->begin_form();

		if( !empty($embed_code) )
		{
			echo '<div class="action_messages center"><div class="log_success"><pre style="padding:0; margin:0">'.$embed_code_pre.'</pre></div>';
			echo '<a href="#" onclick="if( window.focus && window.opener ){ window.opener.focus(); textarea_wrap_selection( window.opener.document.getElementById(\'itemform_post_content\'), \''.$embed_code_js.'\', \'\', 1, window.opener.document ); } return false;">'.$this->T_('Add the code to your post !').'</a></div>';
		}

		echo '<div class="whitebox_center">';
		echo '<div class="notes" style="margin-bottom:5px">'.$this->T_('Select the items and click "Embed into post" to get the embedding code for your post.').'</div>';
		echo '<div style="height:380px; overflow-y:scroll">';

		$SQL = 'SELECT * FROM '.$this->get_sql_table('data').'
				INNER JOIN '.$this->get_sql_table('cats').'
					ON link_cat_ID = cat_ID
				WHERE link_user_ID = '.$current_User->ID.'
				AND link_status = 1
				ORDER BY cat_name ASC';

		$Results = new Results( $SQL, 'sn_', '' );
		$Results->group_by = 'cat_ID';

		$Results->title = '<div style="float: right">Switch to <a href="'.$this->get_plugin_tab_url().'" target="_blank">'.$this->name.'</a></div>
							<div style="float: left">'.$this->T_('Coupons list').'</div>';

		$GLOBALS['CouponPlugin'] = & $this;

		function dl_results_td_cat( $row )
		{
			$r = '<span name="surround_check" class="checkbox_surround_init">';

			// Select a category
			/*
			$r .= '<input title="'.$GLOBALS['CouponPlugin']->T_('Select this category').'" type="checkbox" class="checkbox"
						name="selected_items[]" value="cat_'.$row->cat_ID.'" id="cb_item_cat_'.$row->cat_ID.'"';

			if( $GLOBALS['checkall'] )
			{
				$r .= ' checked="checked"';
			}
			$r .= ' />';
			*/
			$r .= '</span>';

			return $r.' <span style="color:#000">'.$row->cat_name.'</span>';
		}
		$Results->grp_cols[] = array(
				'td_colspan' => 4,  // nb_cols
				'td' => '% dl_results_td_cat( {row} ) %',
			);

		function dl_results_td_box( $row )
		{
			// Checkbox
			$r = '<span name="surround_check" class="checkbox_surround_init">';
			$r .= '<input title="'.$GLOBALS['CouponPlugin']->T_('Select this item').'" type="checkbox" class="checkbox"
						name="selected_items[]" value="'.$row->link_key.'" id="cb_item_'.$row->link_ID.'"';

			if( $GLOBALS['checkall'] )
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

		function dl_results_td_name( $row )
		{
			$title = strmaxlen( $row->link_title, 90 );

			if( empty($title) )
			{
				$title = strmaxlen( $row->link_description, 90 );
			}

			return '<div><span class="note"><i>'.$row->link_code.'</i> &rsaquo;&rsaquo; </span><b style="color:#333">'.$title.'</b></div>';
		}
		$Results->cols[] = array(
				'th' => '',
				'td' => '% dl_results_td_name( {row} ) %',
			);

		require_once $adminskins_path.'chicago/_adminUI.class.php';
		$AdminUI = new AdminUI();

		$Results->display( $AdminUI->get_template('Results') );

		$option1 = $Form->get_select_options_string( $this->get_template_params('names') );

		echo '</div>';
		echo '<div class="notes" style="margin-top:5px">
				<div style="float:right">'.$this->T_('Coupon style').': <select name="option1">'.$option1.'</select> | '.$this->T_('Coupon class').': <input size="7" name="option2" value="" /></div>
				<div>'.$Form->check_all().' '.$this->T_('Check/Uncheck all').'</div>
			  </div>';

		echo '</div>';

		$Form->end_form( array( array( 'submit', 'submit', $this->T_('Embed into post') ) ) );

		echo $this->get_chicago_page('footer');
	}


	function get_chicago_page( $what = 'header', $params = array() )
	{
		global $Hit, $adminskins_url;

		$params = array_merge( array(
				'title'			=> '',
				'require_js'	=> true,
				'jquery'		=> false,
				'checkboxes'	=> false,
			), $params );

		if( $what == 'header' )
		{
			global $evo_charset, $adminskins_url;

			ob_start();

			echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
				<html xmlns="http://www.w3.org/1999/xhtml">
				<head>
				<meta http-equiv="Content-Type" content="text/html; charset='.$evo_charset.'" />
				<meta http-equiv="Expires" content="Sun, 15 Jan 1998 17:38:00 GMT" />
				<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
				<title>'.$params['title'].'</title>';

				require_css( 'basic_styles.css', 'rsc_url' ); // the REAL basic styles
				require_css( 'basic.css', 'rsc_url' ); // Basic styles
				require_css( 'results.css', 'rsc_url' ); // Results/tables styles
				require_css( 'item_base.css', 'rsc_url' ); // Default styles for the post CONTENT
				require_css( 'fileman.css', 'rsc_url' ); // Filemanager styles
				require_css( 'admin.global.css', 'rsc_url' ); // Basic admin styles
				require_css( $adminskins_url.'chicago/rsc/css/chicago.css', true );

				if ( $Hit->is_IE() )
				{
					require_css( 'admin_global_ie.css', 'rsc_url' );
				}
				// CSS for IE9
				add_headline( '<!--[if IE 9 ]>' );
				require_css( 'ie9.css', 'rsc_url' );
				add_headline( '<![endif]-->' );

				add_css_headline('fieldset { margin:0 60px; padding:0; border:none !important }
				.fieldset { border:none !important }
				.embed { text-align:center }
				table.grouped tr.group td { color:#000 }
				.content { width: 70%; margin: 0 auto }
				.whitebox_center { margin:10px; padding:15px; background-color:#fff; border:2px #555 solid }');

			if( $params['require_js'] )
			{
				require_js( 'functions.js');
				require_js( 'form_extensions.js');
				require_js( 'rollovers.js' );
				require_js( 'dynamic_select.js' );
				require_js( 'admin.js' );

				if( $params['jquery'] ) require_js( '#jquery#' );

				if( $params['checkboxes'] )
				{
					add_js_headline('
					var allchecked = Array();
					var idprefix;

					function toggleCheckboxes(the_form, the_elements, set_name )
					{
						if( typeof set_name == "undefined" )
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
						var elems_cnt = (typeof(elems.length) != "undefined") ? elems.length : 0;
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
						if( typeof(allchecked[set_name]) == "undefined" || typeof(set) != "undefined" )
						{ // init
							allchecked[set_name] = set;
						}

						if( allchecked[set_name] )
						{
							var replace = document.createTextNode("uncheck all");
						}
						else
						{
							var replace = document.createTextNode("check all");
						}

						if( document.getElementById( idprefix+"_"+String(set_name) ) )
						{
							document.getElementById( idprefix+"_"+String(set_name) ).replaceChild(replace, document.getElementById( idprefix+"_"+String(set_name) ).firstChild);
						}
						// else alert("no element with id "+idprefix+"_"+String(set_name));
					}

					function initcheckall( htmlid, init )
					{
						// initialize array
						allchecked = Array();
						idprefix = typeof(htmlid) == "undefined" ? "checkallspan" : htmlid;

						for( var lform = 0; lform < document.forms.length; lform++ )
						{
							for( var lelem = 0; lelem < document.forms[lform].elements.length; lelem++ )
							{
								if( document.forms[lform].elements[lelem].id.indexOf( idprefix ) == 0 )
								{
									var index = document.forms[lform].elements[lelem].name.substring( idprefix.length+2, document.forms[lform].elements[lelem].name.length );
									if( document.getElementById( idprefix+"_state_"+String(index)) )
									{
										setcheckallspan( index, document.getElementById( idprefix+"_state_"+String(index)).checked );
									}
									else
									{
										setcheckallspan( index, init );
									}
								}
							}
						}
					}');
				}
			}

			include_headlines();
			echo '</head><body><div class="content"><div class="whitebox_center">';

			$out = ob_get_clean();
		}
		elseif( $what == 'footer' )
		{
			$out = '</div></div></body></html>';
		}

		return $out;
	}

// =========================
// Check user perms

	function check_perms()
	{
		if( !is_logged_in() )
		{	// Not logged in
			$this->msg( $this->T_('You\'re not allowed to view this page!'), 'error' );
			return false;
		}
		return true;
	}


// =========================
// Main functions

	function count_the_hit( $key )
	{
		global $DB, $current_User, $Hit, $localtimenow;

		if( !empty($Hit) && $Hit->get_agent_type() == 'robot' )
		{	// Do not count robot hits
			exit('robot');
		}

		if( !empty($key) )
		{	// "Short" url
			$key = trim($key);
		}
		elseif( !$key = trim(param('coupon', 'string')) )
		{	// "Call plugin" url
			$this->send_response();
		}

		if( !$Coupon = $this->get_Coupon_by_key($key) ) $this->send_response();

		// Do not log this hit into b2evo stats
		$this->_is_our_hit = true;

		// Temporarily unavailable
		if( $Coupon->link_status != 1 ) $this->send_response('disabled');

		// Check expiration date
		$date_end = strtotime($Coupon->link_date_end);
		if( !empty($date_end) && $date_end < $localtimenow ) $this->send_response('expired');

		// Usage limit exceeded
		if( $Coupon->link_click_limit > 0 )
		{
			if( $Coupon->link_clicks >= $Coupon->link_click_limit ) $this->send_response('limit', $Coupon);
		}

		if( $this->UserSettings->get( 'disabled_v', $Coupon->link_user_ID ) && !is_logged_in() )
		{	// links disabled for visitors
			$this->send_response('visitor');
		}

		if( $this->UserSettings->get( 'auto_prune_stats_mode', $Coupon->link_user_ID ) == 'page' )
		{	// Prune old hits
			$this->prune_hit_stats( $Coupon->link_user_ID );
		}

		if( is_object($current_User) )
		{
			$min_level = $this->UserSettings->get( 'user_level', $Coupon->link_user_ID );
			if( $current_User->level < $min_level )
			{
				$this->send_response( 'level', $Coupon, $min_level );
			}
		}

		// Increase click counter
		$DB->query( 'UPDATE '.$this->get_sql_table('data').'
					SET link_clicks = link_clicks + 1
					WHERE link_ID = '.$Coupon->link_ID );

		if( !empty($Hit) )
		{
			$user_ID = (is_object($current_User)) ? $current_User->ID : 0;

			$DB->query( 'INSERT INTO '.$this->get_sql_table('stats').'
							( stat_link_ID, stat_reg_user_ID, stat_remote_addr, stat_referer, stat_agnt_type, stat_user_agnt, stat_datetime )
						VALUES (
							'.$DB->quote( $Coupon->link_ID ).',
							'.$DB->quote( $user_ID ).',
							"'.$DB->escape( $Hit->IP ).'",
							"'.$DB->escape( $Hit->referer ).'",
							"'.$DB->escape( $Hit->referer_type ).'",
							"'.$DB->escape( $Hit->get_user_agent() ).'",
							'.$DB->quote( date('Y-m-d H:i:s', $localtimenow) ).')' );
		}

		if( $Coupon->link_url )
		{	// Redirect to the target URL
			header( 'HTTP/1.1 303 See Other' );
			header( 'Location: '.$Coupon->link_url, true, 303 );
		}
		exit(0);
	}


	function prune_hit_stats( $user_ID )
	{
		global $DB;

		$SQL = 'DELETE FROM '.$this->get_sql_table('stats').'
				USING '.$this->get_sql_table('stats').'
				INNER JOIN '.$this->get_sql_table('data').' ON stat_link_ID = link_ID
				WHERE link_user_ID = '.$user_ID;

		$pruned = $DB->query( $SQL, 'Pruning stats ('.$this->name.')' );
		if( $pruned > 0 )
		{
			$code = 'success';
			$message = sprintf( $this->T_('Successfully pruned %d records!'), $pruned );
		}
		else
		{
			$code = 'note';
			$message = $this->T_('Nothing to prune.');
		}

		return array($code, $message);
	}


	// Display messages
	function send_response( $type = 0, $row = NULL, $param = NULL )
	{
		global $htsrv_url, $ReqHost, $ReqURI;

		switch( $type )
		{
			case 'disabled': // Disabled
				header('HTTP/1.1 404 Not Found');
				$title = '404 Not Found';
				$msg = 'ERROR: This coupon is temporarily unavailable.';
				break;

			case 'visitor': // Link disabled for visitors
				header('HTTP/1.1 403 Forbidden');
				$title = '403 Forbidden';
				$msg = sprintf( 'ERROR: You must be logged in to follow this link.<br />Please <a %s>log in</a> to continue.',
							'href="'.$htsrv_url.'login.php?redirect_to='.urlencode($ReqHost.$ReqURI).'"' );
				break;

			case 'level': // Low user level
				header('HTTP/1.1 403 Forbidden');
				$title = '403 Forbidden';
				$msg = sprintf( 'ERROR: You must have at least level %d to follow this link.', $param );
				$this->notify_user( $row, $msg );
				break;

			case 'limit': // Clicks limit exceeded
				header('HTTP/1.1 403 Forbidden');
				$title = '403 Forbidden';
				$msg = 'ERROR: Clicks limit exceeded for this coupon.';
				$this->notify_user( $row, $msg );
				break;

			case 'expired': // Expired
				header('HTTP/1.1 403 Forbidden');
				$title = '403 Forbidden';
				$msg = 'ERROR: This coupon has expired.';
				break;

			default: // Not found
				header('HTTP/1.1 404 Not Found');
				$title = '404 Not Found';
				$msg = 'ERROR: Invalid coupon key.';
				if( $this->notify_user( $row, $msg ) )
				{
					$msg .= '<br /><br />The author of this coupon has been notified, and this problem will be solved as soon as possible.';
				}
		}

		add_css_headline('.content { width: 400px }');
		echo $this->get_chicago_page( 'header', array('require_js' => false) );

		if( !empty($msg) ) echo '<p class="red">'.$msg.'</p>';
		if( !empty($body) ) echo $body;

		echo $this->get_chicago_page('footer');

		exit();
	}


	// Prevent Hit logging
	function AppendHitLog( & $params )
	{
		if( $this->_is_our_hit )
		{	// Don't log the Hit, we don't want to waste b2evo stats
			return true;
		}
	}


// =========================
// Template functions

	function get_all( $limit = 50, $echo = true )
	{
		global $DB, $Blog, $Item;

		if( is_object($Blog) ) $user_ID = $Blog->owner_user_ID; // Blog owner ID
		if( is_object($Item) ) $user_ID = $Item->creator_user_ID; // Item creator ID
		if( !is_numeric($limit) ) $limit = 20;

		if( empty($user_ID) )
		{	// We can't find a user, display the message and exit
			echo 'Coupon plugin: User not found!';
			return;
		}

		$SQL = 'SELECT link_key FROM '.$this->get_sql_table('data').'
				WHERE link_user_ID = '.$DB->quote($user_ID).'
				AND link_status = 1
				ORDER BY link_cat_ID ASC
				LIMIT 0,'.$DB->quote($limit);

		if( $keys = $DB->get_col($SQL) )
		{
			$r = array();
			foreach( $keys as $key )
			{
				$r[] = $this->get_coupon( $key );
			}
			if( $echo ) echo implode("\n", $r);
			return implode("\n", $r);;
		}
	}


	function replace_shortcode( $content )
	{	// [coupon: KEY SIZE CLASS]
		return preg_replace( '~\[coupon:\s+([A-Za-z0-9]{5,32})(\s+([A-Za-z]+))?(\s+([A-Za-z_\-]+))?\]~ise', '$this->get_coupon( "\\1", "\\3", "\\5" )', $content );
	}


	function get_coupon( $key, $style = '', $class = '', $body = '' )
	{
		global $DB, $plugins_url;

		$r = sprintf( $this->T_('Invalid coupon code [%s]'), $key );

		if( $row = $this->get_Coupon_by_key($key) )
		{
			if( $row->link_status )
			{
				// Increase views counter
				$DB->query( 'UPDATE '.$this->get_sql_table('data').'
							SET link_views = link_views + 1
							WHERE link_ID = '.$row->link_ID );

				if( empty($style) || empty($body) )
				{
					$style = empty($style) ? 'default' : $style;
					$coupon_body = $row->link_template;
				}
				elseif( $template = $this->get_template_params($style) )
				{
					$coupon_body = empty($body) ? $template['template'] : $body;
				}

				$url = $this->get_url($row);

				$img = 'click-to-claim.png'; // Click to open
				if( empty($url) ) $img = 'click-to-reveal.png'; // Click to copy

				$code				= '<span class="'.$this->code.'-code">'.$row->link_code.'</span>';
				$link_code			= empty($url) ? $code : '<a href="'.$url.'" class="'.$this->code.'-link-code">'.$code.'</a>';

				$clicks				= '<span class="'.$this->code.'-clicks">'.$row->link_clicks.'</span>';
				$link_clicks		= empty($url) ? $clicks : '<a href="'.$url.'" class="'.$this->code.'-link-clicks">'.$clicks.'</a>';

				$click_limit		= '<span class="'.$this->code.'-click-limit">'.$row->link_click_limit.'</span>';
				$link_click_limit	= empty($url) ? $click_limit : '<a href="'.$url.'" class="'.$this->code.'-link-clicks-limit">'.$click_limit.'</a>';

				$views				= '<span class="'.$this->code.'-views">'.$row->link_views.'</span>';
				$link_views			= empty($url) ? $views : '<a href="'.$url.'" class="'.$this->code.'-link-views">'.$views.'</a>';

				$title				= '<span class="'.$this->code.'-title">'.$row->link_title.'</span>';
				$link_title			= empty($url) ? $title : '<a href="'.$url.'" class="'.$this->code.'-link-title">'.$title.'</a>';

				$description		= '<span class="'.$this->code.'-description">'.$row->link_description.'</span>';
				$link_description	= empty($url) ? $description : '<a href="'.$url.'" class="'.$this->code.'-link-description">'.$description.'</a>';

				$h_key				= '<span class="'.$this->code.'-h-key" style="display:none">'.$row->link_key.'</span>';
				$h_code				= '<span class="'.$this->code.'-h-code" style="display:none">'.$row->link_code.'</span>';
				$h_tooltip			= '<span class="'.$this->code.'-tooltip"><img src="'.$plugins_url.$this->classname.'/rsc/'.$img.'" alt="" /></span>';

				if( !empty($class) )
				{
					$class = $this->code.'-'.$class;
				}

				$r = '<div class="'.$this->code.' '.$this->code.'-'.$style.' '.$class.'">';
				// Let's build the block
				$r .= str_replace(
						array( '$url$','$code$','$link-code$','$clicks$','$link-clicks$','$click-limit$','$link-click-limit$','$views$','$link-views$','$title$','$link-title$','$description$','$link-description$' ),
						array( $url, $code, $link_code, $clicks, $link_clicks, $click_limit, $link_click_limit, $views, $link_views, $title, $link_title, $description, $link_description ),
						$coupon_body
					);
				$r .= $h_key.$h_code.$h_tooltip;
				$r .= '</div>';
			}
			else
			{
				$r = $this->T_('This coupon is temporarily unavailable');
			}
		}

		if( !empty($r) ) return $r;;
	}


	function get_url( $row )
	{
		global $baseurl;

		if( ! $row->link_url ) return;

		if( ! $row->link_mask_url )
		{
			$url = $row->link_url;
		}
		elseif( $this->Settings->get('nice_permalinks') )
		{
			$url = $baseurl.$this->url_prefix.'/'.$row->link_key;
		}
		else
		{
			$url = url_add_param( $baseurl.'index.php', $this->url_prefix.'='.$row->link_key );
		}

		return $url;
	}


	function get_ajax_url()
	{
		return url_add_param( $GLOBALS['baseurl'].'index.php', $this->url_prefix.'=' );
	}


	function get_plugin_tab_url( $glue = '&' )
	{
		return $GLOBALS['admin_url'].'?ctrl=tools'.$glue.'tab=plug_ID_'.$this->ID;
	}


	function get_mysql_date( $string )
	{
		$ts = strtotime($string);
		if( empty($ts) ) return '';

		return date2mysql( strtotime($string) );
	}


	function get_template_params( $what = 'names', $param = NULL )
	{
		if( $what == 'names' )
		{
			$r = array();
			foreach( $this->template_styles as $key => $value )
			{
				$r[$key] = $value['name'];
			}
		}
		else
		{
			if( ! isset($this->template_styles[$what]) ) $what = 'default'; // use default

			// Get all params
			$r = $this->template_styles[$what];

			if( !is_null($param) )
			{	// Get particular param
				$r = $r[$param];
			}
		}

		return $r;
	}

// =========================
// Misc functions

	function delete_item( $Coupon )
	{
		global $DB;

		if( $DB->query('DELETE FROM '.$this->get_sql_table('data').' WHERE link_ID = '.$Coupon->link_ID) )
		{
			$this->msg( $this->T_('Coupon settings deleted.'), 'success' );
		}
		else
		{
			$this->msg( $this->T_('Unable to delete coupon settings.'), 'error' );
		}

		if( $DB->query('DELETE FROM '.$this->get_sql_table('stats').' WHERE stat_link_ID = '.$Coupon->link_ID) )
		{
			$this->msg( $this->T_('Coupon hits deleted.'), 'success' );
		}
		else
		{
			$this->msg( $this->T_('Unable to delete coupon hits. No hits yet?'), 'note' );
		}

		return true;
	}


	function delete_category( $Category, $move_items = true )
	{
		global $DB, $Messages, $UserSettings, $current_User;

		// Get default category ID
		$default_cat_ID = $UserSettings->get($this->code.'_default_cat_ID');

		if( $Category->cat_ID == $default_cat_ID || (empty($default_cat_ID) && $Category->cat_ID == 1) )
		{
			$this->msg( $this->T_('You can\'t delete the default category.'), 'error' );
			return;
		}

		$linked = array();
		if( $rows = $this->get_cat_items( $Category->cat_ID, $current_User->ID ) )
		{
			foreach( $rows as $Coupon )
			{
				if( $move_items )
				{	// Lets move the item to default category
					if( empty($default_cat_ID) || !is_integer($default_cat_ID) )
					{
						$this->msg( $this->T_('Unable to move items (default category not found).'), 'error' );
						return;
					}

					$SQL = 'UPDATE '.$this->get_sql_table('data').' SET
								link_cat_ID = '.$DB->quote($default_cat_ID).'
							WHERE link_ID = '.$DB->quote($Coupon->link_ID);

					if( $DB->query($SQL) )
					{
						$this->msg( sprintf( $this->T_('Item &laquo;%s&raquo; moved to default category.'), $Coupon->link_title ), 'success' );
					}
					else
					{
						$this->msg( sprintf( $this->T_('Unable to move item &laquo;%s&raquo;.'), $Coupon->link_title ), 'error' );
					}
				}
				else
				{
					$linked[] = '['.$Coupon->link_ID.'] '.$Coupon->link_title;
				}
			}
		}

		if( !empty($linked) && !$move_items )
		{
			$Messages->head = array(
					'container' => sprintf( $this->T_('Cannot delete category &laquo;%s&raquo;'), $Category->cat_name ),
					'restrict' => $this->T_('The following relations prevent deletion:')
				);
			$Messages->foot = sprintf( $this->T_('All items will be moved to default category! Click <a %s>here</a> if you still want to continue?'), 'href="'.regenerate_url( $this->code.'_cat_ID,action,confirm', $this->code.'_cat_ID='.$Category->cat_ID.'&amp;action=delete_cat&amp;confirm=1' ).'"' );

			$this->msg( sprintf( $this->T_('%d items within category'), count($linked) ), 'note' );

			return;
		}

		if( !$Messages->count('error') )
		{	// Delete only if we moved items without errors
			if( $DB->query('DELETE FROM '.$this->get_sql_table('cats').' WHERE cat_ID = '.$Category->cat_ID) )
			{
				$this->msg( sprintf( $this->T_('Category deleted &laquo;%s&raquo;'), $Category->cat_name ), 'success' );
			}
			else
			{
				$this->msg( $this->T_('Unable to delete category.'), 'error' );
			}
		}
		else
		{
			$this->msg( $this->T_('Please fix the above errors before deleting this directory.'), 'error' );
		}

		return;
	}


	function get_Coupon_from_param()
	{
		global $DB, $current_User;

		if( $ID = param( $this->code.'_link_ID', 'integer' ) )
		{
			$SQL = 'SELECT * FROM '.$this->get_sql_table('data').'
					WHERE link_ID = '.$DB->quote( $ID ).'
					AND link_user_ID = '.$current_User->ID;

			if( $row = $DB->get_row($SQL) ) return $row;
		}
		return false;
	}


	function get_Category_from_param()
	{
		global $DB, $current_User;

		if( $ID = param( $this->code.'_cat_ID', 'integer' ) )
		{
			$SQL = 'SELECT * FROM '.$this->get_sql_table('cats').'
					WHERE cat_ID = '.$DB->quote( $ID ).'
					AND cat_user_ID = '.$current_User->ID;

			if( $row = $DB->get_row($SQL) ) return $row;
		}
		return false;
	}


	function get_Coupon_by_key( $key = NULL )
	{
		global $DB;

		$key = trim($key);

		if( empty($key) ) return false;

		// Basic injection filter
		if( preg_match( '/[^A-Za-z0-9]/', $key ) ) return false;

		$SQL = 'SELECT * FROM '.$this->get_sql_table('data').'
				WHERE link_key = "'.$DB->escape( $key ).'"';

		if( $row = $DB->get_row($SQL) )
		{
			return $row;
		}
		return false;
	}


	function get_cat_items( $cat_ID = NULL, $user_ID = NULL )
	{
		global $DB;

		$cat_ID = trim($cat_ID);

		if( !is_numeric($user_ID) ) return false;
		if( !is_numeric($cat_ID) ) return false;

		$SQL = 'SELECT * FROM '.$this->get_sql_table('data').'
				WHERE link_cat_ID = '.$DB->quote( $cat_ID ).'
				AND link_user_ID = '.$DB->quote( $user_ID );

		if( $rows = $DB->get_results($SQL) )
		{
			return $rows;
		}
		return false;
	}


	function disp_item_fields( & $Form, $Item = NULL )
	{
		$link_cat_ID		= empty($Item) ? 0  : $Item->link_cat_ID;
		$link_code			= empty($Item) ? '' : $Item->link_code;
		$link_title			= empty($Item) ? '' : $Item->link_title;
		$link_description	= empty($Item) ? '' : $Item->link_description;
		$link_url			= empty($Item) ? '' : $Item->link_url;
		$link_mask_url		= empty($Item) ? 1  : $Item->link_mask_url;
		$link_click_limit	= empty($Item) ? 0  : $Item->link_click_limit;
		$link_date_end		= empty($Item) ? '' : $this->get_mysql_date( $Item->link_date_end );
		$link_template		= empty($Item) ? $this->get_template_params('default', 'template') : $Item->link_template;

		$opt = array();
		foreach( $this->_categories as $Category )
		{
			$sel = '';
			if( $link_cat_ID == $Category->cat_ID ) $sel = ' selected="selected"';
			$opt[] = '<option value="'.$Category->cat_ID.'"'.$sel.'>'.$Category->cat_name.'</option>';
		}
		$cat_options = implode( "\n", $opt );
		$Form->select_input_options( $this->get_class_id('link_cat_ID'), $cat_options, $this->T_('Category'),
				sprintf( $this->T_('Select a category. <a %s>Manage categories &raquo;</a>'), 'href="'.regenerate_url( 'action', 'action=view_cats' ).'"' ), array( 'maxlength'=>255 ) );
		$Form->text_input( $this->get_class_id('link_code'), $link_code, 30, $this->T_('Code'),
				$this->T_('Specify coupon code [ex. <b>CODE12345</b>]'), array( 'maxlength'=>255 ) );
		$Form->text_input( $this->get_class_id('link_title'), $link_title, 30, $this->T_('Title'),
				$this->T_('Specify the title of your coupon [ex. <b>Free Delivery</b>]'), array( 'maxlength'=>255 ) );
		$Form->textarea( $this->get_class_id('link_description'), $link_description, 2, $this->T_('Description'),
				$this->T_('Specify coupon description [ex. <b>Get free delivery with your 1st purchase</b>]'), 60 );
		$Form->textarea( $this->get_class_id('link_template'), $link_template, 2, $this->T_('Template'),
				sprintf( $this->T_('Enter coupon template code here, you can use any combination of replacement variables listed below.<br /><br />Replacement values: %s.'), '$code$, $link-code$, $title$, $link-title$, $description$, $link-description$, $clicks$, $link-clicks$, $click-limit$, $link-click-limit$, $views$, $link-views$, $url$' ), 60 );
		$Form->textarea( $this->get_class_id('link_url'), $link_url, 2, $this->T_('URL'),
				$this->T_('Specify the URL to direct the user to'), 60 );
		$Form->checkbox( $this->get_class_id('link_mask_url'), $link_mask_url, $this->T_('Mask URL'),
				$this->T_('Check this to mask target coupon URL. Clicks are only counted on masked URLs or when JavaScript is enabled.') );
		$Form->text_input( $this->get_class_id('link_click_limit'), $link_click_limit, 9, $this->T_('Usage limit'),
				$this->T_('Disable this coupon if the number of clicks exceeds this value.') );
		$Form->text_input( $this->get_class_id('link_date_end'), $link_date_end, 20, $this->T_('URL expiration date'),
				sprintf( $this->T_('Expired coupon link will be disabled. See %s for details'), '<a  target="_blank" href="http://www.php.net/manual/en/function.strtotime.php">strtotime()</a>' ).' [ex. <b>"12/31/2011"</b>, <b>"+ 30 days"</b>].' );
	}


	function gen_key( $length = 10 )
	{
		global $DB;

		$key = generate_random_key( $length, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' );
		$SQL = 'SELECT link_ID FROM '.$this->get_sql_table('data').'
				WHERE link_key = '.$DB->quote($key);

		if( $DB->get_var($SQL) )
		{
			return $this->gen_key($length);
		}
		return $key;
	}


	function get_cats()
	{
		global $DB, $current_User;

		$SQL = 'SELECT * FROM '.$this->get_sql_table('cats').'
				WHERE cat_user_ID = '.$DB->quote($current_User->ID).'
				ORDER BY cat_name ASC';

		$this->_categories = $DB->get_results($SQL);
	}


	function notify_user( $Row, $msg = '', $subject = '' )
	{
		global $UserCache, $current_User, $Hit, $notify_from, $baseurl, $admin_url, $app_version;

		if( !is_object($Row) ) return false;

		// Notifications disabled
		if( !$this->UserSettings->get( 'notify_user', $Row->link_user_ID ) ) return false;

		// User not found
		if( !$User = & $UserCache->get_by_ID( $Row->link_user_ID, false, false ) ) return false;

		$from_name = $this->name;
		$to_name = $User->get_preferred_name();

		if( empty($subject) )
		{
			$subject = 'Error detected in coupon: '.$Row->link_title.' [key: '.$Row->link_key.']';
		}

		$message = '';
		if( is_logged_in() )
		{
			$message .= 'User: '.$current_User->get_preferred_name().' ('.$current_User->login.')'."\n";
			$message .= 'User ID: '.$current_User->ID."\n";
		}
		if( is_object($Hit) )
		{
			$message .= 'User IP: '.$Hit->IP."\n";
			$message .= 'Referrer: '.$Hit->referer."\n";
		}
		$message .= "\n".$msg."\n";
		$message .= 'Edit this coupon: '.$this->get_plugin_tab_url().'&'.$this->code.'_link_ID'.'='.$Row->link_ID.'&action=edit';
		$message .= "\n\n-- \n";
		$message .= 'You can edit plugin settings if you don\'t want to receive messages from '.$from_name."\n";
		$message .= 'Unsubscribe URL: '.$admin_url.'?ctrl=users&user_ID='.$User->ID;;

		return send_mail( $User->email, $to_name, $subject, $message, $notify_from, $from_name );
	}
}

?>