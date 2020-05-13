<?php
/**
 * This file implements the SEO Tools plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2012 Russian b2evolution - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Russian b2evolution
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class seo_plugin extends Plugin
{
	var $name = 'SEO Tools';
	var $code = 'sn_seo';
	var $priority = 10;
	var $version = '1.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://www.sonorth.com';
	var $help_url = '';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;

	var $limit_results = 100;
	var $task_run_days = 1;
	var $update_words_limit = 1;

	protected $seo_data = false;


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Detect and track history of Google PageRank and Yandex TIC');
		$this->long_desc = $this->T_('SEO Tools to detect and track history of Google PageRank and Yandex TIC for given URLs');

		//$this->user_agent = 'Mozilla/5.0 (compatible; '.$this->name.' v'.$this->version.')';
	}


	function GetDbLayout()
	{
		return array(
			"CREATE TABLE IF NOT EXISTS seo_urls (
					url_ID INT(11) NOT NULL auto_increment,
					url_proj_ID INT(11) NOT NULL,
					url_status TINYINT(1) NOT NULL DEFAULT 1,
					url_value VARCHAR(500) NOT NULL,
					PRIMARY KEY (url_ID),
					INDEX (url_proj_ID)
				)",

			"CREATE TABLE IF NOT EXISTS seo_words (
					word_ID INT(11) NOT NULL auto_increment,
					word_url_ID INT(11) NOT NULL,
					word_status TINYINT(1) NOT NULL DEFAULT 1,
					word_value VARCHAR(500) NOT NULL,
					word_update_date DATE NOT NULL default '2000-01-01',
					PRIMARY KEY (word_ID),
					INDEX (word_url_ID)
				)",

			"CREATE TABLE IF NOT EXISTS seo_engines (
					eng_ID INT(11) NOT NULL auto_increment,
					eng_status TINYINT(1) NOT NULL DEFAULT 1,
					eng_class VARCHAR(20) NOT NULL,
					eng_name VARCHAR(255) NOT NULL,
					PRIMARY KEY (eng_ID),
					UNIQUE KEY (eng_class),
					INDEX (eng_status)
				)",

			"CREATE TABLE IF NOT EXISTS seo_data (
					data_ID INT(11) NOT NULL auto_increment,
					data_word_ID INT(11) NOT NULL,
					data_url_ID INT(11) NOT NULL,
					data_engine_ID INT(11) NOT NULL,
					data_position INT(11) NOT NULL,
					data_date DATE NOT NULL default '2000-01-01',
					PRIMARY KEY (data_ID),
					INDEX (data_word_ID),
					INDEX (data_date)
				)",
		);
	}


	function BeforeInstall()
	{
		if( !function_exists('curl_init') )
		{
			$this->msg( $this->T_('CURL is not available'), 'error' );
			return false;
		}
		return true;
	}


	function AfterInstall()
	{
		global $DB;

		$DB->query('INSERT IGNORE INTO seo_engines (eng_status, eng_class, eng_name) VALUES
					( 1, "Google", "Google" ),
					( 1, "Yandex", "Yandex" )');

		return true;
	}


	function AdminAfterMenuInit()
	{
		$this->register_menu_entry($this->name);
	}


	function AdminTabPayload()
	{
		global $DB;

		if( $this->hide_tab == 1 ) return;

		$action = param( 'action', 'string' );
		$action_url = regenerate_url( 'action' ).'&amp;';

		$action_links = array(
				'<a href="{url}">'.$this->T_('Home').'</a>',
				'<a href="{url}action=update">'.$this->T_('Update positions').'</a>',
				'<a href="{url}action=add_urls">'.$this->T_('Add URLs').'</a>',
				'<a href="{url}action=add_engines">'.$this->T_('Add Engines').'</a>',
			);

		echo '<div style="margin:0 0 10px 0; font-size:16px">'.
				str_replace( '{url}', $action_url, implode( ' | ', $action_links ) ).'</div>';

		$urls = $DB->get_results('SELECT * FROM seo_urls');
		$words = $DB->get_results('SELECT * FROM seo_words ORDER BY word_value ASC');

		/*
		// Create result set:
		$Results = new Results( $SQL, 'seo_', '-A' );
		$Results->title = $this->T_('Search Engin Ranks');

		$Results->group_by = 'word_url_ID';
		$Results->grp_cols[] = array(
					'td_colspan' => 2,  // nb_cols
					'td' => '$word_url_ID$ $url_value$',
				);

		$Results->cols[] = array(
			'th' => T_('URL'),
			'td' => '$word_value$',
		);

		$Results->cols[] = array(
			'th' => T_('Position'),
			'order' => 'data_position',
			'td' => '$data_position$',
			'td_class' => 'shrinkwrap',
		);

		$Results->display();
		*/

		echo '<div style="width: 500px">';

		$Table = new Table( NULL, 'seo_' );
		$Table->title = T_('Search engines ranks');

		$Table->cols[] = array(
			'th' => T_('Keywords'),
			'td' => '$word_value$',
		);
		$Table->cols[] = array(
			'th' => T_('Google'),
			'td_class' => 'shrinkwrap',
		);
		$Table->cols[] = array(
			'th' => T_('Yandex'),
			'td_class' => 'shrinkwrap',
		);
		$Table->display_init();

		$Table->display_list_start();
		$Table->display_head();
		$Table->display_body_start();

		foreach( $urls as $url )
		{
			echo '<tr class="group">';

			$Table->displayed_cols_count = 0;
			$Table->display_col_start(array( 'colspan' => 3 ));
			echo '<span style="font-size:18px; color: #333">'.strtoupper($url->url_value).'</span>';
			$Table->display_col_end();

			foreach( $words as $word )
			{ // For each named goal, display count:
				if( $word->word_url_ID == $url->url_ID )
				{
					$Table->display_line_start( false, false );

					$Table->display_col_start();
					echo $word->word_value;
					$Table->display_col_end();

					$Table->display_col_start();
					echo $this->position($word, 'google');
					$Table->display_col_end();

					$Table->display_col_start();
					echo $this->position($word, 'yandex');
					$Table->display_col_end();

					$Table->display_line_end();
				}
			}

			echo '</tr>';
		}

		$Table->display_body_end();
		$Table->display_list_end();

		echo '</div>';
	}


	function AdminTabAction()
	{
		global $DB;

		$this->check_perms();
		if( $this->hide_tab == 1 ) return;

		global $DB, $current_User, $localtimenow;

		$action = param( 'action', 'string', 'list' );
		switch($action)
		{
			case 'update':
				$this->update_sites();
				$this->msg( $this->T_('Successfully updated!'), 'success' );
				break;

			case 'add_engines':
				$DB->query('INSERT INTO seo_engines (eng_status, eng_class, eng_name) VALUES
							( 1, "Google", "Google" ),
							( 1, "Yandex", "Yandex" )');

				$this->msg( $this->T_('New engines added.'), 'success' );
				break;

			case 'add_urls':

				$this->keywords = array();
				/*
				$this->keywords['americarussiantours.com'] = array(
						'туры во Флориду',
						'гиды в США',
						'групповые туры в США',
						'индивидуальные туры в США',
					);

				$this->keywords['russianfloridahomes.com'] = array(
						'недвижимость в Майами',
						'недвижимость в США',
						'риэлторы в Майами',
						'дома в Майами',
						'квартиры в Майами',
					);

				$this->keywords['russiantoursmiami.com'] = array(
						'туры в Майами',
						'гиды в Майами',
						'экскурсии в Майами',
						'переводчики в Майами',
					);

				$this->keywords['russiantoursorlando.com'] = array(
						'туры в Орландо',
						'гиды в Орландо',
						'экскурсии в Орландо',
					);


				$this->keywords['americanbusinessstandard.com'] = array(
						'деловые поездки в США',
						'переводчики в США',
						'экскурсии в Орландо',
					);
				*/

				foreach( $this->keywords as $url=>$words )
				{
					$SQL = 'INSERT INTO seo_urls ( url_proj_ID, url_value )
							VALUES ( 1, "'.$DB->escape($url).'" )';

					if( $DB->query($SQL) )
					{	// Insert keywords
						$url_ID = $DB->insert_id;

						$r = array();
						foreach( $words as $word )
						{
							$r[] = '( '.$DB->quote($url_ID).', "'.$DB->escape($word).'" )';
						}
						$SQL = 'INSERT INTO seo_words ( word_url_ID, word_value ) VALUES ';
						$SQL .= implode( ",\n", $r );

						$DB->query( $SQL );
					}
				}
				$this->msg( $this->T_('New URL added to the project.'), 'success' );
				break;
		}
	}


	function AdminEndHtmlHead()
	{
		//echo '<style type="text/css">
		//		</style>';
	}


	function GetCronJobs( & $params )
	{
		return array(
			array(
				'name' => $this->T_('Run tasks').' ('.$this->name.')',
				'ctrl' => 'run_tasks',
			),
		);
	}


	function ExecCronJob( & $params )
	{	// We provide only one cron job, so no need to check $params['ctrl'] here.
		$this->update_sites();

		$code = 1;
		$message = $this->T_('Successfully updated!');

		return array( 'code' => $code, 'message' => $message );
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


	function get_urls( $date = '', $limit = 1 )
	{
		global $DB;

		$SQL = 'SELECT * FROM seo_urls INNER JOIN seo_words ON word_url_ID = url_ID';

		if( !empty($date) )
		{
			$SQL .= ' WHERE word_update_date < "'.$DB->escape($date).'"';
		}

		$SQL .= ' ORDER BY word_update_date ASC, word_ID ASC';

		if( $limit > 0 )
		{
			$SQL .= ' LIMIT '.$limit;
		}

		return $DB->get_results($SQL);
	}


	function view_url( $word, $pos, $eng )
	{
		$url = '';
		switch( $eng )
		{
			case 'google':
				$url = 'http://www.google.com/search?q=[keyword]';
				break;

			case 'yandex':
				$url = 'http://yandex.ru/yandsearch?text=[keyword]&lr=102582';
				break;
		}

		$url = str_replace( '[keyword]', rawurlencode($word), $url );
		echo '<a href="'.$url.'" target="_blank" title="Open in new window">'.$pos.'</a>';
	}


	function get_engines( $status = 1 )
	{
		global $DB;

		$SQL = 'SELECT * FROM seo_engines
				WHERE eng_status = '.$DB->quote($status).'
				ORDER BY eng_ID ASC';

		return $DB->get_results($SQL);
	}


	function init_data( $latest = true )
	{
		global $DB;

		$engines = $this->get_engines();

		$results = array();
		foreach( $engines as $engine )
		{
			$SQL = 'SELECT * FROM seo_data
					INNER JOIN seo_engines ON eng_ID = data_engine_ID
					WHERE eng_ID = '.$DB->quote($engine->eng_ID);

			if( $latest )
			{
				//$SQL .= ' AND data_date = (SELECT MAX(data_date) FROM seo_data LIMIT 1)';
			}
			$SQL .= ' GROUP BY data_word_ID ORDER BY data_ID DESC';

			if( $rows = $DB->get_results($SQL) )
			{
				foreach( $rows as $row )
				{
					$results['w_'.$row->data_url_ID.'_'.$row->data_word_ID.'_'.strtolower($row->eng_class)] = $row;
				}
			}
		}
		//var_export($results);die;
		$this->seo_data = $results;
	}


	function position( $word, $e, $format = true )
	{
		$k = 'w_'.$word->word_url_ID.'_'.$word->word_ID.'_'.$e;

		if( ! $this->seo_data )
		{
			$this->init_data();
		}

		$pos = '<span class="notes">n/a</span>';
		if( isset($this->seo_data[$k]) )
		{
			$p = $this->seo_data[$k]->data_position;

			$color = 'green';
			if( $p > 3 ) $color = '#555';
			if( $p > 10 ) $color = 'red';

			$pos = '<span style="color:'.$color.'; font-weight: bold">'.$p.'</span>';
		}

		return $this->view_url( $word->word_value, $pos, $e );
	}


	function update_sites()
	{
		global $DB, $localtimenow;

		require_once '_serp.class.php';

		//$time_run_before = ($localtimenow - $this->task_run_days * 86400); // 1 day

		$urls = $this->get_urls( date('Y-m-d', $localtimenow), $this->update_words_limit );
		$engines = $this->get_engines();

		//var_export($urls);die;

		foreach( $engines as $engine )
		{
			if( ! class_exists($engine->eng_class) )
			{
				$this->msg( sprintf($this->T_('You must create the "%s" class before running updates.'), $engine->eng_class), 'error' );
				return;
			}

			$Data = new $engine->eng_class();
			//$Data->use_proxy('proxy.txt');

			foreach( $urls as $url )
			{
				$Data->run( $url->word_value, $url->url_value, $this->limit_results );
				$results = $Data->get_results();

				if( ! is_array($results) ) continue;

				//var_export($results);die;

				$r = array();
				foreach( $results as $word_array )
				{
					$position = isset($word_array[$url->word_value]) ? $word_array[$url->word_value] : 0;

					$r[] = '( '.$DB->quote($url->word_ID).',
								'.$DB->quote($url->url_ID).',
								'.$DB->quote($engine->eng_ID).',
								'.$DB->quote($position).',
								'.$DB->quote( date('Y-m-d', $localtimenow) ).' )';
				}

				if( !empty($r) )
				{
					// Update data
					$DB->query('INSERT IGNORE INTO seo_data (data_word_ID, data_url_ID, data_engine_ID, data_position, data_date)
								VALUES '.implode( "\n", $r ));

					// Update words timestamp
					$DB->query('UPDATE seo_words
								SET word_update_date = '.$DB->quote( date('Y-m-d', $localtimenow) ).'
								WHERE word_ID = '.$DB->quote($url->word_ID));
				}
				//var_export($SQL);
			}

			$this->msg( $Data->get_log('less'), 'notes' );
		}
	}
}

?>