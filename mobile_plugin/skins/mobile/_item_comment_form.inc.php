<?php
/**
 * This is the template that displays the comment form for a post
 *
 * This file is not meant to be called directly.
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2008 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @package evoskins
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

global $cookie_name, $cookie_email, $cookie_url;
global $comment_allowed_tags, $comments_use_autobr;
global $comment_cookies, $comment_allow_msgform;

// Default params:
$params = array_merge( array(
		'disp_comment_form'	   => true,
		'form_title_start'     => '<h3>',
		'form_title_end'       => '</h3>',
		'policy_text'          => '',
		'textarea_lines'       => 10,
		'default_text'         => '',
		'preview_start'        => '<div class="bComment" id="comment_preview">',
		'comment_template'     => '_item_comment.inc.php',	// The template used for displaying individual comments (including preview)
		'preview_end'          => '</div>',
	), $params );

/*
 * Comment form:
 */
if( $params['disp_comment_form'] && $Item->can_comment() )
{ // We want to display the comments form and the item can be commented on:

	// INIT/PREVIEW:
	if( $Comment = $Session->get('core.preview_Comment') )
	{	// We have a comment to preview
		if( $Comment->item_ID == $Item->ID )
		{ // display PREVIEW:

			// ------------------ PREVIEW COMMENT INCLUDED HERE ------------------
			skin_include( $params['comment_template'], array(
				  'Comment'              => & $Comment,
				  'comment_start'        => $params['preview_start'],
				  'comment_end'          => $params['preview_end'],
				) );
			// Note: You can customize the default item feedback by copying the generic
			// /skins/_item_comment.inc.php file into the current skin folder.
			// ---------------------- END OF PREVIEW COMMENT ---------------------

			// Form fields:
			$comment_content = $Comment->original_content;
			// for visitors:
			$comment_author = $Comment->author;
			$comment_author_email = $Comment->author_email;
			$comment_author_url = $Comment->author_url;
		}

		// delete any preview comment from session data:
		$Session->delete( 'core.preview_Comment' );
	}
	else
	{ // New comment:
		$Comment = new Comment();
		$comment_author = isset($_COOKIE[$cookie_name]) ? trim($_COOKIE[$cookie_name]) : '';
		$comment_author_email = isset($_COOKIE[$cookie_email]) ? trim($_COOKIE[$cookie_email]) : '';
		$comment_author_url = isset($_COOKIE[$cookie_url]) ? trim($_COOKIE[$cookie_url]) : '';
		if( empty($comment_author_url) )
		{	// Even if we have a blank cookie, let's reset this to remind the bozos what it's for
			$comment_author_url = 'http://';
		}
		$comment_content =  $params['default_text'];
	}

	echo $params['form_title_start'];
	echo T_('Leave a comment');
	echo $params['form_title_end'];

	$Form = new Form( $htsrv_url.'comment_post.php', 'bComment_form_id_'.$Item->ID, 'post' );
	$Form->begin_form( 'bComment', '', array( 'target' => '_self' ) );

	// TODO: dh> a plugin hook would be useful here to add something to the top of the Form.
	//           Actually, the best would be, if the $Form object could be changed by a plugin
	//           before display!

	$Form->hidden( 'comment_post_ID', $Item->ID );
	$Form->hidden( 'redirect_to',
			url_rel_to_same_host(regenerate_url( '', '', $Blog->get('blogurl'), '&' ), $htsrv_url) );
	$Form->hidden( 'comment_autobr', ( ($comments_use_autobr == 'opt-out') ? '1' : '0' ) );
	$Form->hidden( 'comment_cookies', $comment_cookies );
	$Form->hidden( 'comment_allow_msgform', $comment_allow_msgform );

	if( is_logged_in() )
	{ // User is logged in:
		$Form->info_field( T_('User'), '<strong>'.$current_User->get_preferred_name().'</strong>'
			.' '.get_user_profile_link( ' [', ']', T_('Edit profile') ) );
	}
	else
	{ // User is not logged in:
		// Note: we use funky field names to defeat the most basic guestbook spam bots
		$Form->text( 'u', $comment_author, 40, T_('Name'), '', 100, 'bComment' );
		$Form->text( 'i', $comment_author_email, 40, T_('Email'), '<br />'.T_('Your email address will <strong>not</strong> be revealed on this site.'), 100, 'bComment' );
		$Form->text( 'o', $comment_author_url, 40, T_('Website'), '<br />'.T_('Your URL will be displayed.'), 100, 'bComment' );
	}

	if( $Item->can_rate() )
	{	// Comment rating:
		echo $Form->begin_field( NULL, T_('Your vote'), true );
		$Comment->rating_input();
		echo $Form->end_field();
	}

	if( !empty($params['policy_text']) )
	{	// We have a policy text to display
		$Form->info_field( '', $params['policy_text'] );
	}

	//echo '<div class="comment_toolbars">';
	// CALL PLUGINS NOW:
	//$Plugins->trigger_event( 'DisplayCommentToolbar', array() );
	//echo '</div>';

	// Message field:
	$note = '';
	// $note = T_('Allowed XHTML tags').': '.htmlspecialchars(str_replace( '><',', ', $comment_allowed_tags));
	$Form->textarea( 'p', $comment_content, $params['textarea_lines'], T_('Comment text'), $note, 40, 'bComment' );

	// set b2evoCanvas for plugins
	echo '<script type="text/javascript">var b2evoCanvas = document.getElementById( "p" );</script>';

	$Plugins->trigger_event( 'DisplayCommentFormFieldset', array( 'Form' => & $Form, 'Item' => & $Item ) );

	$Form->begin_fieldset();
		echo '<div class="input">';

		$Form->button_input( array( 'name' => 'submit_comment_post_'.$Item->ID.'[save]', 'class' => 'submit', 'value' => T_('Send comment'), 'tabindex' => 10 ) );

		$Plugins->trigger_event( 'DisplayCommentFormButton', array( 'Form' => & $Form, 'Item' => & $Item ) );

		echo '</div>';
	$Form->end_fieldset();
	?>
	<div class="clear"></div>
	<?php
	$Form->end_form();
}


?>