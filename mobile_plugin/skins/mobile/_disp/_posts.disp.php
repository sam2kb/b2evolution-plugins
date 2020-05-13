<?php
/**
 * Home page template
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

$MobilePlugin = & $Plugins->get_by_code('mobile');

// Display message if no post:
display_if_empty();

while( $Item = & mainlist_get_item() )
{
?>
<div id="<?php $Item->anchor_id() ?>" class="bPost bPost<?php $Item->status_raw() ?>">
  <?php $Item->locale_temp_switch(); ?>
  <h3 class="bTitle"><?php $Item->title(array('format'=>'htmlhead')); ?></h3>
  <div class="bSmallHead">
	<?php
	// Permalink:
	$Item->permanent_link( array(
			'before' => '',
			'after'  => ' ',
			'text'   => ' #',
		) );
	$Item->issue_date( array(
			'before'    => '',
			'after'     => ' | ',
		));
	$Item->msgform_link( array(
			'text'      => '@',
			'before'    => ' ',
			'after'     => ' ',
		) );
	$Item->author( array(
			'before'    => '<b>',
			'after'     => '</b><br />',
		) );
	
	$Item->categories( array(
			'before'          => $MobilePlugin->T_('Categories').': ',
			'after'           => '',
			'include_main'    => true,
			'include_other'   => ($disp == 'single'),
			'include_external'=> ($disp == 'single'),
			'link_categories' => true,
		) );
	
	?>
  </div>
  <?php
	// -- POST CONTENT INCLUDED HERE --
	skin_include( '_item_content.inc.php', array(
			'Item'			=>	& $Item,
			'image_size'	=>	'fit-80x80',
		) );
	
	if( $disp == 'single' )
	{
		echo '<div class="bSmallPrint">';
		
		// List all tags attached to this post:
		$Item->tags();
		$Item->edit_link( array(
				'before'	=> ' | ',
				'after'		=> '',
				'text'		=> $MobilePlugin->T_('Edit'),
			) );
		
		if( $MobilePlugin->Settings->get('disp_comment_form') )
		{	// Link to comments, trackbacks, etc.:
			$Item->feedback_link( array(
					'type' => 'comments',
					'link_before' => ' | ',
					'link_after' => '',
					'link_text_zero' => '#',
					'link_text_one' => '#',
					'link_text_more' => '#',
					'link_title' => '#',
					'use_popup' => false,
				) );
		}
		
		echo '</div>';
	}

	// -- PREV/NEXT POST LINKS (SINGLE POST MODE) --
	item_prevnext_links( array(
			'block_start' => '<div class="prev_next">',
			'prev_start'  => '',
			'prev_end'    => '',
			'next_start'  => '&nbsp;&nbsp;',
			'next_end'    => '',
			'block_end'   => '</div>',
			'prev_text' => '&laquo; '.$MobilePlugin->T_('Previous'),
			'next_text' => $MobilePlugin->T_('Next').' &raquo;',
		) );
	
	// -- FEEDBACK (COMMENTS/TRACKBACKS) INCLUDED HERE --
	skin_include( '_item_feedback.inc.php', array(
			'Item'				   => & $Item,
			'before_section_title' => '<br /><h4>',
			'after_section_title'  => '</h4>',
			'form_title_start'     => '<h4 align="right">',
			'form_title_end'       => '</h4>',
			'disp_trackbacks'	   => false,
			'disp_trackback_url'   => false,
			'disp_pingbacks'	   => false,
			'disp_comment_form'	   => $MobilePlugin->Settings->get('disp_comment_form'),
			'limit_comments'	   => $MobilePlugin->Settings->get('limit_comments'),
		) );
	
	locale_restore_previous();
	?>
</div>
<?php
}

// -- PREV/NEXT PAGE LINKS (POST LIST MODE) --
mainlist_page_links( array(
		'block_start' => '<div class="pages">',
		'block_end' => '</div>',
		'prev_text' => '&lsaquo;&lsaquo;',
		'next_text' => '&rsaquo;&rsaquo;',
		'list_span' => 6,
	) );

?>