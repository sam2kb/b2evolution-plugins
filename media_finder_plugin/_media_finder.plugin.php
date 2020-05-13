<?php
/**
 * This file implements the Media Finder plugin for {@link http://b2evolution.net/}.
 *
 * @copyright (c)2012 Russian b2evolution - {@link http://b2evo.sonorth.com/}.
 * @license GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *
 * @author Russian b2evolution
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class media_finder_plugin extends Plugin
{
	var $name = 'Media Finder';
	var $code = 'sn_mediafinder';
	var $priority = 10;
	var $version = '1.1.0';
	var $group = 'Sonorth Corp.';
	var $author = 'Sonorth Corp.';
	var $author_url = 'http://www.sonorth.com';
	var $help_url = '';

	var $apply_rendering = 'stealth';
	var $number_of_installs = 1;

	// Settings
	var $cache_dirname = 'mediafinder';
	var $cache_images = true;
	var $cache_images_hours = 12;

	var $thumbnail_sizes = array(
			'l' => array( 'fit', 640, 0, 90 ),
			'm' => array( 'fit', 200, 0, 85 ),
			//'s' => array( 'crop', 80, 80, 85 ),
		);

	var $video_width = '425';
	var $video_height = '350';

	var $curl_max_redirects_page = 5;
	var $curl_timeout_page = 10;		// seconds
	var $curl_size_page = 2048;			// kb

	var $curl_max_redirects_img = 3;
	var $curl_timeout_img = 5;			// seconds
	var $curl_size_img = 1024;			// kb

	var $upscale_image_width = 200;
	var $min_image_width = 100;
	var $min_image_height = 80;

	// Internal
	protected $DOM = NULL ;
	protected $XPATH = NULL;
	protected $fixed_charset = NULL;
	protected $meta = array();
	protected $_salt = 'XXX';
	protected $_n = 'sn-mediafinder';
	protected $_bb_url_prefix = 'toot-button';


	function PluginInit( & $params )
	{
		$this->short_desc = $this->T_('Fetches and displays images from remote websites');
		$this->long_desc = $this->short_desc;

		$this->user_agent = 'Mozilla/5.0 (compatible; '.$this->name.' v'.$this->version.')';
		//$this->user_agent = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:14.0) Gecko/20100101 Firefox/14.0.1';
		$this->images_path = $GLOBALS['media_path'].$this->cache_dirname.'/';
		$this->images_url = $GLOBALS['media_url'].$this->cache_dirname.'/';
	}


	function BeforeInstall()
	{
		if( ! function_exists('gd_info') )
		{
			$this->msg( $this->T_('You will not be able to automatically generate thumbnails for images. Enable the gd2 extension in your php.ini file or ask your hosting provider about it.'), 'error' );
			return false;
		}

		if( !function_exists('curl_init') )
		{
			$this->msg( $this->T_('CURL extension is not available'), 'error' );
			return false;
		}

		// Create cache directory
		mkdir_r( $this->images_path );
		@touch( $this->images_path.'index.html' );

		if( ! is_writable($this->images_path) )
		{
			$this->msg( sprintf( $this->T_('You must create the following directory with write permissions (777):%s'), $this->images_path), 'error' );
			return false;
		}

		return true;
	}


	function GetHtsrvMethods()
	{
		return array('fetch');
	}


	function GetCronJobs( & $params )
	{
		return array(
			array(
				'name' => $this->T_('Clear image cache').' ('.$this->name.')',
				'ctrl' => 'clear_cache',
			),
		);
	}


	function ExecCronJob( & $params )
	{	// We provide only one cron job, so no need to check $params['ctrl'] here.
		load_funcs( 'files/model/_file.funcs.php');

		if( cleardir_r( $this->images_path ) )
		{
			@touch( $this->images_path.'index.html' );

			$code = 1;
			$message = $this->T_('Image cache has been cleared.');
		}
		else
		{
			$code = -1;
			$message = $this->T_('There was an error during image cache clearing procedure.');
		}

		return array( 'code' => $code, 'message' => $message );
	}


	// METHOD DISABLED
	function __AfterMainInit()
	{
		global $rsc_url, $baseurl, $Session, $ReqHost, $ReqURI, $io_charset;

		if( preg_match( '~^\?'.$this->_bb_url_prefix.'$~', str_replace($baseurl, '', $ReqHost.$ReqURI) ) )
		{
			// Make sure the async responses are never cached:
			header_nocache();
			header_content_type( 'text/html', $io_charset );

			$insert_js = '

			init_finder_dialog( false );

			jQuery(".'.$this->_n.'").dialog({
				width: 620,
				height: 130,
				resizable: false,
				modal: false,
				dialogClass: "'.$this->_n.'-dialog"
			});
			jQuery("#'.$this->_n.'-upload-link").show();
			jQuery(address).show();
			jQuery(button).show();
			jQuery("#'.$this->_n.'-images").show();
			jQuery(".'.$this->_n.'").show();
			jQuery(button).attr("tabindex",-1).focus();

			//jQuery(address).val("http://yahoo.ru");
			//find_media();

			';

			$params = array(
					'action'				=> 'fetch',
					'crumb_'.$this->code	=> $Session->create_crumb($this->code),
					'blog'					=> 0,
					'redirect_to'			=> '',
					'add_js_tags'			=> false,
				);

			$wi_js = $this->get_widget_js( $params, $insert_js );
			$wi_code = $this->get_widget_code( $params );
			$wi_code .= '<script type="text/javascript">'.$wi_js.'</script>';
			$wi_code = preg_replace( '~[\t\n\r]~', '', $wi_code );


			$r = '
			function loadfile(filename, filetype, doc)
			{
				if (filetype=="js"){ //if filename is a external JavaScript file
					var fileref=document.createElement("script")
					fileref.setAttribute("type","text/javascript")
					fileref.setAttribute("src", filename)
				}
				else if (filetype=="css"){ //if filename is an external CSS file
					var fileref=document.createElement("link")
					fileref.setAttribute("rel", "stylesheet")
					fileref.setAttribute("type", "text/css")
					fileref.setAttribute("href", filename)
				}
				if (typeof fileref!="undefined")
					doc.appendChild(fileref)
			}

			frame = document.createElement("iframe");
			frame.src = "about:blank";
			frame.style.top = "0px";
			frame.style.position="fixed";
			frame.style.display = "block";
			frame.style.zIndex = 1000000;
			frame.style.border = "solid #CCC 4px";
			frame.style.background = "#333";
			frame.style.opacity = "0.9";
			frame.height = "100%";
			frame.width = "100%";
			frame.id = "loginFrame";
			frame.name = "loginFrame";

			document.body.margin = "0";
			document.body.appendChild(frame);
			var idocument = frame.contentWindow.document;

			loadfile("https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js", "js", idocument.head);
			loadfile("https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/jquery-ui.min.js", "js", idocument.head);
			loadfile("'.$rsc_url.'css/jquery/smoothness/jquery-ui.css", "css", idocument.head);
			loadfile("'.$this->get_plugin_url(true).'rsc/media_finder_plugin.css", "css", idocument.head);
			loadfile("'.$this->get_plugin_url(true).'rsc/media_finder_plugin.js", "js", idocument.body);

			//alert(parent.window.location);
			div = idocument.createElement("div");
			div.innerHTML = '.evo_json_encode($wi_code).';
			idocument.body.appendChild(div);
			idocument.body.setAttribute("onload", "initMediaFinder()");
			';

			echo $r;

			exit(0);
		}
	}


	function AdminAfterMenuInit()
	{
		$this->register_menu_entry($this->name);
	}


	function AdminTabPayload()
	{
		//echo '<div class="sn-mediafinder-dialog-btn">Add a tOOt</div>';
		//echo $this->get_widget();
		//echo '<br /><hr /><br />';

		//$this->update_toot_settings();

		$url = param( $this->get_class_id('url'), 'string', '' );

		$Form = new Form( 'admin.php', '', 'post' );
		$Form->begin_form( 'fform' );
		$Form->hidden_ctrl();
		$Form->hiddens_by_key( get_memorized() );

		$Form->begin_fieldset('URL checker');
		echo '<fieldset>Enter a URL address: <input name="'.$this->get_class_id('url').'" value="'.$url.'" style="width:90%" /></fieldset>';

		if( $url != '' && ! $this->is_url($url) )
		{
			echo '<p class="red">Bad URL</p>';
		}
		elseif( $url != '' )
		{
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $this->curl_timeout_page );
			curl_setopt( $ch, CURLOPT_TIMEOUT, $this->curl_timeout_page );
			@curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true ); // made silent due to possible errors with safe_mode/open_basedir(?)
			curl_setopt( $ch, CURLOPT_MAXREDIRS, $this->curl_max_redirects_page );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_USERAGENT, $this->user_agent );
			curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
			$info['content'] = curl_exec( $ch );

			$info['mimetype'] = curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );
			$info['status'] = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$info['error'] = curl_error( $ch );
			if( ( $errno = curl_errno( $ch ) ) )
			{
				$info['error'] .= ' (#'.$errno.')';
			}
			curl_close( $ch );

			echo '<fieldset>Status: '.$info['status'];
			echo '<br />MIME Type: '.$info['mimetype'];
			echo '<br />Error (if any): <span class="red">'.$info['error'].'</span></fieldset>';
			echo '<pre style="max-width: 900px; height: 400px; overflow: auto; border: 1px solid #CCC; margin-top: 10px; padding: 5px">'.htmlspecialchars($info['content']).'</pre>';
		}
		$Form->end_fieldset();

		$Form->end_form( array( array( 'value' => 'Check' ) ) );
	}


	function SkinEndHtmlBody()
	{
		echo $this->get_widget();
	}


	function AdminEndHtmlHead()
	{
		return $this->SkinBeginHtmlHead();
	}


	function SkinBeginHtmlHead()
	{
		global $rsc_url;

		require_js('#jquery#');
		require_js('#jqueryUI#');
		require_js('ajax.js');
		require_js( $this->get_plugin_url().'rsc/'.$this->classname.'.js', true );

		require_css( $rsc_url.'css/jquery/smoothness/jquery-ui.css' );
		require_css( $this->get_plugin_url().'rsc/'.$this->classname.'.css', true );
	}


	function get_widget( $action = 'fetch', $params = array() )
	{
		global $Session, $ReqURI, $blog;

		$params = array_merge( array(
			'action'				=> $action,
			'crumb_'.$this->code	=> $Session->create_crumb($this->code),
			'blog'					=> ( !empty($blog) ? intval($blog) : 0 ),
			'redirect_to'			=> rawurlencode($ReqURI),
			'add_js_tags'			=> true,
			'format'				=> 'html',
		), $params );

		$r  = $this->get_widget_code( $params );
		$r .= $this->get_widget_js( $params );

		return $r;
	}


	function get_widget_js( $params, $insert_js = '' )
	{
		$r = '';

		if( $params['add_js_tags'] )
		{
			$r .= '<script type="text/javascript">
					/* <![CDATA[ */
					';
		}

		$form_params = $params;
		$form_params['action'] = 'submit';
		$form_url_submit = url_add_param( $this->get_htsrv_url('fetch', '', '&', true), 'params='.base64_encode(serialize($form_params)), '&' );

		$form_params['action'] = 'upload';
		$form_url_upload = url_add_param( $this->get_htsrv_url('fetch', '', '&', true), 'params='.base64_encode(serialize($form_params)), '&' );

		$r .= '
		jQuery(function()
		{
			var address = jQuery("#'.$this->_n.'-address");
			var button = jQuery("#'.$this->_n.'-btn");
			var upload = jQuery("#'.$this->_n.'-upload");
			var result = jQuery("#'.$this->_n.'-result");
			var error = jQuery("#'.$this->_n.'-error");

			var submitURL = "'.$form_url_submit.'";
			var uploadURL = "'.$form_url_upload.'";
			var fetchURL = "'.url_add_param( $this->get_htsrv_url('fetch', '', '&', true), 'params='.base64_encode(serialize($params)), '&' ).'";

			jQuery("#'.$this->_n.'-address, #'.$this->_n.'-title, #'.$this->_n.'-description, #'.$this->_n.'-tags").labelify({labelledClass: "'.$this->_n.'-label"});

			jQuery(".'.$this->_n.'-dialog-btn").bind("click", function()
			{
				init_finder_dialog( false );

				jQuery(".'.$this->_n.'").dialog({
					width: 620,
					height: 130,
					resizable: false,
					modal: true,
					dialogClass: "'.$this->_n.'-dialog"
				});
				jQuery("#'.$this->_n.'-upload-link").show();
				jQuery(address).show();
				jQuery(button).show();
				jQuery("#'.$this->_n.'-images").show();
				jQuery(".'.$this->_n.'").show();
				jQuery(button).attr("tabindex",-1).focus();
				return false;
			});

			jQuery("#'.$this->_n.'-upload-link").bind("click", function()
			{
				jQuery("#'.$this->_n.'-upload-link").hide();
				jQuery(error).hide();
				jQuery(address).hide();
				jQuery(button).hide();
				jQuery("#'.$this->_n.'-images").hide();

				jQuery("#'.$this->_n.'-upload").show();
				jQuery("#'.$this->_n.'-form").css({width: "100%"});
				jQuery(".ui-widget-content").animate({height: "400px"});

				jQuery("#'.$this->_n.'-form").attr("action", uploadURL);

				jQuery(upload).show();
				jQuery(result).show();

				return false;
			});

			jQuery("#'.$this->_n.'-submit").click(function()
			{
				var title = jQuery("#'.$this->_n.'-title").val().replace(/^\s+|\s+$/g,"");
				var description = jQuery("#'.$this->_n.'-description").val().replace(/^\s+|\s+$/g,"");
				var tags = jQuery("#'.$this->_n.'-tags").val().replace(/^\s+|\s+$/g,"");

				if( title.length > 0 && description.length > 0 && tags.length > 0 &&
					title != "Enter tOOt title Here" &&
					description != "Enter or cut and paste tOOt description or contents here:" &&
					tags != "Enter tags, separate with a comma"
				)
				{
					jQuery("#'.$this->_n.'-form").submit();
				}
				else
				{
					alert("'.$this->T_('You must enter tOOt title, description and tags').'");
				}
			});

			jQuery("#'.$this->_n.'-description").keyup(function(i)
			{
				var max = 500;
				var val = jQuery(this).attr("value");
				var cur = 0;
				if(val) { cur = val.length; }
				var left = max-cur;
				if( left < 0 ) { left = 0; }

				jQuery("#'.$this->_n.'-counter").text(left.toString());
			});

			jQuery(button).click(function() {
				find_media();
			});

			jQuery("#'.$this->_n.'-address").bind("keypress", function(e) {
				if(e.keyCode==13){ find_media(); }
			});


			function find_media()
			{
				var urlvalue = jQuery(address).val().replace(/^\s+|\s+$/g,"");
				if( urlvalue.length < 3 || urlvalue == "Enter a Website Address eg. www.website.com" )
				{
					alert("'.$this->T_('Please enter a valid URL address').'");
					return false;
				}

				init_finder_dialog( true );

				if( carousel = jQuery(".jcarousel-skin-tango").data("jcarousel") ) {
					carousel.reset();
				}

				jQuery("<img id=\"'.$this->_n.'-loader\" src=\"'.$this->get_plugin_url(true).'rsc/loader.gif\" />").insertAfter(address);

				jQuery.ajax( {
					type: "GET",
					dataType: "json",
					url: fetchURL + "&url=" + encodeURIComponent( jQuery(address).val() ),
					success: function(data) {
						//data = ajax_debug_clear(data);
						jQuery("#'.$this->_n.'-slide").html("");
						if( data.images.length == 0 && data.videos.length == 0 )
						{
							jQuery(".ui-widget-content").animate({height: "150px"});
							jQuery(error).html(data.msg);
							jQuery(error).show();
						}
						else
						{
							var images = "";
							var total = 0;
							jQuery("#'.$this->_n.'-url").val( data.url );
							jQuery(".ui-widget-content").animate({height: "420px"});

							jQuery.each(data.videos, function(k,video){
								images += "<li><div id=\"" + video.embed_code + "\"  rel=\"" + video.url + "\" title=\"" + video.title + "\">" + video.embed + "</div></li>";
								total++;
							});
							jQuery.each(data.images, function(k,img){
								images += "<li><img src=\"'.$this->images_url.'" + k + "-m.jpg\" id=\"" + k + "\" title=\"" + img.title + "\" /></li>";
								total++;
							});
							// alert(total);

							var slide = "'.str_replace( array('"', '@'), array('\"', '"'), '<ul id="'.$this->_n.'-slide" class="jcarousel-skin-tango">@ + images + @</ul><div id="'.$this->_n.'-controls"><div id="'.$this->_n.'-prev">&larr;&nbsp;Prev</div><div id="'.$this->_n.'-next">Next&nbsp;&rarr;</div></div>').'";
							jQuery("#'.$this->_n.'-images").html(slide);

							if( total > 1 )
							{
								jQuery("#'.$this->_n.'-slide").jcarousel({
									scroll: 1,
									itemFallbackDimension: '.$this->thumbnail_sizes['m'][1].',
									initCallback: carousel_initCallback,
									itemVisibleInCallback: carousel_itemVisibleInCallback
								});
							}
							else
							{	// Hide controls
								jQuery("#'.$this->_n.'-controls").hide();
							}

							// Init first item
							carousel_itemVisibleInCallback( 0, jQuery("#'.$this->_n.'-slide > li"), 0, 0 );

							jQuery(result).show();
						}
					},
					complete: function() {
						jQuery("#'.$this->_n.'-loader").hide();
						jQuery(button).show();
						jQuery("<div class=\"clear\"></div>").appendTo(result);
					}
				} );
			}

			function carousel_initCallback(carousel)
			{
				jQuery("#'.$this->_n.'-next").bind("click", function() {
					carousel.next();
					return false;
				});

				jQuery("#'.$this->_n.'-prev").bind("click", function() {
					carousel.prev();
					return false;
				});
			}

			function init_finder_dialog( search )
			{
				jQuery(error).hide();
				jQuery(upload).hide();

				if( search )
				{
					jQuery(button).hide();
				}
				jQuery(".ui-widget-content").animate({height: "130px"});

				jQuery("#'.$this->_n.'-controls").show();
				jQuery(result).hide();
				jQuery("#'.$this->_n.'-images").html("");
				jQuery("#'.$this->_n.'-media").val("");
				jQuery("#'.$this->_n.'-form").attr("action", submitURL);
			}

			function carousel_itemVisibleInCallback(carousel, li, index, state)
			{
				var imageID = jQuery(li).find("img").attr("id");
				var imagetitle = jQuery(li).find("img").attr("title");

				var videoID = jQuery(li).find("div").attr("id");
				var videotitle = jQuery(li).find("div").attr("title");
				var videoUrl = jQuery(li).find("div").attr("rel");

				if( imageID !== undefined )	{
					jQuery("#'.$this->_n.'-media").val( imageID );
				}
				if( videoID !== undefined ) {
					jQuery("#'.$this->_n.'-media").val( videoID );
				}
				if( imagetitle !== undefined ) {
					jQuery("#'.$this->_n.'-title").val( imagetitle );
				}
				if( videotitle !== undefined ) {
					jQuery("#'.$this->_n.'-title").val( videotitle );
				}
				if( videoUrl !== undefined ) {
					jQuery("#'.$this->_n.'-videourl").val( videoUrl );
				}

				if( jQuery("#'.$this->_n.'-title").val().length == 0 ) {
					jQuery("#'.$this->_n.'-title").labelify({labelledClass: "'.$this->_n.'-label"});
				} else {
					jQuery("#'.$this->_n.'-title").removeAttr("class");
				}
			}

			'.$insert_js.'

		})';

		if( $params['add_js_tags'] )
		{
			$r .= '/* ]]> */
					</script>';
		}

		return $r;
	}


	function get_widget_code( $params = array() )
	{
		global $DB, $Blog, $Settings, $Item, $cat;

		$default_category = 0;
		if( !empty($Blog) )
		{
			$default_category = $cat;
			if( empty($default_category) && !empty($Item) )
			{
				$default_category = $Item->main_cat_ID;
			}
			elseif( empty($default_category) )
			{
				$default_category = $Blog->get_default_cat_ID();
			}
		}

		$BlogCache = & get_BlogCache();
		$public_blogs = $BlogCache->load_public();

		$cat_select = '';
		$categories = '';

		foreach( $public_blogs as $pblog )
		{
			if( ! $p_Blog = & $BlogCache->get_by_ID( $pblog, false, false ) ) continue;

			$SQL = 'SELECT cat_ID, cat_name FROM T_categories WHERE cat_blog_ID = '.$pblog.' ORDER BY cat_ID ASC';
			if( $rows = $DB->get_results($SQL) )
			{
				$categories .= '<optgroup label="&nbsp;'.$p_Blog->dget('name', 'htmlattr').'">';
				foreach( $rows as $row )
				{
					$categories .= '<option value="'.$row->cat_ID.'"';
					if( $row->cat_ID == $default_category )
					{	// default
						$categories .= ' selected="selected"';
					}
					$categories .= '>'.htmlspecialchars($row->cat_name).'</option>';
				}
				$categories .= '</optgroup>';
			}
		}
		if( !empty($categories) )
		{
			$cat_select = '<div class="sn-select-label">'.$this->T_('Please select a category').':</div><select name="'.$this->get_class_id('cat').'" id="'.$this->_n.'-cats">'.$categories.'</select>';
		}
		$form_params = $params;
		$form_params['action'] = 'submit';
		$form_action = url_add_param( $this->get_htsrv_url('fetch', '', '&', true), 'params='.base64_encode(serialize($form_params)), '&' );

		$code = '
	<div class="'.$this->_n.'" title="Add a tOOt">
		<input id="'.$this->_n.'-address" name="" value="" title="Enter a Website Address eg. www.website.com" />
		<span id="'.$this->_n.'-btn">'.$this->T_('Find Images').'</span>
		<a href="#" id="'.$this->_n.'-upload-link">'.$this->T_('Click here to upload your own image').'</a>
		<div id="'.$this->_n.'-error"></div>
		<div id="'.$this->_n.'-result">
		<table width="100%" cellpadding="0" cellspacing="0">
		  <tr>
			<td width="200px" valign="top" id="'.$this->_n.'-images"></td>
			<td valign="top">
			<div id="'.$this->_n.'-content">
			<form id="'.$this->_n.'-form" method="post" action="'.$form_action.'" enctype="multipart/form-data">
				<div id="'.$this->_n.'-upload">
					<input name="MAX_FILE_SIZE" value="'.($Settings->get('upload_maxkb')*1024).'" type="hidden" />
					<input name="uploadfile[]" type="file" />
				</div>
				'.$cat_select.'
				<input id="'.$this->_n.'-title" name="'.$this->get_class_id('title').'" maxlength="250" title="Enter tOOt title Here" />
				<input id="'.$this->_n.'-tags" name="'.$this->get_class_id('tags').'" maxlength="250" title="Enter tags, separate with a comma" />
				<textarea id="'.$this->_n.'-description" name="'.$this->get_class_id('desc').'" maxlength="500" title="Enter or cut and paste tOOt description or contents here:"></textarea>

				<input id="'.$this->_n.'-media" name="'.$this->get_class_id('media').'" type="hidden" />
				<input id="'.$this->_n.'-url" name="'.$this->get_class_id('url').'" type="hidden" />
				<input id="'.$this->_n.'-videourl" name="'.$this->get_class_id('videourl').'" type="hidden" />
				<div style="margin-top:5px"><span id="'.$this->_n.'-submit">'.$this->T_('tOOt').'</span><span id="'.$this->_n.'-counter">500</span></div>
			</form>
			</div>
			</td>
		  </tr>
		</table>
		</div>
	</div>';

		return $code;
	}


	function err( $str, $add_prefix = false )
	{
		if( $add_prefix ) $str = $this->name.': '.$str;

		$response = array(
				'status' => 'error',
				'msg' => $str,
				'url' => '',
				'images' => '',
				'videos' => '',
			);

		echo json_encode( $response );
		die(0);
	}


	function js_err( $str, $add_prefix = false )
	{
		if( $add_prefix ) $str = $this->name.': '.$str;

		echo 'alert("'.$str.'");';
		//echo 'document.write("<h3>'.$str.'</h3>");';

		die(0);
	}


	function htsrv_fetch( & $opts )
	{
		global $io_charset, $secure_htsrv_url;

		// Make sure the async responses are never cached:
		header_nocache();
		header_content_type( 'text/html', $io_charset );

		if( !is_logged_in() ) $this->err( sprintf('You must <a %s>Login</a> to tOOt', 'href="'.$secure_htsrv_url.'login.php"') );

		if( ! function_exists('curl_init') ) $this->err('CURL extension is not loaded');
		if( empty($_GET['params']) ) $this->err('Bad params passed');

		$params = trim($_GET['params']);
		if( ! $params = @base64_decode($params) ) $this->err('Bad params passed');
		if( ! $params = @unserialize($params) ) $this->err('Bad params passed');

		if( empty($params['action']) ) $this->err('Unknown action');
		$action = $params['action'];

		if( empty($params['redirect_to']) ) $params['redirect_to'] = '';
		$params['redirect_to'] = rawurldecode($params['redirect_to']);
		if( preg_match( '~^(ht|f)tps?://~', $params['redirect_to'] ) )
		{	// Either empty or absolute URL, reset it
			$params['redirect_to'] = '';
		}

		$this->check_received_crumb( $this->code, $params );

		switch( $action )
		{
			case 'submit':
				$this->do_submit( $params );
				break;

			case 'fetch':
				$this->do_fetch( $params );
				break;

			case 'upload':
				$this->do_upload( $params );
				break;

			default:
				$this->err('Unknown action');
		}
	}


	function do_submit( $params )
	{
		global $Messages, $current_User, $DB, $localtimenow;

		$title = param( $this->get_class_id('title'), 'string', '', true );
		$tags = param( $this->get_class_id('tags'), 'string', '', true );
		$cat_ID = param( $this->get_class_id('cat'), 'integer', '', true );
		$content = param( $this->get_class_id('desc'), 'html', '', true );
		$media = param( $this->get_class_id('media'), 'string', '', true );
		$url = param( $this->get_class_id('url'), 'string', '', true );
		$media_url = param( $this->get_class_id('videourl'), 'string', '', true );

		$title = check_html_sanity( $title, 'posting', $current_User );
		$tags = check_html_sanity( $tags, 'posting', $current_User );
		$content = check_html_sanity( $content, 'posting', $current_User );

		if( $Messages->has_errors() )
		{
			header_redirect($params['redirect_to']);
		}

		$media_type = 'image';
		preg_match( '~^video_([a-z]+)_(.*)$~', $media, $matches );
		if( !empty($matches) )
		{
			$media_type = 'video';
			$provider = $matches[1];

			if( !$embed_code = @base64_decode($matches[2]) )
			{
				$this->msg( $this->T_('Invalid video code'), 'error');
				header_redirect($params['redirect_to']);
			}

			if( preg_match( '~^s_(.+)$~', $media_url, $match ) )
			{
				$media_url = $match[1];
			}

			if( !$media_url = @base64_decode($media_url) )
			{
				$this->msg( $this->T_('Invalid video URL'), 'error');
				header_redirect($params['redirect_to']);
			}
			else
			{
				$tmp_url = @unserialize($media_url);
				if( !empty($tmp_url) && is_array($tmp_url) )
				{	// og:video & og:image
					$image_url = $tmp_url['image'];
					$media_url = $tmp_url['video'];
				}

				// Remove with and height params from video URL
				$media_url = preg_replace( '~(;|&)(width|height)=[0-9]+~', '', $media_url );
			}

			if( strpos($embed_code, '[video:') === false )
			{
				// Resize container
				$code = preg_replace( '~ width="[0-9]+"~', ' width="'.$this->video_width.'"', $embed_code );
				$code = preg_replace( '~ height="[0-9]+"~', ' height="'.$this->video_height.'"', $code );

				// Resize URL
				$code = preg_replace( '~(;|&)width=[0-9]+~', '\\1width='.$this->video_width, $code );
				$code = preg_replace( '~(;|&)height=[0-9]+~', '\\1height='.$this->video_height, $code );

				$code = '<div class="videoblock">'.$code.'</div>';
				$code .= "\n<!-- [video:".$provider.":dummy] -->\n";

				$content = $code."\n<br />\n".$content;
			}
			else
			{
				$content = $embed_code."\n<br />\n".$content;
			}
		}

		load_class( 'items/model/_item.class.php', 'Item' );
		$edited_Item = new Item();

		$edited_Item->set_creator_User( $current_User );
		$edited_Item->set( $edited_Item->lasteditor_field, $current_User->ID );
		$edited_Item->set( 'title', $title );
		$edited_Item->set( 'urltitle', urltitle_validate('', $title) );
		$edited_Item->set( 'content', $content );
		$edited_Item->set( 'main_cat_ID', $cat_ID );
		$edited_Item->set( 'locale', $current_User->locale );
		$edited_Item->set( 'datestart', date('Y-m-d H:i:s',$localtimenow) );
		$edited_Item->set( 'status', 'published' );
		$edited_Item->set_tags_from_string( $tags );

		if( ($url = @base64_decode($url)) && $this->is_url($url) )
		{
			$edited_Item->set( 'url', $url );
		}

		$DB->begin();

		// INSERT INTO DB:
		$edited_Item->dbinsert();

		if( $edited_Item->ID )
		{
			if( $media_type == 'image' )
			{
				$filename = $this->images_path.$media.'-l.jpg';
				$target_dir = $current_User->get_media_dir();

				if( $target_dir && @copy( $filename, $target_dir.$media.'.jpg' ) )
				{
					$FileCache = & get_FileCache();
					$File = & $FileCache->get_by_root_and_path( 'user', $current_User->ID, $media.'.jpg' );
					$File->meta = 'notfound'; // Save time and don't try to load meta from DB, it's not there anyway
					$File->dbsave();

					$media_url = $File->get_url();

					// Let's make the link!
					$LinkOwner = new LinkItem( $edited_Item );
					$LinkOwner->add_link( $File->ID, 'teaser', 1 );
				}
				else
				{
					$image_failed = true;
					$DB->rollback();
				}
			}

			// Save to DB if image is not failed
			if( ! isset($image_failed) ) $DB->commit();

			// Add item settigns
			$edited_Item->load_ItemSettings();
			$edited_Item->ItemSettings->set( $edited_Item->ID, 'toot_media_url', $media_url );
			$edited_Item->ItemSettings->set( $edited_Item->ID, 'toot_media_type', $media_type );

			if( !empty($image_url) )
			{	// og:image
				$edited_Item->ItemSettings->set( $edited_Item->ID, 'toot_screenshot', $image_url );
			}
			$edited_Item->ItemSettings->dbupdate();

			// Execute or schedule notifications & pings:
			$edited_Item->handle_post_processing( false );
			$Messages->clear();

			$this->msg( sprintf( $this->T_('New tOOt created: %s'), $title ), 'success');
			header_redirect( $edited_Item->get_permanent_url() );
		}
		else
		{
			$this->msg( $this->T_('Failed to tOOt'), 'error');
			header_redirect($params['redirect_to']);
		}
	}


	function do_upload( $params )
	{
		global $Messages, $current_User, $DB, $localtimenow;

		$title = param( $this->get_class_id('title'), 'string', '', true );
		$cat_ID = param( $this->get_class_id('cat'), 'integer', '', true );
		$content = param( $this->get_class_id('desc'), 'html', '', true );

		$title = check_html_sanity( $title, 'posting', $current_User );
		$content = check_html_sanity( $content, 'posting', $current_User );

		if( $Messages->has_errors() )
		{
			header_redirect($params['redirect_to']);
		}

		load_class( 'items/model/_item.class.php', 'Item' );
		$edited_Item = new Item();

		$edited_Item->set_creator_User( $current_User );
		$edited_Item->set( $edited_Item->lasteditor_field, $current_User->ID );
		$edited_Item->set( 'title', $title );
		$edited_Item->set( 'urltitle', urltitle_validate('', $title) );
		$edited_Item->set( 'content', $content );
		$edited_Item->set( 'main_cat_ID', $cat_ID );
		$edited_Item->set( 'locale', $current_User->locale );
		$edited_Item->set( 'datemodified', date('Y-m-d H:i:s',$localtimenow) );
		$edited_Item->set( 'status', 'published' );

		$DB->begin();

		$FileRootCache = & get_FileRootCache();
		$root = FileRoot::gen_ID( 'user', $current_User->ID );
		$result = process_upload( $root, '', true, false, true, false );
		if( empty( $result ) )
		{
			$Messages->add( T_( 'You don\'t have permission to selected user file root.' ), 'error' );
			$DB->rollback();

			header_redirect($params['redirect_to']);
		}

		$is_video = $media_is_ok = false;

		$uploadedFiles = $result['uploadedFiles'];
		if( !empty( $uploadedFiles ) )
		{	// upload was successful
			$File = $uploadedFiles[0];
			if( $File->is_image() )
			{
				$width = $File->get_image_size('width');
				$height = $File->get_image_size('height');

				if( $width < $this->upscale_image_width || $height < $this->min_image_height )
				{
					$Messages->add( $this->T_('The image is too small'), 'error' );
					$File->unlink();
					$DB->rollback();

					header_redirect($params['redirect_to']);
				}

				$media_is_ok = true;
			}
			else
			{
				$mimetype = $this->get_mimetype( $File->_adfp_full_path );
				if( is_string($mimetype) && preg_match( '~video/(quicktime|mp4|x-flv|x-m4v)~i', $mimetype ) )
				{	// This is a video file
					$code = '<div class="videoblock"><a class="flowplayer" style="display: block; width:'.$this->video_width.'px; height:'.$this->video_height.'px" href="'.$File->get_url().'"></a></div>';
					$code .= "\n<!-- [video:flowplayer:dummy] -->\n";
					$content = $code."\n<br />\n".$content;
					$edited_Item->set( 'content', $content );

					$is_video = true;
					$media_is_ok = true;
				}
				else
				{	// Uploaded file is not an image or video, delete the file
					$Messages->add( T_( 'The file you uploaded does not seem to be an image or video.' ) );
					$File->unlink();
					$DB->rollback();

					header_redirect($params['redirect_to']);
				}
			}

			if( $media_is_ok )
			{
				// INSERT INTO DB:
				$edited_Item->dbinsert();

				if( ! $is_video )
				{	// Let's make the link!
					$LinkOwner = new LinkItem( $edited_Item );
					$LinkOwner->add_link( $File->ID, 'teaser', 1 );
				}

				$DB->commit();
			}
		}

		$failedFiles = $result['failedFiles'];
		if( !empty( $failedFiles ) )
		{
			$Messages->add( $failedFiles[0] );
		}

		if( $edited_Item->ID )
		{
			// Execute or schedule notifications & pings:
			$edited_Item->handle_post_processing();
			$Messages->clear();

			$this->msg( sprintf( $this->T_('New tOOt created: %s'), $title ), 'success');
			header_redirect( $edited_Item->get_permanent_url() );
		}
		else
		{
			$this->msg( $this->T_('Failed to tOOt'), 'error');
			header_redirect($params['redirect_to']);
		}
	}


	function do_fetch( $params, $search_videos = true )
	{
		global $DB, $current_User, $io_charset;

		$url = param('url', 'string', '', true);
		if( empty($url) ) $this->err('Empty URL field?');

		if( ! $this->is_url($url) ) $url = 'http://'.$url;
		if( ! $content = $this->get_data($url, $info) )
		{
			$str = 'Unable to read remote page';
			if( !empty($info['error']) ) $str .= ' ('.$info['error'].')';
			$this->err($str);
		}

		if( !empty($info['charset']) )
		{	// Use server charset if available
			$content = convert_charset( $content, $io_charset, $info['charset'] );
		}

		// Use custom error handler
		libxml_use_internal_errors(true);

		$this->DOM = new DOMDocument;
		$this->DOM->strictErrorChecking = false;
		$this->DOM->loadHTML('<?xml encoding="UTF-8">'.$content);
		$this->DOM->encoding = 'utf-8';

		if( $info['charset'] != 'utf-8' || $info['charset'] != 'utf8' )
		{	// Convert page charset to UTF-8
			// We need that in order to get correct image titles
			$content_type = $this->get_meta_tag('Content-Type', 'http-equiv');
			//$XPATH = new DOMXpath($DOM);
			//$content_type = $XPATH->query('//meta[@http-equiv="Content-Type"]/@content')->item(0);
			if( preg_match( '~charset =(.+)$~ix', $content_type, $match ) )
			{
				$info['charset'] = strtolower(trim($match[1]));
				$this->fixed_charset = $info['charset'];

				/*
				$content = convert_charset( $content, $io_charset, $info['charset'] );

				// Load again
				unset($DOM);
				libxml_clear_errors();

				$DOM = new DOMDocument;
				$DOM->strictErrorChecking = false;
				$DOM->loadHTML('<?xml encoding="UTF-8">'.$content);
				$DOM->encoding = 'utf-8';
				*/
			}
		}

		/*
		echo implode( '<br />', array(
				'URL: '.$url,
				'Page MIME type: '.$info['mimetype'],
				'Page charset: '.$info['charset'],
				'Page status code: '.$info['status'],
				'Error (if any): '.$info['error'],
			)).'<br />';
		*/

		$base = '';
		$base_tags = $this->DOM->getElementsByTagName('base');
		if( $base_tags->length > 0 )
		{	// Get base URL
			foreach( $base_tags as $basetag )
			{	// We overrwrite previous tags
				$base = $basetag->getAttribute('href');
			}
			if( ! $this->is_url($base) )
			{	// Make sure we don't use invalid base URLs
				$base = '';
			}
		}

		// Get images
		$image_tags = $this->DOM->getElementsByTagName('img');

		$images = array();
		foreach( $image_tags as $tag )
		{
			if( ! $src = $tag->getAttribute('src') ) continue;

			$title = '';
			$t = $tag->getAttribute('title');
			$a = $tag->getAttribute('alt');
			if( empty($t) && !empty($a) ) $t = $a;
			if( empty($t) ) $title = $this->get_meta_tag('og:title');
			if( !empty($t) ) $title = $t;

			if( ! $this->is_url($src) )
			{	// Relative URI
				if( empty($base) )
				{	// Relative to host
					$src = preg_replace( '~^//~', '/', $src );
					$src = url_absolute($src, $url);
				}
				else
				{	// Relative to base
					$src = url_absolute($src, $base);
				}
			}

			$src = trim($src);

			$images[md5($src.$this->_salt)] = array(
					'url' => $src,
					'title' => $this->enc($title),
				);
		}

		$videos = array();
		if( $search_videos )
		{
			// Get videos from META tag
			$src = $type = $title = '';
			if( $src = $this->get_meta_tag('og:video') )
			{
				if( $this->is_video_url($src, $embed, $embed_code, $provider) )
				{
					$type = $this->get_meta_tag('og:video:type');
					$title = $this->get_meta_tag('og:title');

					$image = $this->get_meta_tag('og:image');
					$image = $this->og_video_image( $provider, $image );

					if( empty($image) ) $image = '';
					$urls = array( 'video' => $src, 'image' => $image );

					if( empty($embed) )
					{
						$embed = '<object data="'.$src.'" type="'.$type.'" width="200" height="110"></object>';
					}
					if( empty($embed_code) )
					{
						$embed_code = 'video_'.$provider.'_'.base64_encode($embed);
					}

					$videos[md5($src.$this->_salt)] = array(
							'url' => 's_'.base64_encode( serialize( $urls ) ),
							'title' => $this->enc($title),
							'embed' => $embed,
							'embed_code' => $embed_code,
						);
				}
			}

			// Get videos from IFRAME tags
			$video_tags = $this->DOM->getElementsByTagName('iframe');
			foreach( $video_tags as $tag )
			{
				$src = $tag->getAttribute('src');
				if( $this->is_video_url($src, $embed, $embed_code, $provider) )
				{
					if( empty($embed) )
					{
						$embed = '<iframe src="'.$src.'" width="200" height="110" frameborder="0"></iframe>';
					}
					if( empty($embed_code) )
					{
						$embed_code = 'video_'.$provider.'_'.base64_encode($embed);
					}
					$title = $this->get_meta_tag('og:title');

					$videos[md5($src.$this->_salt)] = array(
							'url' => base64_encode($src),
							'title' => $this->enc($title),
							'embed' => $embed,
							'embed_code' => $embed_code,
						);
				}
			}

			// Get videos from EMBED
			$video_tags = $this->DOM->getElementsByTagName('param');
			foreach( $video_tags as $tag )
			{
				$name = $tag->getAttribute('name');
				if( empty($name) || $name != 'movie' ) continue;

				$src = $tag->getAttribute('value');
				if( $this->is_video_url($src, $embed, $embed_code, $provider) )
				{
					if( ! $type = $tag->getAttribute('type') ) continue;

					if( empty($embed) )
					{
						$embed = '<embed src="'.$src.'" type="'.$type.'" width="200" height="110"></embed>';
					}
					if( empty($embed_code) )
					{
						$embed_code = 'video_'.$provider.'_'.base64_encode($embed);
					}
					$title = $this->get_meta_tag('og:title');

					$videos[md5($src.$this->_salt)] = array(
							'url' => base64_encode($src),
							'title' => $this->enc($title),
							'embed' => $embed,
							'embed_code' => $embed_code,
						);
				}
			}

			// Get videos from OBJECT tag
			$video_tags = $this->DOM->getElementsByTagName('object');
			foreach( $video_tags as $tag )
			{
				$src = $tag->getAttribute('data');
				if( $this->is_video_url($src, $embed, $embed_code, $provider) )
				{
					if( ! $type = $tag->getAttribute('type') ) continue;

					if( empty($embed) )
					{
						$embed = '<object data="'.$src.'" type="'.$type.'" width="200" height="110"></object>';
					}
					if( empty($embed_code) )
					{
						$embed_code = 'video_'.$provider.'_'.base64_encode($embed);
					}
					$title = $this->get_meta_tag('og:title');

					$videos[md5($src.$this->_salt)] = array(
							'url' => base64_encode($src),
							'title' => $this->enc($title),
							'embed' => $embed,
							'embed_code' => $embed_code,
						);
				}
			}

			/*
			$video_tags = $this->DOM->getElementsByTagName('meta');
			foreach( $video_tags as $tag )
			{
				$property = $tag->getAttribute('property');
				if( $property == 'og:video' )
				{
					$src = $tag->getAttribute('content');
					if( $this->is_video_url($src, $embed, $embed_code, $provider) )
					{	// Search for video type
						foreach( $video_tags as $tag )
						{
							switch( $tag->getAttribute('property') )
							{
								case 'og:title':
									$title = $tag->getAttribute('content');
									break;

								case 'og:video:type':
									$type = $tag->getAttribute('content');
									break;
							}
							if( !empty($title) && !empty($type) ) break;
						}
						break;
					}
					else
					{
						$src = ''; // invalid URL
					}
				}
			}
			*/

			//var_export($videos);die;
		}

		if( empty($images) && empty($videos) ) $this->err('Unable to get media files');

		set_max_execution_time(60); // 60 seconds
		@ini_set('memory_limit', '256M');

		$all_images = $images;
		//echo '<pre>'.htmlspecialchars(var_export($images, true)).'</pre>';

		// STEP 1. Filter out bad images
		//$step1_images = $images;
		if( ! $this->delete_bad_type_images($images) && empty($videos) ) $this->err('Couldn\'t find valid images (type)');
		//$step1_images = array_diff_key( $step1_images, $images );

		// STEP 2. Delete small images
		//$step2_images = $images;
		if( ! $this->delete_small_images($images) && empty($videos) ) $this->err('Couldn\'t find valid images (size)');
		//$step2_images = array_diff_key( $step2_images, $images );

		// STEP 3. Save images
		if( ! $this->save_images($images) && empty($videos) ) $this->err('Couldn\'t save images');

		//echo 'Stats: '.count($all_images).'(total) - '.count($step1_images).'(bad type) - '.count($step2_images).'(too small) = '.(count($all_images)-count($step1_images)-count($step2_images)).' good images';
		//echo '<pre>'.htmlspecialchars(var_export($images, true)).'</pre>';

		$response = array(
				'status' => 'ok',
				'url' => base64_encode($url),
				'images' => $images,
				'videos' => $videos,
			);

		echo json_encode( $response );
		die(0);
	}

	// Read remote or local file
	function get_data( $url, & $info, $params = array() )
	{
		$params = array_merge( array(
				'type'			=> 'GET',
				'timeout'		=> 8,
				'max_size_kb'	=> 2000,
			), $params );

		//$content = fetch_remote_page( $url, $info );
		//if($info['status'] != '200') $content = '';

		$info = array(
				'error' => '',
				'status' => NULL,
				'mimetype' => NULL,
				'charset' => NULL,
			);

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $this->curl_timeout_page );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $this->curl_timeout_page );
		@curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true ); // made silent due to possible errors with safe_mode/open_basedir(?)
		curl_setopt( $ch, CURLOPT_MAXREDIRS, $this->curl_max_redirects_page );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_USERAGENT, $this->user_agent );
		curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
		$content = curl_exec( $ch );

		$info['mimetype'] = curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );
		$info['status'] = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$info['error'] = curl_error( $ch );
		if( ( $errno = curl_errno( $ch ) ) )
		{
			$info['error'] .= ' (#'.$errno.')';
		}
		curl_close( $ch );

		if( ! $this->is_valid_mimetype( $info['mimetype'], 'text/(plain|html)', $info ) )
		{
			$info['error'] = 'Unsupported MIME type [ '.htmlspecialchars($info['mimetype']).' ]';
			return false;
		}

		// Return content
		if( !empty($content) ) return $content;

		$info['error'] = 'Empty string';
		return false;
	}


	function og_video_image( $provider, $url = '' )
	{
		switch( $provider )
		{
			case 'cnn':
				return preg_replace( '~-[a-z]+-[a-z]+\.jpg$~', '-story-top.jpg', $url );
				break;

			case 'youtube':
				return preg_replace( '~(mq)?default.jpg$~', 'hqdefault.jpg', $url );
				break;
		}
		return $url;
	}


	function is_video_url( & $url, & $embed, & $embed_code, & $provider )
	{
		$this->iframe_urls = array(
				'(?P<p1>youtube)\.com/(embed|v)/(?P<v1>[a-z0-9-_]+)',
				'player\.(?P<p2>vimeo)\.com/video/(?P<v2>[a-z0-9-]+)+',
				'(?P<p3>vimeo)\.com/moogaloop\.swf\?clip_id=(?P<v3>[a-z0-9-]+)',
				'([a-z0-9.]+)+(?P<p4>yimg)\.com/([a-z0-9/]+)+player.(swf\?|html#)vid=[a-z0-9-_]+',
				'(?P<p5>cnn)\.com/video/assets/[a-z0-9]+\.swf',
				'msnbc\.(?P<p6>msn)\.com/id/[a-z0-9]+\?launch=(?P<v6>[a-z0-9]+)',
			);

		$embed = $embed_code = '';

		$search = '~^https?://(www\.)?(('.implode( ')|(', $this->iframe_urls ).'))~i';
		if( preg_match( $search, $url, $matches ) )
		{
			$matches = array_filter($matches);
			foreach( $matches as $k=>$v )
			{
				if( strstr($k, 'p') ) $provider = $v;
				if( strstr($k, 'v') ) $videoID = $v;
			}

			$w = 200;
			$h = 110;
			switch( $provider )
			{
				case 'youtube':
					$embed = '<iframe src="http://www.youtube.com/embed/'.$videoID.'" width="200" height="110" frameborder="0"></iframe>';
					$embed_code = 'video_plugin_'.base64_encode('[video:youtube:'.$videoID.']');
					break;

				case 'vimeo':
					$embed = '<iframe src="http://player.vimeo.com/video/'.$videoID.'" width="200" height="110" frameborder="0"></iframe>';
					$embed_code = 'video_plugin_'.base64_encode('[video:vimeo:'.$videoID.']');
					break;

				case 'msn':
					// Delete ADs
					$url = preg_replace( '~(;|&)bts=[a-z0-9]+~i', '\\1bts=0', $url );
					$h = 200;

					//$embed = '<object src="http://www.msnbc.msn.com/id/39789967/?launch='.$videoID.'&autoplay=false" type="application/x-shockwave-flash" width="200" height="110"></object>';
					break;
			}

			$url = preg_replace( '~(;|&)width=[0-9]+~i', '\\1width='.$w, $url );
			$url = preg_replace( '~(;|&)height=[0-9]+~i', '\\1height='.$h, $url );

			// Disable video autoplay
			$url = preg_replace( '~(auto[_-]?(play|start))=(true|on|yes|enable|1)~ie', '$this->no_autoplay( "\\1", "\\3" )', $url );
			return true;
		}
		return false;
	}


	function is_url( $url )
	{
		return @preg_match('~^https?://.{4}~', $url);
	}


	function no_autoplay( $arg, $value )
	{
		switch( $value )
		{
			case 'true': $v = 'false'; break;
			case 'on': $v = 'off'; break;
			case 'yes': $v = 'no'; break;
			case 'enable': $v = 'disable'; break;
			case '1': $v = '0'; break;
			default: $v = '';
		}
		return $arg.'='.$v;
	}


	function delete_bad_type_images( & $images )
	{
        if( count($images) <= 0 ) return false;

        $arr = array();
        foreach( $images as $k=>$img )
        {
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $img['url'] );
			curl_setopt( $ch, CURLOPT_HEADER, 0 );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 ); // return the image value
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $this->curl_timeout_img );
			curl_setopt( $ch, CURLOPT_TIMEOUT, $this->curl_timeout_img );
			@curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true ); // made silent due to possible errors with safe_mode/open_basedir(?)
			curl_setopt( $ch, CURLOPT_MAXREDIRS, $this->curl_max_redirects_img );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_USERAGENT, $this->user_agent );
			curl_setopt( $ch, CURLOPT_AUTOREFERER, true );

			$images[$k]['ch'] = $ch;
        }

        $mh = curl_multi_init();
        foreach( $images as $k=>$img ) curl_multi_add_handle($mh,$img['ch']);

        $running = 0;
        do { curl_multi_exec($mh,$running); } while($running > 0);

        // Get the result and save it in the result ARRAY
		foreach( $images as $k=>$img )
        {
			if( ! $this->is_valid_mimetype( curl_getinfo( $img['ch'], CURLINFO_CONTENT_TYPE ), 'image/(jpe?g|png)' ) )
			{	// Not JPEG or PNG
				unset($images[$k]);
				continue;
			}
			$images[$k]['data'] = curl_multi_getcontent($img['ch']);
        }

        // Close all connections
        foreach( $images as $k=>$img )
        {
			curl_multi_remove_handle($mh,$img['ch']);
			unset($images[$k]['ch']);
        }
        curl_multi_close($mh);

		if( empty($images) ) return false;
        return true;
	}


	function delete_small_images( & $images )
	{
		foreach( $images as $k=>$img )
		{
			// Delete invalid image
			if( !$imh = @imagecreatefromstring($img['data']) )
			{
				unset($images[$k]);
				continue;
			}

			$w = imagesx($imh);
			$h = imagesy($imh);

			if( $w < $this->min_image_width || $h < $this->min_image_height )
			{	// Delete small image
				unset($images[$k]);
				continue;
			}
			if( $w < $this->upscale_image_width )
			{	// We need to upscale the image
				$images[$k]['upscale'] = $this->upscale_image_width;
			}

			unset($images[$k]['data']); // we don't need it any more
			$images[$k]['imh'] = $imh;
		}

		if( empty($images) ) return false;
		return true;
	}


	function save_images( & $images )
	{
		global $servertimenow;

		load_funcs('files/model/_image.funcs.php');

		if( ! $this->check_cahce_dir( false ) ) return;

		foreach( $images as $k=>$img )
		{
			foreach( $this->thumbnail_sizes as $size_key=>$size )
			{	// Loop through sizes
				$path = $this->images_path.$k.'-'.$size_key.'.jpg';

				if( $this->cache_images && file_exists($path) )
				{	// Image already cached
					if( $servertimenow < (@filemtime($path) + $this->cache_images_hours * 3600) )
					{	// Cache time is OK, let's reuse the image
						continue;
					}
				}

				if( isset($img['upscale']) )
				{
					$src_width = imagesx( $img['imh'] ) ;
					$src_height = imagesy( $img['imh'] );
					$src_ratio = $src_width / $src_height;
					$src_x = $src_y = 0;

					$dest_width = $img['upscale'];
					$dest_height = (int)round( $img['upscale'] / $src_ratio );

					$size[1] = $dest_width;
					$size[2] = $dest_height;

					// Let's upscale the image
					$dest_imh = imagecreatetruecolor( $dest_width, $dest_height );
					imagealphablending($dest_imh, true);
					imagefill($dest_imh, 0, 0, imagecolortransparent($dest_imh, imagecolorallocatealpha($dest_imh, 0, 0, 0, 127)));
					imagesavealpha($dest_imh, true);

					if( ! imagecopyresampled( $dest_imh, $img['imh'], 0, 0, $src_x, $src_y, $dest_width, $dest_height, $src_width, $src_height ) )
					{
						unset($images[$k]);
						continue;
					}

					$img['imh'] = $dest_imh;
				}

				list( $err, $imh ) = generate_thumb( $img['imh'], $size[0], $size[1], $size[2], 0, true );
				unset($images[$k]['imh']); // we don't need it any more

				if( !empty( $err ) )
				{
					unset($images[$k]);
					continue;
				}

				save_image( $imh, $path, 'image/jpeg', $size[3] );

				if( ! file_exists($path) )
				{
					unset($images[$k]);
					continue;
				}
			}
			//$images[$k]['path'] = $path;
			unset($images[$k]['imh']);
		}

		if( empty($images) ) return false;
		return true;
	}


	function is_valid_mimetype( $mimetype = '', $type, &$info = array() )
	{
		if( preg_match( '~^('.$type.')((;|\s*))?(.*charset =(.+))?$~ix', $mimetype, $match ) )
		{
			//var_export($match);
			$info['mimetype'] = strtolower( trim($match[1]) );

			if( !empty($match[6]) )
			{
				$info['charset'] = strtolower( trim( str_replace('=', '', $match[6]) ) );
			}

			return true;
		}
		return false;
	}


	function get_mimetype( $filename )
	{
		$mimetype = false;
		if( function_exists('finfo_fopen') )
		{
			$f = finfo_open( FILEINFO_MIME_TYPE );
			$mimetype = @finfo_file( $f, $filename );
			finfo_close($f);
		}
		elseif( function_exists('mime_content_type') )
		{
			$mimetype = @mime_content_type($filename);
		}
		return $mimetype;
	}


	function get_meta_tag( $value = '', $name = 'property' )
	{
		if( ! isset($this->meta[$name.'='.$value]) )
		{
			$content = '';

			if( ! $this->XPATH )
			{
				$this->XPATH = new DOMXpath( $this->DOM );
			}

			if( $content = $this->XPATH->query('//meta[@'.$name.'="'.$value.'"]/@content')->item(0) )
			{
				$content = $content->value;
			}
			else
			{
				$content = '';
			}

			$this->meta[$name.'='.$value] = $content;
		}
		return $this->meta[$name.'='.$value];
	}


	function enc( $content, $convert_charset = true )
	{
		global $io_charset;

		if( $convert_charset && $this->fixed_charset )
		{	// Non-unicode charset, let's convert title to UTF-8
			$content = convert_charset( $content, $io_charset, $this->fixed_charset );
		}
		$content = format_to_output($content, 'htmlattr');

		return $content;
	}


	function check_received_crumb( $crumb_name, $params, $die = true )
	{
		global $Session, $servertimenow, $crumb_expires, $debug;

		if( empty($params['crumb_'.$crumb_name]) )
		{ // We did not receive a crumb!
			if( $die )
			{
				bad_request_die( 'Missing crumb ['.$crumb_name.'] -- It looks like this request is not legit.' );
			}
			return false;
		}

		$crumb_received = $params['crumb_'.$crumb_name];

		// Retrieve latest saved crumb:
		$crumb_recalled = $Session->get( 'crumb_latest_'.$crumb_name, '-0' );
		list( $crumb_value, $crumb_time ) = explode( '-', $crumb_recalled );
		if( $crumb_received == $crumb_value && $servertimenow - $crumb_time <= $crumb_expires )
		{	// Crumb is valid
			// echo '<p>-<p>-<p>A';
			return true;
		}

		$crumb_valid_latest = $crumb_value;

		// Retrieve previous saved crumb:
		$crumb_recalled = $Session->get( 'crumb_prev_'.$crumb_name, '-0' );
		list( $crumb_value, $crumb_time ) = explode( '-', $crumb_recalled );
		if( $crumb_received == $crumb_value && $servertimenow - $crumb_time <= $crumb_expires )
		{	// Crumb is valid
			// echo '<p>-<p>-<p>B';
			return true;
		}

		if( ! $die )
		{
			return false;
		}

		// ERROR MESSAGE, with form/button to bypass and enough warning hopefully.
		// TODO: dh> please review carefully!
		echo '<div style="background-color: #fdd; padding: 1ex; margin-bottom: 1ex;">';
		echo '<h3 style="color:#f00;">'.T_('Incorrect crumb received!').' ['.$crumb_name.']</h3>';
		echo '<p>'.T_('Your request was stopped for security reasons.').'</p>';
		echo '<p>'.sprintf( T_('Have you waited more than %d minutes before submitting your request?'), floor($crumb_expires/60) ).'</p>';
		echo '<p>'.T_('Please go back to the previous page and refresh it before submitting the form again.').'</p>';
		echo '</div>';

		die();
	}


	function update_toot_settings()
	{
		$ItemCache = & get_ItemCache();
		$ItemCache->load_all();

		$out['video'] = $out['images'] = array();
		while( $edited_Item = & $ItemCache->get_next() )
		{
			$edited_Item->load_ItemSettings();

			$link = '<a href="admin.php?ctrl=items&amp;action=edit&amp;p='.$edited_Item->ID.'">'.format_to_output($edited_Item->title, 'htmlattr').'</a>';

			if( $edited_Item->get_setting('toot_media_url') )
			{
				echo 'OK';
			}
			elseif( $info = $this->get_toot_info($edited_Item) )
			{
				$out[$info[0]][] = $info[1].'<br>'.$link;

				// Add item settigns
				$edited_Item->set_setting( 'toot_media_url', trim($info[1]) );
				$edited_Item->set_setting( 'toot_media_type', trim($info[0]) );
				$edited_Item->dbupdate();
			}
			else
			{
				echo '<br>'.$link;
			}
		}
	//	echo '<br>'.implode( '<br><br>', $out['video'] );
	//	echo '<hr />';
	//	echo implode( '<br><br>', $out['image'] );
	}


	function get_toot_info( & $Item )
	{
		$LinkOnwer = new LinkItem( $Item );

		if( preg_match( '~(\[video:([a-z]+):(.+?)])~ix', $Item->content, $match1 ) )
		{	// Video toot
			$type = 'video';
			if( $match1[2] == 'flowplayer' )
			{	// Local video file
				if( $FileList = $LinkOnwer->get_attachment_FileList(1) )
				{
					while( $File = & $FileList->get_next() )
					{    // Loop through attached files
						if( $File->exists() )
						{    // Got the file
							$url = $File->_FileRoot->ads_url.no_leading_slash($File->_rdfp_rel_path);
							break;
						}
					}
				}
				elseif( preg_match( '~(http://www\.dotoot\.com/dotootblog/media/users.*?)">~', $Item->content, $match3 ) )
				{
					$url = $match3[1];
				}
			}
			elseif( $match1[3] == 'dummy' )
			{	// Parse content fro video URL
				$iframe_urls = array(
						'(?P<p1>youtube)\.com/(embed|v)/(?P<v1>[a-z0-9-_]+)',
						'player\.(?P<p2>vimeo)\.com/video/(?P<v2>[a-z0-9-]+)+',
						'(?P<p3>vimeo)\.com/moogaloop\.swf\?clip_id=(?P<v3>[a-z0-9-]+)',
						'([a-z0-9.]+)+(?P<p4>yimg)\.com/([a-z0-9/]+)+player.(swf\?|html#)vid=[a-z0-9-_]+',
						'(?P<p5>cnn)\.com/video/assets/[a-z0-9]+\.swf',
						'msnbc\.(?P<p6>msn)\.com/id/[a-z0-9]+\?launch=(?P<v6>[a-z0-9]+)',
					);

				$search = '~(https?://(www\.)?(('.implode( ')|(', $iframe_urls ).')))~i';
				if( preg_match( $search, $Item->content, $match2 ) )
				{
					$url = $match2[1];
				}
			}
			else
			{	// Generate video URL
				$url = preg_replace( '#\[video:youtube:(.+?)]#', 'http://www.youtube.com/v/\\1', $match1[1] );
				$url = preg_replace( '#\[video:dailymotion:(.+?)]#', 'http://www.dailymotion.com/swf/\\1', $url );
				$url = preg_replace( '#\[video:google:(.+?)]#', 'http://video.google.com/googleplayer.swf?docId=\\1&hl=en', $url );
				$url = preg_replace( '#\[video:livevideo:(.+?)]#', 'http://www.livevideo.com/flvplayer/embed/\\1', $url );
				$url = preg_replace( '#\[video:ifilm:(.+?)]#', 'http://www.ifilm.com/efp?flvbaseclip=\\1', $url );
				$url = preg_replace( '#\[video:vimeo:(.+?)]#', 'http://player.vimeo.com/video/\\1', $url );
			}
		}
		else
		{
			$type = 'image';
			if( $FileList = $LinkOnwer->get_attachment_FileList(5) )
			{
				while( $File = & $FileList->get_next() )
				{    // Loop through attached files
					if( $File->exists() && $File->is_image() )
					{    // Got the file
						$url = $File->_FileRoot->ads_url.no_leading_slash($File->_rdfp_rel_path);
						break;
					}
				}
			}
		}

		if( !empty($type) && !empty($url) && $this->is_url($url) )
		{
			return array($type, $url);
		}
		return false;
	}


	function check_cahce_dir( $msg = true )
	{
		mkdir_r( $this->images_path );

		if( !is_writable($this->images_path) )
		{
			if( $msg )
			{
				$this->msg( sprintf( $this->T_('You must create the following directory with write permissions (777):%s'), '<br />'.$this->images_path ), 'error' );
			}
			return false;
		}
		return true;
	}
}

?>