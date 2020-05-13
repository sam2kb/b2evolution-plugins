<?php
/**
 * This file implements the Easy Rating plugin for
 *
 * @copyright (c)2010-2012 by Russian b2evolution - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Alex (sam2kb)
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 *
 * TODO:
 * Antispam (and robot) hidden first option, then block
 * Allow restricting to members
 */
class easy_rating_plugin extends Plugin
{
	var $name = 'Easy Rating';
	var $code = 'easy_rating';
	var $priority = 10;
	var $version = '1.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=';

	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;

	// Internal
	var $disp_params = array();


	function PluginInit()
	{
		$this->name = $this->T_('Easy Rating');
		$this->short_desc = $this->T_('AJAX-powered ratings for posts and files.');
	}


	function GetDefaultSettings()
	{
		return array(
				'checkip' => array(
					'label' => $this->T_('Check IP'),
					'defaultvalue' => 1,
					'note' => $this->T_('Check IP to prevent multiple votes on one post from one person.'),
					'type' => 'checkbox'
				),
				'users_only' => array(
					'label' => $this->T_('Users only'),
					'defaultvalue' => 0,
					'note' => $this->T_('Check this to allow ratings to registered users only.'),
					'type' => 'checkbox'
				),
				'vote_value' => array(
					'label' => $this->T_('Vote value'),
					'defaultvalue' => 1,
					'type' => 'integer'
				),
			);
	}


	/**
	 * Create the table to store rating votes
	 */
	function GetDbLayout()
	{
		return array(
			"CREATE TABLE ".$this->get_sql_table('data')." (
					vote_ID INT(11) NOT NULL auto_increment,
					target_ID INT(11) NOT NULL,
					total_votes INT(11) NOT NULL default '0',
					total_value INT(11) NOT NULL default '0',
					user_ID INT(11) NOT NULL,
					type ENUM( 'item', 'file', 'comment', 'other' ) DEFAULT 'item' NOT NULL,
					used_ips LONGTEXT,
					PRIMARY KEY (vote_ID),
					INDEX (target_ID),
					INDEX (user_ID)
				)",
			);
	}


	function RenderItemAsHtml( & $params )
	{
		// This is not actually a renderer,
		// we're just using the checkbox
		// to see if we should display for
		// the given post.
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
					'label' => $this->T_('Widget title'),
					'note' => $this->T_('Widget title displayed in skin.'),
					'defaultvalue' => $this->T_('Top Rated'),
				),
				'limit' => array(
					'label' => $this->T_('Display'),
					'note' => $this->T_('Max items to display.'),
					'size' => 3,
					'defaultvalue' => 5,
					'type' => 'integer',
				),
				'minvote' => array(
					'label' => $this->T_('Minimum votes'),
					'note' => $this->T_('Minimum number of votes required to be in the list.'),
					'size' => 3,
					'defaultvalue' => 1,
					'type' => 'integer',
				),
				'action' => array(
					'label' => $this->T_('Method'),
					'defaultvalue' => 'top_rated',
					'disabled' => 1,
				),
			);
	}


	function BeforeBlogDisplay()
	{
		// Save the object for use in skin
		set_param( 'EasyRating_plugin', $this );
	}


	function SkinBeginHtmlHead( & $params )
	{
		global $plugins_url;

		$plugin_url = $this->get_plugin_url( true );

		// Display the head code:
		require_js('#jquery#');
		require_js( $plugin_url.'easy_rating.js', false );
		require_css( $plugin_url.'easy_rating.css', false );

		add_js_headline('
jQuery(function(){
	var t_hover_vote_img = "'.$plugin_url.'images/vote-button_t_hov.png";
	var b_hover_vote_img = "'.$plugin_url.'images/vote-button_b_hov.png";
	var orig_vote_img = "'.$plugin_url.'images/vote-button.png";

	jQuery(".item-vote-t, .comment-vote-t").mouseover(function () {
			jQuery(this).parent().css("backgroundImage", "url(" + t_hover_vote_img + ")" );
		});
	jQuery(".item-vote-b, .comment-vote-b").mouseover(function () {
			jQuery(this).parent().css("backgroundImage", "url(" + b_hover_vote_img + ")" );
		});
	jQuery(".item-vote-t, .comment-vote-t, .item-vote-b, .comment-vote-b").mouseout(function () {
			jQuery(this).parent().css("backgroundImage", "url(" + orig_vote_img + ")" );
		});
});
');
	}


	function SkinEndHtmlBody( & $params )
	{
		echo '<div class="easy_ratingResults" style="display:none"></div>';
	}


	function SkinTag( $params )
	{
		global $DB, $Plugins, $plugins_url, $blog, $disp, $Blog, $Item, $ReqHost, $ReqURI;

		if( empty($params['action']) ) $params['action'] = 'vote';
		if( empty($params['target_ID']) && $params['action'] != 'top_rated' ) return;

		switch( $params['action'] )
		{
			case 'top_rated':	// Widget
				$this->disp_top_rated( $params );
				break;

			case 'get_rating':
				echo $this->get_rating( $params['target_ID'], $params['type'] );
				break;

			case 'get_covers':
				if( !$disp_params = $params['disp_params'] ) return;

				if( is_object($disp_params['Item']) ) $Item = $disp_params['Item'];

				// AJAX url:
				$url_js = $this->get_htsrv_url( 'vote',
					  array(
						  'vote' => 1,
						  'blog' => $blog,
						  'target' => 'file',
					  ) );
				$url_js = url_add_param( $url_js, 'formID=EasyRatingForm_'.$Item->ID );

				// Url for non-JS browsers
				$url = $this->get_htsrv_url('vote',
					  array(
						  'vote' => 1,
						  'blog' => $blog,
						  'target' => 'file',
						  'returnto' => $ReqHost.$ReqURI,
					  ) );

				$onsubmit = array( 'onsubmit' => 'javascript:sndReq("#EasyRatingForm_'.$Item->ID.'", "'.$url_js.'");return false;' );
				$Form = new Form( $url, 'EasyRatingForm_'.$Item->ID );
				$Form->output = false;

				$r = '<div class="easy_ratingFormResults">';
				$r .= $Form->begin_form( '', '', $onsubmit );

				$votes = 0;

				// Get list of attached files
				$FileList = $Item->get_attachment_FileList(20);
				while( $File = & $FileList->get_next() )
				{
					if( ! $File->exists() || ! $File->is_image() ) continue;

					$value = $this->get_rating( $File->ID, 'file', 'value' );
					$votes = $votes + $this->get_rating( $File->ID, 'file', 'votes' );

					$image_size = $disp_params['image_size'];
					if( $disp == 'posts' ) $image_size = $disp_params['excerpt_image_size'];

					// Generate the IMG tag with all the alt, title and desc if available
					$image_tag = $File->get_tag( '', $disp_params['before_image_legend'],
									$disp_params['after_image_legend'], '',
									$image_size, 'original' );

					$image_tag = preg_replace( '~<a href~', '<a class="fancybox-nobg" href', $image_tag );


					$width = 80;
					if( preg_match( '~([0-9]+)x[0-9]+$~', $image_size, $matches ) )
					{	// Thumbnail width
						$width = $matches[1];
					}

					$Images[] = array(
							'value'		=> $value,
							'tag'		=> $image_tag,
							'ID'		=> $File->ID,
							'width'		=> $width,
						);

					$winners[] = $value;
				}

				// No images
				if( empty($Images) ) return;

				$winner_value = max($winners);
				$first_cover = true;
				$new_vote = true;
				$notes_class = array();

				foreach( $Images as $Image )
				{
					// Percent of total votes
					$percent = round( $Image['value'] / ($votes + 0.0001) * 100 );

					$winner = '';
					if( $Image['value'] == $winner_value )
					{	// Cpecial class for winner
						$winner = 'winner-line';
					}

					if( $first_cover )
					{
						$r .= str_replace( 'class="', 'class="first_cover ', $disp_params['before_image'] );
					}
					else
					{
						$r .= $disp_params['before_image'];
					}

					// Image
					$r .= $Image['tag'];

					// Line
					$r .= '<div class="cover_results_line" style="width:'.$Image['width'].'px"><span class="cover_results_line_border"><span class="cover_results_line_choice '.$winner.'" style="width: '.$percent.'%"></span></span></div>';

					// Votes
					$r .= '<div class="cover_results_percent">'.$percent.'%</div>';

					// Radio
					$r .= '<div class="cover_radio">
							<input name="target_ID" type="radio" value="'.$Image['ID'].'" /></div>';

					$r .= $disp_params['after_image'];

					//$notes_class[] = 'easy_ratingFormResults_'.$Image['ID'];

					// See if already voted
					if( $new_vote ) $new_vote = $this->is_new_vote( $Image['ID'], 'file' );

					$first_cover = false;
				}

				$r .= '<div class="cover_item_excerpt">'.$Item->get_excerpt().'</div>';


				$submit = '';

				if( $new_vote )
				{	// Not voted yet
					$submit = '<input type="submit" value="Проголосовать" />';
				}
				else
				{	// Already voted
					$submit = '<p class="voted">Спасибо,<br />ваш голос учтен!</p>';
				}

				$r .= '<div class="cover_item_submit">
						<div>'.$submit.'</div>
					   </div>';

				$r .= $Form->end_form();
				$r .= '<div class="clear"></div></div>';

				return $r;
				break;

			case 'vote':
				if( $params['type'] == 'item' && is_object($Item) )
				{
					// Check to see if we want a rating for this item
					$renders = $Plugins->validate_list( $Item->get_renderers() );

					// If the renderer checkbox is unchecked, then display nothing
					if( !in_array($this->code, $renders) ) return false;
				}

				// Plugin URL
				$plugin_url = $this->get_plugin_url( true );

				// Vote value
				$value = $this->Settings->get('vote_value');

				// AJAX url:
				$url_js_pos = $this->get_htsrv_url( 'vote',
					  array(
						  'vote' => $value,
						  'ID' => $params['target_ID'],
						  'blog' => $blog,
						  'target' => $params['type'],
					  ) );
				// Url for non-JS browsers
				$url_pos = $this->get_htsrv_url('vote',
					  array(
						  'vote' => $value,
						  'ID' => $params['target_ID'],
						  'blog' => $blog,
						  'target' => $params['type'],
						  'returnto' => $ReqHost.$ReqURI,
					  ) );

				// AJAX url:
				$url_js_neg = $this->get_htsrv_url( 'vote',
					  array(
						  'vote' => -$value,
						  'ID' => $params['target_ID'],
						  'blog' => $blog,
						  'target' => $params['type'],
					  ) );
				// Url for non-JS browsers
				$url_neg = $this->get_htsrv_url('vote',
					  array(
						  'vote' => -$value,
						  'ID' => $params['target_ID'],
						  'blog' => $blog,
						  'target' => $params['type'],
						  'returnto' => $ReqHost.$ReqURI,
					  ) );

				echo '<div class="'.$params['type'].'-vote">
				<div class="'.$params['type'].'-vote-t"><a href="'.$url_pos.'" rel="nofollow" onclick="javascript:sndReq(\'#'.$params['type'].'_ratingResults_'.$params['target_ID'].'\', \''.$url_js_pos.'\');return false"><img width="30" height="60" src="'.$plugin_url.'images/vote-button_blank.png" alt="+" /></a></div>

				<div class="'.$params['type'].'-vote-b"><a href="'.$url_neg.'" rel="nofollow" onclick="javascript:sndReq(\'#'.$params['type'].'_ratingResults_'.$params['target_ID'].'\', \''.$url_js_neg.'\');return false"><img width="30" height="60" src="'.$plugin_url.'images/vote-button_blank.png" alt="-" /></a></div>
				</div>';
				break;
		}
	}


	// Set the plugin up to take AJAX calls
	function GetHtsrvMethods()
	{
		return array( 'vote', 'get_rating' );
	}


	function htsrv_get_rating( & $params )
	{
		global $DB, $Hit;

		if( !empty($Hit) && $Hit->get_agent_type() != 'browser' )
		{	// Do not allow robots to vote ;)
			if( ! headers_sent() )
			{
				header('HTTP/1.0 400 Bad Request');
			}
			return false;
		}

		if( empty($params['ID']) ) return;

		$params = array_merge( array(
				'ID'		=> 0,
				'target'	=> 'item',
				'what'		=> 'value',
			), $params );

		$rating = $this->get_rating( $params['ID'], $params['target'], $params['what'] );

		echo $rating;
		exit;
	}


	function htsrv_vote( & $params )
	{
		global $DB, $Hit, $Debuglog, $current_User, $current_charset, $htsrv_url_sensitive;

		if( !empty($Hit) && $Hit->get_agent_type() != 'browser' )
		{	// Do not allow robots to vote ;)
			if( ! headers_sent() )
			{
				header('HTTP/1.0 400 Bad Request');
			}
			return false;
		}

		if( !is_logged_in() )
		{	// Use blog locale if not logged in
			$BlogCache = & get_Cache( 'BlogCache' );
			if( $Blog = & $BlogCache->get_by_ID( $params['blog'], false, false ) )
			{
				$Debuglog->add( 'Activating blog locale: '.$Blog->get('locale'), 'locale' );
				locale_activate( $Blog->get('locale') );
				init_charsets($current_charset);
			}
		}

		$user_ID = 0;
		if( is_logged_in() && $current_User->ID )
		{	// User vote
			$user_ID = $current_User->ID;
		}

		if( $formID = param( 'formID', 'string' ) )
		{	// Covers
			$params['formID'] = $formID;
		}

		if( $this->Settings->get('users_only') && empty($user_ID) )
		{	// Visitor vote
			$msg = '<p>Вам нужно<br />
					<a href="'.$htsrv_url_sensitive.'register.php">зарегистрироваться</a><br />
					или <a href="'.get_login_url().'">войти на сайт</a></p>';

			return $this->message( $msg, 'error', $params );
		}

		switch( $params['target'] )
		{
			case 'item':
			case 'file':
			case 'comment':
				break;

			default:
				return $this->message( $this->T_('Unknown target type').' "'.$params['target'].'"', 'error', $params );
		}

		if( !isset($params['ID']) && $target_ID = param( 'target_ID', 'integer' ) )
		{
			$params['ID'] = $target_ID;
		}

		if( empty($params['ID']) )
		{	// Nothing selected, can't get target ID
			return $this->message( 'Ничего не выбрано!', 'error', $params );
		}


		$r = $DB->get_row('
					SELECT * FROM '.$this->get_sql_table('data').'
					WHERE target_ID = '.$DB->quote( $params['ID'] ).'
					AND type = "'.$DB->escape( $params['target'] ).'"'
				);

		$vote_ID = empty($r->vote_ID) ? 0 : $r->vote_ID;
		$IPs = empty($r->used_ips) ? NULL : unserialize($r->used_ips);
		$votes = empty($r->total_votes) ? 0 : $r->total_votes;
		$current_rating = empty($r->total_value) ? 0 : $r->total_value;

		$new_rating = $params['vote'] + $current_rating;

		if( !$this->is_new_vote( $params['ID'], $params['target'], true, $params ) )
		{	// Already voted
			return;
		}

		// Cookie lasts 3 months
		$cookie_name = 'ezVote_'.$params['target'].'_'.$params['ID'];
		$cookietime = 60*60*24*30*3;

		// Set a cookie
		setcookie( $cookie_name, 1, time() + $cookietime, '/' );

		if( $new_rating == 0 )
		{
			$new_votes = 0;
		}
		else
		{
			$new_votes = $votes + 1;
		}

		if( is_array($IPs) )
		{	// If it is an array i.e. already has entries then push in another value
			array_push( $IPs, $Hit->IP );
		}
		else
		{	// For the first entry
			$IPs = array($Hit->IP);
		}
		$IPs_ser = serialize($IPs);


		if( !empty($vote_ID) )
		{
			$DB->query( 'UPDATE '.$this->get_sql_table('data').'
						 SET total_votes = '.$DB->quote( $new_votes ).',
							 total_value = '.$DB->quote( $new_rating ).',
							 used_ips = '.$DB->quote( $IPs_ser ).',
							 user_ID = '.$DB->quote( $user_ID ).'
						 WHERE vote_ID = '.$DB->quote( $vote_ID ) );
		}
		else
		{
			$DB->query( 'INSERT INTO '.$this->get_sql_table('data').'
								( target_ID, total_votes, total_value, used_ips, user_ID, type )
						 VALUES ('.$DB->quote( $params['ID'] ).',
								 '.$DB->quote( $new_votes ).',
								 '.$DB->quote( $new_rating ).',
								 '.$DB->quote( $IPs_ser ).',
								 '.$DB->quote( $user_ID ).',
								 '.$DB->quote( $params['target'] ).')' );
		}

		return $this->message( 'Спасибо,<br />ваш голос учтен!', 'success', $params );
	}


	function message( $msg, $msg_status, $params = array() )
	{
		global $current_charset;

		// If JavaScript is off, then just send them back to the referer
		if( empty($params) || isset($params['returnto']) )
		{
			if( empty($params['returnto']) ) $params['returnto'] = NULL;

			//$this->msg( $msg, $msg_status );
			header_redirect($params['returnto']);
		}
		else
		{
			if( !empty($params['formID']) )
			{
				$selector = $params['formID'];
				$echo = true;

				// Name of the div id to be updated | the html that needs to be changed
				$output = $selector.'|<p class="voted">'.$msg.'</p>';
			}
			else
			{
				$selector = 'ratingResults';
				$echo = false;

				// Name of the div id to be updated | the html that needs to be changed
				$output = $selector.'|<div class="ratingNotes">
						<span class="'.$msg_status.'">'.$msg.'</span></div>';
			}
			header('Content-Type: text/html; charset='.$current_charset);

			if( $echo ) echo $output;
		}
	}


	// Display the Top Rated block
	function disp_top_rated( $params )
	{
		global $DB, $blog;

		if( !isset($params['title'])) $params['title'] = $this->T_('Top Rated');	// Title
		if( !isset($params['before_rating'])) $params['before_rating'] = ' (';		// What comes before and after the comment count
		if( !isset($params['after_rating'])) $params['after_rating'] = ')';			// How many of the top posts to show
		if( !isset($params['limit'])) $params['limit'] = 5;							// Limit
		if( !isset($params['minvote'])) $params['minvote'] = 1;						// Minimum number of votes required to be in the list
		$this->init_display( $params );

		$sql = '
			SELECT DISTINCT target_ID, total_value, total_votes
			FROM ( T_items__item , '.$this->get_sql_table('data').')
			INNER JOIN T_postcats ON target_ID = postcat_post_ID
			INNER JOIN T_categories ON postcat_cat_ID = cat_ID
			WHERE cat_blog_id = '.$DB->quote($blog).'
			AND '.$this->get_sql_table('data').'.target_ID = T_items__item.post_ID
			AND total_votes >= '.$DB->quote($params['minvote']).'
			AND type = "item"
			ORDER BY total_value DESC
			LIMIT 0,'.$params['limit'];

		$results = $DB->get_results($sql);

		if( empty($results) ) return;
		if( empty($results[0]->total_value) ) return;

		$ItemCacheLight = & get_Cache( 'ItemCacheLight' );

		// START DISPLAY:
		echo $params['block_start'];
		echo $params['block_title_start'];
		echo $params['title'];
		echo $params['block_title_end'];

		echo $params['list_start'];
		for( $i = 0; $i < $params['limit']; $i++ )
		{
			// Stop the loop if you get to a post with no votes
			if( empty( $results[$i]->total_value ) ) break;

			if( ($rated_item = & $ItemCacheLight->get_by_ID( $results[$i]->target_ID, false )) !== false )
			{
				echo $params['item_start'];
				echo '<a href="'.$rated_item->get_permanent_url().'">'.$rated_item->title.'</a>';

				echo $params['before_rating'];
				echo $results[$i]->total_value;
				echo $params['after_rating'];

				echo $params['item_end'];
			}
		}
		echo $params['list_end'];
		echo $params['block_end'];
	}


	function get_rating( $ID = 0, $type = 'item', $what = 'value' )
	{
		global $DB;

		$SQL = 'SELECT total_'.$what.' FROM '.$this->get_sql_table('data').'
				WHERE target_ID = '.$DB->quote($ID).'
				AND type = "'.$DB->escape($type).'"';

		$num = $DB->get_var($SQL);

		return ($num) ? $num : 0;
	}


	function get_winner( $IDs = array(), $type = 'item', $what = 'value' )
	{
		if( count($IDs) < 2 ) return 0;

		foreach( $IDs as $ID )
		{
			$results[$ID] = $this->get_rating( $ID, $type, $what );
		}
		arsort($results);
		$keys = array_keys($results);
		$winnerID = $keys[0];

		return array( $winnerID, $results[$winnerID] );
	}


	function is_new_vote( $ID, $target, $output = false, $params = array() )
	{
		global $DB, $Hit;

		$msg = 'Вы уже голосовали';

		// Cookie name
		$cookie_name = 'ezVote_'.$target.'_'.$ID;
		if( isset($_COOKIE[$cookie_name]) )
		{	// Found matched cookie
			if( $output ) return $this->message( $msg, 'error', $params );
			return false;
		}

		if( $this->Settings->get('checkip') )
		{	// Check if this ip has voted before or not
			$SQL = 'SELECT vote_ID FROM '.$this->get_sql_table('data').'
					WHERE target_ID = '.$DB->quote($ID).'
					AND type = "'.$DB->escape($target).'"
					AND used_ips LIKE '.$DB->quote( '%'.$Hit->IP.'%' );

			if( $Hit->IP && $DB->get_var($SQL) )
			{	// Found matched IP
				if( $output ) return $this->message( $msg, 'error', $params );
				return false;
			}
		}
		return true; // New vote
	}



	/**
	 * Sets all the display parameters
	 * these will either be the default display params
	 * or the widget display params if it's in a container
	 *
	 * @param array $params
	 */
	function init_display( $params = array() )
	{ // Merge Default settings (from this plugin) with basic widget settings into array $disp_params
		$temp = $this->get_widget_param_definitions( array() );
		foreach( $temp as $setting => $values )
			$this->disp_params[ $setting ] = ( isset( $params[ $setting ] ) ? $params[ $setting ] : $this->Settings->get( $setting ) );

		foreach( $params as $param => $value )
			$this->disp_params[ $param ] = $value;
	}
}


?>