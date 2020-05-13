<?php
/**
 * BirthdayShoes plugin for b2evolution
 *
 * @author sam2kb - {@link http://b2evo.sonorth.com/}
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class birthdayshoes_plugin extends Plugin
{
	var $name = 'Birthday Shoes';
	var $code = 'bShoes';
	var $priority = 50;
	var $version = '1.0.2';
	var $group = 'rendering';
	var $author = 'Russian b2evolution';
	var $author_url = 'http://b2evo.sonorth.com';

	var $number_of_installs = 1;

	// Internal
	var $_Galleries = NULL;
	var $_files = array();
	var $_disp_items = array();
	var $_search_list;
	var $_replace_list;

	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Custom plugin');
		$this->long_desc = $this->short_desc;
	}


	function GetDefaultSettings( & $params )
	{
		return array(
			'search_list' => array(
				'label' => $this->T_('Search list'),
				'note' => $this->T_('This is the BBcode search array (one per line) ONLY CHANGE THESE IF YOU KNOW WHAT YOU\'RE DOING'),
				'type' => 'html_textarea',
				'rows' => 16,
				'defaultvalue' => '#\[b](.+?)\[/b]#is
#\[i](.+?)\[/i]#is
#\[u](.+?)\[/u]#is
#\[s](.+?)\[/s]#is
!\[color=(#?[A-Za-z0-9]+?)](.+?)\[/color]!is
#\[size=([0-9]+?)](.+?)\[/size]#is
#\[font=([A-Za-z0-9 ;\-]+?)](.+?)\[/font]#is
#\[code](.+?)\[/code]#is
#\[quote](.+?)\[/quote]#is
#\[list=1](.+?)\[/list]#is
#\[list=a](.+?)\[/list]#is
#\[list](.+?)\[/list]#is
#\[\*](.+?)\n#is
!\[bg=(#?[A-Za-z0-9]+?)](.+?)\[/bg]!is
!\[url\](.+?)\[\/url\]!i
!\[url=(.+?)\](.+?)\[\/url\]!i',
			),
			'replace_list' => array(
				'label' => $this->T_('Replace list'),
				'note' => $this->T_('This is the replace array (one per line) it must match the exact order of the search array'),
				'type' => 'html_textarea',
				'rows' => 16,
				'defaultvalue' => '<strong>$1</strong>
<em>$1</em>
<span style="text-decoration:underline">$1</span>
<span style="text-decoration:line-through">$1</span>
<span style="color:$1">$2</span>
<span style="font-size:$1px">$2</span>
<span style="font-family:$1">$2</span>
<pre>$1</pre>
&laquo;&nbsp;$1&nbsp;&raquo;
<ol type="1">$1</ol>
<ol type="a">$1</ol>
<ul>$1</ul>
<li>$1</li>
<span style="background-color:$1">$2</span>
<a href="$1" rel="nofollow">[Link]</a>
<a href="$1">$2</a>',
			),
		);
	}


	function DisplayItemAsHtml( & $params )
	{
		// [gallery] or [gallery:8] to display only 8 images
		$params['data'] = preg_replace_callback( '~\[gallery:?(\d+)?:?(asc|desc|rand)?\]~is', function($m) use($params) {
				$m1 = empty($m[1]) ? '' : $m[1];
				$m2 = empty($m[2]) ? '' : $m[2];
				return $this->get_gallery( $params['Item'], $m1, $m2 );
			}, $params['data'] );


		//if( isset($params['view_type']) && $params['view_type'] != 'teaser' )
		if( false )
		{	// b2evo 4.2
			$params['Item']->get_creator_User();
			$params['data'] .= $this->get_author_block( $params['Item']->creator_User );
		}
		elseif( ! in_array( $params['Item']->ID, $this->_disp_items ) )
		{	// B2evo 4.1
			$this->_disp_items[] = $params['Item']->ID;

			$params['Item']->get_creator_User();
			$params['Item']->get_Blog();
			if( ($author_block = $this->get_author_block( $params['Item']->creator_User )) !== false )
			{
				$params['Item']->Blog->set_setting( 'single_item_footer_text',
				$params['Item']->Blog->get_setting('single_item_footer_text').$author_block );

				//$params['Item']->Blog->set_setting( 'xml_item_footer_text',
				//		$params['Item']->Blog->get_setting('xml_item_footer_text').$author_block );
			}
		}

		return true;
	}


	function DisplayItemAsXml( & $params )
	{
		return $this->DisplayItemAsHtml( $params );
	}


	function get_widget_param_definitions( $params )
	{
		load_funcs( 'files/model/_image.funcs.php' );

		$r = array(
			'block_title' => array(
				'label' => T_('Title'),
				'type' => 'text',
			),
			'disp_year' => array(
				'label' => T_('Show year'),
				'type' => 'checkbox',
				'defaultvalue' => 1,
			),
			'limit' => array(
				'label' => T_('Limit the number of tiles'),
				'type' => 'integer',
				'defaultvalue' => 18,
			),
			'limit_year' => array(
				'label' => T_('Select year'),
				'type' => 'text',
				'defaultvalue' => '2014,2013',
			),
			'thumb_size' => array(
				'label' => T_('Thumbnail size'),
				'note' => T_('Cropping and sizing of thumbnails'),
				'type' => 'select',
				'options' => get_available_thumb_sizes(),
				'defaultvalue' => 'crop-80x60',
			),
		);
		return $r;
	}


	function SkinTag( & $params )
	{
		echo $params['block_start'];

		if( !empty($params['block_title']) )
		{
			echo $params['block_title_start'];
			echo $params['block_title'];
			echo $params['block_title_end'];
		}

		$this->get_tag_tiles( array(
				'tags'			=> 'review',
				'limit'			=> $params['limit'],
				'limit_year'	=> $params['limit_year'],
				'thumb_size'	=> $params['thumb_size'],
				'disp_year'		=> $params['disp_year'],
			) );

		echo $params['block_end'];

		return true;
	}


	function get_author_block( & $User )
	{
		global $Blog, $UserCache;

		if( $Blog->ID != 1 ) return false;

		if( is_integer($User) )
		{	// Get user by ID
			if( ($User = $UserCache->get_by_ID( $User, false, false )) === false ) return;
		}

		if( empty($User) || !is_object($User) ) return;

		$User->userfields_load();

		$avatar = $User->get_avatar_imgtag('crop-100x100');
		$num_posts = $User->get_num_posts();
		$num_comm = $User->get_num_comments();
		$name = $User->get_preferred_name();
		$about_me = '';

		$author_posts = url_add_param( $Blog->get('url'), 'author='.$User->ID );
	//	$author_comments = url_add_param( $Blog->get('url'), 'author='.$User->ID );

		foreach( $User->userfields as $arr )
		{
			switch( $arr->ufdf_ID )
			{
				case 20000:
					$plus_id = $arr->uf_varchar;
					break;

				case 20001:
					$about_me = $arr->uf_varchar;
					break;
			}
		}

		if( !empty($avatar) )
		{
			$avatar = '<a href="'.$User->get_userpage_url().'">'.$avatar.'</a>';
		}
		if( !empty($plus_id) )
		{
			$name = sprintf(' <a href="https://plus.google.com/%s" rel="author" target="_blank">%s+</a>', $plus_id, $name );
		}
		if( !empty($about_me) )
		{
			if( !isset($this->_search_list) )
			{
				$this->_search_list = explode( "\n", str_replace( "\r", '', $this->Settings->get('search_list') ) );
			}
			if( !isset($this->_replace_list) )
			{
				$this->_replace_list = explode( "\n", str_replace( "\r", '', $this->Settings->get('replace_list') ) );
			}

			$about_me = preg_replace( $this->_search_list, $this->_replace_list, $about_me );

			$about_me = '<b>About the Author</b> &mdash; '.$about_me;
		}

		$r = $avatar.$about_me.'<br />';
		$r .= sprintf( '%s has written <a href="'.$author_posts.'">%d articles</a> and %d comments.', $name, $num_posts, $num_comm );

		return '<a name="author"></a><div class="author-block">'.$r.'<div class="clear"></div></div>';
	}


	function DisplayMessageFormFieldset( & $params )
	{
		$text = '<p><strong>Note:</strong> I do my best to respond to every inquiry I receive and I am always eager to hear any site feedback&mdash;positive or negative.</p>
		<p><b>If you have general questions about Vibram Five Fingers (the toe shoes you see on this site!), before emailing me, please take a second to see these resources:</b></p>

		<ul><li><a href="http://birthdayshoes.com/the-beginner-s-guide-to-five-fingers"><b>Beginner\'s Guide to Vibrams</b></a> &mdash; this covers all the basics for newbies.  It\'s a quick, read, too.</li>
		<li><a href="http://birthdayshoes.com/wiki/index.php?title=Fivefingers_sizing"><b>VFF Sizing Guide</b></a> &mdash; FiveFingers sizing doesn\'t correspond to any standard shoe size metric. Go here to figure out your size!</li>
		<li><a href="http://birthdayshoes.com/forum/"><b>The Vibram fan forums</b></a> &mdash; there are many, many VFF fans in the forums who are extremely friendly and capable of crowdsource-addressing <strong>nuanced</strong> questions about models, sizing, etc.  Membership is, of course, <em>free!</em></li>
		<li><a href="http://birthdayshoes.com/store/"><b>Where can I buy Vibram Five Fingers?</b></a> &mdash; if you want to buy VFFs online, start at the <a href="http://birthdayshoes.com/store/"><b>store!</b></a> Alternatively, look for a local retailer using Vibram\'s store locater (<a href="http://www.vibramfivefingers.com/productsupport/store_locator.cfm">here</a>).</li></ul>

		<p>I point these resources out because as much as I like spreading the word about Vibram Five Fingers, I\'m just one guy. It is amazingly time-consuming to answer common questions and keep BirthdayShoes.com a great place to visit on the web, so I appreciate your understanding!</p>
		<p>Finally, I\'ll say it again, <b>site feedback is VERY, VERY much appreciated! If you have ideas on how to make BirthdayShoes.com better or want to see more of any given topic, let me know!</b></p>';

		echo '<fieldset>'.$text.'</fieldset>';
	}


	function get_tag_tiles( $params = array() )
	{
		global $Blog;

		$limit_years = sanitize_id_list($params['limit_year'], true);

		load_class('items/model/_itemlistlight.class.php', 'ItemListLight');

		$ItemList = new ItemListLight( $Blog, NULL, NULL, 0 );
		$ItemList->set_filters( array(
				'tags'	=> $params['tags'],
				'posts'	=> 99999,
			), false );

		// Run the query:
		$ItemList->query();

		if( $ItemList->result_num_rows > 0 )
		{
			$count = 0;

			while( $Item = & $ItemList->get_item() )
			{
				if( $count >= $params['limit'] ) break;

				$year = date('Y', mysql2timestamp($Item->issue_date) );

				if( ! in_array($year, $limit_years) ) continue;

				$title = htmlspecialchars_decode($Item->title);
				$title = htmlspecialchars_decode($title);

				// Get list of attached files
				$LinkOnwer = new LinkItem( $Item );
				$FileList = $LinkOnwer->get_attachment_FileList( 100, 'teaser' );

				while( $File = & $FileList->get_next() )
				{
					if( ! $File->exists() ) continue;
					if( ! $File->is_image() ) continue;

					$img_tag = $File->get_tag( '', NULL, '', '', $params['thumb_size'], $Item->get_permanent_url(), $title, '', '', '', $title );

					$img_tag = preg_replace( '~title="[^"]+"~', 'title="'.htmlspecialchars($title).'"', $img_tag );

					$images[$year][] = $img_tag;
					$count++;
					break;
				}
			}
		}

		if( !empty($images) )
		{
			krsort($images);

			$r = '<div class="image-tiles">';

			$year = 0;
			foreach( $images as $y => $img )
			{
				if( $y != $year && $params['disp_year'] )
				{
					$r .= '<h2 style="margin: 10px 0">&laquo; '.$y.' shoe reviews &raquo;</h2>';
				}
				$y = $year;

				$r .= implode( "\n", $img );
			}
			$r .= '</div>';

			echo $r;
		}
	}


	function get_gallery( & $Item, $limit = '', $order = '' )
	{
		$r = '';

		if( $limit == '' ) $limit = 1000;

		// Get list of attached files
		$LinkOnwer = new LinkItem( $Item );
		$FileList = $LinkOnwer->get_attachment_FileList( 1000 );

		// Get list of attached files
		if( ! $FileList )
		{
			return $r;
		}

		while( $File = & $FileList->get_next() )
		{
			if( ! $File->exists() ) continue;

			if( $File->is_dir() )
			{	// This is a directory/gallery

				// Have we displayed this gallery already?
				if( isset($this->_files['dir_'.$File->ID]) ) continue;

				// Save displayed gallery ID to skip it next time
				$this->_files['dir_'.$File->ID] = '';

				$params['gallery_order'] = str_replace( '-', '', trim($order) );
				$r = $this->build_gallery( $File, $limit, $params );
				break;
			}
		}

		return $r;
	}


	function build_gallery( & $File, $limit, $params = array() )
	{
		$params = array_merge( array(
				'before_gallery'      => '<div class="bGallery">',
				'after_gallery'       => '</div>',
				'gallery_image_size'  => 'crop-80x80',
				'gallery_image_limit' => $limit,
				'gallery_colls'       => 5,
				'gallery_order'       => '', // ASC, DESC, RAND, empty string
			), $params );

		if( ! $File->is_dir() )
		{	// Not a directory
			return '';
		}
		if( ! $FileList = $File->get_gallery_images( $params['gallery_image_limit'], $params['gallery_order'] ) )
		{	// No images in this directory
			return '';
		}

		$count = 0;
		$r = '<table class="image_index">';
		foreach( $FileList as $l_File )
		{
			$img_tag = $l_File->get_tag( '', NULL, '', '', $params['gallery_image_size'], 'original' );

			if( $count % $params['gallery_colls'] == 0 ) $r .= "\n<tr>";
			$count++;
			$r .= "\n\t".'<td valign="top">';
			// ======================================
			$r .= '<div>'.$img_tag.'</div>';
			// ======================================
			$r .= '</td>';
			if( $count % $params['gallery_colls'] == 0 ) $r .= "\n</tr>";
		}
		if( $count && ( $count % $params['gallery_colls'] != 0 ) ) $r .= "\n</tr>";
		$r .= '</table>';

		$r = $params['before_gallery'].$r.$params['after_gallery'];

		return $r;
	}
}

?>
