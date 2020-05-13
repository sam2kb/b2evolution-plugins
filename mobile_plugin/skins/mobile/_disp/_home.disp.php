<?php
/**
 * Home page template
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

$MobilePlugin = & $Plugins->get_by_code('mobile');
?>

<h2>Latest posts</h2>

<?php
if( $Blog->get('longdesc') )
{
	echo '<p>'.$Blog->dget('longdesc').'</p>';
}

// Display latest posts from each category
$MobilePlugin->disp_cat_item_list( array(
		'title' => $MobilePlugin->T_('Latest posts'), // Block title
		'limit'	=> 3, // Max items to display
		'cols'	=> 1, // Number of columns/categories displayed in one row
	) );
?>