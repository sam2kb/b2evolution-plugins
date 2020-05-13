<?php
/**
 * This file implements the Smart links plugin for {@link http://b2evolution.net/}.
 *
 * @author sam2kb - {@link http://b2evo.sonorth.com/}
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class smart_links_plugin extends Plugin
{
	var $name = 'Smart links';
	var $code = 'sn_smartlinks';
	var $priority = 30;
	var $version = '0.1';
	var $group = 'Sonorth Corp.';
	var $author = 'Russian b2evolution';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=';

	var $min_app_version = '4';
	var $max_app_version = '5';

	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Create smart links to articles in your blogs.');
		$this->long_desc = $this->T_('Create smart links to articles in your blogs.');
	}


	function DisplayItemAsHtml( & $params )
	{
		// [smartlink:keyword one, keyword two]link text[/smartlink]
		$params['data'] = preg_replace( '#\[smartlink:([^\]]+)\](.*?)\[/smartlink\]#ise',
				'$this->construct_link( "\\1", "\\2", $params[\'Item\'] )', $params['data'] );

		return true;
	}


	function DisplayItemAsXml( & $params )
	{
		return $this->DisplayItemAsHtml( $params );
	}


	function construct_link( $keywords, $text, $Item )
	{
		global $Blog;

		if( empty($keywords) ) return;

		$keywords = trim($keywords);
		$keywords_array = @explode( ',', $keywords );

		if( count($keywords_array) > 1 )
		{
			$keywords = array_map( 'trim', $keywords_array );
		}

		$params = array(
				'keywords'	=> $keywords,
				'user_ID'	=> $Item->creator_user_ID,
				'post_ID'	=> $Item->ID,
			);

		if( $link = $this->search( $params ) )
		{
			if( is_array($link) )
			{	// Only one matched post
				$r = '<a href="'.$link['url'].'" rel="nofollow" title="'.format_to_output( $link['title'], 'htmlattr' ).'">'.$text.'</a>';
			}
			else
			{	// 2 or more matched posts
				if( is_array($keywords) )
				{
					$keywords = implode( ' ', $keywords );
				}

				// &amp;author='.$params['user_ID']
				$url_params = 's='.urlencode($keywords).'&amp;sentence=OR';

				$r = '<a href="'.url_add_param( $Blog->gen_blogurl(), $url_params ).'" title="'.$this->T_('Read more...').'">'.$text.'</a>';
			}
			return $r;
		}
		else
		{
			return $text;
		}
	}


	function search( $params )
	{
		global $DB, $Blog;

		$keywords = (is_array($params['keywords'])) ? implode( ' ', $params['keywords'] ) : $params['keywords'];

		$temp_ItemList = new ItemListLight( $Blog, NULL, 'now', 2 );
		$temp_ItemList->set_filters( array(
				//'authors'	=> $params['user_ID'],
				'keywords'	=> $keywords,
				'phrase'	=> 'OR',
			), false );

		// Run the query:
		$temp_ItemList->query();

		switch( $temp_ItemList->result_num_rows )
		{
			case 1:
				$Item = & $temp_ItemList->get_item();
				return array(
						'title'	=> $Item->title,
						'url'	=> $Item->get_permanent_url(),
					);
				break;

			case 2:
				return true;
				break;

			default:
				return false;
		}
	}
}

?>