// Init the poll
jQuery(function() { initDemocracy() });

function initDemocracy() {
    view_results = document.getElementById('view-results');
	
	if (view_results) {
      view_results.href = "javascript: SeeResults();";
    }
    
    addAnswer = document.getElementById('jalAddAnswer');
    
    if (addAnswer) {
    	addAnswer.onclick = function () {
			this.style.display = "none";
			document.getElementById('jalAddAnswerRadio').style.display = "inline";
			document.getElementById('jalAddAnswerRadio').checked = true;
			document.getElementById('jalAddAnswerInput').style.display = "inline";
		
			all_inputs = document.getElementsByTagName('input');
		
			for (var i = 0; i < all_inputs.length; i++) {
				if (all_inputs[i].getAttribute('name') == "poll_aid" && all_inputs[i].getAttribute('id') != "jalAddAnswerRadio") {
					all_inputs[i].onclick = function () {
						document.getElementById('jalAddAnswerRadio').style.display = "none";
						document.getElementById('jalAddAnswerInput').style.display = "none";
						document.getElementById('jalAddAnswerInput').value = "";
						document.getElementById('jalAddAnswer').style.display = "inline";
					}
				}
			}
			return false;
		}
    }
}


function SendVote (the_vote) {
    poll_id = document.getElementById("poll_id").value;
    params = document.getElementById("params").value;
    cookie = jal_getCookie('b2demVoted_'+poll_id);
    if (cookie) {
        alert("You have already voted on this poll!");
        return;
    }
	else
	{
    	new_vote = document.getElementById('jalAddAnswerInput');
		
		if (new_vote && new_vote.value != "") {
			param = 'demSend=true&poll_vote='+encodeURIComponent(new_vote.value)+'&new_vote=true&poll_id='+poll_id+'&params='+params;
		} else {
			param = 'demSend=true&poll_aid='+the_vote+'&poll_id='+poll_id+'&params='+params;
		}
		
		jQuery.ajax({
			type: "POST",
			url: DemocracyURI.replace('&amp;','&'),
			data: param,
			success: function(results) {
				jQuery("div#democracy").replaceWith(results);
			}
		});
  	}
}


function ReadVote (url) {
  var the_vote;
  the_poll = document.getElementById("democracyForm");
  for (x = 0; x < the_poll.poll_aid.length; x++) {
	 if (the_poll.poll_aid[x].checked) {
	   the_vote = the_poll.poll_aid[x].value;
	 }
   }
  if (!the_vote) {
    alert ("You must vote first!");
  } else {
	// Display "loading" image
	document.getElementById('pollloading').innerHTML = '<img src="' + url + 'democracy_plugin/loading.gif" />';
    SendVote(the_vote);
  }
  return false;
}


function SeeResults() {
    poll_id = document.getElementById("poll_id").value;
    params = document.getElementById("params").value;
	param = '&demGet=true&poll_id='+poll_id+'&params='+params+'&rand='+Math.floor(Math.random() * 1000000);
	
	jQuery.ajax({
		type: "POST",
		url: DemocracyURI.replace('&amp;','&'),
		data: param,
		success: function(results) {
			jQuery("div#democracy").replaceWith(results);
		}
	});
}


function jal_getCookie(name) {
  var dc = document.cookie;
  var prefix = name + "=";
  var begin = dc.indexOf("; " + prefix);
  if (begin == -1) {
    begin = dc.indexOf(prefix);
    if (begin != 0) return null;
  } else
    begin += 2;
  var end = document.cookie.indexOf(";", begin);
  if (end == -1)
    end = dc.length;
  return unescape(dc.substring(begin + prefix.length, end));
}