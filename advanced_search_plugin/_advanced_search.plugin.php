<?php
/**
 * This file implements the Advanced Search plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2008-2012 by Sonorth Corp. - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Sonorth Corp.
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class advanced_search_plugin extends Plugin
{
	var $name = 'Advanced Search';
	var $code = 'advanced_search';
	var $priority = 100;
	var $version = '1.7.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=15593';

	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;

	var $debug = false; // Set this to true to see debug info

	// Internal
	var $Items = array();
	var $search_terms = false;
	var $search_mode = false;
	var $search_author = false;
	var $paged = false;
	var $am_protect_enabled = false;
	var $save_aggregate_coll_IDs = '';
	var $save_m = '';
	var $save_types = false;
	var $save_posts = false;
	var $in_blogs = false;
	var $month = false;


	/**
	 * Init
	 *
	 * This gets called after a plugin has been registered/instantiated.
	 */
	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Filter search results by many parameters');
		$this->long_desc = $this->T_('It allows you to filter search results by many parameters plus you can customize almost everything.');
	}


	/**
	 * Define settings that the plugin uses/provides.
	 */
	function GetDefaultSettings()
	{
		return array(
				'filter_results' => array(
					'label' => $this->T_('Precise search'),
					'defaultvalue' => true,
					'type' => 'checkbox',
					'note' => $this->T_('If enabled, the plugin will search through <u>rendered</u> content, and <u>outside</u> of HTML tags only. This means that a lot of false positive results will be filtered out.').'<br /><span class="red">'.$this->T_('Warning: this also increases search time!').'</span>',
				),
				'posts_per_page' => array(
					'label' => $this->T_('Results per page'),
					'defaultvalue' => 10,
					'type' => 'integer',
					'size' => 4,
					'note' => $this->T_('Number of results per page.'),
				),
				'hi_class' => array(
					'label' => $this->T_('Keywords class'),
					'defaultvalue' => 'highlighted_words',
					'type' => 'text',
					'size' => 20,
					'note' => $this->T_('Class for highlighted keywords. If you change this you must also edit adv_search.css'),
				),
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
				'search_title' => array(
					'label' => $this->T_('Search form Title'),
					'defaultvalue' => $this->T_('Search'),
					'type' => 'text',
					'note' => $this->T_('Search form title displayed in skin.'),
				),
				'in_category' => array(
					'label' => $this->T_('Categories label'),
					'defaultvalue' => $this->T_('In category'),
					'note' => $this->T_('Label for categories select menu.'),
				),
				'by_month' => array(
					'label' => $this->T_('Month/year label'),
					'defaultvalue' => $this->T_('Posted on'),
					'note' => $this->T_('Label for month/year select menu.'),
				),
				'by_author' => array(
					'label' => $this->T_('Authors label'),
					'defaultvalue' => $this->T_('Author'),
					'note' => $this->T_('Label for authors select menu.'),
				),
				'by_type' => array(
					'label' => $this->T_('Post types label'),
					'defaultvalue' => $this->T_('Post type'),
					'note' => $this->T_('Label for post types select menu.'),
				),
				'search_button' => array(
					'label' => $this->T_('Submit button'),
					'defaultvalue' => $this->T_('Search'),
					'note' => $this->T_('Submit button name.'),
				),
				'in_blogs' => array(
					'label' => $this->T_('Search in Blogs'),
					'defaultvalue' => '',
					'type' => 'text',
					'note' => $this->T_('List blog IDs separated by ,').'<br />'.
								$this->T_('[ All ] - to search in all public blogs.').'<br />'.
								$this->T_('Leave empty to search in current blog only.'),
				),
				'author_exclude' => array(
					'label' => $this->T_('Exclude authors'),
					'defaultvalue' => '',
					'type' => 'text',
					'note' => $this->T_('List user IDs separated by ,'),
				),
				'category_exclude' => array(
					'label' => $this->T_('Exclude categories'),
					'defaultvalue' => '',
					'type' => 'text',
					'note' => $this->T_('List category IDs separated by ,').'<br />'.
								$this->T_('Note: if you exclude a parent category, all subcategories will be also excluded.'),
				),
				'search_cat' => array(
					'label' => $this->T_('Search in category'),
					'defaultvalue' => true,
					'type' => 'checkbox',
					'note' => $this->T_('Display categories select menu in search form.'),
				),
				'search_by_month' => array(
					'label' => $this->T_('Search by month/year'),
					'defaultvalue' => true,
					'type' => 'checkbox',
					'note' => $this->T_('Display month/year select menu in search form.'),
				),
				'search_author' => array(
					'label' => $this->T_('Search by author'),
					'defaultvalue' => true,
					'type' => 'checkbox',
					'note' => $this->T_('Display authors select menu in search form.'),
				),
				'search_types' => array(
					'label' => $this->T_('Search by post type'),
					'defaultvalue' => true,
					'type' => 'checkbox',
					'note' => $this->T_('Display post types select menu in search form.'),
				),
				'search_all' => array(
					'label' => $this->T_('Search all words'),
					'defaultvalue' => true,
					'type' => 'checkbox',
					'note' => $this->T_('Display <b>All Words</b> radio button.'),
				),
				'search_some' => array(
					'label' => $this->T_('Search some word'),
					'defaultvalue' => true,
					'type' => 'checkbox',
					'note' => $this->T_('Display <b>Some Word</b> radio button.'),
				),
				'search_phrase' => array(
					'label' => $this->T_('Search entire phrase'),
					'defaultvalue' => true,
					'type' => 'checkbox',
					'note' => $this->T_('Display <b>Entire phrase</b> radio button.'),
				),
			);
	}


	function SessionLoaded()
	{
		global $Session, $highlight, $app_version, $debug;

		if( $this->debug )
		{
			$debug = 1;
		}

		// Set/Get highlighted keywords
		// This is a workaround for b2evo 3.x and later versions, where we can't pass GET requests
		if( version_compare( $app_version, '3' ) > 0 )
		{
			if( $highlight = param( 'highlight', 'string' ) )
			{
				$Session->set( 'AdvSearch_highlight', $highlight, 10 );
				$Session->dbsave();
			}
			elseif( $highlight = $Session->get('AdvSearch_highlight') )
			{
				// Already set, do nothing for now
			}
		}
	}


	/**
	 * Event handler: Called before a blog gets displayed (in _blog_main.inc.php).
	 */
	function BeforeBlogDisplay( & $params )
	{
		global $Blog, $DB, $posts, $types;

		// Let's check if we want to exclude some authors
		if( $author = param( 'author', 'integer', '' ) )
		{
			// Get params of the latest AdvSearch widget installed in current blog
			// This is needed if somebody installed 2 or more widgets
			$wi_params = $DB->get_var( 'SELECT wi_params
										FROM T_widget
										WHERE wi_coll_ID = '.$Blog->ID.'
										AND wi_code = "'.$this->code.'"
										ORDER BY wi_ID DESC' );

			if( !empty($wi_params) )
			{
				$wi_params = unserialize($wi_params);
				$author_exclude = sanitize_id_list( $wi_params['author_exclude'], true );

				if( in_array( $author, $author_exclude ) )
				{	// Die if searching by excluded author
					debug_die( 'No permission to search by user ['.$author.']' );
				}
			}
			$this->search_author = $author;
		}

		if ( $s = param( 's', 'string' ) )
		{
			$Blog->set_setting( 'paged_nofollowto', 1 );
			$Blog->set_setting( 'posts_per_page', $this->Settings->get('posts_per_page') );

			$this->search_terms = $s;
			$this->search_mode = param( 'sentence', 'string' );
			$this->paged = param( 'paged', 'integer', '', true, true );
			$this->save_posts = $posts;

			if( $this->Settings->get('filter_results') )
			{
				$posts = 9999999;
			}
		}

		if( $in_blogs = param( 'in_blogs', 'string' ) )
		{
			if( preg_match( '~^all$~i', $in_blogs ) )
			{	// Get all public blogs
				$BlogCache = & get_BlogCache();
				$blog_array = $BlogCache->load_public();
				$this->in_blogs = implode( ',', $blog_array );
			}
			else
			{	// Sanitize the list
				$this->in_blogs = sanitize_id_list( $in_blogs );
			}

			if( $this->in_blogs )
			{
				// Save original aggregate_coll_IDs
				$this->save_aggregate_coll_IDs = $Blog->get_setting( 'aggregate_coll_IDs' );
				$Blog->set_setting( 'aggregate_coll_IDs', $this->in_blogs );

				// Memorize for prev/next links
				memorize_param( 'in_blogs', 'string', '', $this->in_blogs );
			}
		}

		if( $advy = param( 'advy', 'integer' ) )
		{
			// Save original month
			$this->save_m = get_param('m');

			$m = $advy;
			if( $advm = param( 'advm', 'string' ) )
			{	// The above code won't work with 'integer'
				if( is_numeric($advm) && strlen($advm) == 2 )
				{
					$m .= $advm;
				}
			}
			$this->month = $m;

			// Memorize for prev/next links
			memorize_param( 'm', 'integer', '', $m );
		}

		if( $this->types = param( 'adv_types', 'string' ) )
		{	// We want to restrict to selected post types
			$this->save_types = $types;
			if( $this->types == 'all' )
			{	// Search through all types
				$types = '';
			}
			else
			{
				$types = $this->types;
			}

			// Memorize for prev/next links
			memorize_param( 'adv_types', 'string', '', $this->types );
		}
	}


	function SkinBeginHtmlHead()
	{
		global $Blog, $BlogCache, $MainList, $current_User, $plugins_url;

		require_css( $plugins_url.'advanced_search_plugin/adv_search.css', true );	// Add our css

		if( ! $this->search_terms ) return; // Nothing to search

		// Save the object for use in skin
		set_param( 'ASearch_plugin', $this );
		// Save custom disp
		set_param( 'disp_detail', 'adv_search' );

		if( $this->Settings->get('filter_results') )
		{
			$this->am_protect_enabled = $this->is_plugin_enabled( 'am_protect' );

			$matched_posts = array();
			$rows_before = $MainList->result_num_rows;

			$search = '~'.$this->get_search_pattern().'~iu';

			while( $tItem = & $MainList->get_Item() )
			{
				if( $this->am_protect_enabled )
				{	// Exclude posts from protected blogs
					$temp_Blog = & $BlogCache->get_by_ID( $tItem->blog_ID, false, false );
					if( !empty( $temp_Blog ) && preg_match( '~am:protect~i', $temp_Blog->get('notes') ) )
					{ // This blog is protected (private)
						if( !is_logged_in() || !$current_User->check_perm( 'blog_ismember', 'any', false, $temp_Blog->ID ) )
						{	// user/vistor is not a blog member, skip this post
							continue;
						}
					}
				}

				$title = $this->remove_spaces( $tItem->title );
				$content = $this->remove_spaces( $tItem->get_prerendered_content('htmlbody') );

				// TODO: count matches and check according to 'AND' & 'OR' modes
				/*preg_match_all( $search, $content, $m_content );
				preg_match_all( $search, $title, $m_title );
				$count_c = count($m_content[0]);
				$count_t = count($m_title[0]);*/

				if( preg_match( $search, $title ) || preg_match( $search, $content ) )
				{
					// Save this in order to use ItemListLight later
					$this->Items[$tItem->ID] = array(
							'title'		=> $this->filter_content( $title, false ),
							'content'	=> $this->filter_content( $content, false ),
						);

					//$matches = $count_c;
					// Posts with matches in title go higher
					//$matches = $matches + ($count_t * 5);

					// Get IDs of matched posts.
					//$matched_posts[$tItem->ID] = $matches;
					$matched_posts[$tItem->ID] = 0;
				}
			}

			$rows_after = count($matched_posts);

			// Forget ugly 'posts' param since we don't need it any more
			forget_param('posts');

			if( $rows_after > 0 )
			{
				if( $rows_after != $rows_before && $this->debug )
				{
					$this->msg( sprintf('Plugin filtered %s good results out of %s found by b2evo search ;)', $rows_after, $rows_before ), 'note' );
				}

				// Sort IDs by number of matches (hi > low)
				//arsort($matched_posts);

				//echo '<br /><br /><br />'.implode( ', ', array_keys($matched_posts) ).'<br />';

				$adv_MainList = new ItemList2( $Blog, NULL, NULL, $this->Settings->get('posts_per_page') );
				$adv_MainList->set_filters( array(
						'types'			=>	NULL,
						'page'			=>	$this->paged,
						'post_ID_list'	=>	implode( ',', array_keys($matched_posts) ),
					//	'order'			=> 'DESC',
					//	'orderby'		=> 'status',
					), false ); // do not memorize

				$adv_MainList->query(); // Run the query
				$MainList = $adv_MainList;
				unset($adv_MainList);
			}
			else
			{	// We filtered out all results or nothing was found
				$MainList->result_num_rows = 0;
			}
		}

		if( $this->in_blogs )
		{	// Restore Blog settings
			$Blog->set_setting( 'aggregate_coll_IDs', $this->save_aggregate_coll_IDs );
		}

		// Restore month
		set_param( 'm', $this->save_m );

		if( strlen($this->save_types) )
		{	// Restore types, if param is not empty
			set_param( 'types', $this->save_types );
		}
		else
		{	// If empty, we don't need it
			forget_param('types');
		}
	}


	function display_results()
	{
		global $MainList, $adv_MainList, $Blog, $plugins_path, $disp_detail;

		if( ! $this->search_terms ) return; // Nothing to search

		if( !empty($disp_detail) && $disp_detail == 'adv_search' )
		{
			$adv_MainList = $MainList;

			if( isset($GLOBALS['MainList']) )
			{
				unset($GLOBALS['MainList']);
			}
			if( !is_object($adv_MainList) )
			{
				echo '<p class="msg_nothing">'.$this->T_('Sorry, there is nothing to display...').'</p>';
				return;
			}

			include $plugins_path.'advanced_search_plugin/_adv_search.disp.php';
		}
	}


	function DisplayItemAsHtml( & $params )
	{
		global $plugins_path, $disp_detail;

		$this->search_mode = param( 'sentence', 'string' );

		if( $highlight = param( 'highlight', 'string' ) )
		{	// Single post mode
			$s = $highlight;
		}
		elseif( !empty($disp_detail) && $disp_detail == 'adv_search' )
		{	// Post list mode, full posts (b2evo default)
			$s = $this->search_terms;
		}

		if( !empty($s) )
		{
			$params['data'] = $this->do_highlight( $params['data'], $s );
			return true;
		}
	}


	/**
	 * Event handler: SkinTag (widget)
	 *
	 */
	function SkinTag( $params )
	{
		global $Blog, $UserCache;

		// This is what will enclose the block in the skin:
		if(!isset($params['block_start'])) $params['block_start'] = '<div class="bSideItem widget_plugin_advanced_search">';
		if(!isset($params['block_end'])) $params['block_end'] = "</div>\n";
		if(!isset($params['block_title_start'])) $params['block_title_start'] = '<h3>';
		if(!isset($params['block_title_end'])) $params['block_title_end'] = '</h3>';

		// Labels:
		if(!isset($params['search_title'])) $params['search_title'] = $this->T_('Search');
		if(!isset($params['search_button'])) $params['search_button'] = $this->T_('Search');
		if(!isset($params['in_category'])) $params['in_category'] = $this->T_('In category');
		if(!isset($params['by_month'])) $params['by_month'] = $this->T_('Posted on');
		if(!isset($params['by_author'])) $params['by_author'] = $this->T_('Author');
		if(!isset($params['by_type'])) $params['by_type'] = $this->T_('Post type');

		// Params:
		if(!isset($params['in_blogs'])) $params['in_blogs'] = NULL;
		if(!isset($params['category_exclude'])) $params['category_exclude'] = NULL;
		if(!isset($params['author_exclude'])) $params['author_exclude'] = NULL;

		// Display:
		if(!isset($params['search_cat'])) $params['search_cat'] = true;
		if(!isset($params['search_by_month'])) $params['search_by_month'] = true;
		if(!isset($params['search_author'])) $params['search_author'] = true;
		if(!isset($params['search_types'])) $params['search_types'] = true;
		if(!isset($params['search_all'])) $params['search_all'] = true;
		if(!isset($params['search_some'])) $params['search_some'] = true;
		if(!isset($params['search_phrase'])) $params['search_phrase'] = true;


		$category_exclude = array();
		if( ! empty($params['category_exclude']) )
		{	// Exclude categories
			$category_exclude = sanitize_id_list( $params['category_exclude'], true );
		}

		$author_exclude = array();
		if( ! empty($params['author_exclude']) )
		{	// Exclude authors
			$author_exclude = sanitize_id_list( $params['author_exclude'], true );
		}

		// ===========================
		// Start output

		echo $params['block_start'];

		$Form = new Form( $Blog->gen_blogurl(), 'SearchForm', 'get' );

		echo $params['block_title_start'];
		echo $params['search_title'];
		echo $params['block_title_end'];

		$Form->begin_form( 'search' );

		echo '<input id="adv_s" type="text" name="s" size="25" value="'.htmlspecialchars( get_param('s') ).'" class="SearchField" />';

		if( $params['in_blogs'] )
		{	// Search in Blogs
			$Form->hidden( 'in_blogs', sanitize_id_list($params['in_blogs']) );
		}

		if( $params['search_cat'] || $params['search_author'] || $params['search_by_month'] )
		{
			echo '<div class="options_select">';

			/*
			 * Month/Year:
			 */
			if( $params['search_by_month'] )
			{
				global $month, $month_abbrev;

				$advmonth = $month; // Change to $month_abbrev to use months short form (typically 3 letters)

				if( isset($advmonth['00']) )
				{
					unset($advmonth['00']);
				}

				$years = array();
				for( $i=0; $i<5; $i++ )
				{
					$years[$i] = date('Y')-$i;
				}

				$cur_m = $cur_y = 0;
				if( $cur_month = $this->month )
				{
					$cur_m = @substr( $cur_month, 4, 2 );
					$cur_y = @substr( $cur_month, 0, 4 );
				}

				echo '<fieldset>';
				echo '<div class="label"><label for="month">'.$params['by_month'].':</label></div>';
				echo '<div class="input">';

				// Month
				echo '<select id="adv_advm" name="advm"><option value="">'.$this->T_('All').'</option>';
				foreach( $advmonth as $mkey=>$mval )
				{
					$selected = '';
					if( $mkey == $cur_m )
					{
						$selected = ' selected="selected"';
					}
					echo '<option value="'.$mkey.'"'.$selected.'>'.$this->T_($mval).'</option>';
				}
				echo '</select> ';

				// Year
				echo '<select id="adv_advy" name="advy"><option value="">'.$this->T_('All').'</option>';
				foreach( $years as $yval )
				{
					$selected = '';
					if( $yval == $cur_y )
					{
						$selected = ' selected="selected"';
					}
					echo '<option value="'.$yval.'"'.$selected.'>'.$yval.'</option>';
				}
				echo '</select>';

				echo '</div></fieldset>';
			}

			/*
			 * Categories:
			 */
			if( $params['search_cat'] )
			{
				global $cat;

				$ChapterCache = & get_ChapterCache();
				$cat_opt = $ChapterCache->recurse_select( NULL, $Blog->ID, true, NULL, 1, $category_exclude );
				$cat_opt = preg_replace( '~(<option value=""[^>]*>)'.$this->T_('Root').'(</option>)~i', '$1'.$this->T_('All').'$2', $cat_opt );
				$cat_opt = preg_replace( '~value="'.$cat.'"~i', 'value="'.$cat.'" selected="selected"', $cat_opt );

				$Form->select_input_options( 'cat', $cat_opt, $params['in_category'] );
			}

			/*
			 * Authors:
			 */
			if( $params['search_author'] )
			{
				global $author;

				$author_displayed = false;

				$r_auth = '<fieldset>';
				$r_auth .= '<div class="label"><label for="author">'.$params['by_author'].':</label></div>';
				// Load current blog members into cache:
				$UserCache->load_blogmembers( $Blog->ID );
				if( count($UserCache->cache) )
				{
					$r_auth .= '<div class="input"><select id="adv_author" name="author"><option value="">'.$this->T_('All').'</option>';
					foreach( $UserCache->cache as $loop_Obj )
					{
						if( !empty($author_exclude) )
						{	// Exclude authors
							if( in_array( $loop_Obj->ID, $author_exclude ) ) continue;
						}
						$author_displayed = true;

						$selected = '';
						if( $loop_Obj->ID == $author )
						{
							$selected = ' selected="selected"';
						}
						$r_auth .= '<option value="'.$loop_Obj->ID.'"'.$selected.'>'.$loop_Obj->get('preferredname').'</option>';
					}
					$r_auth .= '</select></div>';
				}
				$r_auth .= '</fieldset>';

				if( $author_displayed )
				{	// Display fieldset only if at least one author available
					echo $r_auth;
				}
			}

			echo '</div>';
		}


		/*
		 * Post Type:
		 */
		if( $params['search_types'] )
		{
			$ItemTypeCache = & get_ItemTypeCache();
			$Form->output = false;
			$types_fieldset = $Form->select_object( 'adv_types', $this->types, $ItemTypeCache, $params['by_type'] );
			$Form->output = true;
			$types_fieldset = preg_replace( '~(<option value="1")~i', '<option value="all">'.$this->T_('All').'</option>$1', $types_fieldset );

			// Delete "Reserved" type options
			echo preg_replace( '~<option[^>]+>Reserved</option>~i', '', $types_fieldset );
		}


		/*
		 * Radio buttons:
		 */
		if( $params['search_all'] || $params['search_some'] || $params['search_phrase'] )
		{
			echo '<div class="radio_select">';
			$sentence = $this->search_mode;

			if( $params['search_all'] )
			{
				if( ! $params['search_some'] && ! $params['search_phrase'] )
				{
					$Form->hidden( 'sentence', 'AND' );
				}
				else
				{
					echo '<input type="radio" name="sentence" value="AND" id="sentAND" '.
							( $sentence=='AND' ? 'checked="checked" ' : '' ).'/><label for="sentAND">'.
							$this->T_('All Words').'</label><br />';
				}
			}

			if( $params['search_some'] )
			{
				if( ! $params['search_all'] && ! $params['search_phrase'] )
				{
					$Form->hidden( 'sentence', 'OR' );
				}
				else
				{
					echo '<input type="radio" name="sentence" value="OR" id="sentOR" '.
							( $sentence=='OR' ? 'checked="checked" ' : '' ).'/><label for="sentOR">'.
							$this->T_('Some Word').'</label><br />';
				}
			}

			if( $params['search_phrase'] )
			{
				if( ! $params['search_some'] && ! $params['search_all'] )
				{
					$Form->hidden( 'sentence', 'sentence' );
				}
				else
				{
					echo '<input type="radio" name="sentence" value="sentence" id="sentence" '.
							( $sentence=='sentence' ? 'checked="checked" ' : '' ).'/><label for="sentence">'.
							$this->T_('Entire phrase').'</label>';
				}
			}

			echo '</div>'."\n";
			echo '<div class="searchSelectSubmit">'."\n"; // allow css overrides
		}
		elseif( $params['search_cat'] || $params['search_author'] )
		{
			echo '<div class="searchCatSubmit">'; // allow css overrides
		}
		else
		{
			echo '<div class="searchNormalSubmit">'; // allow css overrides
		}

		$Form->submit( array( '', $params['search_button'], 'search_submit' ) );
		echo '</div>'."\n";

		$Form->end_form();

		echo $params['block_end'];

		return true;
	}


	/**
	 * Cut text by words
	 *
	 * @param string: text to process
	 * @param integer: the number of characters to cut from the start (if the value is positive)
	 * or from the end (if negative)
	 * @param string: additional string, added before the cropped text
	 * @param string: additional string, added after the cropped text
	 * @param string: words delimeter
	 *
	 * @return processed string
	 */
	function cut_text( $string, $cut = 250, $before_text = '...', $after_text = '...', $delimeter = ' ' )
	{
		$string = trim( preg_replace( '/[\s]+/i', ' ', format_to_output( $string, 'text' ) ) );
		$length = abs($cut);

		if( function_exists('mb_strlen') && function_exists('mb_substr') )
		{
			if( $length < mb_strlen($string) && $cut > 0 )
			{
				while ( $string{$length} != $delimeter && $length > 0 )
				{
					$length--;
				}
				return mb_substr($string, 0, $length).$after_text;
			}
			elseif( $length < mb_strlen($string) && $cut < 0 )
			{
				$string = strrev($string);

				while ( $string{$length} != $delimeter && $length > 0 )
				{
					$length--;
				}
				return strrev( mb_substr($string, 0, $length).$before_text );
			}
			else
			{
				return $string;
			}
		}
		else
		{
			if( $length < strlen($string) && $cut > 0 )
			{
				while ( $string{$length} != $delimeter && $length > 0 )
				{
					$length--;
				}
				return substr($string, 0, $length);
			}
			elseif( $length < strlen($string) && $cut < 0 )
			{
				$string = strrev($string);

				while ( $string{$length} != $delimeter && $length > 0 )
				{
					$length--;
				}
				return strrev( substr($string, 0, $length) );
			}
			else
			{
				return $string;
			}
		}
	}


	// Filter content by keywords
	function filter_content( $content, $remove_spaces = true )
	{
		$search = '~(.*?)('.$this->get_search_pattern().')(.*?)$~iu';

		if( $remove_spaces )
		{
			$content = $this->remove_spaces( $content );
		}

		if( @preg_match( $search, $content, $matches ) )
		{	//var_export($matches);
			if( strlen($matches[3]) < 50 )
			{
				$before = $this->cut_text( $matches[1], -120 );
			}
			else
			{
				$before = $this->cut_text( $matches[1], -70 );
			}
			$after = $this->cut_text( $matches[3], 200 );

			if( !empty($matches[3]) && $matches[3]{0} == ' ' )
			{
				$matches[2] .= ' ';
			}

			if( $k = strrev($matches[1]) )
			{
				if( !empty($k) && $k[0] == ' ' )
				{
					$before .= ' ';
				}
			}
			return $this->do_highlight( $this->cut_text( $before.$matches[2].$after ) );
		}
		return $this->do_highlight( $this->cut_text( $content ) );
	}


	// Highlight keywords
	function do_highlight( $content, $s = NULL )
	{
		if( is_null($s) )
		{
			$s = $this->search_terms;
		}

		return callback_on_non_matching_blocks( $content, '~<[^>]+?>~', array( & $this, 'do_highlight_callback' ), array($s) );
	}


	function do_highlight_callback( $content, $s )
	{
		$search = '~('.$this->get_search_pattern( $s ).')~iu';
		$replace = '<span class="'.$this->Settings->get('hi_class').'">$1</span>';

		return preg_replace( $search, $replace, $content );
	}


	// Remove tags and whitespace
	function remove_spaces( $content )
	{
		return trim( preg_replace( '~[\s]+~', ' ', format_to_output( $content, 'xml' ) ) );
	}


	// Get search pattern according to search mode ( OR, AND, sentence )
	function get_search_pattern( $s = NULL )
	{
		if( is_null($s) )
		{
			$s = $this->search_terms;
		}

		switch( true )
		{
			case preg_match( '~^or$~i', $this->search_mode ):
				$search = @implode( '|', @explode( ' ', $s ) );
				break;

			case preg_match( '~^and$~i', $this->search_mode ):
				$search = @implode( '|', @explode( ' ', $s ) );
				break;

			case preg_match( '~^sentence$~i', $this->search_mode ):
			default:
				$search = $s;
		}
		return quotemeta($search);
	}


	function is_plugin_enabled( $code )
	{
		global $Plugins;

		if( ($Plugin = & $Plugins->get_by_code($code)) !== false )
		{	// Get the requested plugin by code
			if( $Plugin->status == 'enabled' )
			{	// Plugin is installed & enabled
				return true;
			}
		}
		return false;
	}


	function refine_url( $blog = 'all', $blogname = '#' )
	{
		if( $blogname == '#' ) $blogname = $this->T_('All');

		return '<a href="'.regenerate_url( 'blog,in_blogs', 'in_blogs='.$blog ).'">'.format_to_output($blogname, 'text').'</a>';
	}
}


?>