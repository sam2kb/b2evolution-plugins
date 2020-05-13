function sndReq(id_num, hturl)
{
	var $ = jQuery.noConflict();
	$('#unit_long'+id_num).html('<div style="height: 20px;"><em>Loading ...</em></div>');
	$.get(hturl, function(data) {
		if(data.indexOf('|') != -1) {
            update = data.split('|');
			$('#' + update[0]).html(update[1])
            if (update[2]) disp_notice(update[3]);
            //new Effect.Highlight(update[0], {startcolor: '#ff9900', endcolor: '#EEEEEE'});
		}
	})
}

function disp_notice(text)
{
	var $ = jQuery.noConflict();
	if ($('#ratingResults').length == 0) {
		$('html').append('<div id="ratingResults" style="display:none"></div>')
	}
	$('#ratingResults').html(text)
    el = $('#ratingResults');
    var theTop = 30;
    if (window.innerHeight)	{
  		  pos = window.pageYOffset
  	} else if (document.documentElement && document.documentElement.scrollTop) {
  		pos = document.documentElement.scrollTop
  	} else if (document.body) {
  		  pos = document.body.scrollTop
  	}
  	if (pos < theTop) pos = theTop;
  	else pos += 30;
	el.css('top', pos +'px');
	el.css('opacity', 0.01)
	el.show()
	el.fadeTo('slow', 0.8)
  	setTimeout('el.fadeOut("slow")', 4000);
}