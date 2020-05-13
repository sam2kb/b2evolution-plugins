<?php
/**
 * This is the main/default page template for the "custom" skin.
 *
 * This skin only uses one single template which includes most of its features.
 * It will also rely on default includes for specific dispays (like the comment form).
 *
 * For a quick explanation of b2evo 2.0 skins, please start here:
 * {@link http://manual.b2evolution.net/Skins_2.0}
 *
 * The main page template is used to display the blog when no specific page template is available
 * to handle the request (based on $disp).
 *
 * @package evoskins
 * @subpackage custom
 *
 * @version $Id: index.main.php,v 1.11.2.2 2008/04/26 22:28:53 fplanque Exp $
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

if( version_compare( $app_version, '2.4.1' ) < 0 )
{
	die( 'This skin is designed for b2evolution 2.4.1 and above. Please <a href="http://b2evolution.net/downloads/index.html">upgrade your b2evolution</a>.' );
}

skin_init( $disp );
skin_include( '_html_header.inc.php' );

if( is_default_page() )
{
	$disp = 'home';
}

?>

<div id="wrapper">
  <div class="pageHeader">
    <h1>
      <?php echo '<a href="'.$Blog->gen_blogurl().'">'.$Blog->name.'</a>'; ?>
    </h1>
    <div class="widget_core_coll_tagline">
      <?php $Blog->disp( 'tagline', 'htmlbody' ) ?>
    </div>
  </div>
  <div class="bPosts">
    <?php
	// -- MESSAGES GENERATED FROM ACTIONS --
	messages( array(
			'block_start' => '<div class="action_messages">',
			'block_end'   => '</div>',
		) );
		
	// -- TITLE FOR THE CURRENT REQUEST --
	request_title( array(
			'title_before'=> '<h2>',
			'title_after' => '</h2>',
			'title_single_disp' => false,
		) );
	
	// -- MAIN CONTENT TEMPLATE INCLUDED HERE (Based on $disp) --
	skin_include( '$disp$', array(
			'disp_posts'			=> '_disp/_posts.disp.php',
			'disp_single'			=> '_disp/_posts.disp.php',
			'disp_page'				=> '_disp/_posts.disp.php',
			'disp_home'				=> '_disp/_home.disp.php',			// Blog home page
			'disp_public_blogs'		=> '_disp/_public_blogs.disp.php',	// Public blogs page
			'disp_comments'			=> '_disp/_comments.disp.php',		// Comments page
		) );
	
	?>
  </div>
  <div class="after_posts">
    <?php
	// Common links
	echo '<a accesskey="1" href="'.$Blog->get('url').'">'.$MobilePlugin->T_('Home').'</a><br />';
	echo '<a accesskey="2" href="'.$Blog->gen_blogurl().'?disp=public_blogs">'.$MobilePlugin->T_('Public blogs').'</a><br />';
	echo '<a accesskey="3" href="'.$Blog->get('arcdirurl').'">'.$MobilePlugin->T_('Archives').'</a><br />';
	echo '<a accesskey="4" href="'.$Blog->get('catdirurl').'">'.$MobilePlugin->T_('Categories').'</a><br />';
	echo '<a href="'.$Blog->get('lastcommentsurl').'">'.$MobilePlugin->T_('Latest comments').'</a><br />';	
	
	echo '<br />';
	
	// Administrative links:
	user_login_link( '', '<br />' );
	user_register_link( '', '<br />' );
	user_admin_link( '', '<br />' );
	user_profile_link( '', '<br />' );
	user_subs_link( '', '<br />' );
	user_logout_link( '', '<br />' );
	?>
    <form action="<?php echo $Blog->gen_blogurl() ?>" method="get">
      <div>
        <input name="s" type="text" value="<?php echo $s ?>" class="SearchField" />
        <input value="<?php echo $MobilePlugin->T_('Search') ?>" type="submit" />
      </div>
    </form>
  </div>
  <div id="pageFooter">
    <?php
    // Display footer text (text can be edited in Blog Settings):
    $Blog->footer_text( array(
            'before'      => '',
            'after'       => ' | ',
        ) );
	// Provide a link to the normal skin
	echo '<a href="'.regenerate_url( 'tempskin,disp,mobi', 'mobi=off' ).'">'.$this->T_('Desktop site').'</a>';
    
	// Contact link
    $Blog->contact_link( array(
            'before' => ' | ',
            'after'  => ' | ',
            'text'   => $MobilePlugin->T_('Contact'),
            'title'  => $MobilePlugin->T_('Send a message to the owner of this blog...'),
        ) );
    // Powered by
    echo '<a href="http://b2evolution.net">Powered by b2evolution</a>';
    ?>
  </div>
</div>
<?php
// -- HTML FOOTER INCLUDED HERE --
skin_include( '_html_footer.inc.php' );

?>