<?php
/**
 * This is the template that displays a single comment
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

// Default params:
$params = array_merge( array(
    'comment_start'  => '<div class="bComment">',
    'comment_end'    => '</div>',
    'Comment'        => NULL, // This object MUST be passed as a param!
	), $params );

/**
 * @var Comment
 */
$Comment = & $params['Comment'];

echo $params['comment_start'];
?>
	<div class="bCommentTitle">
	<?php
		switch( $Comment->get( 'type' ) )
		{
			case 'comment': // Display a comment:
				if( empty($Comment->ID) )
				{	// PREVIEW comment
					echo T_('PREVIEW Comment from:').' ';
				}
				else
				{	// Normal comment
					$Comment->permanent_link( array(
							'before'    => '',
							'after'     => ' ',
							'nofollow'	=> true,
							'text' 		=> ' #',
						) );
				}
				$Comment->date();
				$Comment->msgform_link( $Blog->get('msgformurl'), ' ', ' ', '@' );
				$Comment->author( '<b>', '</b> ['.T_('Visitor').']', '<b>', '</b>' );
				$Comment->rating( array(
						'before' => ' | ',
						'after'  => '',
					));
				echo '<br />';				
				$Comment->edit_link( '', ' | ', T_('Edit') );
				$Comment->delete_link( '', ' | ', T_('Delete') );
				$Comment->author_url( '', '', '' );
				break;
		}
	?>
	</div>
	<div class="bCommentText"><?php $Comment->content(); ?></div>
<?php
echo $params['comment_end'];
?>