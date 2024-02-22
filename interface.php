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
class meteonet_interface {
	var $net;
	function __construct() {
		//include (dirname(__FILE__) . '/config.php');
		$this->net = new meteonet_core();
	}
        
	function image_request($webcam_id,$timestamp,$resized) {
		$data = $this->net->get_webcam_image($this->net->get_webcam_by_id($webcam_id),$timestamp,$resized);
		header("Content-Type: image/jpeg"); 
		return $data;
	} 

	function dailylog($station_id) {
		header('Content-Type:text/plain');
		$cache_key = "dailylog_station_" . $station_id;
		$old_cache = $this->net->cache->get_val($cache_key);
		$now = time();
		if ($old_cache && $old_cache['last_timestamp']>$now-300) 
			return $old_cache['last_value'];
		
		if (!$station = $this->net->get_station_by_id($station_id)) 
			return "Stazione inesistente";

		$retarr = $this->net->get_station_data_today($station);
		//$retval = print_r($retarr,true);
		$retval = "day month year hour minute temperature   humidity     dewpoint   barometer    windspeed    gustspeed direction   rainlastmin    dailyrain  monthlyrain    yearlyrain   heatindex\n";
		
		foreach ($retarr as $currval) {
			$curr_y_rain = $currval['rain_year'];
			if (!isset($old_y_rain))
				$curr_m_rain = 0;
			   else
				$curr_m_rain = $curr_y_rain - $old_y_rain;

			if (!isset($start_y_rain)) $start_y_rain = $curr_y_rain;
			$curr_d_rain = $curr_y_rain - $start_y_rain;

			$currdate = getdate($currval['timestamp']);
			$retval .= $currdate['mday']." ".$currdate['mon']." ".$currdate['year']." ".$currdate['hours']." ".$currdate['minutes']." ";
			$retval .= $currval['temp']." ".$currval['rh']." ".$currval['dewpoint']." ".$currval['press']." ";
			// 1 nodo = 1.852 km/h
			$ws = ($currval['wind_speed'] != NULL ? round($currval['wind_speed']/1.852,3) : "0");
			$wgs = ($currval['wind_gust'] != NULL ? round($currval['wind_gust']/1.852,3) : "0");
			$wdir = ($currval['wind_dir'] != NULL ? $currval['wind_dir'] : "0");
			$retval .= $ws ." ". $wgs ." ". $wdir ." ";


			$retval .= $curr_m_rain . " " . $curr_d_rain . " ND ". $curr_y_rain . " ND\n";
			$old_y_rain = $curr_y_rain;
		}
		$this->net->cache->set_val($cache_key, array("last_timestamp"=>$now, "last_value"=>$retval));
		return $retval;
	} 

	function json_net() {
		$return = array();
		$array_webcams = array();
		$array_stations = array();
		foreach ($this->net->net_config->webcams->webcam as $webcam)
		{
			if ((boolean)$webcam->visible==true) {
				$my = array ('location'=>(string)$webcam->location,
					     'info'=>(string)$webcam->info);
				$array_webcams[(string)$webcam['id']] = $my;
			}
		}
		foreach ($this->net->net_config->stations->station as $station)
		{
			if ((boolean)$station->visible==true) {
				$notes = array();
				foreach ($station->note as $note)
					$notes[] = (string)$note;

				$my = array (
					'pos_x'=>(string)$station->img_coordinates['x'],
					'pos_y'=>(string)$station->img_coordinates['y'],
					'gps_long'=>(string)$station->coordinates['long'],
					'gps_lat'=>(string)$station->coordinates['lat'],					
					'location'=>(string)$station->location,
					'info'=>(string)$station->info,
					'notes'=>$notes);

				$array_stations[(string)$station['id']] = $my;
			}
		}
		$return['webcams'] = $array_webcams;
		$return['stations'] = $array_stations;
		$return['time'] = meteonet_utils::utc_time();

		$xml = simplexml_load_file (dirname(__FILE__) . '/info.xml');
		foreach ($xml->info as $info) {
			if ($info['id'] == 'changelog') {
				$return['lastupdate'] = (string)$info['lastupdate'];
				break;
			}
		}


		return  $this->json_encode($return);
	}

	function json_last() {
		$last_station = $this->net->get_stations_last();
		foreach ($last_station as $st=>$stval) {
			$last_station[$st]['data'] = $this->round($stval['data']);
			$last_station[$st]['statistics'] = $this->round($stval['statistics']);
		}
		$a = array("stations" => $last_station,
			"webcams" => $this->net->get_webcams_last_timestamp());
		return $this->json_encode($a);
	}

	function json_encode($array) {
		return  (isset($_GET['jsoncallback']) ? $_GET['jsoncallback']. '('.json_encode($array).')' : json_encode($array));
	}

	function json_meteo_archive($station_id,$year,$month,$day,$hour) {
		return $this->json_encode($this->net->get_station_data($this->net->get_station_by_id($station_id),
			$year,$month,$day,$hour));
	}

	function json_webcam_archive($webcam_id,$year,$month,$day,$hour) {
		return $this->json_encode($this->net->get_webcam_data($this->net->get_webcam_by_id($webcam_id),
			$year,$month,$day,$hour));
	}

	function json_meteo_archive_exist($station_id,$year,$month,$day,$hour) {
		return $this->json_encode($this->net->get_station_exist_records($this->net->get_station_by_id($station_id),
			$year,$month,$day,$hour));
	}

	function json_webcam_archive_exist($webcam_id,$year,$month,$day,$hour) {
		return $this->json_encode($this->net->get_webcam_exist_records($this->net->get_webcam_by_id($webcam_id),
			$year,$month,$day,$hour));
	}

	function json_meteo_archive_today($station_id,$hour) {
		return $this->json_encode($this->net->get_station_data_today($this->net->get_station_by_id($station_id),$hour));
	}

	function json_meteo_archive_today_lasthours($station_id,$lasthours) {
                if (isset($station_id))
		    $cache_key = "dailyjson_lasthours_" . $lasthours;
                 else
		    $cache_key = "dailyjson_lasthours_" . $lasthours . "_" . $station_id;

		$old_cache = $this->net->cache->get_val($cache_key);
		$now = time();
		if ($old_cache && $old_cache['last_timestamp']>$now-300) 
			return $old_cache['last_value'];

		if (!isset($station_id)){
			$retarr = array();
			foreach ($this->net->net_config->stations->station as $station) {
				$station_id = (string)$station['id'];
				$retarr[$station_id] = $this->net->get_station_data_today(
                                    $this->net->get_station_by_id($station_id),NULL,meteonet_utils::utc_time()-($lasthours*3600));
			}
		} else
			$retarr = $this->net->get_station_data_today($this->net->get_station_by_id($station_id),NULL,meteonet_utils::utc_time()-($lasthours*3600));

		$retval = json_encode($retarr);
		$this->net->cache->set_val($cache_key, array("last_timestamp"=>$now, "last_value"=>$retval));
		return $retval;
	}

	function json_meteo_archive_today_lasttime($station_id,$starttimestamp) {
		if (!isset($station_id)){
			$retarr = array();
			foreach ($this->net->net_config->stations->station as $station) {
				$station_id = (string)$station['id'];
				$retarr[$station_id] = $this->net->get_station_data_today(
                                    $this->net->get_station_by_id($station_id),NULL,$starttimestamp);
			}
		} else
			$retarr = $this->net->get_station_data_today($this->net->get_station_by_id($station_id),NULL,$starttimestamp);

		$retval = json_encode($retarr);
		return $retval;
	}

	function json_meteo_archive_today_exist($station_id) {
		return $this->json_encode($this->net->get_station_exist_records_today($this->net->get_station_by_id($station_id)));
	}

	function json_meteo_month_statistics($station_id) {
		$array = $this->round($this->net->get_station_month_statistics($this->net->get_station_by_id($station_id)));
		return $this->json_encode($array);
	}

	function json_meteo_year_statistics($station_id) {
		$array =$this->round($this->net->get_station_year_statistics($this->net->get_station_by_id($station_id)));
		return $this->json_encode($array);
	}

	function json_info($type) {
		$xml = simplexml_load_file (dirname(__FILE__) . '/info.xml');
//print_r($xml);
		$ret = array();
		switch ($type) {
			case 'faqs':
				foreach ($xml->info as $info) {
					if ((string)$info['id'] == 'faqs') {
						foreach ($info->faq as $faq) 
							$ret[] = array('a'=>(string)$faq->a,'q'=>(string)$faq->q);
						break;
					}
				}
				break;
			case 'credits':
				foreach ($xml->info as $info) {
					if ($info['id'] == 'credits') {
						$ret[] = (string)$info;
						break;
					}
				}
				break;
			case 'changelog':
				foreach ($xml->info as $info) {
					if ($info['id'] == 'changelog') {
						$ret[] = (string)$info;
						break;
					}
				}
				break;
		}
		return $this->json_encode($ret);
	}

	function round($array) {
		foreach ($array as $st=>$stval) {
			if ($stval == NULL)
				$array[$st] = "N.D";
			elseif ($st == 'wind_dir')   
			 	$array[$st] = meteonet_utils::deg2literal($stval);
			else
				$array[$st] = round($stval,1);
		}
		return $array;
	}

}
?>
