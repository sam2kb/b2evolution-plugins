<?php 
/**
 * READ THE README! That's where all the answers to all your questions are!
 *
 * This plugin is a direct descendent of the "quicktags" plugin.  In fact, it 
 * WAS the quicktags plugin until I changed the names of stuff.  My contribution 
 * is to use the same techniques to produce different effects when you post to 
 * your blog.
 *
 * @author Russian b2evolution
 * @author EdB
 *
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * This plugin is released into the wilds with the same license as b2evolution.
 * I'm EdB {@link http://wonderwinds.com/} and I approve this message
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class moretags_plugin extends Plugin
{
	var $name = 'More Quick Tags';
	var $code = 'MoreTags';
	var $priority = 30;
	var $version = '1.0.0';
	var $group = 'Wonder winds';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=23895';
	
	var $min_app_version = '3';
	var $max_app_version = '5';

	var $apply_rendering = 'never';
	var $number_of_installs = 1;

	
	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Easy ADDITIONAL HTML tags inserting');
		$this->long_desc = $this->T_('This plugin will display a toolbar with buttons to quickly insert ADDITIONAL HTML tags around selected text in a post.');
	}
	
	
	/* Settings are cool! */
	function GetDefaultSettings()
	{
		return array(
			'moretags_div1' => array(
					'label' => $this->T_('.MT_div_1 styles'),
					'id' => $this->classname.'moretags_div1',
					'defaultvalue' => '',
					'type' => 'textarea',
					'rows' => 3,
					'note' => $this->T_('Read the readme for how to create a custom &lt;div class="MT_div_1"&gt; button.'),
				),
			'moretags_div2' => array(
					'label' => $this->T_('.MT_div_2 styles'),
					'id' => $this->classname.'moretags_div2',
					'defaultvalue' => '',
					'type' => 'textarea',
					'rows' => 3,
					'note' => $this->T_('Read the readme for how to create a custom &lt;div class="MT_div_1"&gt; button.'),
				),
			'moretags_div3' => array(
					'label' => $this->T_('.MT_div_3 styles'),
					'id' => $this->classname.'moretags_div3',
					'defaultvalue' => '',
					'type' => 'textarea',
					'rows' => 3,
					'note' => $this->T_('Read the readme for how to create a custom &lt;div class="MT_div_1"&gt; button.'),
				),
			);
	}
	
	
	/* Grab the style sheet for public use */
	function SkinBeginHtmlHead()
	{
		require_css( $this->get_plugin_url().'moretags.css', true );
	
		$custom_divs_styles = '';
		
		for( $i=1; $i<4; $i++ )
		{
			if( $this->Settings->get('moretags_div'.$i) )
			{
				$custom_divs_styles .= '.MT_div_'.$i.' {'.$this->Settings->get('moretags_div'.$i).'}'."\n";
			}
		}
		
		if( !empty($custom_divs_styles) )
		{
			add_css_headline( $custom_divs_styles );
		}
	}


	// Use the same thing for the back office
	function AdminEndHtmlHead()
	{
		return $this->SkinBeginHtmlHead();
	}
	
	
	// Display the awesomest toolbar ever
	function AdminDisplayToolbar( & $params )
	{
		global $Hit;

		if( $params['edit_layout'] == 'simple' ) return false;
		if( $Hit->is_lynx ) return false;
		
		?>

		<script type="text/javascript">
		//<![CDATA[
		var MoreButtons = new Array();
		var MoreLinks = new Array();
		var MoreOpenTags = new Array();

		function AnotherButton(id, display, tagClass, tagStart, tagEnd, tit, open)
		{
			this.id = id; // used to name the toolbar button
			this.display = display; // label on button
			this.tagClass = tagClass; // class for button
			this.tagStart = tagStart; // open tag
			this.tagEnd = tagEnd; // close tag
			this.tit = tit; // title
			this.open = open; // set to -1 if tag does not need to be closed
		}

		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_red','R','spans MT_red','<span class="MT_red">','</span>','<?php echo $this->T_('RED text') ?>'
			);
		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_ora','O','spans MT_orange','<span class="MT_orange">','</span>','<?php echo $this->T_('ORANGE text') ?>'
			);
		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_yel','Y','spans MT_yellow','<span class="MT_yellow">','</span>','<?php echo $this->T_('YELLOW text') ?>'
			);
		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_gre','G','spans MT_green','<span class="MT_green">','</span>','<?php echo $this->T_('GREEN text') ?>'
			);
		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_blu','B','spans MT_blue','<span class="MT_blue">','</span>','<?php echo $this->T_('BLUE text') ?>'
			);
		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_ind','I','spans MT_indigo','<span class="MT_indigo">','</span>','<?php echo $this->T_('INDIGO text') ?>'
			);
		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_vio','V','spans MT_violet','<span class="MT_violet">','</span>','<?php echo $this->T_('VIOLET text') ?>'
			);

		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_large','t +','spans','<span class="MT_larger">','</span>','<?php echo $this->T_('text larger') ?>'
			);
		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_small','t -','spans leftgap','<span class="MT_smaller">','</span>','<?php echo $this->T_('text smaller') ?>'
			);
		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_under','__','spans','<span class="MT_under">','</span>','<?php echo $this->T_('underline text') ?>'
			);

		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_h1','h1','leftgap','<h1>','</h1>','<?php echo $this->T_('H1') ?>'
			);
		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_h2','h2','','<h2>','</h2>','<?php echo $this->T_('H2') ?>'
			);
		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_h3','h3','','<h3>','</h3>','<?php echo $this->T_('H3') ?>'
			);
		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_h4','h4','','<h4>','</h4>','<?php echo $this->T_('H4') ?>'
			);
		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_h5','h5','','<h5>','</h5>','<?php echo $this->T_('H5') ?>'
			);
		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_h6','h6','','<h6>','</h6>','<?php echo $this->T_('H6') ?>'
			);

		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_div1','div1','divs leftgap','<div class="MT_div_1">','</div>','<?php echo $this->T_('definable div #1') ?>'
			);
		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_div2','div2','divs','<div class="MT_div_2">','</div>','<?php echo $this->T_('definable div #2') ?>'
			);
		MoreButtons[MoreButtons.length] = new AnotherButton(
			'mtb_div3','div3','divs','<div class="MT_div_3">','</div>','<?php echo $this->T_('definable div #3') ?>'
			);

		function MoreShowButton(button, i) {
			document.write('<input type="button" id="' + button.id + '" title="' + button.tit + '" class="' + button.tagClass + '" onclick="MoreInsertTag(b2evoCanvas, ' + i + ');" value="' + button.display + '"  />');
			}

		// Memorize a new open tag
		function MoreAddTag(button) {
			if( MoreButtons[button].tagEnd != '' ) {
				MoreOpenTags[MoreOpenTags.length] = button;
				document.getElementById(MoreButtons[button].id).value = '/' + document.getElementById(MoreButtons[button].id).value;
				}
			}

		// Forget about an open tag
		function MoreRemoveTag(button) {
			for (i = 0; i < MoreOpenTags.length; i++) {
				if (MoreOpenTags[i] == button) {
					MoreOpenTags.splice(i, 1);
					document.getElementById(MoreButtons[button].id).value = document.getElementById(MoreButtons[button].id).value.replace('/', '');
					}
				}
			}

		function MoreCheckOpenTags(button) {
			var tag = 0;
			for (i = 0; i < MoreOpenTags.length; i++) {
				if (MoreOpenTags[i] == button) {
					tag++;
					}
				}
			if (tag > 0) {
				return true; // tag found
				} else {
				return false; // tag not found
				}
			}

		function MoreCloseAllTags() {
			var count = MoreOpenTags.length;
			for (o = 0; o < count; o++) {
				MoreInsertTag(b2evoCanvas, MoreOpenTags[MoreOpenTags.length - 1]);
				}
			}

		function MoreToolbar() {
			document.write('<div class="more_tags">');
			for (var i = 0; i < MoreButtons.length; i++) {
				MoreShowButton(MoreButtons[i], i);
				}
			document.write('<input type="button" id="mtb_close" class="leftgap" onclick="MoreCloseAllTags();" title="<?php echo $this->T_('Close all tags') ?>" value="X" />');
			document.write('</div>');
			}

		/* insertion code */
		function MoreInsertTag( myField, i ) {
			var sel_text = b2evo_Callbacks.trigger_callback("get_selected_text_for_"+myField.id);
			var focus_when_finished = false;
			if( sel_text == null ) {
				if(document.selection) {
					myField.focus();
					var sel = document.selection.createRange();
					sel_text = sel.text;
					focus_when_finished = true;
					}
				else if(myField.selectionStart || myField.selectionStart == '0') {
					var startPos = myField.selectionStart;
					var endPos = myField.selectionEnd;
					sel_text = (startPos != endPos);
					}
				}
			if( sel_text ) {
				textarea_wrap_selection( myField, MoreButtons[i].tagStart, MoreButtons[i].tagEnd, 0 );
				} else {
				if( !MoreCheckOpenTags(i) || MoreButtons[i].tagEnd == '') {
					textarea_wrap_selection( myField, MoreButtons[i].tagStart, '', 0 );
					MoreAddTag(i);
					} else {
					textarea_wrap_selection( myField, '', MoreButtons[i].tagEnd, 0 );
					MoreRemoveTag(i);
					}
				}
			if(focus_when_finished) {
				myField.focus();
				}
			}
		//]]>
		</script>

		<div class="edit_toolbar"><script type="text/javascript">MoreToolbar();</script></div>

		<?php
		return true;
	}
}

?>