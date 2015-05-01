function backup_log(div, msg) {
	//get background color from appropriate jquery ui class
	var bcolor = div.parent().parent().css('backgroundColor');
	
	//build span
	var span = $('<span></span>')
			.html(msg)
			.css('backgroundColor', '#fff2a8');
	
	//append to div
	div.append(span);

	//scroll down and show new span; and remove highlighting
	div.animate({scrollTop: div.prop("scrollHeight")}, 
		500,
		function(){
			span.animate({backgroundColor: bcolor},
			1500,
			function() {
				span.css('background-color', '')
			}
			)
		});

}
$('#merlin').on('show.bs.modal', function (e) {

	$('#wizard').smartWizard();
});
$('input[name="wizremote"]').change(function(){
	if($(this).val() == 'yes'){
		$(".wizservboth").removeClass('hidden');
		switch($('input[name="wizremtype"]').val()){
			case 'ftp':
				$(".wizservssh").addClass('hidden');
				$(".wizservftp").removeClass('hidden');
			break;
			case 'ssh':
				$(".wizservftp").addClass('hidden');
				$(".wizservssh").removeClass('hidden');
			break;
		}
	}
});
$('input[name="wiznotif"]').on('change',function(){
	if($(this).val() == 'no'){
		$('#wizemail').attr('disabled',true);
	}else{
		$('#wizemail').attr('disabled',false);
	}
});

$('input[name="wizremtype"]').on('change',function(){
	$(".wizservboth").removeClass('hidden');
	switch($(this).val()){
		case 'ftp':
			$(".wizservssh").addClass('hidden');
			$(".wizservftp").removeClass('hidden');
		break;
		case 'ssh':
			$(".wizservftp").addClass('hidden');
			$(".wizservssh").removeClass('hidden');
		break;
	}
});
$('input[name="wizfreq"]').change(function(){
	var dailyhtml = '<div class="input-group"><input type="number" min="0" max="23" class="form-control" id="wizathr" name="wizat" value="23"><span class="input-group-addon" id="wizat-addon">:00</span></div>';
	var weeklyhtmlon = '<select class="form-control" id="wizatday" name="wizat[]"><option value="0">'+_("Sunday")+'</option><option value="1">'+_("Monday")+'</option><option value="2">'+_("Tuesday")+'</option><option value="3">'+_("Wednesday")+'</option><option value="4">'+_("Thursday")+'</option><option value="5">'+_("Friday")+'</option><option value="6">'+_("Saturday")+'</option></select>';
	var weeklyhtml = '<div class="input-group"><input type="number" min="0" max="23" class="form-control" id="wizathr" name="wizat[]" value="23"><span class="input-group-addon" id="wizat-addon">:00</span></div>';
	var monthlyhtml = '<select class="form-control" id="wizatmonthday" name="wizat[]">';
	for (var i = 1; i <= 30; i++) {
		monthlyhtml += '<option value="'+i+'">'+i+'</option>';
	};
	monthlyhtml += '</select>';
	switch($(this).val()){
		case 'weekly':
			$('#atinput').html(weeklyhtml);
			$('#atlabel').html('<b>'+_("AT")+'</b>')
			$('#onlabel').html('<b>'+_("ON")+'</b>');
			$('#oninput').html(weeklyhtmlon);
		break;
		case 'monthly':
			$('#atinput').html(monthlyhtml);
			$('#onlabel').html('<b>'+_("Day each month")+'</b>');
			$('#oninput').html('');
			$('#atlabel').html('<b>'+_("On the")+'</b>')
		break;
		default:
			$('#atinput').html(dailyhtml);
			$('#atlabel').html('<b>'+_("AT")+'</b>')
			$('#onlabel').html('');
			$('#oninput').html('');
		break;
	}
});
function linkFormatter(foo,value){

    var html = '<a href="?display=backup&action=edit&id='+value.id+'"><i class="fa fa-pencil"></i></a>';
    if(!value.immortal){
    	html += '&nbsp;<a href="?display=backup&action=delete&id='+value.id+'" class="delAction"><i class="fa fa-trash"></i></a>';
    }
    	html += '&nbsp;<a href="?display=backup&action=run&id='+value.id+'" target="_blank"><i class="fa fa-play-circle"></i></a>';    
    return html;
}