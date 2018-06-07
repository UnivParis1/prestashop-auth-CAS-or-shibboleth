$(document).ready(function() {
	//Initialise the second table specifying a dragClass and an onDrop function that will display an alert
	if ($().jquery > "1.3") {
		$("#wrapConfigTab").tabs({cache:false});
	} else {
		$("#configTab").tabs({cache:false});
	}
});
