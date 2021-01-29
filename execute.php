<?php
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
ob_start();
include_once('core.php');
//include_once('router.php');
include_once('interface.php');

if (!isset($_GET['action'])) die("No action defined"); else $action = $_GET['action'];

$if = new meteonet_interface();

if ($action == 'json')
{
	if (!isset($_GET['json_request'])) die("Not enough parameters");  else	$json_request = $_GET['json_request'];

	$year = (isset($_GET['year']) ? (int)$_GET['year'] : NULL );
	$month = (isset($_GET['month']) ? (int)$_GET['month'] : NULL );
	$day = (isset($_GET['day']) ? (int)$_GET['day'] : NULL );
	$hour = (isset($_GET['hour']) ? (int)$_GET['hour'] : NULL );

	switch ($json_request) {
		case 'load_net':
			$ret = $if->json_net();
			break;
		case 'load_last':
			$ret = $if->json_last();
			break;
		case 'load_meteo_last': //deprecated
			$ret = $if->json_meteo_last();
			break;
		case 'load_webcams_last': //deprecated
			$ret = $if->json_webcams_last();
			break;
		case 'load_meteo_archive_today':
			$station_id = $_GET['station_id'];
			$ret = $if->json_meteo_archive_today($station_id,$hour);
			break;
		case 'load_meteo_archive_lasthours':
			$lasthours = (isset($_GET['lasthours']) ? (int)$_GET['lasthours'] : NULL );
			$station_id = $_GET['station_id'];
			$ret = $if->json_meteo_archive_today_lasthours($station_id,$lasthours);
			break;
		case 'load_meteo_archive_lasttime':
			$starttimestamp = (isset($_GET['starttimestamp']) ? (int)$_GET['starttimestamp'] : NULL );
			$station_id = $_GET['station_id'];
			$ret = $if->json_meteo_archive_today_lasttime($station_id,$starttimestamp);
			break;
		case 'load_meteo_archive_today_exist':
			$station_id = $_GET['station_id'];
			$ret = $if->json_meteo_archive_today_exist($station_id);
			break;
		case 'load_meteo_archive':
			$station_id = $_GET['station_id'];
			$ret = $if->json_meteo_archive($station_id,$year,$month,$day,$hour);
			break;
		case 'load_meteo_archive_exist':
			$station_id = $_GET['station_id'];
			$ret = $if->json_meteo_archive_exist($station_id,$year,$month,$day,$hour);
			break;
		case 'load_webcam_archive':
			$webcam_id = $_GET['webcam_id'];
			$ret = $if->json_webcam_archive($webcam_id,$year,$month,$day,$hour);
			break;
		case 'load_webcam_archive_exist':
			$webcam_id = $_GET['webcam_id'];
			$ret = $if->json_webcam_archive_exist($webcam_id,$year,$month,$day,$hour);
			break;
		case 'load_meteo_month_statistics':
			$station_id = $_GET['station_id'];
			$ret = $if->json_meteo_month_statistics($station_id);
			break;
		case 'load_meteo_year_statistics':
			$station_id = $_GET['station_id'];
			$ret = $if->json_meteo_year_statistics($station_id);
			break;
		case 'load_info_faqs':
			$ret = $if->json_info('faqs');
			break;
		case 'load_info_changelog':
			$ret = $if->json_info('changelog');
			break;
		case 'load_info_credits':
			$ret = $if->json_info('credits');
			break;
	}
} elseif ($action == 'image') {
	$webcam_id = $_GET['webcam_id'];
	$timestamp = (int)$_GET['timestamp'];
	$resized = isset($_GET['resized']);

	$ret = $if->image_request($webcam_id,$timestamp,$resized);
} elseif ($action == 'dailylog') {
	$station_id = $_GET['station_id'];
	$ret = $if->dailylog($station_id);
}else die("No valid action"); 

$out = ob_get_contents();

//if (!empty($out))
//{
	$file = METEONET_SERVER_FILE_DIR . "/log.execute.txt";
	file_put_contents($file,"Output from execute.php, " . date(DATE_RFC822) . "\n" . $out  . "\n---------------\n");
//}

ob_end_clean();
echo $ret;
?>