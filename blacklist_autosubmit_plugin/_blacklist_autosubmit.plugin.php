<?php
/**
 * This file implements the Blacklist autosubmit plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2012 Russian b2evolution - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Alex (sam2kb)
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class blacklist_autosubmit_plugin extends Plugin
{
	var $name = 'Blacklist autosubmit';
	var $code = 'blstsubmit';
	var $priority = 50;
	var $version = '1.1.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=';

	var $min_app_version = '1';
	var $max_app_version = '5';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;
	var $hide_tab = 0;


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Automatically send new commetns to b2evolution.net centralized antispam blacklist.');
		$this->long_desc = $this->short_desc;
	}


	function GetDependencies()
	{
		return array(
				'requires' => array(
					'app_min' => '1.0',
				),
			);
	}


	function AdminAfterMenuInit()
	{
		$this->register_menu_entry($this->name);
	}


	function ExecCronJob( & $params )
	{	// We provide only one cron job, so no need to check $params['ctrl'] here.
		global $DB, $Messages;

		$code = 1;
		$message = array('Completed<br />');

		global $debug, $antispamsrv_host, $antispamsrv_port, $antispamsrv_uri, $antispam_test_for_real;
		global $baseurl, $Messages, $Settings;

		if( preg_match( '#^http://localhost[/:]#', $baseurl) && ( $antispamsrv_host != 'localhost' ) && empty( $antispam_test_for_real )  )
		{ // Local install can only report to local test server
			$message = T_('Reporting abuse to b2evolution aborted (Running on localhost).');
			$code = 0;
			return;
		}

		// Construct XML-RPC client:
		$this->load_funcs('xmlrpc');
		$this->set_max_execution_time(600);

		$client = new xmlrpc_client( $antispamsrv_uri, $antispamsrv_host, $antispamsrv_port);
		$client->debug = $debug;

		foreach( $params['params']['words'] as $abuse_string )
		{
			// Construct XML-RPC message:
			$result = $client->send( new xmlrpcmsg(
										'b2evo.reportabuse',                        // Function to be called
										array(
											new xmlrpcval(0,'int'),                   // Reserved
											new xmlrpcval('annonymous','string'),     // Reserved
											new xmlrpcval('nopassrequired','string'), // Reserved
											new xmlrpcval($abuse_string,'string'),    // The abusive string to report
											new xmlrpcval($baseurl,'string'),         // The base URL of this b2evo
										)
									) );

			if( $ret = xmlrpc_logresult( $result, $Messages, true ) )
			{	// Remote operation successful:
				$message[] = sprintf( $this->T_('Reported abuse [ %s ]'), $abuse_string );
			}
			else
			{
				$message[] = sprintf( $this->T_('Failed to report abuse [ %s ]'), $abuse_string );
			}
			$message[] = $Messages->get_string( '', '', 'all', '', '' ).'<hr />';

			$Messages->clear('all');

			@sleep( rand(2,4) ); // don't abuse b2evo servers
		}

		if( is_array($message) )
		{
			$message = implode( '<br />', $message );
		}

		return array(
				'code' => $code,
				'message' => $message,
			);
	}


	function AdminTabPayload()
	{
		global $DB, $app_version;

		$this->check_perms();
		if( $this->hide_tab == 1 ) return;

		$b2evo_version = substr( $app_version, 0, 1 );

		$status_opts = $this->get_form_options( array(
				'draft' => $this->T_('Draft'),
				'published' => $this->T_('Published'),
				'any' => $this->T_('All (warning)'),
			) );

		$url_opts = $this->get_form_options( array(
				'any' => $this->T_('All'),
				'null' => $this->T_('No URL'),
				'not-null' => $this->T_('With URL'),
			) );

		$limit = param( $this->get_class_id('limit'), 'integer', 500 );

		if( param( $this->get_class_id('start_processing'), 'integer' ) )
		{	// Get values
			$dry_run = param( $this->get_class_id('dry_run'), 'integer' );
			$blacklist = param( $this->get_class_id('blacklist'), 'integer' );
			$report = param( $this->get_class_id('report'), 'integer' );
			$move_to_trash = param( $this->get_class_id('move_to_trash'), 'integer' );
			$anonymous = param( $this->get_class_id('anonymous'), 'integer' );
		}
		else
		{	// Set defaults
			$dry_run = $blacklist = $report = $move_to_trash = $anonymous = true;
		}

		$Form = new Form( 'admin.php', '', 'post' );
		$Form->begin_form( 'fform' );
		$Form->hidden_ctrl();
		$Form->hiddens_by_key( get_memorized() );
		$Form->hidden( $this->get_class_id('start_processing'), true );

		$Form->begin_fieldset($this->name);
		$Form->checkbox( $this->get_class_id('dry_run'), $dry_run, $this->T_('Dry run'), '<span class="red">'.$this->T_('Dry run only, preview deleted comments.').'</span>' );

		echo '<br />';
		$Form->checkbox( $this->get_class_id('blacklist'), $blacklist, $this->T_('Blacklist locally'), $this->T_('Add to local antispam blacklist.') );
		$Form->checkbox( $this->get_class_id('report'), $report, T_('Report to central blacklist'), T_('When banning a keyword, offer an option to report to the central blacklist.').' [<a href="http://b2evolution.net/about/terms.html">'.T_('Terms of service').'</a>]' );
		if( $b2evo_version >= 4 )
		{
			$Form->checkbox( $this->get_class_id('move_to_trash'), $move_to_trash, $this->T_('Move to trash'), $this->T_('If checked, comments will be moved to trash. Uncheck to delete them permanently.') );
		}
		echo '<br />';

		$Form->checkbox( $this->get_class_id('anonymous'), $anonymous, $this->T_('Anonymous only'), $this->T_('Process anonymous comments only.') );
		$Form->select_options( $this->get_class_id('status'), $status_opts, $this->T_('Filter by comment status'), $this->T_('Process comments with this status only.') );
		$Form->select_options( $this->get_class_id('url'), $url_opts, $this->T_('Filter by comment URL'), $this->T_('Process comments with or without author URL.') );
		$Form->text( $this->get_class_id('post_ID_list'), param( $this->get_class_id('post_ID_list'), 'string' ), 40, $this->T_('Filter by comment posts'), $this->T_('List post IDs separated by comma.') );
		$Form->text( $this->get_class_id('limit'), $limit, 6, $this->T_('Limit comments'), $this->T_('Enter the number of comments to process.') );
		$Form->end_fieldset();

		$Form->buttons( array( array( 'value' => $this->T_('Blacklist and delete comments!'), 'onclick' => 'return confirm(\''.$this->T_('Do you really want to continue?').'\')' ) ) );


		// Results
		if( $status = param( $this->get_class_id('status'), 'string' ) )
		{
			$limit = param( $this->get_class_id('limit'), 'integer' );
			$url = param( $this->get_class_id('url'), 'string' );

			$post_ID_list = param( $this->get_class_id('post_ID_list'), 'string' );
			$post_ID_list = $this->sanitize_id_list( $post_ID_list, false, true );

			$SQL = 'SELECT * FROM T_comments
				WHERE comment_ID > 0'; // dummy stuff

			if( $anonymous )
			{
				$SQL .= ' AND comment_author_ID IS NULL';
			}
			if( $status != 'any' )
			{
				if( $status == 'published' )
					$SQL .= ' AND comment_status = "published"';
				else
					$SQL .= ' AND comment_status = "draft"';
			}
			if( $url != 'any' )
			{
				if( $url == 'null' )
					$SQL .= ' AND (comment_author_url IS NULL OR comment_author_url = "")';
				else
					$SQL .= ' AND (comment_author_url IS NOT NULL AND comment_author_url != "")';
			}
			if( !empty($post_ID_list) )
			{	// posts
				$SQL .= ' AND comment_post_ID IN ('.$post_ID_list.')';
			}
			if( !empty($limit) )
			{
				$SQL .= ' LIMIT '.$limit;
			}

			//echo $SQL;die;

			if( $b2evo_version >= 4 )
			{	// We need to instanciate a Comment object in v4.0 or higher
				$init_comment = true;
			}

			$deleted = $abuse_urls = $abuse_emails = array();
			if( $rows = $DB->get_results($SQL) )
			{
				$this->load_funcs('antispam');
				$this->set_max_execution_time(600);

				$Form->begin_fieldset('Results preview');
				echo '<table class="grouped" cellspacing="0">';

				$count = 0;
				foreach( $rows as $row )
				{
					$abuse_urls[] = get_ban_domain($row->comment_author_url);

					if( $b2evo_version == 1 )
					{
						$editurl = 'admin.php?ctrl=edit&amp;action=editcomment&amp;comment='.$row->comment_ID;
					}
					elseif( $b2evo_version > 1 )
					{
						$editurl = 'admin.php?ctrl=comments&amp;action=edit&amp;comment_ID='.$row->comment_ID;
					}

					echo '<tr class="'.(($count%2 == 1) ? 'odd' : 'even').'">';
					echo '<td class="firstcol shrinkwrap timestamp"><a href="'.$editurl.'" title="'.$this->T_('Edit this comment').'" target="_blank">'.mysql2localedatetime_spans( $row->comment_date ).'</a></td>';
					echo '<td>'.$row->comment_author_email.'</td>';
					echo '<td>'.$this->get_short_url( $row->comment_author_url, 40 ).'</td>';
					echo '<td>'.substr( strip_tags($row->comment_content), 0, 60 ).'</td>';
					echo '</tr>';

					$count++;

					if( ! $dry_run )
					{
						// Delete
						if( isset($init_comment) )
						{
							$Comment = new Comment($row);

							if( ! $move_to_trash )
							{	// Set status to 'trash' in order to delete comments permanently
								$Comment->status = 'trash';
							}
							$deleted[] = (bool) $Comment->dbdelete();
						}
						else
						{	// Record deleted comment IDs and process them later with one query
							$deleted[] = $row->comment_ID;
						}
					}
				}

				$abuse_urls = array_unique( array_filter($abuse_urls) );

				$dry_run_msg = '';
				if( $dry_run )
				{
					$dry_run_msg = '<span style="color:red">Note, this is only a dry run, we have not applied any changes!</span>';
				}
				elseif( $blacklist )
				{
					if( !empty($abuse_urls) )
					{
						// Insert into DB:
						$sql_values = array();
						foreach( $abuse_urls as $abuse_string )
						{
							$sql_values[] = '( "'.$DB->escape($abuse_string).'", "local" )';
						}
						$SQL = 'INSERT IGNORE INTO T_antispam( aspm_string, aspm_source ) VALUES '.implode(', ', $sql_values);
						$DB->query($SQL);

						if( $report )
						{	// We want to send URLs to b2evolution.net
							// Don't send all requests together since that will most likely kill b2evo server,
							// instead we will schedule tasks to process abusive strings in chunks of 5

							global $servertimenow;

							$this->load_cron_job_class();

							$chunks = array_chunk( $abuse_urls, 5 );
							$total_tasks = count($chunks);
							for( $task=0; $task < $total_tasks; $task++ )
							{
								$chunk = $chunks[$task];

								$edited_Cronjob = new Cronjob();
								$edited_Cronjob->set( 'start_datetime', date2mysql($servertimenow) );
								$edited_Cronjob->set( 'name', sprintf( $this->T_('Report to blacklist (task %d out of %d)'), $task+1, $total_tasks ) );
								$edited_Cronjob->set( 'controller', 'plugin_'.$this->ID.'_report' );
								$edited_Cronjob->set( 'params', array('words' => $chunk) );
								$edited_Cronjob->dbinsert();
							}
						}
					}
				}

				echo '<thead><tr>';
				echo '<th class="firstcol">'.T_('Date').'</th>';
				echo '<th>'.T_('Author').'</th>';
				echo '<th>'.T_('Auth. URL').'</th>';
				echo '<th>'.T_('Content starts with...').'</th>';
				echo '</tr>';

				echo '<tr><td colspan="2">Comments deleted: '.count($deleted).'</td><td colspan="2">'.$dry_run_msg.'</td></tr>';
				echo '<tr><td colspan="2">Blacklisted strings: '.count($abuse_urls).'</td><td colspan="2"></td></tr>';

				echo '</thead>';
				echo '</table>';

				$Form->end_fieldset();

				if( ! $dry_run && ! isset($init_comment) && !empty($deleted) )
				{
					$DB->query('DELETE FROM T_comments WHERE comment_ID IN ('.$this->quote_array($deleted).')');
				}
			}
		}

		$Form->end_form();
	}


	function check_perms()
	{
		global $current_User;

		$this->hide_tab = 1;
		if( !is_logged_in() )
		{	// Not logged in
			return false;
		}
		if( ! $current_User->check_perm('options', 'edit') )
		{
			return false;
		}
		$this->hide_tab = 0;
		return true;
	}


	function getCache( $objectName )
	{
		global $app_version;

		if( version_compare( $app_version, '4' ) > 0 )
		{	// b2evo 4 and up
			$func_name = 'get_'.$objectName;
			if( function_exists($func_name) )
			{
				return $func_name();
			}
		}
		else
		{
			return get_Cache($objectName);
		}
	}


	/**
	 * Sanitize a comma-separated list of numbers (IDs)
	 *
	 * @param string
	 * @param bool Return array if true, string otherwise
	 * @param bool Quote each element (for use in SQL queries)
	 * @return string
	 */
	function sanitize_id_list( $str, $return_array = false, $quote = false )
	{
		if( is_null($str) )
		{	// Allow NULL values
			$str = '';
		}

		// Explode and trim
		$array = array_map( 'trim', explode(',', $str) );

		// Convert to integer and remove all empty values
		$array = array_filter( array_map('intval', $array) );

		if( !$return_array && $quote )
		{	// Quote each element and return a string
			return $this->quote_array($array);
		}
		return ( $return_array ? $array : implode(',', $array) );
	}


	function get_form_options( $opts )
	{
		$opt = array();
		foreach( $opts as $n_opt => $v_opt )
		{
			$opt[] = '<option value="'.$n_opt.'">'.$v_opt.'</option>';
		}
		$string = implode( "\n", $opt );

		return $string;
	}


	/**
	 * antispam_create(-)
	 *
	 * Insert a new abuse string into DB
	 */
	function antispam_create( $abuse_string, $aspm_source = 'local' )
	{
		global $DB;

		// Cut the crap if the string is empty:
		$abuse_string = trim( $abuse_string );
		if( empty( $abuse_string ) )
		{
			return false;
		}

		// Insert new string into DB:
		$sql = "INSERT IGNORE INTO T_antispam( aspm_string, aspm_source )
				VALUES( '".$DB->escape($abuse_string)."', '$aspm_source' )";

		$DB->query( $sql );
	}


	function quote_array( $array )
	{
		global $DB;

		$r = '';
		foreach( $array as $elt )
		{
			$r .= $DB->quote($elt).',';
		}
		return substr($r, 0, -1);
	}


	/**
	 * Load functions file
	 */
	function load_funcs( $funcs )
	{
		global $inc_path;

		if( $funcs == 'antispam' )
		{
			if( file_exists($inc_path.'antispam/model/_antispam.funcs.php') )
			{	// v2 and higher
				require_once $inc_path.'antispam/model/_antispam.funcs.php';
			}
			if( file_exists($inc_path.'MODEL/antispam/_antispam.funcs.php') )
			{	// v1
				require_once $inc_path.'MODEL/antispam/_antispam.funcs.php';
			}
		}

		if( $funcs == 'xmlrpc' )
		{
			if( file_exists($inc_path.'xmlrpc/model/_xmlrpc.funcs.php') )
			{	// v2 and higher
				require_once $inc_path.'xmlrpc/model/_xmlrpc.funcs.php';
			}
			if( file_exists($inc_path.'_misc/ext/_xmlrpc.php') )
			{	// v1
				require_once $inc_path.'_misc/ext/_xmlrpc.php';
			}
		}
	}


	function load_cron_job_class()
	{
		global $app_version, $inc_path;

		if( version_compare( $app_version, '4' ) > 0 )
		{	// v4.1 and up
			load_class( '/cron/model/_cronjob.class.php', 'Cronjob' );
		}
		elseif( version_compare( $app_version, '2' ) > 0 )
		{	// v2, v3
			load_class( '/cron/model/_cronjob.class.php' );
		}
		else
		{	// v1
			require_once $inc_path.'MODEL/cron/_cronjob.class.php';
		}
	}


	/**
	 * Display an URL, constrained to a max length
	 *
	 * @param string
	 * @param integer
	 */
	function get_short_url( $url, $max_length = NULL )
	{
		if( !empty($max_length) && strlen($url) > $max_length )
		{
			$disp_url = htmlspecialchars(substr( $url, 0, $max_length-1 )).'&hellip;';
		}
		else
		{
			$disp_url = htmlspecialchars($url);
		}
		return '<a href="'.$url.'">'.$disp_url.'</a>';
	}


	/**
	 * Set max execution time
	 * @param integer seconds
	 */
	function set_max_execution_time( $seconds )
	{
		if( function_exists( 'set_time_limit' ) )
		{
			set_time_limit( $seconds );
		}
		@ini_set( 'max_execution_time', $seconds );
	}
}

?>