<?php

if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


$paged = $this->paged;

if( empty($paged) )
{
	$paged = 1;
}

?>
<div class="widget_plugin_advanced_search">
  <?php	
	$total = $adv_MainList->total_rows;
	$pp = $this->Settings->get('posts_per_page');
	$r1 = $paged * $pp - $pp + 1;
	$r2 = $paged * $pp;
	
	if( $r1 > $total ) $r1 = $total;
	if( $r2 > $total ) $r2 = $total;
	
	if( ! $adv_MainList->display_if_empty() )
	{	// We found somethng
		echo '<div class="BlUeBaR">'.sprintf ( $this->T_('Results %d - %d of %d for <b>%s</b>'), $r1, $r2, $total, $this->search_terms ).'</div>';
		
		echo '<ul>';
		while( $Item = & $adv_MainList->get_item() )
		{
			echo "\n\n<li>";
			
			if( isset( $this->Items[$Item->ID] ) )
			{
				$title = $this->Items[$Item->ID]['title'];
				$content = $this->Items[$Item->ID]['content'];
			}
			else
			{
				$title = $this->filter_content( $Item->title );
				$content = $this->filter_content( $Item->content );
			}
			$url = $Item->get_permanent_url();
			
			// Title
			echo '<h3 class="TiTlE">';
			echo '<a href="'.url_add_param( $url, 'highlight='.urlencode($this->search_terms).'&amp;sentence='.$this->search_mode ).'" rel="nofollow">'.$title.'</a>';
			echo '</h3>';
			
			// Content
			echo '<div class="CoNtAiNeR">';
			echo $content.'<br />';			
			echo '<cite>'.$url.'</cite>';
			echo '</div>';
			
			echo '</li>';
		}
		echo '</ul>';
		
		
		$url_params = '';
		$params = array();
		
		if( $this->search_mode )
		{
			$params[] = 'sentence='.$this->search_mode;
			forget_param('sentence');
		}
		if( $this->in_blogs )
		{
			$params[] = 'in_blogs='.$this->in_blogs;
			forget_param('in_blogs');
		}
		if( $this->search_author )
		{
			$params[] = 'author='.$this->search_author;
			forget_param('author');
		}
		if( $this->month )
		{
			$params[] = 'm='.$this->month;
			forget_param('m');
		}
		if( $this->types )
		{
			$params[] = 'adv_types='.$this->types;
			forget_param('adv_types');
		}
		
		if( !empty($params) )
		{
			$url_params = implode( '&amp;', $params );
		}
		
		$adv_MainList->page_links( array(
				'block_start' => '<div class="BlUeBaR">'.$this->T_('Pages:').' ',
				'block_end'   => '</div>',
				'page_url' => url_add_param( $Blog->gen_blogurl(), $url_params ),
			) );
	}
	
?>
</div>