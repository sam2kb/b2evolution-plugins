<?php
/**
 * This is the template that displays the contents for a post
 * (images, teaser, more link, body, etc...)
 *
 * This file is not meant to be called directly.
 * It is meant to be called by an include in the main.page.php template (or other templates)
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2008 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @package evoskins
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

$MobilePlugin = & $Plugins->get_by_code('mobile');

// Default params:
$params = array_merge( array(
		'before_images'       => '<div class="bImages">',
		'before_image'        => '<div class="image_block">',
		'before_image_legend' => '<div class="image_legend">',
		'after_image_legend'  => '</div>',
		'after_image'         => '</div>',
		'after_images'        => '</div>',
		'image_size'	      => 'fit-400x320',
		'before_url_link'     => '<p class="post_link">'.T_('Link:').' ',
		'after_url_link'      => '</p>',
		'url_link_text_template' => '$url$',
		'before_more_link'    => '<p class="bMore">',
		'after_more_link'     => '</p>',
		'more_link_text'      => '#',
	), $params );

if( empty($Item) && !empty($params['Item'] ) )
{
	$Item = & $params['Item'];
}

if( !empty($params['image_size']) )
{
	// Display images that are linked to this post:
	$Item->images( array(
			'before' =>              $params['before_images'],
			'before_image' =>        $params['before_image'],
			'before_image_legend' => $params['before_image_legend'],
			'after_image_legend' =>  $params['after_image_legend'],
			'after_image' =>         $params['after_image_legend'],
			'after' =>               $params['after_images'],
			'image_size' =>			 $params['image_size'],
		) );
}
?>

<div class="bText">
	<?php
		// Increment view count of first post on page:
		$Item->count_view( array(
				'allow_multiple_counts_per_page' => false,
			) );

		// URL link, if the post has one:
		$Item->url_link( array(
				'before'        => $params['before_url_link'],
				'after'         => $params['after_url_link'],
				'text_template' => $params['url_link_text_template'],
				'url_template'  => '$url$',
				'target'        => '',
				'podcast'       => '',        // auto display mp3 player if post type is podcast (=> false, to disable)
			) );

		/*$content = $Item->get_content_teaser();
		$content .= $Item->get_more_link( array(
							'before'      => $params['before_more_link'],
							'after'       => $params['after_more_link'],
							'link_text'   => $params['more_link_text'],
						) );
		$content .= $Item->get_content_extension();
		
		if( $disp == 'posts' )
		{
			$read_more = '<br /><br /><a href="'.$Item->get_permanent_url().'">'.$MobilePlugin->T_('Read more').' &raquo;</a>';
			$content = $MobilePlugin->cut_text( $content, 400, '', $read_more );
		}
		// Filter content
		echo $MobilePlugin->filter_content( $content );
		*/
		
		// Display CONTENT:
		$Item->content_teaser( array(
				'before'      => '',
				'after'       => '',
			) );
		$Item->more_link( array(
				'before'    => $params['before_more_link'],
				'after'     => $params['after_more_link'],
				'link_text' => $params['more_link_text'],
			) );
		$Item->content_extension( array(
				'before'      => '',
				'after'       => '',
			) );
		
		// Links to post pages (for multipage posts):
		$Item->page_links();

		// Display Item footer text (text can be edited in Blog Settings):
		$Item->footer( array(
				'mode'        => '#',				// Will detect 'single' from $disp automatically
				'block_start' => '<div class="item_footer">',
				'block_end'   => '</div>',
			) );
	?>
</div>

<?php

?>