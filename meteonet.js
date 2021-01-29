// Meteonet.js 
/*
	Meteonet - the backend that manages data from weather stations
	used by meteogargano.org
	
	Copyright (C) 2013-2021 Filippo Gurgoglione (ziofil@gmail.com)
	All rights reserved.
	
	Permission is hereby granted, free of charge, to any person obtaining a 
	copy of this software and associated documentation files (the "Software"), 
	to deal in the Software without restriction, including without limitation 
	the rights to use, copy, modify, merge, publish, distribute, sublicense, 
	and/or sell copies of the Software, and to permit persons to whom the 
	Software is furnished to do so, subject to the following conditions:
	
	The above copyright notice and this permission notice shall be included 
	in all copies or substantial portions of the Software.
	
	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS 
	OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, 
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE 
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER 
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, 
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE 
	SOFTWARE.
*/
// Last edit 03/12/2012
// Copyright (c) Filippo Gurgoglione ziofil@gmail.com 2012

var meteonet_net_webcams;
var meteonet_net_stations;
var meteonet_net_webcams_n;
var meteonet_net_time;
var meteonet_last_updated;
var meteonet_last_update;
var meteonet_meteo_last;
var meteonet_webcams_last;
var meteonet_webcams_old_last;

var meteonet_url =  "http://rete.meteogargano.org/meteonet";
var meteonet_url_execute = meteonet_url + "/execute.php?&jsoncallback=?&action=json&json_request=";
var meteonet_url_page_href = "http://www.meteogargano.org/";

var meteonet_functions_on_update = new Array();
var meteonet_functions_on_interval = new Array();
var count = 0;
var meteonet_time = 0;
var meteonet_note = true;
var meteonet_current_webcam;
var meteonet_current_station;
var meteonet_info = new Array();
var meteonet_data_month;

var h = $(window).height()>600+100 ? $(window).height()-100 : 600;
var w = $(window).width()>870+50 ? $(window).width()-50 : 870;
if (h>w) h=w;

var h2;

var timer = $.timer(function() {
		meteonet_time++;
	        for (var i=0;i<meteonet_functions_on_interval.length;i++)
			meteonet_functions_on_interval[i]();
		if (meteonet_note && count>=15) {
			meteonet_note = false;
			meteonet_close_note();		
		}

                if (++count>60)
		{
			count = 0;
			meteonet_load_last();
		}
        });
var dialog_opened = false;

$(document).ready(function() {
	if (window.location.pathname=="/") {
		$(".page").append("<div id='meteonet_main'></div>");
		meteonet_load_main();
	}
//	$('a[href="'+meteonet_url_page_href+'"]').css("font-weight","bold");
//	$('a[href="'+meteonet_url_page_href+'"]').css("color","#FF0000");
//	$('a[href="'+meteonet_url_page_href+'"]').click(function() {
		if (!dialog_opened) {
			//$("body").append("<div id='meteonet_main'></div>");
			meteonet_load_main();
		} else dialog_opened = true;
		return false;
//	});

});

function meteonet_get_date(timestamp,time)
{
	var d = new Date(timestamp*1000);
	var h,m,s,g,mo,y;
	if (d.getHours()<10) h = "0" + d.getHours(); else h = d.getHours();
	if (d.getMinutes()<10) m = "0" + d.getMinutes(); else m = d.getMinutes();
	if (d.getSeconds()<10) s = "0" + d.getSeconds(); else s = d.getSeconds();
	mo =  d.getMonth()+1;
	if (d.getDate()<10) g = "0" + d.getDate(); else g = d.getDate();
	y = d.getFullYear();
	if (time)
		return h + ":" + m + ":" + s + " del " +  g + "/" + mo + "/" + y;
	else
		return g + "/" + mo + "/" + y;
}


function meteonet_get_time(timestamp,seconds)
{
	var d = new Date(timestamp*1000);
	var h,m,s,g,mo,y;
	if (d.getHours()<10) h = "0" + d.getHours(); else h = d.getHours();
	if (d.getMinutes()<10) m = "0" + d.getMinutes(); else m = d.getMinutes();
	if (d.getSeconds()<10) s = "0" + d.getSeconds(); else s = d.getSeconds();
	if (seconds)
		return h + ":" + m + ":" + s;
	else
		return h + ":" + m;
}

function meteonet_load_net() {
      $.getJSON(
         meteonet_url_execute + 'load_net',
         function(data){
		meteonet_last_update = data.lastupdate;
		meteonet_net_webcams = data.webcams;
		meteonet_net_stations = data.stations;
		meteonet_net_time = parseInt(data.time);
		meteonet_net_webcams_n = 0;
		for (k in meteonet_net_webcams)
			meteonet_net_webcams_n++;
		meteonet_load_main(1);
         }
      );

}

function meteonet_load_last(load_main) {
      if (arguments.length === 0)
      $.getJSON(
         meteonet_url_execute + 'load_last',
         function(data){
		meteonet_meteo_last = data['stations'];
		meteonet_webcams_old_last = meteonet_webcams_last;
		meteonet_webcams_last = data['webcams'];
	        for (var i=0;i<meteonet_functions_on_update.length;i++)
			meteonet_functions_on_update[i].call();
		meteonet_last_updated = new Date();
         }
      ); else
      $.getJSON(
         meteonet_url_execute + 'load_last',
         function(data){
		meteonet_meteo_last = data['stations'];
		meteonet_webcams_old_last = meteonet_webcams_last;
		meteonet_webcams_last = data['webcams'];
	        for (var i=0;i<meteonet_functions_on_update.length;i++)
			meteonet_functions_on_update[i].call();
		meteonet_last_updated = new Date();
		meteonet_load_main(2);
         }
      );
}

function meteonet_register_function(funct,type)
{
	var array,found;
	if (type=='update')
		array = meteonet_functions_on_update;
	 else if (type=='interval')
		array = meteonet_functions_on_interval;
	
	found = false;
	for (var i=0;i<meteonet_functions_on_interval.length;i++) {	
		if (array[i] == funct) {
			found = true;
			break;
		}
	}

	if (!found)
		array.push(funct);

}

function meteonet_unregister_function(funct,type)
{
	var array;
	if (type=='update')
		array = meteonet_functions_on_update;
	 else if (type=='interval')
		array = meteonet_functions_on_interval;
	
	for (var i=0;i<array.length;i++)
		if (array[i] == funct)
			array.splice(i,1);
}

function unregister_all_functions()
{
	meteonet_functions_on_update = new Array(); 
	meteonet_functions_on_interval = new Array();
}

function meteonet_update_map() {
	var i = Math.ceil(count/4) % 4;
	var key, u, label, value;
	switch (i) {
		case 0: label='Temperatura (°C)'; key = 'temp'; break;
		case 1: label='Umidit&agrave; relativa'; key = 'rh'; break;
		case 2: label='Pressione (mbar)'; key = 'press'; break;
		case 3: label='Velocit&agrave; vento'; key = 'wind_speed'; break;
	}

	for(var k in meteonet_net_stations){
		my = $("#meteonet-preview-map-"+ k);
		array = meteonet_meteo_last[k]['data'];
		if (array!=false)
			value = array[key];
		  else
			value = 'offline';

		my.html("<div class='titl' style='font-weight:bold;margin:0px 0px 0px 0px;padding:0px 0px 0px 0px;border-bottom:1px solid;'>"+k + "</div><div style='margin:0px 0px 0px 0px;padding:0px 0px 0px 0px;'>" + value + "</div>");	
	}

	$("#meteonet-preview-map-header").html("Mappa - " + label);
	//$('#meteonet-info-date').html("Orario del server:" + new Date((meteonet_net_time+meteonet_time)*1000));
	//$('#meteonet-info-date').append("<br>Ultimo aggiornamento: " + meteonet_meteo_last_updated.getHours() + ":" + meteonet_meteo_last_updated.getMinutes() + ":" + meteonet_meteo_last_updated.getSeconds());

}



function meteonet_rotate_webcam() {
	var i = Math.ceil(count/5) % meteonet_net_webcams_n;
	var j=0;
	var my,str;
	for (k in meteonet_net_webcams)	{
		if (j==i && (Date.now()/1000-meteonet_webcams_last[k])<5000) {
			str = "Scatto da " +meteonet_net_webcams[k]['location'] + " alle " + meteonet_get_date(meteonet_webcams_last[k],true);
			my = $("#meteonet-preview-webcam-shot-"+ k);
			if (str != $("#meteonet-preview-webcam-descr").html()) {
				$("#meteonet-preview-webcam-shot > img").css("display","none");
				my.fadeIn("slow");
				$("#meteonet-preview-webcam-descr").html(str);
			}
		}
		j++;
	}
}


function meteonet_update_webcam() {
	if (meteonet_webcams_last == undefined) return false;

	var last_time;
	for(var k in meteonet_net_webcams){
		my = $("#meteonet-preview-webcam-shot-"+ k);
		last_time = meteonet_webcams_last[k];
		if (last_time != null) {
			if (meteonet_webcams_old_last == undefined || meteonet_webcams_old_last[k]!=last_time || my.attr('src')=='' || my.attr('src')==undefined) 
				my.attr('src',meteonet_url + '/execute.php?action=image&webcam_id='+k+'&timestamp='+last_time+'&resized');
			 //else alert('gia caricato');
		}
		
	}
}

// seleziona la tab relativa alla stazione elid / evidenzia la stazione nella mappa
function meteonet_select_map(elid) {
	//$("#meteonet-preview-map > div").css("background-image","none");
	//$("#meteonet-preview-map-"+elid).css("background-image","url('"+meteonet_url+"/images/triang.png')");
	$("#meteonet-preview-map > .titl").css("color","blue");
	$("#meteonet-preview-map-"+elid+ " > .titl").css("color","blue");
	$("#meteonet-preview-st-header").html("Dati stazione "+meteonet_net_stations[elid]['location']+" (cliccare sulla mappa per il dettaglio)");
	$("#meteonet-preview-st").html("");
	var my = $("<table  />").appendTo("#meteonet-preview-st");

	var data = meteonet_meteo_last[elid]['data'];
	var stat = meteonet_meteo_last[elid]['statistics'];

	if (data==false || stat==false)
		return false;

	my.append("<thead ><th>&nbsp;</th><th>Corrente</th><th>Max giorn.</th><th>Min giorn.</th><th>Media giorn.</th></thead>");

	my.append("<tr><td>Temperatura °C</td><td>"+data['temp']+"</td><td>"+stat['temp_max']+"</td><td>"+stat['temp_min']+"</td><td>"+stat['temp']+"</td></tr>");	
	my.append("<tr><td>Umidit&agrave; %</td><td>"+data['rh']+"</td><td>"+stat['rh_max']+"</td><td>"+stat['rh_min']+"</td><td>"+stat['rh']+"</td></tr>");
	my.append("<tr><td>Vento km/h</td><td>"+data['wind_speed']+"</td><td>"+stat['wind_gust']+"</td><td>&nbsp;</td></tr>");

	return true;
}


function meteonet_load_maintab_main() {
/* div: preview; preview-webcam; preview-meteo; detail */
	var main = $('#meteonet-maintab-main');
	main.html("");

	main.append("<div id='meteonet-preview' style='float:left;width:410px;padding:0.5em;clear:none;margin:0px 5px 0px 0px;'/>");
	main.append("<div id='meteonet-preview-webcam' style='overflow: hidden;padding:0.5em;' />");
	main.append("<div id='meteonet-detail' />");

	$('<h3 class="ui-widget-header" id="meteonet-preview-map-header" style="font-size:80%; text-align: center; margin:0px;">Mappa</h3>').appendTo("#meteonet-preview");
	$("<div id='meteonet-preview-map' class='ui-widget-content' style='height:250px;width:400px;' />").appendTo("#meteonet-preview");
	$("#meteonet-preview-map").css('background-image','url("'+meteonet_url+'/images/map.png")');
	var pos_x; var pos_y; var my; var i=0;
	for(var k in meteonet_net_stations){
		//console.log("Add " + k + " to map");
		pos_x = meteonet_net_stations[k].pos_x; pos_y = meteonet_net_stations[k].pos_y;
		my = ($("<div id='meteonet-preview-map-"+ k + "' elid='"+k+"'>"+k+"</div>").appendTo("#meteonet-preview-map"));
		my.corner("round 4px");
		my.css("background-color","#b3b3b32c");
		my.css("border","black solid 1px");
		my.css("position", "relative");
		//my.css("font-size", "80%");
		my.css("height", "35px");
		my.css("width", "60px");
		my.css("top", pos_y+'px');
		my.css("left", pos_x+'px');
		my.css("background-repeat","no-repeat");
		my.css("line-height","");
		my.mouseover(function() {
			meteonet_select_map($(this).attr('elid'));
		});
		my.click(function() {
			meteonet_current_station = $(this).attr('elid');
			$('#tabs').tabs('select', '#meteonet-maintab-stations');
		});
	}

	$('<h3 class="ui-widget-header" id="meteonet-preview-st-header" style="font-size:80%; text-align: center; margin: 0px; margin-top: 4px;">Dati stazione </h3>').appendTo("#meteonet-preview");
	$("<div id='meteonet-preview-st' class='ui-widget-content' style='font-size:80%;width:400px;' />").appendTo("#meteonet-preview");

	$('<h3 class="ui-widget-header" id="meteonet-preview-map-header" style="font-size:80%; text-align: center; margin: 0;">Scatti in tempo reale</h3>').appendTo("#meteonet-preview-webcam");
	$("<div id='meteonet-preview-webcam-shot' class='ui-widget-content' style='text-align:center;' />").appendTo("#meteonet-preview-webcam");
	$("<div id='meteonet-preview-webcam-descr' class='ui-widget-content' style='height:40px;' />").appendTo("#meteonet-preview-webcam");

	var img;
	for(var k in meteonet_net_webcams){
		img = $("<img id='meteonet-preview-webcam-shot-"+k+"' style='display:none;width:95%;max-height:400px;' />").appendTo("#meteonet-preview-webcam-shot");
		img.click(function() {
			meteonet_current_webcam = k;
			$('#tabs').tabs('select', '#meteonet-maintab-webcams');
		});
	}
	$("#meteonet-preview-st").html("<i>Posizionare il cursore sui riquadri presenti sulla mappa per avere ulteriori dati o cliccarvi per il dettaglio</i>");
	meteonet_register_function(meteonet_update_map,"update");
	meteonet_register_function(meteonet_update_map,"interval");
	meteonet_register_function(meteonet_update_webcam,"update");
	meteonet_register_function(meteonet_rotate_webcam,"interval");
	meteonet_update_webcam();
	meteonet_update_map();
}

function meteonet_update_stations_realtime()
{
	var station_id = meteonet_current_station;
	//var my = $('#meteonet-maintab-tabs-realtime');
	var station_data = meteonet_meteo_last[station_id]['data'];
	var rain_init = meteonet_meteo_last[station_id]['statistics']['rain_init'];
	var rain_today = Math.round((station_data['rain_year'] - rain_init)*10)/10;

	var online;
	if (station_data['timestamp'] > meteonet_net_time-670)
		online = "online";
	  else
		online = "offline";

	if (station_data!=false) {

	$('#meteonet-stations-realtime-data-header').html("Dati aggiornati alle <b>" + meteonet_get_date(station_data['timestamp'],true) + " ("+online+")</b>");
	$('#meteonet-stations-tabs-realtime-temp').html("Temperatura: <b>"+station_data['temp']+" °C</b>");
	$('#meteonet-stations-tabs-realtime-rh').html("Umidit&agrave; relativa: <b>"+station_data['rh']+" %</b>");
	$('#meteonet-stations-tabs-realtime-dewpoint').html("Punto di rugiada: <b>"+station_data['dewpoint']+" °C</b>");
	$('#meteonet-stations-tabs-realtime-windchill').html("Wind chill: <b>"+station_data['windchill']+" °C</b>");
	$('#meteonet-stations-tabs-realtime-wind_speed').html("Velocit&agrave; del vento: <b>"+station_data['wind_speed']+" km/h</b>");
	$('#meteonet-stations-tabs-realtime-wind_dir').html("Direzione del vento: <b>"+station_data['wind_dir']+" </b>");
	$('#meteonet-stations-tabs-realtime-rain-year').html("Pioggia cumulata annuale: <b>"+station_data['rain_year']+" mm</b>");
	$('#meteonet-stations-tabs-realtime-rain-today').html("Pioggia cumulata oggi: <b>"+rain_today+" mm</b>");
	} else
	{
	$('#meteonet-stations-realtime-data-header').html("Stazione offline, dati real-time non disponibili");
	}
}
function meteonet_update_stations_statistics()
{
	var station_id = meteonet_current_station;
	
}
function meteonet_update_stations_today()
{
	var station_id = meteonet_current_station;
	
}

function meteonet_station_write_statistics(title,data)
{
	$('#meteonet-stations-tabs-statistics-content').html("");
	if (data==false)
	{
	$('#meteonet-stations-tabs-statistics-content').html("Stazione offline, statistiche attualmente non disponibili");
	return;
	}

	var my = $("<div style='float:left;width:410px;padding:0.5em;'/>").appendTo($('#meteonet-stations-tabs-statistics-content'));
	$('<h3 class="ui-widget-header" id="meteonet-stations-realtime-data-header" style="font-size:80%; text-align: center; margin: 0px;">'+title+' </h3>').appendTo(my);
	var data_container = $("<div class='ui-widget-content' style='' />").appendTo(my);
			
	var table = $('<table />').appendTo(data_container);
	table.append('<thead><th>Campo</th><th>Valore o Media</th><th>Massima</th><th>Minima</th></thead>');
	var tbody = $("<tbody />").appendTo(table);
	tbody.append('<tr><td>Temperatura (°C)</td><td>'+data['temp']+'</td><td>'+data['temp_max']+'</td><td>'+data['temp_min']+'</td></tr>');
	tbody.append('<tr><td>Umidit&agrave; (%)</td><td>'+data['rh']+'</td><td>'+data['rh_max']+'</td><td>'+data['rh_min']+'</td></tr>');
	tbody.append('<tr><td>Dew point (°C)</td><td>'+data['dewpoint']+'</td><td>'+data['dewpoint_max']+'</td><td>'+data['dewpoint_min']+'</td></tr>');
	tbody.append('<tr><td>Wind chill (°C)</td><td>'+data['windchill']+'</td><td>'+data['windchill_max']+'</td><td>'+data['windchill_min']+'</td></tr>');
	tbody.append('<tr><td>Velocit&agrave del vento (km/h)</td><td>'+data['wind_speed']+'</td><td>'+data['wind_gust']+'</td><td>&nbsp;</td></tr>');
	tbody.append('<tr><td>Direzione media del vento (°)</td><td>'+data['wind_dir']+'</td><td>&nbsp;</td><td>&nbsp;</td></tr>');

	if (data['rain_init']!='N.D')
		tbody.append('<tr><td>Pioggia cumulata mm</td><td>'+(Math.round((data['rain_year']-data['rain_init'])*10)/10)+'</td><td>&nbsp;</td><td>&nbsp;</td></tr>');
	else if (title == 'Dati annuali')
		tbody.append('<tr><td>Pioggia cumulata mm</td><td>'+data['rain_year']+'</td><td>&nbsp;</td><td>&nbsp;</td></tr>');
	else
		tbody.append('<tr><td>Pioggia cumulata mm</td><td>N.D.</td><td>&nbsp;</td><td>&nbsp;</td></tr>');

}

function meteonet_load_stationtab(tab_id)
{

	var my = $('#'+tab_id);
	var station_id = meteonet_current_station;
	meteonet_unregister_function(meteonet_update_stations_realtime,"update");
	meteonet_unregister_function(meteonet_update_stations_statistics,"update");
	meteonet_unregister_function(meteonet_update_stations_today,"update");

	my.html("");
	switch (tab_id) {
		case 'meteonet-stations-tabs-realtime':
			my.append("<div id='meteonet-stations-realtime-data' style='float:left;width:410px;padding:0.5em;'/>");
			$('<h3 class="ui-widget-header" id="meteonet-stations-realtime-data-header" style="font-size:80%; text-align: center; margin: 0px;">Dati stazione </h3>').appendTo("#meteonet-stations-realtime-data");
			var data_container = $("<div id='meteonet-stations-realtime-data-content' class='ui-widget-content' style='' />").appendTo("#meteonet-stations-realtime-data");
			data_container.append("<div id='meteonet-stations-tabs-realtime-temp'></div>");
			data_container.append("<div id='meteonet-stations-tabs-realtime-rh'></div>");
			data_container.append("<div id='meteonet-stations-tabs-realtime-dewpoint'></div>");
			data_container.append("<div id='meteonet-stations-tabs-realtime-windchill'></div>");
			data_container.append("<div id='meteonet-stations-tabs-realtime-wind_speed'></div>");
			data_container.append("<div id='meteonet-stations-tabs-realtime-wind_dir'></div>");
			data_container.append("<div id='meteonet-stations-tabs-realtime-rain-year'></div>");
			data_container.append("<div id='meteonet-stations-tabs-realtime-rain-today'></div>");

			meteonet_update_stations_realtime();
			meteonet_register_function(meteonet_update_stations_realtime,"update");
			break;
		case 'meteonet-stations-tabs-statistics':
			my.append("<div>Visualizza dati statistici: <select id='meteonet-select-stats'><option value='day' selected='selected'>giornaliero</option>"+
			"<option value='month'>mensile</option><option value='year'>annuale</option></select></div><div id='meteonet-stations-tabs-statistics-content' />");
			$("#meteonet-select-stats").change(function() {
				var type = $(this).val();
				var str,title;
				if (type=='day')
					meteonet_station_write_statistics('Dati giornalieri',meteonet_meteo_last[station_id]['statistics']);
				else {
					switch (type) {
						case 'year':
							str = 'load_meteo_year_statistics';
							title = 'Dati annuali';
							break;
						case 'month':
							str = 'load_meteo_month_statistics';
							title = 'Dati mensili';
							break;
					}
					var url = meteonet_url_execute + str +'&station_id=' + station_id;
					$('#meteonet-stations-tabs-statistics-content').html("Caricamento dati...");
				
					$.getJSON(
					 url, function(data){
						meteonet_station_write_statistics(title,data);
					});
				}

			});
			//$('#meteonet-stations-tabs-statistics').append('<br><p><i>Nota: i campi contrassegnati come N.D. sono quelli per cui almeno un record nel periodo selezionato &egrave; risultato non disponibile.</i></p>');

			$("#meteonet-select-stats").change();
			break;
		case 'meteonet-stations-tabs-today':
			my.append("<h3>Caricamento...</h3>");
		        $.getJSON(
			 meteonet_url_execute + 'load_meteo_archive_today_exist&station_id=' + station_id,
			 function(data){
				var my = $('#meteonet-stations-tabs-today');
				my.html("");
				var t = new Array();
				var def = false;
				for (k in data)
				{
					t[parseInt(k)] = data[k];
					if (data[k]) def = true; 
				}
				if (def) {
					my.append('Selezionare l\'ora del giorno: ');
					var select = $("<select />").appendTo(my);
					var selected_tag = "";
					select.append("<option disabled>-- selezione --</option>");
					for (k in t)
					{
						if (t[k])
							select.append("<option value='"+k+"'>"+k+"</option>");
					}
					my.append("<div id='meteonet-stations-tabs-today-table' />");
					select.change(function() {
						$('meteonet-stations-tabs-today-table').html('Caricamento dati in corso...');	
						$.getJSON(
						 meteonet_url_execute + 'load_meteo_archive_today&station_id=' + station_id + '&hour=' + $(this).val(),
						 function(data){
							var my = $('#meteonet-stations-tabs-today-table');
							my.html("");
							if (data.length==0)
								my.html("Dati non disponibili.");
							 else {
								var mm_start = data[0]['rain_year']; var mm;
								var table = $('<table />').appendTo(my);
								table.append("<thead><tr><th width='150'>Data</th><th width='50'>Temp.</th><th width='50'>Umid.rel.</th><th width='50'>Vel.vento</th><th width='50'>Direz.vento</th><th width='50'>Dew point</th><th width='50'>Wind chill</th><th width='50'>Pioggia</th></tr></thead>");				
								var table_body = $('<tbody />').appendTo(table); 				
								for (var i=0; i<data.length;i++) {
									mm = (mm_start==null || data[i]['rain_year']==null) ? null :data[i]['rain_year'] - mm_start ;
									mm_start = data[i]['rain_year'];
									table_body.append('<tr><td>'+meteonet_get_time(data[i]['timestamp'],false)+'</td><td>'+
										data[i]['temp']+'</td><td>'+data[i]['rh']+'</td>'+
										'<td>'+data[i]['wind_speed']+'</td><td>'+data[i]['wind_dir']+'</td><td>'+data[i]['dewpoint']+'<td>'+data[i]['windchill']+'</td><td>'+(mm==null?'n.d':Math.round(mm*10)/10)+'</td></td></tr>');
								}
								table.flexigrid({resizable:false, height:(h2-200), striped:false});
							}
						 }
						);
					});
					select.change();
				} else my.append('Dati non disponibili');

			 });
			break;
		case 'meteonet-stations-tabs-archive':
			my.append("<h3>Caricamento...</h3>");
		        $.getJSON(
			 meteonet_url_execute + 'load_meteo_archive_exist&station_id=' + station_id,
			 function(data){
				var my = $('#meteonet-stations-tabs-archive');
				my.html("");
				var t = new Array();
				var def = false;
				for (k in data)
				{
					t[parseInt(k)] = data[k];
					if (data[k]) def = true; 
				}
				if (def) {
					my.append('Selezionare anno:');
					var select = $("<select id='meteonet-stations-archive-year' />").appendTo(my);
					var selected_tag = "";
					select.append("<option disabled>-- selezione --</option>");
					var d = new Date();
					var tag_selected = "selected='selected'";
					for (k in t)
					{
						select.append("<option value='"+t[k]+"' "+tag_selected+">"+t[k]+"</option>");
						tag_selected = "";
					}
					my.append("<span id='meteonet-stations-tabs-archive-select'></span>");
					my.append("<div id='meteonet-stations-tabs-archive-content' />");
					select.change(function() {
					$.getJSON(meteonet_url_execute + 'load_meteo_archive_exist&station_id=' + station_id + '&year='+ $(this).val(),get_year_table)
					});
					select.change();
				} else my.append('Dati non disponibili');
			 });
			break;
		case 'meteonet-stations-tabs-info':
			$('#meteonet-stations-tabs-info').html(meteonet_net_stations[station_id]['info']+"<br /><br /><b>Note:</b>");
			var ul = $('<ul/>').appendTo($('#meteonet-stations-tabs-info'));
			var notes =  meteonet_net_stations[station_id]['notes'];
			var i = 0;
			for (note in notes) {
				ul.append("<li>"+notes[note]+"</li>");	
				i++;
			}
			if ( i == 0) {
				$('#meteonet-stations-tabs-info').append("<div><i>Nulla da segnalare</i></div>");
			}
			break;
	}
}

function get_year_table(data) {
	var my = $('#meteonet-stations-tabs-archive-content');
	var station_id = meteonet_current_station;
	my.html("");
	var t = new Array();
	for (k in data)
	{
		t[parseInt(k)] = data[k];
		if (data[k]) def = true; 
	}
	$("#meteonet-stations-tabs-archive-select").html('&nbsp;Mese&nbsp;');
	var select = $("<select />").appendTo($("#meteonet-stations-tabs-archive-select"));
	var selected_tag = "";
	select.append("<option disabled>-- selezione --</option>");
	var d = new Date();
	var tag_selected = "selected='selected'";
	for (k in t)
	{
		if (t[k]) {
			select.append("<option value='"+k+"' "+tag_selected+">"+k+"</option>");
			tag_selected = "";
		}
	}
	select.change(function() {
		$('meteonet-stations-tabs-archive-content').html('Caricamento dati in corso...');	
		$.getJSON(meteonet_url_execute + 'load_meteo_archive&station_id=' + station_id + '&year='+$("#meteonet-stations-archive-year").val()+'&month=' + $(this).val(),
		function(data){
			var my = $('#meteonet-stations-tabs-archive-content');
			my.html("");
			if (data.length==0)
				my.html("Dati non disponibili.");
			 else {
				my.append("<i>Selezionare il giorno per avere ulteriore dettaglio</i>");
				var table = $('<table />').appendTo(my);
				var mm_start = data[0]['rain_year']; var mm;
				my.append('<div>Contatore pioggia annuale ad inizio mese:'+mm_start+' mm</div>');
				table.append("<thead><tr><th width='60'>Data</th><th width='30'>Temp</th><th width='30'>T.min</th><th width='30'>T.max</th><th width='30'>U.R.</th><th width='30'>U.R.min</th><th width='30'>U.R.max</th><th width='30'>Vel.vento</th><th width='30'>Raffica</th><th width='30'>Direz.vento</th><th width='30'>Pioggia</th></tr></thead>");				
				var table_body = $('<tbody />').appendTo(table); 				
				for (var i=0; i<data.length;i++) {
					mm = (mm_start==null || data[i]['rain_year']==null) ? null :data[i]['rain_year'] - mm_start ;
					mm_start = data[i]['rain_year'];
					table_body.append('<tr><td class="table_date">'+meteonet_get_date(data[i]['timestamp'],false)+'</td><td>'+
					data[i]['temp']+'</td><td>'+data[i]['temp_min']+'</td><td>'+data[i]['temp_max']+'</td><td>'+data[i]['rh']+'</td>'+
					'<td>'+data[i]['rh_min']+'</td><td>'+data[i]['rh_max']+'</td><td>'+
					(data[i]['wind_speed']==null?'n.d':data[i]['wind_speed'])+'</td><td>'+(data[i]['wind_gust']==null?'n.d':data[i]['wind_gust'])+
					'</td><td>'+(data[i]['wind_dir']==null?'n.d':data[i]['wind_dir'])+'</td><td>'+(mm==null?'n.d':Math.round(mm*10)/10)+'</td></tr>');
				}
				table.flexigrid({resizable:false, height:(h2-230), striped:false});
				table.click(function(event){
					$('.trSelected', this).each( function(){
						var date = $('.table_date > div',this).html();
						var day = date.substr(0,2);
						var month = date.substr(3,2);
						var year = date.substr(6,4);
						//console.log('day='+day+' month='+month+' year='+year+'date='+date);
						$('#meteonet-stations-tabs-archive-content').html('Caricamento... (questa operazione può richiedere alcuni secondi)');
						$.getJSON(meteonet_url_execute + 'load_meteo_archive&station_id=' + station_id +'&year='+year+'&month=' + month + '&day='+day,
						function(data){
							var my = $('#meteonet-stations-tabs-archive-content');
							my.html("");
							var ret = $("<i style='color:blue;'>Torna al mese</i>").appendTo(my);
							ret.click(function(event){
								$('#meteonet-stations-tabs-archive-select > select').change();
							});
							my.append('<i> oppure clicca su una riga per ulteriore dettaglio</i>');
							var table = $('<table />').appendTo(my);
							var mm_start = data[0]['rain_year']; var mm; var date;
							my.append('<div>Contatore pioggia annuale ad inizio giornata:'+mm_start+' mm</div>')
							table.append("<thead><tr><th width='50'>Orario</th><th width='30'>Temp</th><th width='30'>T.min</th><th width='30'>T.max</th><th width='30'>U.R.</th><th width='30'>U.R.min</th><th width='30'>U.R.max</th><th width='30'>Vel.vento</th><th width='30'>Raffica</th><th width='30'>Direz.vento</th><th width='30'>Pioggia</th></tr></thead>");				
							var table_body = $('<tbody />').appendTo(table); 
							meteonet_data_month = new Array();				
							for (var i=0; i<data.length;i++) {
								date = meteonet_get_time(data[i]['timestamp'],false);
								mm = (mm_start==null || data[i]['rain_year']==null) ? null :data[i]['rain_year'] - mm_start ;
								mm_start = data[i]['rain_year'];
								table_body.append('<tr><td class="table_date">'+date+'</td><td>'+
								data[i]['temp']+'</td><td>'+data[i]['temp_min']+'</td><td>'+data[i]['temp_max']+'</td><td>'+data[i]['rh']+'</td>'+
								'<td>'+data[i]['rh_min']+'</td><td>'+data[i]['rh_max']+'</td><td>'+data[i]['wind_speed']+'</td><td>'+data[i]['wind_gust']+
								'</td><td>'+data[i]['wind_dir']+'</td><td>'+(mm==null?'n.d':Math.round(mm*10)/10)+'</td></tr>');
								meteonet_data_month[date] = data[i];
							}
							table.flexigrid({resizable:false, height:h2-230, striped:false, singleSelect: true });
							table.click(function(event){
								$('.trSelected', this).each( function(){
									var date = $('.table_date > div',this).html();
									var r = meteonet_data_month[date]
									var msg = 'Temperatura media (°C): '+r['temp']+"\nTemperatura minima (°C): "+r['temp_min']+"\nTemperatura massima (°C): "+r['temp_max']+
									 '\n\nU.R. media (%): '+r['rh']+"\nU.R. minima (%): "+r['rh_min']+"\nU.R. massima (%): "+r['rh_max']+
									 '\n\nContatore pioggia annuale (mm): '+(r['rain_year']==null?'n.d':r['rain_year'])+"\n\nVelocità media vento (km/h): "+(r['wind_speed']==null?'n.d':r['wind_speed'])+"\nRaffica (km/h): "+(r['wind_gust']==null?'n.d':r['wind_gust'])+"\nDirezione (°): "+(r['wind_dir']==null?'n.d':r['wind_dir'])
									 '\n\nDewpoint media (°C): '+r['dewpoint']+"\nDewpoint minima (°C): "+r['dewpoint_min']+"\nDewpoint massima (°C): "+r['dewpoint_max']+
									 '\n\nWind chill media (°C): '+(r['windchill']==null?'n.d':r['windchill'])+"\nWind chill minima: "+(r['windchill_min']==null?'n.d':r['windchill_min'])+"\nWind chill massima: "+(r['windchill_max']==null?'n.d':r['windchill_max']);
									alert(msg);
								}); //end $('.trSelected', this).each( function() 
							}); //end table.click(function(event)
						 });//end JSON
					}); //end tr
				}); // end table.click
			} // end if
		}); // end JSON
	}); // end select
	select.change();
}

function meteonet_webcam_image(domobj,timestamp,full)
{
	if (arguments.length<1)
		full = false;

	var url = meteonet_url + '/execute.php?action=image&webcam_id='+meteonet_current_webcam+'&timestamp='+timestamp;
	if (!full) {
		if ($('img',domobj).length>0) { // verifica se esiste l'elemento
			$('img',domobj).attr('src',url + '&resized');
			$('img',domobj).attr('onClick','meteonet_webcam_image(null,'+timestamp+',true)');
		} else {
			domobj.append('<i>Cliccare per ottenere la versione ingrandita</i>');
			domobj.append(
			 "<div><img style='max-width:800px;max-height:600px;' src='"+url+"&resized' onClick='meteonet_webcam_image(null,"+timestamp+",true)'></div>"
			);
		}
	} else {
		//console.log('open webcam window');
		if ($('#meteonet_webcam_window').length>0)
			$('#meteonet_webcam_window').html("");
		  else
			$("<div id='meteonet_webcam_window' />").appendTo('body');
		$('#meteonet_webcam_window').html("<img src='"+url+"'>");
		$('#meteonet_webcam_window').dialog({ height: 500, width: 700, title: 'Meteonet - Ingrandimento scatto'});
	}
}

function meteonet_load_webcamtab(tab_id)
{
	var webcam_id = meteonet_current_webcam;
	var my = $('#'+tab_id);

	//meteonet_unregister_function(meteonet_update_webcams_realtime,"update");

	my.html("");
	switch (tab_id) {
		case 'meteonet-webcams-tabs-today':
			var d = new Date();
			var day = d.getDate();
			var month = d.getMonth()+1;
			var year = d.getFullYear();

			my.append("<h3>Caricamento...</h3>");
		        $.getJSON(
			 meteonet_url_execute + 'load_webcam_archive&webcam_id=' + webcam_id + '&year=' + year + '&month=' + month + '&day=' + day,
			 function(data){
				var my = $('#meteonet-webcams-tabs-today');
				my.html("");
				var t = new Array();
				var def = false;
				
				for (k in data)
				{
					t.push(data[k]['timestamp']);
					if (data[k]) def = true; 
				}
				//console.log(t);
				if (def) {
					my.append('Selezionare lo scatto: ');
					var select = $("<select />").appendTo(my);
					var tag_selected = "selected='selected'";
;
					select.append("<option disabled>-- selezione --</option>");
					for (k in t)
					{
						select.prepend("<option value='"+t[k]+"' "+ tag_selected+">"+meteonet_get_time(t[k])+"</option>");
						tab_selected = "";					
					}
					my.append("<div id='meteonet-webcams-tabs-today-img' />");
					select.change(function() {
						meteonet_webcam_image($('#meteonet-webcams-tabs-today-img'),$(this).val());
					});
					select.change();
				} else my.append('Nessuno scatto pervenuto oggi');
			 });
			break;
		case 'meteonet-webcams-tabs-archive':
			my.append("<h3>Caricamento...</h3>");
		        $.getJSON(
			 meteonet_url_execute + 'load_webcam_archive_exist&webcam_id=' + webcam_id,
			 function(data){
				var my = $('#meteonet-webcams-tabs-archive');
				my.html("");
				var t = new Array();
				var def = false;
				for (k in data)
				{
					t[parseInt(k)] = data[k];
					if (data[k]) def = true; 
				}
				if (def) {
					my.append('Selezionare anno: ');
					var select = $("<select id='meteonet-webcam-archive-select-year'/>").appendTo(my);
					select.append("<option disabled>-- selezione --</option>");
					var d = new Date();
					var tag_selected = "selected='selected'";

					for (k in data)
					{
						select.append("<option value='"+t[k]+"' "+tag_selected+">"+t[k]+"</option>");
						tag_selected = "";					
					}

					my.append("<span id='meteonet-webcam-archive-select-month'/><span id='meteonet-webcam-archive-select-day'/>");
					my.append("<hr><div id='meteonet-webcam-archive-container' />");


					select.change(function() {
						$('#meteonet-webcam-archive-container').html('Caricamento dati in corso...');	
						$.getJSON(
						 meteonet_url_execute + 'load_webcam_archive_exist&webcam_id=' + webcam_id + '&year=' + $(this).val(),
						 function(data){
							if (data.length==0)
								$('#meteonet-webcam-archive-container').html("Dati non disponibili.");
							 else {
								$('#meteonet-webcam-archive-select-month').html("&nbsp;mese:<select />");
								var select_month = $('#meteonet-webcam-archive-select-month > select');
								
								select_month.html("");
								
								var t = new Array();
								var def = false;
								for (k in data)
								{
									t[parseInt(k)] = data[k];
									if (data[k]) def = true; 
								}
								var tag_selected = "selected='selected'";

								for (k in data){
									if (t[k]){
										select_month.append("<option value='"+k+"' "+tag_selected+">"+k+"</option>");
										tag_selected = "";									
									}						
								}

								select_month.change(function() {
									$('#meteonet-webcam-archive-container').html("Caricamento...");
									var select_year = $('#meteonet-webcam-archive-select-year');

									$.getJSON(
									 meteonet_url_execute + 'load_webcam_archive_exist&webcam_id=' + 
									   webcam_id + '&year='+select_year.val()+'&month=' + $(this).val(),meteonet_month_webcam);
								});
								select_month.change();

							}
						 }
						);
					});
					select.change();
				} else my.append('Dati non disponibili');

			 });


			break;
		case 'meteonet-webcams-tabs-info':
			$('#meteonet-webcams-tabs-info').html(meteonet_net_webcams[webcam_id]['info']);
			break;
	}
}

function meteonet_month_webcam(data) {
	var my_select_span = $('#meteonet-webcam-archive-select-day');
	var my_container = $('#meteonet-webcam-archive-select-container');

	$('#meteonet-webcam-archive-select-day').html("");
	
	var t = new Array();
	var def = false;
	for (k in data)
	{
		t[parseInt(k)] = data[k];
		if (data[k]) def = true; 
	}
	my_select_span.append('&nbsp;giorno:&nbsp;');
	var select_day = $("<select />").appendTo(my_select_span);

	select_day.append("<option disabled>-- selezione --</option>");
	var d = new Date();
	var tag_selected = "selected='selected'";
	for (k in data)
	{
		if (t[k])
			select_day.append("<option value='"+k+"' "+tag_selected+">"+k+"</option>");
		tag_selected = "";					
	}
	select_day.change(function() {
		var webcam_id = meteonet_current_webcam;
		var selected_year = $("#meteonet-webcam-archive-select-year").val();
		var selected_month = $("#meteonet-webcam-archive-select-month > select").val();
		$('#meteonet-webcam-archive-container').html("Caricamento...");
		$.getJSON( meteonet_url_execute + 'load_webcam_archive&webcam_id=' + webcam_id + '&year=' + selected_year +'&month=' + selected_month + '&day=' + $(this).val(),
		function(data){
			$('#meteonet-webcam-archive-container').html("Orario: &nbsp;");
			var select_shot = $("<select id='meteonet-webcam-select-shot'/>").appendTo($('#meteonet-webcam-archive-container'));
			select_shot.append("<option disabled>-- selezione --</option>");
			var tag_selected = "selected='selected'";
			for (k in data)
			{
				timestamp = data[k]['timestamp'];	
				select_shot.append("<option value='"+timestamp+"' "+tag_selected+">"+
				meteonet_get_time(timestamp,false)+"</select>");
				tag_selected = "";										
			}
			$('#meteonet-webcam-archive-container').append("<br>");
			select_shot.change(function() {
				meteonet_webcam_image($('#meteonet-webcam-archive-container'),$(this).val());
			});
			select_shot.change();
		});
	});
	select_day.change();
}


function meteonet_load_maintab_stations() {
	if (meteonet_current_station === undefined)
		for (k in  meteonet_net_stations) {
			meteonet_current_station = k;
			break;
		}
	var station_id = meteonet_current_station;

	var main = $('#meteonet-maintab-stations');
	main.html("");
	main.append("Seleziona la stazione meteorologica: ");
	var select = $("<select />").appendTo(main);
	var selected_tag = "";
	for (k in  meteonet_net_stations)
	{
		if (k == station_id) 
			selected_tag = "selected='selected'";
		  else
			selected_tag = "";		
		select.append("<option value='"+k+"' "+selected_tag+">"+meteonet_net_stations[k]['location']+"</option>");
	}
	select.change(function() {
		meteonet_current_station = $(this).val();
 		meteonet_load_maintab_stations();
	});
	main.append("<hr />");
	main.append("<div id='meteonet-stations-current' style='display:none'>" + station_id + "</div>");
	
	/* vertical tabs creation */

	var tabs = $("<div id='meteonet-stations-tabs' style='padding-top:5px;border:0px;'>").appendTo(main);

	var tab_labels = new Array("Tempo reale","Statistiche","Dati odierni","Archivio","Informazioni");
	var tab_names = new Array("realtime","statistics","today","archive","info");

	var ul = $("<ul>").appendTo(tabs);
	for (var i=0;i<tab_names.length;i++) {
		ul.append("<li><a href='#meteonet-stations-tabs-"+tab_names[i]+"'>"+tab_labels[i]+"</a></li>");
		tabs.append("<div id='meteonet-stations-tabs-"+tab_names[i]+"'"+(tab_names[i]=='info'?'style="float:none;overflow:auto;"':'')+"></div>");
	}
	$( "#meteonet-stations-tabs" ).tabs().addClass( "ui-tabs-vertical ui-helper-clearfix" );
        $( "#meteonet-stations-tabs li" ).removeClass( "ui-corner-top" ).addClass( "ui-corner-left" );
	$( "#meteonet-stations-tabs" ).tabs({
	    select: function(event, ui){
		var id = ui.panel.id;
		meteonet_load_stationtab(id);
	    }
	});

	meteonet_load_stationtab('meteonet-stations-tabs-realtime');
}


function meteonet_load_maintab_webcams() {
	
	if (meteonet_current_webcam === undefined)
		for (k in  meteonet_net_webcams) {
			meteonet_current_webcam = k;
			break;
		}
	var webcam_id = meteonet_current_webcam;

	var main = $('#meteonet-maintab-webcams');
	main.html("");
	main.append("Seleziona la webcam: ");
	var select = $("<select />").appendTo(main);
	var selected_tag = "";
	for (k in  meteonet_net_webcams)
	{
		if (k == webcam_id) 
			selected_tag = "selected='selected'";
		  else
			selected_tag = "";		
		select.append("<option value='"+k+"' "+selected_tag+">"+meteonet_net_webcams[k]['location']+"</option>");
	}
	select.change(function() {
		meteonet_current_webcam = $(this).val();
 		meteonet_load_maintab_webcams();
	});
	main.append("<hr />");
	main.append("<div id='meteonet-webcams-current' style='display:none'>" + webcam_id + "</div>");
	
	/* vertical tabs creation */

	var tabs = $("<div id='meteonet-webcams-tabs' style='padding-top:5px;'>").appendTo(main);

	var tab_labels = new Array("Scatti odierni","Archivio scatti","Informazioni");
	var tab_names = new Array("today","archive","info");

	var ul = $("<ul>").appendTo(tabs);
	for (var i=0;i<tab_names.length;i++) {
		ul.append("<li><a href='#meteonet-webcams-tabs-"+tab_names[i]+"'>"+tab_labels[i]+"</a></li>");
		tabs.append("<div id='meteonet-webcams-tabs-"+tab_names[i]+"'></div>");
	}
	//$( "#meteonet-webcams-tabs" ).tabs().addClass( "ui-tabs-vertical ui-helper-clearfix" );
        //$( "#meteonet-webcams-tabs li" ).removeClass( "ui-corner-top" ).addClass( "ui-corner-left" );
	$( "#meteonet-webcams-tabs" ).tabs({
	    select: function(event, ui){
		var id = ui.panel.id;
		meteonet_load_webcamtab(id);
	    }
	});

	meteonet_load_webcamtab('meteonet-webcams-tabs-today',webcam_id);

}

function meteonet_load_maintab_info() {
	var main = $('#meteonet-maintab-info');
	main.html("");
	/* vertical tabs creation */

	var tabs = $("<div id='meteonet-info-tabs' style='padding-top:5px;'>").appendTo(main);
	
	var tab_labels = new Array("FAQs","Diario modifiche","Crediti");
	var tab_names = new Array("faqs","changelog","credits");

	var ul = $("<ul>").appendTo(tabs);
	for (var i=0;i<tab_names.length;i++) {
		ul.append("<li><a href='#meteonet-info-tabs-"+tab_names[i]+"'>"+tab_labels[i]+"</a></li>");
		tabs.append("<div id='meteonet-info-tabs-"+tab_names[i]+"' style='height:95%;overflow:auto;float:none;'></div>");
	}
	//$( "#meteonet-info-tabs" ).tabs().addClass( "ui-tabs-vertical ui-helper-clearfix" );
        //$( "#meteonet-info-tabs li" ).removeClass( "ui-corner-top" ).addClass( "ui-corner-left" );
	$( "#meteonet-info-tabs" ).tabs({
	    select: function(event, ui){
		var id = ui.panel.id;
		switch (id) {	
			case 'meteonet-info-tabs-faqs':
				if (meteonet_info['faqs'] == undefined) {
				      $.getJSON(
					 meteonet_url_execute + 'load_info_faqs',
					 function(data){
						meteonet_info['faqs'] = data;
						meteonet_info_faqs();
					 });
				} else meteonet_info_faqs();
				break;
			case 'meteonet-info-tabs-changelog':
				if (meteonet_info['changelog'] == undefined) {
				      $.getJSON(
					 meteonet_url_execute + 'load_info_changelog',
					 function(data){
						meteonet_info['changelog'] = data;
						meteonet_info_changelog();
					 });
				} else meteonet_info_changelog();
				break;
			case 'meteonet-info-tabs-credits':
				if (meteonet_info['credits'] == undefined) {
				      $.getJSON(
					 meteonet_url_execute + 'load_info_credits',
					 function(data){
						meteonet_info['credits'] = data;
						meteonet_info_credits();
					 });
				} else meteonet_info_credits();
				break;
		}
	    }
	});

	$.getJSON(
		meteonet_url_execute + 'load_info_faqs',
		function(data){
			meteonet_info['faqs'] = data;
			meteonet_info_faqs();
		 });

}


function meteonet_info_faqs() {
	var my=$('#meteonet-info-tabs-faqs');
	my.html("<div id='meteonet-info-faqs-titles' />");
	my.append("<div id='meteonet-info-faqs-body' />");
	var faqs = meteonet_info['faqs'];
	var faq;
	$('#meteonet-info-faqs-titles').append("<a id='meteonet-faq-titles' />");
	for (faq in meteonet_info['faqs'])
	{
		$('#meteonet-info-faqs-titles').append('<h3><a href="#meteonet-faq-'+faq+'">'+meteonet_info['faqs'][faq]['q']+'</a></h3>');
		$('#meteonet-info-faqs-body').append('<h3><a id="meteonet-faq-'+faq+'">'+meteonet_info['faqs'][faq]['q']+'</a></h3>');
		$('#meteonet-info-faqs-body').append('<p>'+meteonet_info['faqs'][faq]['a']+'</p>');
		$('#meteonet-info-faqs-body').append('<p><a href="#meteonet-faq-titles">Ritorna</a></p>');
	}
	$('#meteonet-info-faqs-titles').append('<hr />');
}

function meteonet_info_credits() {
	var my=$('#meteonet-info-tabs-credits');
	my.html(meteonet_info['credits'][0]);
}

function meteonet_info_changelog() {
	var my=$('#meteonet-info-tabs-changelog');
	my.html(meteonet_info['changelog'][0]);
}


function meteonet_close_note() {
	$('#meteonet_note').fadeOut();
}

function meteonet_load_main(step_loading) {
	if ( arguments.length === 0 ) {
		meteonet_webcams_last = undefined;
		meteonet_webcams_old_last = undefined;
		unregister_all_functions();
		meteonet_load_net();

		timer.set({ time : 1000, autostart : true });

		var main = $('#meteonet_main');

		main.html("<h3 id='meteonet_loading'>Caricamento...</h3>");
		main.css("font-family","'Trebuchet MS', 'Helvetica', 'Arial', 'Verdana', 'sans-serif'");
		main.css("font-size", 11);


		//main.dialog({ height: h, width: w, title: 'Meteonet - Rete meteorologica del Gargano'});
	} else { 
		//var main = $('#meteonet_main.ui-dialog-content.ui-widget-content');
		var main = $('#meteonet_main');

		if (step_loading == 1) {
			main.html("<h3 id='meteonet_loading'>Caricamento......</h3>");
			meteonet_load_last(true);
		} else {
			main.html("");
			h2 = h-$('.ui-dialog-titlebar').height();
			main.css("height",h2);
			main.css("overflow","auto");
			//main.append ("<div class='ui-widget' id='meteonet_note'><div class='ui-state-highlight ui-corner-all' style='margin-top: 20px; margin-bottom: 20px; padding: 0.7em;'>" +
			//	"<p><span class='ui-icon ui-icon-info' style='float: left; margin-right: .3em;'></span>"+
			//	"<strong>Benvenuto!</strong> "+
			//	"Il software è in fase di test; ci scusiamo per eventuali problemi o bug."+
			//	" Per segnalarli, manda una mail all'indirizzo ziofil@gmail.com (<a href='#' style='color:blue' onClick='meteonet_close_note()' >chiudi</a>)</p></div>");
			var tabs = $("<div id='tabs' style='height:"+(h2-30)+"px;overflow:auto;'>").appendTo(main);
			main.append("<div style='font-size:80%;'><i>Software a cura di Filippo Gurgoglione - ziofil@gmail.com; ultimo aggiornamento "+ meteonet_last_update +"</i> </div>")
			//tabs.css("height",tabs.height()-$('#meteonet_note').height());
			var tab_labels = new Array("Panoramica","Dettaglio stazioni","Dettaglio webcam","Informazioni");
			var tab_names = new Array("main","stations","webcams","info");

			var ul = $("<ul>").appendTo(tabs);
			for (var i=0;i<tab_names.length;i++) {
				ul.append("<li><a href='#meteonet-maintab-"+tab_names[i]+"'>"+tab_labels[i]+"</a></li>");
				tabs.append("<div id='meteonet-maintab-"+tab_names[i]+"'></div>");
			}
			$( "#tabs" ).tabs();
			$( "#tabs" ).tabs({
			    select: function(event, ui){
				unregister_all_functions();
				var id = ui.panel.id;
				if (id == "meteonet-maintab-main")
					meteonet_load_maintab_main();
				else if (id == "meteonet-maintab-stations")
					meteonet_load_maintab_stations();
				else if (id == "meteonet-maintab-webcams")
					meteonet_load_maintab_webcams();
				else if (id == "meteonet-maintab-info")
					meteonet_load_maintab_info();

			    }
			});
			meteonet_load_maintab_main();
		}
	}
}

