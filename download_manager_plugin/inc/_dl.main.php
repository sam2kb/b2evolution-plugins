<?php
/**
 * This file implements the Download Manager plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2009-2012 by Sonorth Corp. - {@link http://www.sonorth.com/}.
 * @author Sonorth Corp.
 *
 * All rights reserved.
 *
 * THIS COMPUTER PROGRAM IS PROTECTED BY COPYRIGHT LAW AND INTERNATIONAL TREATIES.
 * UNAUTHORIZED REPRODUCTION OR DISTRIBUTION OF DOWNLOAD MANAGER PLUGIN,
 * OR ANY PORTION OF IT THAT IS OWNED BY SONORTH CORP., MAY RESULT IN SEVERE CIVIL
 * AND CRIMINAL PENALTIES, AND WILL BE PROSECUTED TO THE MAXIMUM EXTENT POSSIBLE UNDER THE LAW.
 * 
 * THE DOWNLOAD MANAGER PLUGIN FOR B2EVOLUTION CONTAINED HEREIN IS PROVIDED "AS IS."
 * SONORTH CORP. MAKES NO WARRANTIES OF ANY KIND WHATSOEVER WITH RESPECT TO THE
 * DOWNLOAD MANAGER PLUGIN FOR B2EVOLUTION. ALL EXPRESS OR IMPLIED CONDITIONS,
 * REPRESENTATIONS AND WARRANTIES, INCLUDING ANY WARRANTY OF NON-INFRINGEMENT OR
 * IMPLIED WARRANTY OF MERCHANTABILITY OR FITNESS FOR A PARTICULAR PURPOSE,
 * ARE HEREBY DISCLAIMED AND EXCLUDED TO THE EXTENT ALLOWED BY APPLICABLE LAW.
 * 
 * IN NO EVENT WILL SONORTH CORP. BE LIABLE FOR ANY LOST REVENUE, PROFIT OR DATA,
 * OR FOR DIRECT, SPECIAL, INDIRECT, CONSEQUENTIAL, INCIDENTAL, OR PUNITIVE DAMAGES
 * HOWEVER CAUSED AND REGARDLESS OF THE THEORY OF LIABILITY ARISING OUT OF THE USE OF
 * OR INABILITY TO USE THE DOWNLOAD MANAGER PLUGIN FOR B2EVOLUTION, EVEN IF SONORTH CORP.
 * HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

global $evo_charset;

if( empty($File) )
{	// Contact the admin
	$this->send_header(3);
}

$pass = NULL;
if( $pass = param( 'file_pass', 'html' ) )
{
	$pass = '&file_pass='.urlencode($pass);
}

$direct_url = url_add_param( $this->get_dl_url( $file_key, 2 ), 'skip=true'.$pass, '&' );

// Download page. Do not edit the <head> section!
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo $evo_charset; ?>" />
<meta http-equiv="Expires" content="Sun, 15 Jan 1998 17:38:00 GMT" />
<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
<title><?php echo sprintf( $this->T_('Download %s'), $File->file_name ); ?></title>
<script type="text/javascript">
function dlfile(sec)
{
	if(sec) refresh = setTimeout("document.location='<?php echo $direct_url; ?>';", sec*1000);
}
</script>
</head>
<body onLoad="dlfile(2)">
<!-- Edit below this line -->
<h3 style="margin:15px 0 10px 0; padding:0"><?php echo sprintf( $this->T_('Download %s'), '<span style="color:blue">'.$File->file_name.'</span>' ); ?></h3>
<?php
if( !empty($File->file_description) )
{	// File description
	echo '<div style="width:600px; margin-bottom:30px">'.$File->file_description.'</div>';
}
?>
<div style="margin:10px 0"><?php echo $this->T_('Thank you. Your download will start within seconds.'); ?><br />
  <?php echo sprintf( $this->T_('Please use this <a %s>direct link</a> to start downloading if this didn\'t start automatically.'), 'href="'.$direct_url.'"' ); ?></div>
<hr />
<div style="font-size:12px"><a href="<?php echo $this->help_url; ?>"><?php echo $this->name ?> plugin</a></div>
</body>
</html>