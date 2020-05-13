<?php
/**
 * This is the template that displays the links to the latest comments for a blog
 *
 * This file is not meant to be called directly.
 * It is meant to be called by an include in the main.page.php template.
 * To display a feedback, you should call a stub AND pass the right parameters
 * For example: /blogs/index.php?disp=comments
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2008 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @package evoskins
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


$CommentList = new CommentList( $Blog, "'comment','trackback','pingback'", array('published'), '',	'',	'DESC',	'',	20 );

$CommentList->display_if_empty();

while( $Comment = & $CommentList->get_next() )
{ // Loop through comments:
	// Load comment's Item object:
	$Comment->get_Item();
?>

<div class="bComment">
  <h3 class="bTitle"><?php
  echo T_('In response to:').' ';

  $Comment->Item->title( array(
			'link_type' => 'permalink',
		) );
  ?></h3>
  <div class="bCommentTitle">
    <?php
	  $Comment->permanent_link( array(
			  'before'    => '',
			  'after'     => ' ',
			  'nofollow'	=> true,
			  'text' 		=> ' #',
		  ) );
	  $Comment->date();
	  $Comment->msgform_link( $Blog->get('msgformurl'), ' ', ' ', '@' );
	  $Comment->author( '<b>', '</b> ['.T_('Visitor').']', '<b>', '</b>' );
	  $Comment->rating( array(
			  'before' => ' | ',
			  'after'  => ' ',
		  ));
	?>
  </div>
  <div class="bCommentText"><?php $Comment->content(); ?></div>
</div>
<?php
}	// End of comment loop.


?>