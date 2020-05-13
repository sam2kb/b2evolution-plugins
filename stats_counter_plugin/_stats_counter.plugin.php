<?php
/**
 * This file implements the Stats counter plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2012 by Sonorth Corp. - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Sonorth Corp.
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class stats_counter_plugin extends Plugin
{
	var $name = 'Stats counter';
	var $code = 'stats_counter';
	var $priority = 30;
	var $version = '0.0.1';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=16492';

	var $min_app_version = '4';
	var $max_app_version = '';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;

	// Internal
	var $hide_tab = false;


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Counts website visitors');
		$this->long_desc = $this->short_desc;
	}


	function GetDbLayout()
	{
		return array(
			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('data')." (
					stat_ID INT(11) NOT NULL auto_increment,
					stat_counter INT(11) NOT NULL,
					stat_date DATE NOT NULL default '2000-01-01',
					PRIMARY KEY (stat_ID)
				)",
		);
	}


	function GetCronJobs( & $params )
	{
		return array(
			array(
				'name' => $this->T_('Update stats').' ('.$this->name.')',
				'ctrl' => $this->code,
			),
		);
	}


	function ExecCronJob( & $params )
	{
		global $DB;

		$SQL = 'INSERT IGNORE INTO '.$this->get_sql_table('data').' (stat_counter, stat_date)
					SELECT COUNT(*), sess_lastseen_ts
					FROM T_sessions
					WHERE sess_lastseen_ts = DATE_SUB(CURDATE(),INTERVAL 1 DAY)';
echo $SQL;
		$DB->query($SQL);

		$code = 1;
		$message = $this->T_('Visits recorded');

		return array( 'code' => $code, 'message' => $message );
	}


	function AdminAfterMenuInit()
	{
		$this->register_menu_entry($this->name);
	}


	function AdminTabAction()
	{
		$this->check_perms();
		if( $this->hide_tab == 1 ) return;
	}


	function AdminTabPayload()
	{
		global $DB;

		if( $this->hide_tab == 1 ) return;

		$SQL = 'SELECT * FROM '.$this->get_sql_table('data');

		$Results = new Results( $SQL, 'cntr_', 'D-' );
		$Results->title = $this->T_('Website visitors stats');

		$Results->cols[] = array(
				'th' => $this->T_('Date'),
				'td' => '$stat_date$',
				'th_class' => 'shrinkwrap',
				'td_class' => 'timestamp',
				'order' => 'stat_date',
			);

		$Results->cols[] = array(
				'th' => $this->T_('Visits'),
				'td' => '$stat_counter$',
				'th_class' => 'shrinkwrap',
				'td_class' => 'shrinkwrap',
				'order' => 'stat_counter',
			);

		$Results->display();
	}


	function check_perms()
	{
		global $current_User;

		$this->hide_tab = 1;

		if( !is_logged_in() )
		{
			$this->msg( $this->T_('You\'re not allowed to view this page!'), 'error' );
			return;
		}

		if( !$current_User->check_perm( 'options', 'edit' ) )
		{
			$this->msg( $this->T_('You\'re not allowed to view this page!'), 'error' );
			return;
		}

		$this->hide_tab = 0;
	}
}

?>