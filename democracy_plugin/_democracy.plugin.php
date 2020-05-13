<?php
/**
 * Democracy Poll plugin for b2evolution
 *
 * Original Wordpress plugin by Andrew Sutherland - {@link http://blog.jalenack.com/archives/democracy/}
 * Released under the CC GPL 2.0:
 * http://creativecommons.org/licenses/GPL/2.0/
 *
 * Ported to b2evolution by Danny Ferguson - {@link http://www.brendoman.com/dbc}
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author Danny Ferguson	- {@link http://www.brendoman.com/}
 * @author sam2kb			- {@link http://b2evo.sonorth.com/}
 *
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 *
 * TODO:
 * Use jquery
 * Record who voted for what
 * Allow voting to be restricted to members (maybe even groups, too)
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class democracy_plugin extends Plugin
{
	var $name = 'Democracy Poll';
	var $code = 'democracy';
	var $priority = 50;
	var $version = '3.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Russian b2evolution';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=22699';

	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;


	function PluginInit()
	{
		$this->short_desc = $this->T_('Add a poll to your sidebar');
		$this->long_desc = $this->T_('This plugin allows you to add different polls to the sidebar of each blog. Add and edit polls in Tools > Democracy Poll. See docs for instructions on installing the poll archives.');
	}


	/**
	 * Create tables for poll questions and answers.
	 */
	function GetDbLayout()
	{
		return array(
			"CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('A')." (
					aid int(10) unsigned NOT NULL auto_increment,
					qid int(10) NOT NULL default '0',
					answers varchar(200) NOT NULL default '',
					votes int(10) NOT NULL default '0',
					added_by enum('1','0') NOT NULL default '0',
					PRIMARY KEY (aid)
				   )",

			 "CREATE TABLE IF NOT EXISTS ".$this->get_sql_table('Q')." (
					id int(10) unsigned NOT NULL auto_increment,
					blog_id int(10) NOT NULL default '2',
					question varchar(200) NOT NULL default '',
					timestamp int(10) NOT NULL,
					voters text NULL DEFAULT NULL,
					allowusers enum('0','1') NOT NULL default '0',
					usechart enum('0','1') NOT NULL default '0',
					active enum('0','1') NOT NULL default '0',
					PRIMARY KEY (id)
				   )"
			  );
	}


	/**
	 * We require b2evo 3.0 or higher.
	 */
	function GetDependencies()
	{
		return array(
				'requires' => array(
					'app_min' => '3.0',
				),
			);
	}


	/**
	 * Get the settings that the plugin can use.
	 *
	 * Those settings are transfered into a Settings member object of the plugin
	 * and can be edited in the backoffice (Settings / Plugins).
	 *
	 * @see Plugin::GetDefaultSettings()
	 * @see PluginSettings
	 * @return array
	 */
	function GetDefaultSettings()
	{
		return array(
			'usechart' => array(
				'label' => $this->T_('Display results as a pie chart'),
				'defaultvalue' => 0,
				'note' => 'Not implemented yet',
				//'note' => $this->T_('Uses PHP-SWF Charts. Must also be configured for each poll.'),
				'type' => 'checkbox',
				'disabled' => true,
			)
		);
	}


	/**
	* Get definitions for widget specific editable params
	*
	* @see Plugin::GetDefaultSettings()
	* @param local params like 'for_editing' => true
	*/
	function get_widget_param_definitions( $params )
	{
		return array(
			'title' => array(
					'type' => 'text',
					'label' => $this->T_('Widget title'),
					'defaultvalue' => $this->T_('Poll'),
					'maxlength' => 100,
				),
			'poll_id' => array(
					'type' => 'integer',
					'label' => $this->T_('Poll ID'),
					'size' => 4,
					'defaultvalue' => 0,
					'note'	=> $this->T_('Leave 0 to display the default poll for current blog.'),
				),
			'sort' => array(
					'type' => 'checkbox',
					'label' => $this->T_('Sort by votes'),
					'defaultvalue' => 'true',
					'note'	=> $this->T_('Check to sort the answers.'),
				),
			);
	}


	/**
	 * Event handler: Called when beginning the admin html head section.
	 *
	 * @param array Associative array of parameters
	 * @return boolean did we do something?
	 */
	function SkinBeginHtmlHead( & $params )
	{
		global $plugins_url;

		require_js( $plugins_url.'democracy_plugin/democracy.js' );
		require_css( $plugins_url.'democracy_plugin/democracy.css' );
		add_js_headline('var DemocracyURI = "'.$this->get_htsrv_url( 'cast_vote', array(), '&' ).'";');
	}


	/**
	 * Event handler: Gets invoked in /admin/_header.php for every backoffice page after
	 * the menu structure is build. You can use the {@link $AdminUI} object
	 * to modify it.
	 *
	 * This is the hook to register menu entries. See {@link register_menu_entry()}.
	 */
	function AdminAfterMenuInit()
	{
		$this->register_menu_entry( $this->name );
	}


	function AdminEndHtmlHead()
	{
		global $plugins_url;
		require_js( $plugins_url.'democracy_plugin/democracy_admin.js' );
	}


	function AdminBeginPayload()
	{
		global $ctrl, $tab, $AdminUI;

		if( $ctrl == 'tools' && $tab == 'plug_ID_'.$this->ID )
		{
			$AdminUI->set_coll_list_params('blog_ismember', 1, array('ctrl' => 'tools', 'tab' => 'plug_ID_'.$this->ID));
			echo $AdminUI->get_bloglist_buttons();
		}
	}


	function AdminTabAction( $params )
	{
		if( param( 'jal_dem_activate', 'string' ) )
		{	// When user activates a poll
			$this->jal_activate_poll();
			$this->msg( $this->T_('Poll activated'), 'success' );
		}

		if( param( 'jal_dem_question', 'string' ) )
		{	// Add a new question and its answers via admin panel
			$this->jal_add_question();
			$this->msg( $this->T_('Poll saved'), 'success' );
		}

		if( param( 'jal_dem_edit', 'string' ) )
		{	// When a user edits a poll
			$this->jal_edit_poll();
			$this->msg( $this->T_('Poll saved'), 'success' );
		}

		if( param( 'jal_dem_deactivate', 'string' ) )
		{	// When user deactivates a poll
			$this->jal_deactivate_poll();
			$this->msg( $this->T_('Poll deactivated'), 'success' );
		}

		if( param( 'jal_dem_delete', 'string' ) )
		{	// When user deletes a poll
			$this->jal_delete_poll();
			$this->msg( $this->T_('Poll deleted'), 'success' );
		}
	}


	/**
	 * Event handler: Called when displaying the block in the "Tools" menu.
	 *
	 * @param array Associative array of parameters
	 */
	function AdminTabPayload( $params )
	{
		global $DB, $AdminUI, $blog;

		$poll_id = param( 'poll_id', 'integer', 0 );

        if( empty($blog) )
		{	// No blog could be selected
			echo '<div class="panelinfo">';
			echo '<p>'.$this->T_('Please select a blog where you want to add a poll.').'</p>';
			echo '</div>';
        }
		else
		{
			if( !empty($_GET['edit']) )
			{ ?>
                <div class="wrap">
                <?php
                echo '<h2>'.sprintf( $this->T_('Edit poll #%d'), $poll_id ).'</h2>';

				$SQL = 'SELECT id FROM '.$this->get_sql_table('Q').'
						ORDER BY id DESC
						LIMIT 1';
                $latest = $DB->get_var($SQL);


				$SQL = 'SELECT question, allowusers, usechart FROM '.$this->get_sql_table('Q').'
						WHERE id = '.$DB->quote($poll_id);
                $question = $DB->get_row( $SQL );


				$SQL = 'SELECT * FROM '.$this->get_sql_table('A').'
						WHERE qid = '.$DB->quote($poll_id).'
						ORDER BY aid';
                $results = $DB->get_results($SQL);


                echo '<p>'.$this->T_('To delete an answer, leave the input box blank. Moving an answer from one box to another will erase its votes.').'</p>';

				?>
                <form action="admin.php?ctrl=tools&tab=plug_ID_<?php echo $this->ID ?>" method="post" onsubmit="return jal_validate();">
                  <strong>Question:
                  <input id="question" type="text" name="question" value="<?php echo $question->question; ?>" />
                  </strong>
                  <ol id="inputList">
                    <?php
					$count = 1;
					$loop = '';
					foreach( $results as $r )
					{	// Add to the list of answers in the hidden input element
						$loop = $loop.' '.$r->aid;

						echo '<li><input type="text" value="'.format_to_output( $r->answers, 'formvalue' ).'" name="answer['.$r->aid.']" /></li>';
						$count++;
					}
					?>
                  </ol>

                  <label for="allowNewAnswers">
                    <input type="checkbox" <?php if( $question->allowusers == 1 ) echo 'checked="checked"'; ?> value="1" name="allowNewAnswers" id="allowNewAnswers" />
                    Allow users to add answers</label>
                  <br />
                  <?php

				  if( $this->Settings->get('usechart') )
				  { ?>
                      <label for="usechart"><input type="checkbox" <?php if( $question->usechart == 1 ) echo 'checked="checked"'; ?> value="true" name="usechart" id="usechart" />Display results as a pie chart</label>
                      <br />
                  <?php
                  }

				  ?>
                  <br />
                  <a href="javascript: addQuestion();" id="adder"><?php echo $this->T_('Add an Answer') ?></a>&nbsp;&nbsp; <a href="javascript: eatQuestion();" id="subtractor">Subtract an Answer</a>&nbsp;
                  <input type="hidden" id="qnum" name="qnum" value="<?php echo $count; ?>" />
                  <input type="hidden" name="jal_dem_edit" value="true" />
                  <input type="hidden" name="editable" value="<?php echo trim($loop); ?>" />
                  <input type="hidden" name="poll_id" value="<?php echo $poll_id; ?>" />
                  <input type="submit" value="Edit" />
                </p>
              </form>
			<?php

			}
            else
            {
                $SQL = 'SELECT id FROM '.$this->get_sql_table('Q').'
                        WHERE blog_id = '.$DB->quote($blog).'
                        AND active = 1';

				// Get the current poll
				if( !$current_poll_ID = $DB->get_var($SQL) ) $current_poll_ID = 0;

				echo '<div class="wrap">';

				$winners = array();
				$totalvotes = array();

				$SQL = 'SELECT SUM(votes) as total_votes, qid
						FROM '.$this->get_sql_table('A').'
						GROUP BY qid
						ORDER BY qid';
				$totalvotes = $DB->get_results( $SQL, ARRAY_A );


				$SQL = 'SELECT votes, answers, qid
						FROM '.$this->get_sql_table('A').'
						GROUP BY qid, votes
						ORDER BY qid';
				$winner_answers = $DB->get_results( $SQL, ARRAY_A );

				// index by qid
				foreach( $totalvotes as $winner )
				{
					$total_vote[$winner['qid']] = $winner['total_votes'];
				}

				// index by qid
				foreach( $winner_answers as $winning_answer )
				{
					$winners[$winning_answer['qid']] = $winning_answer['answers'];
				}

				$alt = true;

				$SQL = 'SELECT * FROM '.$this->get_sql_table('Q').'
						WHERE blog_id = '.$DB->quote($blog).'
						ORDER BY id';

				if( !$x = $DB->get_results($SQL) )
				{
					//echo '<h3>'.$this->T_('You have no polls in the database. Add a new one!').'</h3>';
				}
				else
				{
					echo '<h2>'.$this->T_('Manage Polls').'</h2>';

					echo '<table class="grouped" style="width:100%" cellspacing="0">
						  <tr>
							<th scope="col">ID</th>
							<th scope="col">Question</th>
							<th scope="col">Total Votes</th>
							<th scope="col">Winner</th>
							<th scope="col">Date Added</th>
							<th scope="col">Action</th>
						  </tr>';

					foreach( $x as $r )
					{
						$alt = ($alt) ? false : true;
						echo '<tr class="'.($alt ? 'even' : 'odd');
						if( $r->id == $current_poll_ID )
						{
							echo ' active" style="font-weight:bold';
						}
						echo '">';
						?>

						<td style="text-align: "><?php echo $r->id; ?></td>
						<td><?php echo $r->question; ?></td>
						<td style="text-align: center"><?php echo $total_vote[$r->id]; ?></td>
						<?php
							$winner_ans = $winners[$r->id];
							if( empty($total_vote[$r->id]) )
							{
								$winner_ans = '-';
							}
							echo '<td>'.$winner_ans.'</td>';
						?>
						<td style="text-align: center"><?php echo date(locale_datefmt(), $r->timestamp); ?></td>
						<td style="text-align: center">
						<form action="" method="get">
						<div>
						<input type="hidden" name="blog" value="<?php echo $blog ?>" />
						<input type="hidden" name="tab" value="plug_ID_<?php echo $this->ID ?>" />
						<input type="hidden" name="poll_id" value="<?php echo $r->id; ?>" />
						<input type="hidden" name="ctrl" value="tools" />
						<?php
						if( $current_poll_ID !== $r->id )
						{
							echo '<input type="submit" value="'.$this->T_('Activate').'" name="jal_dem_activate" />';

						}
						else
						{
							echo '<input type="submit" value="'.$this->T_('Deactivate').'" name="jal_dem_deactivate" />';
						}
						?>
						<input type="submit" value="Edit" name="edit">
						<input type="submit" value="Delete" onclick="return confirm('You are about to delete this poll.\n  \'Cancel\' to stop, \'OK\' to delete.');" name="jal_dem_delete" class="delete" />
						</div>
						</form>
						</td>
						</tr>
						<?php
					}
					echo '</table>';
				}

				echo '<h2>'.$this->T_('Add a new poll').'</h2>';

				?>

                <p>Polls are fully HTML compatible. No character entities will be converted, so if you want to use &amp;, write <code>&amp;amp;</code>, etc.<br />If you have no idea what the last two sentences meant, don't worry about 'em. Blank fields will be skipped.</p>
                <form action="admin.php?ctrl=tools&blog=<?php echo $blog ?>" method="post" onsubmit="return jal_validate();">
                <div id="form_questions">
                <input type="hidden" name="blog" id="blog" value="<?php echo $blog ?>" />
                <input type="hidden" name="tab" value="plug_ID_<?php echo $this->ID ?>" />

                <p><a href="javascript: addQuestion();" id="adder">Add an answer</a> |
                <a href="javascript: eatQuestion();" id="subtractor">Subtract an answer</a></p>

                <label for="allowNewAnswers">
                <input type="checkbox" value="1" name="allowNewAnswers" id="allowNewAnswers" />
                Allow users to add answers</label><br />

                <?php
                if( $this->Settings->get('usechart') )
				{
                	echo '<label for="usechart"><input type="checkbox" value="true" name="usechart" id="usechart" /> ';
					echo $this->T_('Display results as a pie chart');
					echo '</label><br />';
                }

                echo '<br />';

				echo '<label for="question"><b>'.$this->T_('Question').':</strong></label> ';
                echo '<input type="text" name="jal_dem_question" value="" id="question" size="80" />';

                echo '<ol id="inputList">';
                for( $i = 1; $i < 5; $i++ )
                {
                	echo '<li><input type="text" value="" name="answer[]" size="60" /></li>';
                }
                echo '</ol>';
				echo '<input type="submit" value="Create New Poll" />';

                echo '</div></form>';
            }
			echo '</div>';
		}
	}


	/**
	 * Perform rendering for HTML
	 */
	function DisplayItemAsHtml( & $params )
	{
		// Display requested poll
		$params['data'] = $this->render_content( $params['data'] );
		return true;
	}


	/**
	 * Perform rendering for XML feeds
	 */
	function DisplayItemAsXml( & $params )
	{
		return $this->DisplayItemAsHtml( $params );
	}


	// Render content
	function render_content( $content )
	{
		// <!--poll:120-->
		$content = preg_replace( '~<\!--poll:([0-9]+)-->~ie', '$this->SkinTag( "\\1", false )', $content );

		return $content;
	}


	function AdminDisplayEditorButton( $params )
	{
		if( $params['edit_layout'] == 'simple' ) return;

		?>
		<script type="text/javascript">
		//<![CDATA[
		function democrcy () {
			var poll_id_t = '<?php echo $this->T_('Enter poll ID') ?>';
			var poll_id = prompt( poll_id_t, '' );

			if( poll_id.match( /^[0-9]+$/ ) )
			{ // valid
				code = '<!--poll:'+poll_id+'-->';
				textarea_wrap_selection( b2evoCanvas, code, '', 1 );
				return;
			}
			alert( '<?php echo $this->TS_('The poll ID is invalid.'); ?>' );
		}
		//]]>
		</script>

		 <input type="button" name="poll_id" class="quicktags" onclick="democrcy();" value="<?php echo $this->T_('Add a Poll') ?>" />
		<?php
	}


 	/**
	 * Event handler: SkinTag
	 *
	 * @param array Associative array of parameters. Valid keys are:
	 *		- 'display' : head|archives (Default: NULL)
	 *		- 'title' : (Default: '<h3>'.$this->T_('Poll').'</h3>')
	 *		- 'before_q' : (default: '<p id="poll-question"><b>')
	 *		- 'after_q' : (default: '</b></p>')
	 *		- 'sort' : true|false (default: true)
	 *		- 'gft' : true|false (default: true)
	 *		- 'gct' : (default: '#06c')
	 *		- 'gcb' : (default: '#05a')
	 * @return boolean did we display?
	 */
	function SkinTag( $params, $display = true )
	{
		global $blog;

		if( !is_array($params) )
		{	// Poll requeted from post
			$params = array(
					'poll_id'		=> $params,
					'block_start'	=> '<div class="DemocracyPoll">',
				);
		}

		// This is what will enclose the block in the skin:
		if(!isset($params['block_start'])) $params['block_start'] = '<div class="bSideItem DemocracyPoll">';
		if(!isset($params['block_end'])) $params['block_end'] = "</div>\n";
		if(!isset($params['block_title_start'])) $params['block_title_start'] = "<h3>";
		if(!isset($params['block_title_end'])) $params['block_title_end'] = "</h3>";

		// Title:
		if(!isset($params['title'])) $params['title'] = $this->T_('Poll');

		// Code to show before question:
		if(!isset($params['before_q'])) $params['before_q'] = '<p class="poll-question"><b>';
		if(!isset($params['after_q'])) $params['after_q'] = '</b></p>';

		// Should the results be order by number of votes?
		if(!isset($params['sort'])) $params['sort'] = true;

		// Pass this so we can build a link to return from results
		$params['blog'] = $blog;

		// DEPRECATED
		if(!isset($params['archive_link'])) $params['archive_link'] = false;

		// Graph percentages as a percent of the total votes or of the winner
		// If it is false, then the winning vote will be 100% of the graph,
		// and the other answers will be a percent of that,
		if(!isset($params['gft'])) $params['gft'] = true;

		// Color for graph bars.
		// Make the bottom color a few shades lighter than the top color to get a slight 3d effect
		if(!isset($params['gct'])) $params['gct'] = '#06c';
		if(!isset($params['gcb'])) $params['gcb'] = '#05a';

		ob_start();
		if( !empty( $params['display'] ) && $params['display'] == 'archives' )
		{	// Display poll archives
			$this->jal_democracy_archives( $params );
		}
		else
		{	// Display the poll
			$this->display_poll( $params );
		}
		$output = @ob_get_clean();

		// Display content (if requested)
		if( $display ) echo $output;

		return $output;
	}


	function display_poll( $params )
	{
		global $blog, $plugins_url, $DB, $Blog, $ReqURI;

		if( !empty($params['poll_id']) )
		{	// Specific poll requested
			$where = ' WHERE id = '.$DB->quote($params['poll_id']);
		}
		else
		{	// Get by blog
			$where = ' WHERE blog_id = '.$DB->quote($blog).' AND active = 1';
		}

		$SQL = 'SELECT id, question, voters, allowusers
				FROM '.$this->get_sql_table('Q').
				$where;

		if( !$poll_Q = $DB->get_row($SQL) ) return false;

		// Save return_to URI
		$params['return_to'] = $ReqURI;

		if( param( 'jal_view_results', 'boolean' ) || param( 'b2demVoted_'.$poll_Q->id, 'integer' ) || !$this->is_ip_allowed($poll_Q->id, true) )
		{	// User either already voted or viewing results
			echo $params['block_start'];

			echo $params['block_title_start'];
			echo $params['title'];
			echo $params['block_title_end'];

			$this->jal_SeeResults( $params, $poll_Q->id );

			if( !empty($params['archive_link']) )
			{
				$archive_url = url_add_param( $Blog->gen_blogurl(), 'disp=pollarchives' );
				echo '<a href="'.$archive_url.'" title="'.$this->T_('See the old polls').'">'.$this->T_('Poll archives').'</a>';
			}
			echo $params['block_end'];
		}
		else
		{
			$SQL = 'SELECT aid, answers, added_by
					FROM '.$this->get_sql_table('A').'
					WHERE qid = '.$DB->quote($poll_Q->id).'
					ORDER BY aid';

			if( !$poll_A = $DB->get_results($SQL) ) return;

			// START DISPLAY:
			echo $params['block_start'];

			echo $params['block_title_start'];
			echo $params['title'];
			echo $params['block_title_end'];

			$latestaid = $DB->get_var('SELECT aid FROM '.$this->get_sql_table('A').'
									   ORDER BY aid DESC
									   LIMIT 1');

			$total_votes = $DB->get_var('SELECT SUM(votes) FROM '.$this->get_sql_table('A').'
										 WHERE qid = '.$DB->quote($poll_Q->id) );


			// ====================
			// Display the form

			$form_action = $this->get_htsrv_url('cast_vote', array(), '&').'&jal_nojs=true&return_to='.$ReqURI;

			$Form = new Form( $form_action, 'democracyForm', 'post', 'linespan' );
			$Form->begin_form( '', '', array( 'onsubmit' => 'return ReadVote("'.$plugins_url.'");' ) );
			$Form->hidden( 'params', str_replace( "\n", ' ', serialize($params) ), array('id'=>'params') );

			echo '<div id="democracy">';
			echo '<div id="pollloading" style="float:right;height:16px;width:16px;margin:0;padding:0"></div>';

			// Poll question
			echo $params['before_q'];
			echo $poll_Q->question;
			echo $params['after_q'];

			echo '<ul>';

			foreach( $poll_A as $A )
			{
				$label = $A->answers;

				if( $A->added_by == 1 )
				{
					$label .= '<sup title="'.$this->T_('Added by users').'">1</sup>';
					$user_added = true;
				}

				echo '<li><label for="choice_'.$A->aid.'">';
				echo '<input name="poll_aid" value="'.$A->aid.'" type="radio" id="choice_'.$A->aid.'" />';
				echo $label.'</label></li>';
			}

			if( $poll_Q->allowusers == 1 )
			{	// Add a user answer field
				echo '<li>';
				if( param( 'jal_add_user_answer', 'boolean' ) )
				{	//No-JS users
					echo '<input name="poll_aid" value="newAnswer" type="radio" id="jalAddAnswerRadio" checked="checked" />';
					echo '<input name="poll_vote" value="" type="text" size="15" id="jalAddAnswerInput" />';
				}
				else
				{
					$add_answer_url = regenerate_url( 'jal_add_user_answer,redir', 'redir=no&amp;jal_add_user_answer=true' );

					echo '<a href="'.$add_answer_url.'" id="jalAddAnswer">'.$this->T_('Add an answer').'</a>';
					echo '<input type="radio" name="poll_aid" id="jalAddAnswerRadio" value="'.($latestaid+1).'" style="display: none" />';
					echo '<input type="text" size="20" id="jalAddAnswerInput" style="display: none" />';
				}
				echo '</li>';
			}

			echo '</ul>';

			echo '<p>
				  <input type="hidden" id="poll_id" name="poll_id" value="'.$poll_Q->id.'" />
				  <input type="hidden" id="poll_id" name="poll_latest_aid" value="'.$latestaid.'" />
				  <input type="submit" name="vote" value="'.$this->T_('Vote').'" />
				  </p>';

			if( !empty( $total_votes ) && $total_votes > 0 )
			{	// For non-js users...JS users get this link changed onload
				$non_js_url = regenerate_url( 'jal_view_results,redir,poll_id,return_to',
							'redir=no&amp;jal_view_results=true&amp;poll_id='.$poll_Q->id.'&amp;return_to='.rawurlencode($ReqURI) );
				echo '<p><a id="view-results" href="'.$non_js_url.'">'.$this->T_('View Results').'</a></p>';

				if( !empty($user_added) )
				{
					echo '<p><small><sup>1</sup> = '.$this->T_('Added by a guest').'</small></p>';
				}
			}
			else
			{
				echo $this->T_('No votes yet');
			}

			echo '</div>';

			$Form->end_form();

			if( !empty($params['archive_link']) && !empty($Blog) )
			{
				$archive_url = url_add_param( $Blog->gen_blogurl(), 'disp=pollarchives' );
				echo '<a href="'.$archive_url.'" title="'.$this->T_('See the old polls').'">'.$this->T_('Poll Archives').'</a><br />';
			}
			echo $params['block_end'];
		}
	}


	function jal_activate_poll ()
	{
		global $DB, $current_User, $blog;

		// Security
		$current_User->check_perm( 'blog_properties', 'edit', true, $blog );

		if( !$id = param( 'poll_id', 'integer' ) ) return;

		// Deactivate the old active poll
		$DB->query('UPDATE '.$this->get_sql_table('Q').'
					SET active = 0
					WHERE blog_id = '.$DB->quote($blog).'
					AND active = 1');

		// Activate the new poll
		$DB->query('UPDATE '.$this->get_sql_table('Q').'
					SET active = 1
					WHERE id = '.$DB->quote($id));
	}


	function jal_add_question()
	{
		global $DB, $current_User, $blog, $localtimenow;

		// Security
		$current_User->check_perm( 'blog_properties', 'edit', true, $blog );

		// deactive old poll
		$DB->query('UPDATE '.$this->get_sql_table('Q').'
					SET active = 0
					WHERE blog_id = '.$DB->quote($blog).'
					AND active = 1');

		// Let users add their own answers?
		$allowusers = param( 'allowNewAnswers', 'integer', 0 );
		$usechart = param( 'usechart', 'integer', 0 );

		// Add a new question and activate it
		$DB->query('INSERT INTO '.$this->get_sql_table('Q').'
						(blog_id, question, timestamp, allowusers, usechart, active)
					VALUES ('.$DB->quote($blog).',
							"'.$DB->escape($_POST['jal_dem_question']).'",
							"'.$DB->escape( $localtimenow ).'",
							'.$DB->quote($allowusers).',
							'.$DB->quote($usechart).',
							1 )');

		// Get the id of that new question
		$new_q = $DB->get_var('SELECT id FROM '.$this->get_sql_table('Q').'
							   WHERE blog_id = '.$DB->quote($blog).'
							   AND active = 1');

		// Add the question
		$answers = array_filter($_POST['answer']);
		foreach( $answers as $answer )
		{
			$DB->query('INSERT INTO '.$this->get_sql_table('A').'
							(qid, answers, votes)
						VALUES ( '.$DB->quote($new_q).',
							"'.$DB->escape($answer).'",
							0 )');
		}
	}


	function jal_edit_poll()
	{
		global $DB, $current_User, $blog;

		// Security
		$current_User->check_perm( 'blog_properties', 'edit', true, $blog );

		$poll_id = param( 'poll_id', 'integer' );

		// read which aids are in this poll
		$edits = explode(' ', $_POST['editable']);
		$question = param( 'question', 'string' );
		$answers = param( 'answer', 'array' );

		// Let users add their own answers?
		$allowusers = param( 'allowNewAnswers', 'integer', 0 );
		$usechart = param( 'usechart', 'integer', 0 );

		// update the question
		$DB->query('UPDATE '.$this->get_sql_table('Q').'
					SET question = "'.$DB->escape($question).'",
						allowusers = '.$DB->quote($allowusers).',
						usechart= '.$DB->quote($usechart).'
					WHERE id = '.$DB->quote($poll_id));

		foreach( $answers as $aid => $answer )
		{
			$aid = (int)$aid;

			if( empty($answer) && in_array($aid, $edits) )
			{
				$DB->query('DELETE FROM '.$this->get_sql_table('A').'
							WHERE qid = '.$DB->quote($poll_id).'
							AND aid = '.$DB->quote($aid) );
			}

			if( !empty($answer) && in_array($aid, $edits) )
			{
				$DB->query('UPDATE '.$this->get_sql_table('A').'
							SET answers = "'.$DB->escape($answer).'"
							WHERE qid = '.$DB->quote($poll_id).'
							AND aid = '.$DB->quote($aid) );
			}

			if( !empty($answer) && !in_array($aid, $edits) )
			{
				$DB->query('INSERT INTO '.$this->get_sql_table('A').'
								(qid, answers, votes)
							VALUES ( '.$DB->quote($poll_id).', "'.$DB->escape($answer).'", 0 )');
			}
		}
	}


	function jal_deactivate_poll()
	{
		global $DB, $current_User, $blog;

		// Security
		$current_User->check_perm( 'blog_properties', 'edit', true, $blog );

		// Deactivate the old active poll
		$DB->query('UPDATE '.$this->get_sql_table('Q').'
					SET active = 0
					WHERE blog_id = '.$DB->quote($blog).'
					AND active = 1');
	}


	function jal_delete_poll()
	{
		global $DB, $current_User, $blog;

		// Security
		$current_User->check_perm( 'blog_properties', 'edit', true, $blog );

		$poll_id = param( 'poll_id', 'integer' );

		// Delete the poll question and its answers
		$DB->query('DELETE FROM '.$this->get_sql_table('Q').' WHERE id = '.$DB->quote($poll_id));
		$DB->query('DELETE FROM '.$this->get_sql_table('A').' WHERE qid = '.$DB->quote($poll_id));
	}


	// Set the plugin up to take AJAX calls
	function GetHtsrvMethods()
	{
		return array( 'cast_vote' );
	}


	function htsrv_cast_vote( $params )
	{
		global $DB, $Hit;

		// Prevent spam
		if( $Hit->get_agent_type() != 'browser' || !isset($_POST['params']) ) return;

		$post_params = unserialize( stripslashes( $_POST['params'] ) );
		$poll_id = param( 'poll_id', 'integer' );

		if( param('demGet', 'boolean') )
		{	// JS enabled, we just want to display results and exit
			// This is not a vote
			$this->jal_SeeResults( $post_params, $poll_id, true );
			return;
		}

		$poll_aid = param( 'poll_aid', 'string' );
		$poll_vote = param( 'poll_vote', 'string' );

		if( empty($poll_aid) || empty($poll_id) || ($poll_vote == '' && $poll_aid == 'newAnswer') ) return;

		// Check if already woted
		if( ! $this->is_ip_allowed( $poll_id, false, $post_params ) ) return;

		if( $poll_aid == 'newAnswer' )
		{	// Add a new answer to the choice list
			$aid = (int) $DB->get_var("SELECT aid FROM ".$this->get_sql_table('A')."
										ORDER BY aid DESC
										LIMIT 1");

			$cookie_answer_id = $aid + 1;
		}
		else
		{
			$cookie_answer_id = $poll_aid;
		}
		// Give the user a cookie
		setcookie( 'b2demVoted_'.$poll_id, $cookie_answer_id, (time() + 60*60*24*30*3), '/' );

		if( param( 'jal_nojs', 'boolean' ) )
		{	// JavaScript is turned off

			if( $poll_aid == 'newAnswer' )
			{	// Add a new answer to the choice list
				$SQL = 'SELECT allowusers FROM '.$this->get_sql_table('Q').'
						WHERE id = '.$DB->quote($poll_id);

				if( $DB->get_var($SQL) == 1 )
				{	// Add the new choice
					$DB->query('INSERT INTO '.$this->get_sql_table('A').' (qid, answers, votes, added_by)
								VALUES ('.$DB->quote($poll_id).', "'.$DB->escape($poll_vote).'", 1, 1)');
				}
			}
			else
			{	// A vote for existing answer
				$DB->query('UPDATE '.$this->get_sql_table('A').'
							SET votes = (votes+1)
							WHERE qid = '.$DB->quote($poll_id).'
							AND aid = "'.$DB->escape($poll_aid).'"' );
			}
			// Send them back
			header_redirect( $post_params['return_to'] );
		}
		else
		{	// JavaScript is working

			if( param( 'new_vote', 'boolean' ) )
			{	// Adding new answer
				$SQL = 'SELECT allowusers FROM '.$this->get_sql_table('Q').'
						WHERE id = '.$DB->quote($poll_id);

				if( $DB->get_var($SQL) == 1 )
				{
					// Add the new answer and give it one vote
					$DB->query('INSERT INTO '.$this->get_sql_table('A').' (qid, answers, votes, added_by)
								VALUES ( '.$DB->quote($poll_id).', "'.$DB->escape($poll_vote).'", 1, 1)');
				}
			}
			else
			{
				$DB->query('UPDATE '.$this->get_sql_table('A').'
							SET votes = (votes+1)
							WHERE qid = '.$DB->quote($poll_id).'
							AND aid = "'.$DB->escape($poll_aid).'"' );
			}
			// Display results
			$this->jal_SeeResults( $post_params, $poll_id );
		}
	}


	// Run a check of visitors IP to make sure they haven't voted already
	function is_ip_allowed( $poll_id = 0, $return = false, $params = array() )
	{
		global $DB, $Hit;

		// No IP
		if( !$Hit->IP ) return true;

		$where = ($poll_id == 0) ? 'active = 1' : 'id = '.$DB->quote($poll_id);

		$SQL = 'SELECT voters FROM '.$this->get_sql_table('Q').'
				WHERE '.$where;

		if( ($all_ips = $DB->get_var($SQL)) === NULL )
		{	// No voters yet
			$results = array($Hit->IP);

			if( $return ) return true;
		}
		else
		{
			$results = @unserialize($all_ips);

			// Make sure there have been votes
			if( !empty($results) && is_array($results) )
			{
				if( in_array($Hit->IP, $results) )
				{	// Already voted
					if( $return ) return false;

					if( param( 'jal_nojs', 'boolean' ) )
					{	// Send them back
						$this->msg( $this->T_('You have already voted on this poll.'), 'error');
						header_redirect( $params['return_to'] );
					}
					else
					{	// JS is working, display results and die
						//$this->jal_SeeResults( $params, $poll_id );
						echo $this->T_('You have already voted on this poll.');
						die;
					}
				}

				if( $return ) return true;

				// Add new IP address to the array
				$results[] = $Hit->IP;
			}
			else
			{	// This is the first vote
				if( $return ) return true;

				$results = array($Hit->IP);
			}
		}

		if( ! $return )
		{
			$DB->query('UPDATE '.$this->get_sql_table('Q').'
						SET voters = "'.$DB->escape(serialize($results)).'"
						WHERE '.$where);
		}

		return true;
	}


	// Prints the standings of a poll
	function jal_SeeResults( $params, $poll_id = 0, $return_to_vote = false )
	{
		global $DB, $jal_sort, $blog, $Blog, $Hit, $baseurl;

		$where = (empty($poll_id)) ? 'active = 1' : 'id = '.$DB->quote($poll_id);
		$order_by = ($params['sort']) ? 'votes DESC' : 'aid ASC';

		$SQL = 'SELECT * FROM '.$this->get_sql_table('Q').'
				WHERE '.$where;

		if( !$poll_Q = $DB->get_row($SQL) ) return;

		$SQL = 'SELECT * FROM '.$this->get_sql_table('A').'
				WHERE qid = '.$DB->quote($poll_id).'
				ORDER BY '.$order_by;

		if( !$poll_A = $DB->get_results($SQL) ) return;

		echo "<div id='democracy'>\n\n\t";

		// Get COOKIE value
		$cookie = param( 'b2demVoted_'.$poll_Q->id, 'integer', 0 );

		// Dsiplay Poll question
		echo $params['before_q'];
		echo $poll_Q->question;
		echo $params['after_q'];

		if( $this->Settings->get('usechart') && ( $poll_Q->usechart ) )
		{
			echo 'Charts are not available in this version.';
		/*
			// Chart it
			$chart[ 'chart_data' ][0][0] = "";
			$chart[ 'chart_data' ][1][0] = 'Answers';

			$count = 0;
			foreach( $poll_A as $A )
			{
				++$count;
				$chart[ 'chart_data' ][0][ $count ] = $A->votes. ' - '.strip_tags($A->answers);
				$chart[ 'chart_data' ] [1][ $count ] = $A->votes;

			}

			$chart[ 'chart_type' ] = 'pie';

			$chart[ 'legend_rect' ] = array (
					'x'      => 0,
					'y'      => 10,
					'width'  => 160,
					'height' => 100,
					'margin' => 2
					);

			$chart[ 'canvas_bg' ] = array (
					'width'  => 150,
					'height' => 250,
					'color'  => 'ffffff'
				);

			$chart [ 'legend_label' ] = array(
					'size'    =>  10,
				);

			$chart[ 'chart_rect' ] = array (
					'x'      => 15,
					'y'      => 120,
					'width'  => 120,
					'height' => 120,
				);

			$chart [ 'chart_value' ] = array (
					'position'       =>  "inside",
					'hide_zero'      =>  true,
					'as_percentage'  =>  true,
					'font'           =>  "arial",
					'bold'           =>  true,
					'size'           =>  15,
					'color'          =>  "000000",
					'alpha'          =>  75
				);

			echo '<div class="center">';
			DrawChart( $chart );
			echo '</div>';
		*/
		}
		else
		{
			echo '<ul>';

			// Search for the winner of the poll
			$values = array();
			foreach( $poll_A as $A )
			{
				$values[] = $A->votes;
			}

			$winner = max($values);
			$total_votes = array_sum($values);

			$output = '';
			// Loop for the number of answers
			foreach( $poll_A as $A )
			{
				// Percent of total votes
				$percent = round( $A->votes / ($total_votes + 0.0001) * 100 );

				// Percent to display in the graph, as set at the top of the file
				$graph_percent = ($params['gft']) ? $percent : round( $A->votes / ($winner + 0.0001) * 100 );

				if( $A->added_by == 1 )
				{
					$user_added = '<sup title="'.$this->T_('Added by a guest').'">1</sup>';
				}
				else
				{
					$user_added = '';
				}

				// See which choice they voted for
				$voted_for_this = ($cookie == $A->aid) ? true : false;

				// In the graphs, define which class/id to use
				$graph_hooks = 'class="dem-choice-border"';
				if( $voted_for_this )
				{
					$graph_hooks = 'id="voted-for-this" class="dem-choice-border"';
				}

                $output .="\n\t<li>";
                $output .= $A->answers.$user_added.': ';
                $output .= '<strong>'.$percent.'%</strong>';
                $output .= ' ('.$A->votes.')';

                // Graph it
                $output .= '<span '.$graph_hooks.'><span class="democracy-choice" style="width: '.
							$graph_percent.'%; background:'.$params['gct'].
							'; border-bottom:2px solid '.$params['gcb'].'"></span></span>';

                $output .= '</li>';

                echo $output;

                $output = ''; // reset for the next loop
        	}
            echo '</ul>';
			echo '<p><em id="dem-total-votes">'.$this->T_('Total votes').': <b>'.$total_votes.'</b></em></p>';
		}
		// end chart

		if( empty($cookie) && $return_to_vote )
		{	// If they are just looking at the results and haven't voted

			if( !empty($params['return_to']) )
			{
				$return_to = $params['return_to'];
			}
			elseif( !$return_to = param( 'return_to', 'string' ) )
			{
				if( $Hit->referer )
				{
					$return_to = $Hit->referer;
				}
				else
				{
					$BlogCache = & get_Cache( 'BlogCache' );
					$Blog = & $BlogCache->get_by_ID( $params['blog'] );

					$return_to = $Blog->get('url');
				}
			}
			if( empty($return_to) ) $return_to = $baseurl;

			echo '<p><a href="'.$return_to.'">'.$this->T_('Return to vote').'</a></p>';
		}

		if( !empty($user_added) )
		{
			echo '<p><small><sup>1</sup> = '.$this->T_('Added by a guest').'</small></p>';
		}

		echo "</div>\n\n";
	}


	// The archiving function
	function jal_democracy_archives( $params )
	{
		global $DB, $jal_graph_from_total, $blog, $cookie;

		$where = "WHERE active = '0'";
		$where .= ( empty( $params['allblogs'] ) ) ? "" : " AND blog_id = '{$blog}'";

		$poll_questions = $DB->get_results("SELECT * FROM ".$this->get_sql_table('Q')." {$where} ORDER BY id DESC", ARRAY_A);
		$poll_answers   = $DB->get_results("SELECT * FROM ".$this->get_sql_table('A')." ORDER BY votes DESC", ARRAY_A);

		$poll_q = array();
		$poll_votes = array();

		// index by poll question id. Much faster than querying for each question
		foreach( $poll_answers as $answer )
		{
			 // index answer arrays
			 $poll_q[$answer['qid']][] = $answer;
			 // index total votes
			 $poll_votes[$answer['qid']][] = $answer['votes'];
		}

		// loop for all the poll questions
		foreach( $poll_questions as $question )
		{
			$total_votes = array_sum( $poll_votes[$question->id] );
			$winner = max( $poll_votes[$question->id] );

			echo $params['block_start'];
			echo $params['before_q'].$question->question.$params['after_q'];
			echo '<br /><strong>'.$this->T_('Started').':</strong> '.date(locale_datefmt(), $question->timestamp);
			echo '<br /><strong>'.$this->T_('Total Votes').':</strong> '.$total_votes;
			echo '<ul>';

			foreach( $poll_q[$question->id] as $answer )
			{
				// Percent of total votes
				$percent = round($answer['votes'] / ($total_votes + 0.0001) * 100);
				// Percent to display in the graph, as set at the top of the file
				$graph_percent = ($jal_graph_from_total) ? $percent : round($answer['votes'] / ($winner + 0.0001) * 100);

				// See which choice they voted for
				$voted_for_this = ($cookie == $answer['aid']) ? TRUE : FALSE;

				if( $answer['added_by'] == "1" )
				{
					$user_added = '<sup title="'.$this->T_('Added by a guest').'">1</sup>';
					$add_sup = TRUE;
				}
				else
				{
					$user_added = '';
				}

				$output = "<li>";
				$output .= $answer['answers'].$user_added.': ';
				$output .= "<strong>".$percent."%</strong>";
				$output .= " ({$answer['votes']})";

				// Graph it
				$output .= '<span class="dem-choice-border"><span class="democracy-choice" style="width: '.$graph_percent.'%;background:'.$params['gct'].';border-bottom:2px solid '.$params['gcb'].'"></span></span>';
				$output .= "</li>";

				echo $output;
			}
			echo "</ul>";
			echo $params['block_end'];
		}
		if( !empty( $add_sup ) )
		{
			echo '<br /><small><sup>1</sup> = '.$this->T_('Added by a guest').'</small>';
		}
	}
}

?>