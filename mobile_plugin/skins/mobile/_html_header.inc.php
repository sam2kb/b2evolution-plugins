<?php
/**
 * This is the HTML header include template.
 *
 * For a quick explanation of b2evo 2.0 skins, please start here:
 * {@link http://manual.b2evolution.net/Skins_2.0}
 *
 * This is meant to be included in a page template.
 * Note: This is also included in the popup: do not include site navigation!
 *
 * @package evoskins
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

global $current_charset, $rsc_path, $rsc_url;

?>
<?php echo ('<?xml version="1.0" encoding="utf-8"?>') ?>
<!--<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML Basic 1.0//EN" "http://www.w3.org/TR/xhtml-basic/xhtml-basic10.dtd">-->
<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.0//EN" "http://www.wapforum.org/DTD/xhtml-mobile10.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<?php skin_content_meta(); /* Charset for static pages */ ?>
<?php skin_base_tag(); /* Base URL for this skin. You need this to fix relative links! */ ?>
<?php $Plugins->trigger_event( 'SkinBeginHtmlHead' ); ?>
<title><?php
	// ------------------------- TITLE FOR THE CURRENT REQUEST -------------------------
	request_title( array(
		'auto_pilot'      => 'seo_title',
	) );
	// ------------------------------ END OF REQUEST TITLE -----------------------------
?></title>
<meta name="generator" content="b2evolution Mobile plugin" />
<meta name="viewport" content="width=device-width" />
<?php
echo '<link rel="stylesheet" href="'.$rsc_url.'css/basic.css" type="text/css" />';
if( file_exists($rsc_path.'css/item_base.css') )
{	// b2evo 3
	echo '<link rel="stylesheet" href="'.$rsc_url.'css/blog_base.css" type="text/css" />';
	echo '<link rel="stylesheet" href="'.$rsc_url.'css/item_base.css" type="text/css" />';
}
else
{	// b2evo 2
	echo '<link rel="stylesheet" href="'.$rsc_url.'css/forms.css" type="text/css" />';
}
// include_headlines();
?>
<link rel="stylesheet" href="style.css" type="text/css" />
</head>
<body>
<div id="skin_wrapper" class="skin_wrapper_anonymous">