<?php
/**
 * This file implements the Boxter plugin for {@link http://b2evolution.net/}.
 *
 * @author sam2kb - {@link http://b2evo.sonorth.com/}
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class boxter_plugin extends Plugin
{
	/**
	 * Variables below MUST be overriden by plugin implementations,
	 * either in the subclass declaration or in the subclass constructor.
	 */
	var $name = 'Boxter';
	/**
	 * Code, if this is a renderer or pingback plugin.
	 */
	var $code = 'boxter';
	var $priority = 30;
	var $version = '0.1';
	var $group = 'Sonorth Corp.';
	var $author = 'Russian b2evolution';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=';

	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;

	var $headline_added = false;

	var $english_words = array('th(e|is|at|ose|en|ere)','a','is','to','[io]n','thanks?','tahkns?','tanks?','was','were','it','i','me','how','man','post','best','seen','lot','your?','kudos','all','with','who');

	var $badwords = array(
			'беспонт',
			'д[еи]бил',
			'дур[аые]',
			'ид[еи]от',
			'хрен[оьаи]',
		);

	/**
	 * Init
	 *
	 * This gets called after a plugin has been registered/instantiated.
	 */
	function PluginInit( & $params )
	{
		global $media_path;

		$this->short_desc = $this->T_('Boxter');
		$this->long_desc = $this->T_('Boxter');
	}


	function AfterLoginAnonymousUser( $params )
	{
		return;
	}


	function AfterLoginRegisteredUser( $params )
	{
		global $instance_name, $cookie_path, $cookie_domain;

		if( empty($_COOKIE['cookie'.$instance_name.'nocache']) )
		{
			setcookie( 'cookie'.$instance_name.'nocache', '1', time()+315360000, $cookie_path, $cookie_domain );
		}
		header('X-Accel-Expires: 0');
	}


	function CommentFormSent ( & $params )
	{
		if( !empty($params['anon_url']) ) $this->msg( 'Spam comment rejected!', 'error' );
		if( empty($params['anon_email']) ) $params['anon_email'] = 'sitevisitor@boxter.org';

		if( empty($params['anon_name']) )
		{
			$params['anon_name'] = 'Почтальон Печкин';
			$params['anon_email'] = 'pechkin@boxter.org';
		}

		// Strip whitespace
		$params['comment'] = trim( $params['comment'] );

		// Remove repetitions
		$params['anon_name'] = $this->remove_repetition( $params['anon_name'] );
		$params['comment'] = $this->remove_repetition( $params['comment'] );

		header('X-Accel-Expires: 0');
		header_nocache();
	}


	function AfterCommentFormInsert( & $params )
	{
		global $Hit, $Session, $Messages, $instance_name, $cookie_path, $cookie_domain;

		$redirect_to = $params['Comment']->Item->get_permanent_url();

		$params['Comment']->send_email_notifications();

		$meta_redirect = '<meta http-equiv="refresh" content="6;url='.$redirect_to.'" />';

		if( !is_logged_in() )
		{
			// Set nocache cookie to 60 sec
			setcookie( 'cookie'.$instance_name.'nocache', '1', time() + 60, $cookie_path, $cookie_domain );

			load_class( '_core/model/_pagecache.class.php', 'PageCache' );
			$PageCache = new PageCache( $params['Comment']->Item->Blog );
			$PageCache->invalidate( $params['Comment']->Item->get_single_url() );
		}

		if( $Hit->is_opera() || $Hit->is_gecko() || is_logged_in() )
		{
			if( $params['Comment']->status == 'published' )
			{
				$Messages->add( T_('Your comment has been submitted.'), 'success' );
			}
			else
			{
				$Messages->add( T_('Your comment has been submitted. It will appear once it has been approved.'), 'success' );
			}

			// Set Messages into user's session, so they get restored on the next page (after redirect):
			$Session->set( 'Messages', $Messages );
			$Session->dbsave();

			//$meta_redirect = '';
		}

		headers_content_mightcache( 'text/html', 0 );

		echo '<header>
				<title>Ваш отзыв был принят</title>
				'.$meta_redirect.'
				<meta name="robots" content="NOINDEX NOFOLLOW" />
			  </header>
			  <div style="margin:30px">
				<h2>Ваш отзыв был принят и появится на сайте через некоторое время.</h2>
				<p>Страница автоматически обновится через секунду или две.<br />
					Если этого не произошло, то <a href="'.$redirect_to.'">нажмите сюда</a>.</p>
			  </div>';

		exit(0);
	}


	function AdminAfterMenuInit()
	{
		global $current_User, $AdminUI;

		// Do the following only for UserBlog group members
		if( $current_User->ID == 2 )
        {
			// Hide backoffice menu entries from all users
			if( !empty( $AdminUI->_menus['entries']['tools'] ) )
				unset( $AdminUI->_menus['entries']['tools'] );

			if( !empty( $AdminUI->_menus['entries']['blogs'] ) )
				unset( $AdminUI->_menus['entries']['blogs'] );

			if( !empty( $AdminUI->_menus['entries']['users'] ) )
				unset( $AdminUI->_menus['entries']['users'] );

			if( !empty( $AdminUI->_menus['entries']['dashboard'] ) )
				unset( $AdminUI->_menus['entries']['dashboard'] );
		}
	}


	function AdminBeforeItemEditCreate( & $params )
	{
		$params['Item']->set( 'content', $this->filter_content( $params['Item']->content ) );

		return $params['Item'];
	}


	function AfterItemInsert( & $params )
	{
		if( !empty($params['Item']->ID) && !preg_match( '~^v[0-9]+$~', $params['Item']->urltitle ) )
		{
			global $DB;

			$DB->query('UPDATE T_items__item SET
					    post_urltitle = "'.$DB->escape('p'.$params['Item']->ID).'"
						WHERE post_ID = '.$params['Item']->ID);

			return true;
		}
	}


	function AfterItemUpdate( & $params )
	{
		return $this->AfterItemInsert( $params );
	}


	function SkinBeginHtmlHead()
	{
		add_js_headline( $this->get_js_headline() );
		//$this->headline_added = true;
	}


	function DisplayItemAsHtml( & $params )
	{
		if( !$this->headline_added ) return;

		//if( !$url = $params['Item']->get_tinyurl() ) return false;
		if( !$url = $params['Item']->get_permanent_url() ) return false;
		if( !$title = $params['Item']->title ) return false;

		$params['data'] .= '<div class="social-bookmarks">
							<script type="text/javascript">
								/* <![CDATA[ */
								jQuery(document).ready(function() {
									write_a_link( "'.urlencode($url).'", "'.format_to_output( $title, 'htmlattr' ).'" );
								});
								/* ]]> */
							</script>
							</div>';

		return true;
	}


	function DisplayItemAsXml( & $params )
	{
		return $this->DisplayItemAsHtml( $params );
	}


	/**
	 * Event handler: Called before a comment gets inserted through the public comment
	 *                form.
	 *
	 * Use this, to validate a comment: you could {@link Plugin::msg() add a message} of
	 * category "error" here, to prevent the comment from being inserted.
	 *
	 * @see Plugin::DisplayCommentFormFieldset()
	 * @param array Associative array of parameters
	 *   - 'Comment': the Comment (by reference)
	 *   - 'original_comment': this is the unstripped and unformated posted comment
	 *   - 'action': "save" or "preview" (by reference) (since 1.10)
	 *   - 'is_preview': is this a request for previewing the comment? (boolean)
	 */
	function BeforeCommentFormInsert( & $params )
	{
		if( is_logged_in() || $params['action'] != 'save' ) return;

		$text = $params['Comment']->author."\n\n".$params['Comment']->content;

		if( ! preg_match( '~[а-я]~iu', $text, $match1 ) && preg_match( '~('.implode( ' | ', $this->english_words ).' )~iu', $text ) )
		{	// No Russian letters
			$this->msg( 'Your comment was blocked as spam!', 'error' );
		}

		if( ! preg_match( '~[а-я]~iu', $text, $match1 ) && preg_match( '~(ht|f)tps?://~i', $text ) )
		{	// Too many links
			$this->msg( 'Your comment was blocked as spam!', 'error' );
		}

		if( $bad_word = $this->filter_bad_words($text) )
		{
			$params['Comment']->status = 'draft';
			//$this->msg( 'Найдено вульгарное слово "'.$matches[1].'". Ваш комментарий отклонен!', 'error' );
			// Notify admin
			$url = $params['Comment']->Item->get_permanent_url();
			$msg = $bad_word."\n".$url."\n\n".$text;
			send_mail( 'alex@boxter.org', 'Alex', 'Bad word found', $msg, 'b2evo@boxter.org' );
		}
	}


	function remove_repetition( $str = '' )
	{
		return @preg_replace( '~(.)\\1{3,}~iu', '$1$1$1', $str );
	}


	function filter_bad_words( $string )
	{
		global $evo_charset;

		$pattern = '~('.implode( '|', $this->badwords ).')~iu';

		$ie = 'utf-8'; // default
		if( can_check_encoding() )
		{
			foreach( array('utf-8', 'windows-1251', 'koi8-r') as $test_encoding )
			{
				if( check_encoding($string, $test_encoding) )
				{
					$ie = $test_encoding;
					break;
				}
			}
		}
		$string = convert_charset($string, $evo_charset, $ie);

		if( @preg_match( $pattern, $string, $matches ) )
		{
			return $matches[1];
		}
		return false;
	}


	function filter_content( $content )
	{
		$content = $this->array_trim( preg_split( '~\n~', $content ) );

		$rows = array();
		foreach( $content as $row )
		{
			if( $row == '' )
			{
				//$rows[] = ' ';
				continue;
			}

			$rows[] = preg_replace( array(
							'~\s{2,}~',				// Remove extra whitespace
						), array(
							' ',
						), $row );
		}
		$content = implode( "\n\n", $rows );

		return $content;
	}


	function get_js_headline()
	{
		global $baseurl;

		$headline = '';

		return $headline;
	}


	/**
	 * Trim array
	 */
	function array_trim( $array )
	{
		return array_map( 'trim', $array );
	}


	/*function FilterItemContents( & $params )
	{
		global $Blog;

		// Get the main category
		$main_category = param( 'post_category', 'integer', $Blog->get_default_cat_ID() );

		$template = ''; // Default template, overwritten below

		if( empty($main_category) )
		{	// No category selected, use a common template
			$template = 'No category selected';
		}
		else
		{	// A category is selected,
			// let's get a template for specific category ID
			switch( $main_category )
			{
				case 4: // Category #4
					$template = 'You selected category #4';
					break;

				case 22:
					$template = 'Category #22, not a bad choice';
					break;

				default: // Some other category selected
					$template = 'Some other category selected';
			}
		}
		// Set our template
		$params['content'] = $template;

		return true;
	}*/
}

?>