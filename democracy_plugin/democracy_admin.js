 function addQuestion() {
	ol = document.getElementById('inputList');
	li = document.createElement('li');
	
	input = document.createElement('input');
	input.setAttribute('name', 'answer[]');
	input.setAttribute('type', 'text');
	input.setAttribute('size', '60');
	
	li.appendChild(input);
	ol.appendChild(li);

}
function eatQuestion() {
	ol = document.getElementById('inputList');
	
	if (ol.getElementsByTagName('li').length < 3) 
		 alert("You must have at least 2 answers");
	else 
		ol.removeChild(ol.lastChild);
}

function jal_validate() {
    
	ol = document.getElementById('inputList');
	inputs = ol.getElementsByTagName('input');
	
	answers = 0;
	
	for (i=0; i < inputs.length; i++)
		if (inputs[i].value)
			answers++;

	if (answers < 2) {
		alert("You don't have at least two answers!");
		return false;
	}
   
	if (document.getElementById('question').value == "") {
        alert ("You don't have a question!");
        return false;
    }
    
}