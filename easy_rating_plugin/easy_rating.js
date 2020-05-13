function sndReq( selector, hturl )
{
	target_ID = jQuery('input[name=target_ID]:checked').val();
	
	if( target_ID )
	{
		hturl = hturl + '&target_ID=' + target_ID;
	}
	
	if( selector.match("EasyRatingForm") )
	{
		var sel = jQuery(selector + ' .cover_item_submit div');
		
		if( !target_ID )
		{	// Nothing selected
			function show_submit()
			{
				sel.html('<input type="submit" value="Проголосовать" />');
				sel.fadeIn("fast");
			}
			
			sel.html('<p class="voted">Ничего<br />не выбрано!</p>');
			setTimeout(show_submit, 2000);
			return;
		}
		else
		{	// add loader
			sel.html('<img src="images/ajaxloader.gif" alt="" />');
		}
	}	
	
	jQuery.get(hturl, function(data){
		//alert(data);
		if(data.indexOf('|') != -1)
		{
            update = data.split('|');
            if( update[1].length > 0 )
			{
				if( target_ID )
				{	// Covers rating
					disp_notice( selector + ' .cover_item_submit div', update[1] );
				}
				else
				{
					disp_notice( selector, update[1] );
				}
			}
		}
		
		if( !target_ID )
		{	// Update results
			hturl = hturl.replace( 'method=vote', 'method=get_rating' );
			jQuery.get(hturl, function(data){
				jQuery(selector).parent().find(".item-rating").text(data);		// item rating
				jQuery(selector).parent().find(".rating").text(data);			// wil rating
				jQuery(selector).parent().find(".comment-votes").text(data);	// comment rating
			});
		}
	})
}

function disp_notice(selector, text)
{
	
	if( jQuery(selector).length == 0 )
	{
		jQuery('.easy_ratingResults').html(text)
		el = jQuery('.easy_ratingResults');
		
		var theTop = 30;
		if (window.innerHeight) {
			  pos = window.pageYOffset
		} else if (document.documentElement && document.documentElement.scrollTop) {
			pos = document.documentElement.scrollTop
		} else if (document.body) {
			  pos = document.body.scrollTop
		}
		if (pos < theTop) pos = theTop;
		else pos += 30;
		
		el.css('top', pos +'px');
		el.css('right', '30px');
		el.css('opacity', 0.01);
		el.show();
		el.fadeTo('slow', 0.9);
		setTimeout('el.fadeOut("slow")', 2000);
	}
	else
	{
		jQuery(selector).html(text);
		el = jQuery(selector);
		
		el.css('opacity', 0.01);
		el.show();
		el.fadeTo('slow', 1);
		//setTimeout('el.fadeOut("slow")', 2000);
	}
}

function ez_getParam(url)
{
	var map = {};
	var parts = url.replace(/[?&#]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
		map[key] = value;
	});
	return map;
}