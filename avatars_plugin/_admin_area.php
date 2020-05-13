<?php
/**
 * B2evolution Avatars Plugin
 *
 * The Avatars Plugin allows you to attach avatars (display pictures, gravatars, profile pics, etc) to a user, blog, post and category.
 * You upload avatars within the b2evo adminstration, with either a large or small (or both) avatar images.
 * If a large avatar image is specified then lightbox functionality is seen, as when the user clicks the small avatar, the large avatar will show in a lightbox.
 * It makes use of the GD2 Image and the Gallery Plugin’s Libraries allowing for images to be resized and compressed to specified values. 
 *
 * @name B2evolution Avatars Plugin: _avatars.plugin.php
 * @version 2.3.0
 * @package plugins
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }
 * @author sam2kb: Russian b2evolution - {@link http://b2evo.sonorth.com/}
 * @author balupton: Benjamin Lupton - {@link http://www.balupton.com}
 *
 * Built on code originally released by balupton: Benjamin LUPTON - {@link http://www.balupton.com/}
 * @copyright (c) 2006-2007 Benjamin "balupton" Lupton {@link http://www.balupton.com}
 * @copyright (c) 2008-2012 by Russian b2evolution - {@link http://b2evo.sonorth.com/}
 *
 * @license GNU General Public License 2 (GPL) - {@link http://www.opensource.org/licenses/gpl-license.php}
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

global $baseurl;

$icon = 'collapse';
if( $this->Settings->get('area_collapse') )
{
	$icon = 'expand';
}
?>

<div id="<?php echo $this->code; ?>_area">
  <div class="fieldset_title">
    <div class="fieldset_title_right">
     <div class="fieldset_title_bg" onclick="toggle_clickopen('avatars')"><?php echo $this->T_('Avatars').' '.get_icon( $icon, 'imgtag', array('id'=>'clickimg_avatars') ); ?></div>
    </div>
  </div>
  <fieldset>
    <div style="padding-top:10px; clear:both" id="clickdiv_avatars">
    
    <div class="label">
        <input type="hidden" name="avatar_runonce" value="true" />
        <input type="hidden" name="avatar_name" value="<?php echo $avatar_name ?>" />
        <input type="hidden" name="avatar_type" value="<?php echo $avatar_type ?>" />
        <?php
        $avatar = $this->get_avatar($avatar_type, $avatar_name, 'default');
        if( $avatar_type == 'post' )
        {
            echo $this->get_avatar_display($avatar, 'float:left; display:block; margin:0 25px 5px 5px');
        }
        else
        {
            echo $this->get_avatar_display($avatar, 'float:right; display:block; margin:0 5px 5px 5px');
        }
        ?>
    </div>
        
    <div>
        <div style="float:left; padding-left:10px; margin:0 25px 0 0">
            <?php
            $large_width = $this->Settings->get( 'avatar_'.$avatar_type.'_large_width' );
            $large_height = $this->Settings->get( 'avatar_'.$avatar_type.'_large_height' );
            echo $this->T_('Large Avatar');
            if ( !($large_width === $large_height && $large_height === 0) )
                echo '&nbsp;<span class="notes">('.$this->T_('Up to').' '.$large_width.'x'.$large_height.').</span>';
            ?><br /><input name="avatar_large_file" id="avatar_large_file" type="file" />
           
            <label for="avatar_large_delete" style="display:block; margin:3px 0 0 0; padding:0">
                <input type="checkbox" name="avatar_large_delete" id="avatar_large_delete" /> <?php echo $this->T_('Remove Large Avatar') ?> 
            </label>
        </div>
        
        <div style="float:left; margin:0; padding:0">
            <?php
            $small_width = $this->Settings->get( 'avatar_'.$avatar_type.'_small_width' );
            $small_height = $this->Settings->get( 'avatar_'.$avatar_type.'_small_height' );
            echo $this->T_('Small Avatar');
            if ( !($small_width === $small_height && $small_height === 0) )
                echo '&nbsp;<span class="notes">('.$this->T_('Up to').' '.$small_width.'x'.$small_height.').</span>';
            ?><br /><input name="avatar_small_file" id="avatar_small_file" type="file"  />
            
            <label for="avatar_small_delete" style="display:block; margin:3px 0 0 0; padding:0">
            <input type="checkbox" name="avatar_small_delete" id="avatar_small_delete" /> <?php echo $this->T_('Remove Small Avatar') ?> 
            </label>
        </div>
    </div>
    
    <div style="padding-bottom:5px; clear:both"></div>
    
   </div>
  </fieldset>
</div>

<script type="text/javascript">
//<![CDATA[
<?php if( $this->Settings->get('area_collapse') ) { echo 'toggle_clickopen(\'avatars\');'; } ?>

jQuery(document).ready(function()
{	// Let's do our stuff
	jQuery('form.fform').attr('enctype', 'multipart/form-data').attr('encoding', 'multipart/form-data'); // encoding is for IE, IE is sure wierd
	
	<?php	
	if( $admin_area === 'post' )
	{
	?>
		jQuery('form#item_checkchanges').attr('enctype', 'multipart/form-data').attr('encoding', 'multipart/form-data');
	<?php
	}
	else
	{ ?>
		jQuery('div#evo_avatars_area').insertBefore('form.fform > fieldset:has(input.SaveButton)');
	<?php
	} ?>
});
//]]>
</script>