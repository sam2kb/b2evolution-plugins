<?php
/**
 *
 * This file implements the Google Analytics code plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2008 by Foppe HEMMINGA - {@link http://www.blog.hemminga.net/}.
 * @copyright (c)2012 by Sonorth Corp. - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Foppe Hemminga
 * @author Sonorth Corp.
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class google_analytics_plugin extends Plugin
{
	var $name = 'Google Analytics';
	var $code = 'ADGoogleAnalytics';
	var $priority = 30;
	var $version = '1.0.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://b2evo.sonorth.com';
	var $help_url = 'http://forums.b2evolution.net/viewtopic.php?t=15370';
	var $apply_rendering = 'never';


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('This plugin puts your Google Analytics code on every page.');
		$this->long_desc = $this->short_desc;
	}


	function GetDefaultSettings()
	{
		return array(
			'ga_code' => array(
					'label' => $this->T_('_uacct / pageTracker code'),
					'note' => $this->T_('Enter the code you obtained from Google. The code is in the form UA-1234567-1'),
					'type' => 'text',
				),
			'ga_format' => array(
					'label' => $this->T_('Tracking code syntax'),
					'note' => $this->T_('Select between old and new (asynchronous) tracking code syntaxes.'),
					'defaultvalue' => 'new',
					'type' => 'select',
					'options' => array(
						'new'	=> $this->T_('New (asynchronous)'),
						'old'	=> $this->T_('Old'),
					),
				),
			'ga_params' => array(
					'label' => $this->T_('Optional params'),
					'type' => 'html_textarea',
					'cols' => 60,
					'rows' => 3,
					'note' => $this->T_('Here you can add additional params to asynchronous syntax, one param per line:').'<b><br />_gaq.push([\'_setDomainName\', \'example.com\']);<br />_gaq.push([\'_setAllowLinker\', true]);</b>',
				),
			);
	}


	/**
	 * Define here default collection/blog settings that are to be made available in the backoffice.
	 *
	 * @return array See {@link Plugin::GetDefaultSettings()}.
	 */
	function get_coll_setting_definitions( & $params )
	{
		return array(
			'coll_ga_code' => array(
					'label' => $this->T_('_uacct / pageTracker code'),
					'note' => $this->T_('Enter the code you obtained from Google. The code is in the form UA-1234567-1'),
					'type' => 'text',
					'defaultvalue' => $this->Settings->get('ga_code'),
				),
			'coll_ga_format' => array(
					'label' => $this->T_('Tracking code syntax'),
					'note' => $this->T_('Select between old and new (asynchronous) tracking code syntaxes.'),
					'defaultvalue' => $this->Settings->get('ga_format'),
					'type' => 'select',
					'options' => array(
						'new'	=> $this->T_('New (asynchronous)'),
						'old'	=> $this->T_('Old'),
					),
				),
			'coll_ga_params' => array(
					'label' => $this->T_('Optional params'),
					'type' => 'html_textarea',
					'defaultvalue' => $this->Settings->get('ga_params'),
					'cols' => 60,
					'rows' => 3,
					'note' => $this->T_('Here you can add additional params to asynchronous syntax, one param per line:').'<b><br />_gaq.push([\'_setDomainName\', \'example.com\']);<br />_gaq.push([\'_setAllowLinker\', true]);</b>',
				),
			);
	}


	// Puts the Google Analytics code in the footer of every blog page
	function SkinEndHtmlBody()
	{
		global $Blog;

		if( is_object($Blog) )
		{	// Frontend
			$ga_code = $this->get_coll_setting( 'coll_ga_code', $Blog );
			$ga_format = $this->get_coll_setting( 'coll_ga_format', $Blog );
			$ga_params = $this->get_coll_setting( 'coll_ga_params', $Blog );
		}

		if( !empty($ga_code) )
		{
			if( $ga_format == 'new' )
			{
				?>
<script type="text/javascript">
  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', '<?php echo $ga_code; ?>']);
  _gaq.push(['_trackPageview']);
  <?php echo $ga_params ?>

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();
</script>
				<?php
			}
			else
			{
				?>
<script type="text/javascript">
  var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
  document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
  var pageTracker = _gat._getTracker("<?php echo $ga_code; ?>");
  pageTracker._initData();
  pageTracker._trackPageview();
</script>
				<?php
			}
		}
	}
}
?>