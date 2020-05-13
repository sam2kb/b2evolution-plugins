<?php
/**
 * B2evolution Avatars Plugin
 *
 * The Avatars Plugin allows you to attach avatars (display pictures, gravatars, profile pics, etc) to a user, blog, post and category.
 * You upload avatars within the b2evo adminstration, with either a large or small (or both) avatar images.
 * If a large avatar image is specified then lightbox functionality is seen, as when the user clicks the small avatar, the large avatar will show in a lightbox.
 * It makes use of the GD2 Image and the Gallery Plugin's Libraries allowing for images to be resized and compressed to specified values.
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

global $UserSettings, $current_User, $disp;

if( isset($disp) && $disp == 'profile' )
{
?>
<fieldset class="<?php echo $this->classname.'fieldset'; ?> renderer_plugin" id="<?php echo $this->code; ?>_area">
<div class="label">
  <label><?php echo $this->T_('Avatars').':'?></label>
</div>
<div>
  <div class="label" style="margin:-3px 0 0 0; float:left; width:auto">
    <input type="hidden" name="avatar_runonce" value="true" />
    <input type="hidden" name="avatar_name" value="<?php echo $current_User->ID ?>" />
    <input type="hidden" name="avatar_type" value="user" />
    <?php
	$avatar = $this->get_avatar('user', $current_User, 'default');
	echo $this->get_avatar_display($avatar);
	?>
  </div>
  <div class="input" style="padding-bottom:10px; margin:0 0 0 15px; float:left">
    <div>
      <?php
		$large_width = $this->Settings->get('avatar_user_large_width');
		$large_height = $this->Settings->get('avatar_user_large_height');
		echo '<span style="font-weight:bold">'.$this->T_('Large Avatar').'</span>';
		if ( !($large_width === $large_height && $large_height === 0) )
		echo '&nbsp;&nbsp;<span class="notes">('.$this->T_('Up to').' '.$large_width.'x'.$large_height.').</span>';
	   ?>
      <br />
      <input name="avatar_large_file" id="avatar_large_file" type="file" size="15" />
      <label for="avatar_large_delete" style="margin-top:5px; height:20px; display:block; font-weight:normal">
      <input type="checkbox" name="avatar_large_delete" id="avatar_large_delete" />
      <?php echo $this->T_('Remove Avatar') ?></label>
    </div>

    <div style="margin-top:20px">
      <?php
		$small_width = $this->Settings->get('avatar_user_small_width');
		$small_height = $this->Settings->get('avatar_user_small_height');
		echo '<span style="font-weight:bold; padding-bottom:3px">'.$this->T_('Small Avatar').'</span>';
		if ( !($small_width === $small_height && $small_height === 0) )
		echo '&nbsp;&nbsp;<span class="notes">('.$this->T_('Up to').' '.$small_width.'x'.$small_height.').</span>';
	   ?>
      <br />
      <input name="avatar_small_file" id="avatar_small_file" type="file" size="15" />
      <label for="avatar_small_delete" style="margin-top:5px; height:20px; display:block; font-weight:normal">
      <input type="checkbox" name="avatar_small_delete" id="avatar_small_delete" />
      <?php echo $this->T_('Remove Avatar') ?></label>
    </div>
  </div>
</div>
</fieldset>

<script type="text/javascript">
//<![CDATA[
	jQuery(document).ready(function()
	{
		jQuery('form#ProfileForm').attr('enctype', 'multipart/form-data').attr('encoding', 'multipart/form-data');
		jQuery('form.bComment').attr('enctype', 'multipart/form-data').attr('encoding', 'multipart/form-data');
	});
//]]>
</script>

<?php
}
?>
