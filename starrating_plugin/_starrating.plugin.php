<?php
/**
 * This file implements the Star Rating plugin for
 * {@link http://b2evolution.net b2evolution}.
 *
 * @author Danny Ferguson - {@link http://www.brendoman.com/dbc}
 * @author sam2kb: Russian b2evolution - {@link http://b2evo.sonorth.com/}
 *
 * @license GNU General Public License 2 (GPL) - {@link http://www.opensource.org/licenses/gpl-license.php}
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 *
 * TODO:
 * Antispam (and robot) hidden first option, then block
 * This is what is skewing votes low
 * Allow restricting to members
 * Record who voted for what
 * Ability to change vote
 * Ability to display main column posts in rating order
 * Remove some repetition of code
 *
 */
class starrating_plugin extends Plugin
{
	var $version = '0.8.0';
	var $priority = 50;
	var $code = 'starrating';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=17010';
	var $width = 20;  // Width of individual star in px.
	var $apply_rendering = 'opt-in';

	/**
	 * @internal
	 */
	var $disp_params = array();

	/**
	 * Set name and desc.
	 */
	function PluginInit()
	{
		$this->name = $this->T_('Star Rating');
		$this->short_desc = $this->T_('AJAX-powered star ratings for posts.');
	}


	/**
	 * We require b2evo 4.1 or above.
	 */
	function GetDependencies()
	{
		return array(
				'requires' => array(
					'app_min' => '4.1',
				),
			);
	}


	function GetDefaultSettings()
	{
		return array(
		'outof' => array(
			'label' => $this->T_('Out of'),
			'defaultvalue' => 5,
			'note' => $this->T_('How many stars are possible?'),
			'type' => 'integer',
			'size' => '2'
		),
		'checkip' => array(
			'label' => $this->T_('Check IP'),
			'defaultvalue' => 1,
			'note' => $this->T_('Check IP to prevent multiple votes on one post from one person.'),
			'type' => 'checkbox'
		),
		'popup' => array(
			'label' => $this->T_('Pop-up info'),
			'defaultvalue' => 1,
			'note' => $this->T_('Show info and notices in pop-ups that fade in and out. Requires javascript, but does not disrupt layout.'),
			'type' => 'checkbox'
		),
		'inline' => array(
			'label' => $this->T_('Inline info'),
			'defaultvalue' => 0,
			'note' => $this->T_('Show info and notices just below the stars. Works with or without javascript enabled, but may disrupt layout.'),
			'type' => 'checkbox'
		) );
	}


	/**
	 * Create the table to store the rating votes
	 *
	 * @return array
	 */
	function GetDbLayout()
	{
		return array("CREATE TABLE ".$this->get_sql_table('ratings')." (
						`id` varchar(11) NOT NULL,
						`total_votes` int(11) NOT NULL default '0',
						`total_value` int(11) NOT NULL default '0',
						`used_ips` longtext,
						`blog_id` int(11) NOT NULL default '0', PRIMARY KEY (`id`) );");
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
				'display' => array(
					'label' => $this->T_('Method'),
					'defaultvalue' => 'top_rated',
					'disabled' => 1,
				),
			);
	}


	function SkinBeginHtmlHead( & $params )
	{
		global $plugins_url;

		$outof = $this->Settings->get('outof');
		$css = '.unit-rating { width: '.$outof * $this->width.'px }';
		for( $ncount = 1; $ncount <= $outof; $ncount++ )
		{
			$css .= ' .unit-rating a.r'.$ncount.'-unit { left: '.($ncount - 1) * $this->width.'px }';
			$css .= ' .unit-rating a.r'.$ncount.'-unit:hover { width: '.$ncount * $this->width.'px }';
		}

		// Display the head code:
		require_js( '#jquery#' );
		require_js( $plugins_url.'starrating_plugin/starrating.js', false );
		require_css( $plugins_url.'starrating_plugin/stars.css', false );
		add_css_headline($css);

		return true;
	}


	function SkinEndHtmlBody( & $params )
	{
		echo '<div id="ratingResults" style="display:none"></div>';
	}


	function SkinTag( $params )
	{
		global $DB, $Item, $Plugins, $blog, $Blog;

  		if( !isset($params['display']) ) $params['display'] = 'stars';

		switch( $params['display'] )
		{
			case 'notice':		// Deprecated
				break;

			case 'toprated':	// Skin method (deprecated)
			case 'top_rated':	// Widget
				$this->disp_top_rated( $params );
				break;

			case 'stars' && is_object($Item):
			default:
				if( empty($params['id']) ) return;

				// Display the rating code for an Item:
				$outof = $this->Settings->get('outof');

				// Check to see if we want a rating for this post
				$renders = $Plugins->validate_list( $Item->get_renderers() );

				// If the renderer checkbox is unchecked, then display nothing
				if( !in_array($this->code, $renders) ) return false;

				$ip = $_SERVER['REMOTE_ADDR'];
				$r = $DB->get_row('
							SELECT total_votes, total_value, used_ips
							FROM '.$this->get_sql_table('ratings').'
							WHERE id='.$DB->quote( $params['id'] ) );

				if( isset($r) ) $tense = ($r->total_votes == 1) ? $this->T_('vote') : $this->T_('votes');

				echo '<div id="unit_long'.$Item->ID.'">';
					echo '<ul class="unit-rating">';

					echo '<li class="current-rating" style="width: '.@number_format($r->total_value / $r->total_votes , 2 ) * $this->width.'px">'.$this->T_('Currently').' '.@number_format($r->total_value / $r->total_votes , 2 ).'/'.$outof.'</li>';

					for( $ncount = 1; $ncount <= $outof; $ncount++ )
					{	// AJAX url:
						$hturl = $this->get_htsrv_url('vote', array('vote' => $ncount, 'ip' => $ip, 'id' => $Item->ID, 'blog' => $blog ) );

						// Url for non-JS browsers
						$current_url = $Blog->get('url');
						$hturlnojs = $this->get_htsrv_url('vote', array('vote' => $ncount, 'ip' => $ip, 'id' => $Item->ID, 'returnto' => $current_url, 'blog' => $blog ) );

						echo '<li><a href="'.$hturlnojs.'" title="'.$ncount.' '.$this->T_('out of').' '.$outof.'" class="r'.$ncount.'-unit" onclick="javascript:sndReq(\''.$Item->ID.'\', \''.$hturl.'\');return false">'.$ncount.'</a></li>'."\n";
					}

					$ncount = 0; // resets the count
					if( !empty($r->total_votes) )
					{
						$avg = @number_format($r->total_value / $r->total_votes, 1);
						$total = $r->total_votes;
					}
					else
					{
						$avg = '0.0';
						$total = 0;
					}
					$tense = ($total == 1) ? $this->T_('vote') : $this->T_('votes');

					if( $this->Settings->get('popup') )
					{
						echo '<li><a href="javascript:void(0);" onclick="disp_notice(\'&lt;p class=\\\'ratingNotes\\\'>'.sprintf( /*TRANS: Rating: 34 out of 50 votes cast*/ $this->T_('Rating: %s out of %d %s cast'), '&lt;strong>'.$avg.'&lt;/strong>', $total, $tense ).'&lt;/p>\'); return false;" class="starinfo"><span>i</span></a></li>';
					}

					echo '</ul>';

				if( $this->Settings->get('inline') )
				{
					echo '<p class="ratingNotes">'.sprintf( $this->T_('Rating: %s out of %d %s cast'), '<strong>'.$avg.'</strong>', $total, $tense ).' </p>';
				}

				echo '</div>';
				break;
		}
	}


	// Set the plugin up to take AJAX calls
	function GetHtsrvMethods()
	{
		return array( 'vote' );
	}


	function htsrv_vote( & $params )
	{
		global $DB, $Debuglog, $current_charset;

		if( !is_logged_in() )
		{	// Use blog locale if not logged in
			$BlogCache = & get_BlogCache();
			if( $Blog = & $BlogCache->get_by_ID( $params['blog'], false, false ) )
			{
				$Debuglog->add( 'Activating blog locale: '.$Blog->get('locale'), 'locale' );
				locale_activate( $Blog->get('locale') );
				init_charsets($current_charset);
			}
		}

		$outof = $this->Settings->get('outof');
		$ip_num = $params['ip'];
		$id_sent = $params['id'];
		$vote_sent = $params['vote'];
		$blog = $params['blog'];
		$tableName = $this->get_sql_table('ratings');

		$r = $DB->get_row('
					SELECT total_votes, total_value, used_ips
					FROM '.$this->get_sql_table('ratings').'
					WHERE id = '.$DB->quote( $params['id'] ) );

		$checkIP = ( empty( $r->used_ips) ? NULL : unserialize($r->used_ips) );
		$count = ( empty( $r->total_votes ) ? NULL : $r->total_votes );
		$current_rating = ( empty( $r->total_value ) ? 0 : $r->total_value );
		$sum = $params['vote'] + $current_rating;
		$tense = ($count == 1) ? $this->T_('vote') : $this->T_('votes');

		//check see if this ip has voted before or not
		if( $this->Settings->get('checkip') )
		{
			$voted = $DB->get_var( 'SELECT count(*) FROM '.$tableName
								  .' WHERE used_ips LIKE '.$DB->quote( '%'.$ip_num.'%' )
								  .' AND id = '.$DB->quote( $id_sent ) );
		}
		// the above pattern match ip:suggested by Bramus! //http://www.bram.us/

		if( !empty($voted) )
		{
			$new_back = '<ul class="unit-rating">'."\n".
						'<li class="current-rating" style="width:'.@number_format($current_rating/$count,2) * $this->width .'px">'.$this->T_('Current rating').'.</li>'."\n";

			for( $ncount = 1; $ncount <= $outof; $ncount++ )
			{
				$new_back .= "<li class=\"r{$ncount}-unit\">{$ncount}</li>\n";
			}

			if( $this->Settings->get('popup'))
			{
		    	$new_back .= '<li><a href="javascript:void(0);" onclick="disp_notice(\'&lt;p class=\\\'ratingNotes\\\'>'.sprintf( $this->T_('Rating: %s out of %d %s cast'), '&lt;strong>'.@number_format($r->total_value / $r->total_votes, 1).'&lt;/strong>', $r->total_votes, $tense ).'&lt;/p>\'); return false;" class="starinfo"><span>i</span></a></li>';
			}
			$new_back .= '</ul>';

			// show the current value of the vote with the current numbers
			$notice = '<p class="ratingNotes">'.sprintf( $this->T_('Rating: %s out of %d %s cast'), '<strong>'.@number_format($current_rating/$count,1).'</strong>', $count, $tense ).'<br /><span class="error">'.$this->T_('You have previously voted').'!</span></p>';
		}
		else
		{
			if( $sum == 0 )
			{
				$added = 0; //checking to see if the first vote has been tallied
			}
			else
			{
				$added = $count + 1; //increment the current number of votes
			}

			if( is_array($checkIP) )
			{
				array_push( $checkIP, $ip_num ); //if it is an array i.e. already has entries the push in another value
			}
			else
			{
				$checkIP = array($ip_num); //for the first entry
			}
			$insert = serialize($checkIP);

			// see if the ID already exists
			$idexists = $DB->get_var( 'SELECT count(*) FROM '.$tableName.' WHERE id='.$DB->quote( $id_sent ) );

			if( $idexists == true )
			{
				$DB->query('UPDATE '.$tableName.'
							SET	total_votes='.$DB->quote( $added ).',
								total_value='.$DB->quote( $sum ).',
								used_ips='.$DB->quote( $insert ).',
								blog_id='.$DB->quote( $blog ).'
							WHERE id='.$DB->quote( $id_sent ));
			}
			else
			{
				$DB->query('INSERT INTO '.$tableName.'
							VALUES ('.$DB->quote( $id_sent ).',
									'.$DB->quote( $added ).',
									'.$DB->quote( $sum ).',
									'.$DB->quote( $insert ).',
									'.$DB->quote( $blog ).')');
			}

			// update the database and echo back the new stuff
			$r = $DB->get_row( 'SELECT total_votes, total_value, used_ips
								FROM '.$tableName.'
								WHERE id='.$DB->quote( $id_sent ) );

			$count = $r->total_votes; // how many votes total
			$current_rating = $r->total_value; // total number of rating added together and stored

			$new_back = '<ul class="unit-rating">'."\n".
						'<li class="current-rating" style="width:'.@number_format($current_rating/$count,2) * $this->width.'px">'.$this->T_('Current rating').'.</li>'."\n";

			for( $ncount = 1; $ncount <= $outof; $ncount++ )
			{
				$new_back .= "<li class=\"r{$ncount}-unit\">{$ncount}</li>\n";
			}

			if( $this->Settings->get('popup') )
			{
				$new_back .= '<li><a href="javascript:void(0);" onclick="disp_notice(\'&lt;p class=\\\'ratingNotes\\\'>'.sprintf( $this->T_('Rating: %s out of %d %s cast'), '&lt;strong>'.@number_format($r->total_value / $r->total_votes, 1).'&lt;/strong>', $r->total_votes, $tense ).'&lt;/p>\'); return false;" class="starinfo"><span>i</span></a></li>';
			}
			$new_back .= '</ul>';

			$tense = ($count == 1) ? $this->T_('vote') : $this->T_('votes');
			$notice = '<p class="ratingNotes">'.sprintf( $this->T_('Rating: %s out of %d %s cast'), '<strong>'.@number_format($sum/$added,1).'</strong>', $added, $tense ).'<br /><span class="success">'.$this->T_('Thank you for your vote').'!</span></p>';
		}

		// If JavaScript is off, then just send them back to the referer
		if( isset($params['returnto']) )
		{
			header('location: '.$params['returnto']);
		}
		else
		{	// Name of the div id to be updated | the html that needs to be changed
			$output = 'unit_long'.$id_sent.'|'.$new_back;
			if( $this->Settings->get('inline') ) $output .= $notice;
			if( $this->Settings->get('popup') ) $output .= '|ratingResults|'.$notice;

			header('Content-Type: text/html; charset='.$current_charset);
			echo $output;
		}
	}


	// Display the Top Rated block
	function disp_top_rated( $params )
	{
		global $DB, $blog;

		if( !isset($params['title'])) $params['title'] = $this->T_('Top Rated');	// Title
		if( !isset($params['before_rating'])) $params['before_rating'] = ' (';		// What comes before and after the comment count
		if( !isset($params['after_rating'])) $params['after_rating'] = ')';		// How many of the top posts to show
		if( !isset($params['limit'])) $params['limit'] = 5;						// Limit
		if( !isset($params['minvote'])) $params['minvote'] = 1;					// Minimum number of votes required to be in the list

		$this->init_display( $params );

		$SQL = '
			SELECT DISTINCT post_ID , total_value / total_votes AS rating, total_votes
			FROM ( T_items__item , '.$this->get_sql_table('ratings').')
			INNER JOIN T_postcats ON post_ID = postcat_post_ID
			INNER JOIN T_categories ON postcat_cat_ID = cat_ID
			WHERE cat_blog_id = '.$DB->quote( $blog ).'
			AND '.$this->get_sql_table('ratings').'.id = T_items__item.post_ID
			AND total_votes >= '.$DB->quote( $params['minvote'] ).'
			ORDER BY rating DESC
			LIMIT '.$DB->quote( $params['limit'] );

		$results = $DB->get_results($SQL);

		if( empty($results) ) return;
		if( empty($results[0]->rating) ) return;

		$ItemCacheLight = & get_ItemCacheLight();

		// START DISPLAY:
		echo $params['block_start'];
		echo $params['block_title_start'];
		echo $params['title'];
		echo $params['block_title_end'];

		echo $params['list_start'];
		for( $i = 0; $i < $params['limit']; $i++ )
		{
			// Stop the loop if you get to a post with 0 comments
			if( empty( $results[$i]->rating ) ) break;

			if( ($rated_item = & $ItemCacheLight->get_by_ID( $results[$i]->post_ID, false )) !== false )
			{
				echo $params['item_start'];
				echo '<a href="'.$rated_item->get_permanent_url().'">'.$rated_item->title.'</a>';
				echo $params['before_rating'].number_format( $results[$i]->rating, 1 ).$params['after_rating'];
				echo ' <span class="notes">'.$results[$i]->total_votes.' '.$this->T_('votes').'</span>';
				echo $params['item_end'];
			}
		}
		echo $params['list_end'];
		echo $params['block_end'];
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
		{
			$this->disp_params[$setting] = ( isset($params[$setting]) ? $params[$setting] : $this->Settings->get($setting) );
		}

		foreach( $params as $param => $value )
		{
			$this->disp_params[$param] = $value;
		}
	}
}

?>