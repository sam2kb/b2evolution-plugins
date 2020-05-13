<?php
/**
 * Home page template
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

$MobilePlugin = & $Plugins->get_by_code('mobile');
?>

<h2>Public blog list</h2>

<?php
// Display a list of all public blogs
load_class('items/model/_itemlist.class.php');
$BlogCache = & get_Cache( 'BlogCache' );
$blog_array = $BlogCache->load_public();

foreach( $blog_array as $l_blog )
{	// Loop through all public blogs:
	# by uncommenting the following lines you can hide some blogs
	//if( $l_blog == 2 ) continue; // Hide blog 2...

	$l_Blog = & $BlogCache->get_by_ID( $l_blog );

	echo '<h3><a href="'.$l_Blog->gen_blogurl().'">'.$l_Blog->dget( 'name', 'htmlattr' ).'</a></h3>';
	echo '<div>'.$l_Blog->dget( 'tagline', 'htmlattr' ).'</div><br />';
}
?>