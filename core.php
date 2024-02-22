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

include_once ('config.php');
include_once('ser.php');
include_once('utils.php');

class meteonet_core {
		var $net_config;
		var $db;
		function __construct()
		{			
			$this->net_config = simplexml_load_file (dirname(__FILE__) . '/config_net.xml');
			if (METEONET_ROLE == "server") {
				$this->cache = new meteonet_ser(METEONET_SERVER_FILE_DIR . 'cache/cache.dat');
				$this->update_db();
			}
		}

		private function db_query($sql) {
			if (!isset($this->db))
			{
				$this->db = mysqli_connect(
				        METEONET_DB_HOST, METEONET_DB_USER, METEONET_DB_PASSWORD)
					    or err("Connessione non riuscita: " . mysqli_error(),true);
				mysqli_select_db  ($this->db, METEONET_DB_NAME);
			}

			if ($ret = mysqli_query($this->db, $sql))
				return $ret;
			 else
				$this->log("Mysql query error: query:'".$sql."'; error:'".mysqli_error($this->db)."'");
			return false;
			
		}

		function get_station_by_id($station_id)
		{
			foreach ($this->net_config->stations->station as $station)
				if ((string)$station['id']==$station_id)
					return $station;
			return false;
		}

		function get_webcam_by_id($webcam_id)
		{
			foreach ($this->net_config->webcams->webcam as $webcam)
				if ((string)$webcam['id']==$webcam_id)
					return $webcam;
			return false;
		}

		function get_station_data_today($station,$hour=NULL,$starttimestamp=NULL)
		{
			if (date_default_timezone_get() != 'UTC') date_default_timezone_set('UTC');
			$db_id = (string)$station['db_id'];
			$day_now = meteonet_utils::get_basetime(meteonet_utils::utc_time(),'day');
			if (isset($hour)) {
				$interval1 = ($hour-1)*3600 + $day_now;
				$interval2 = $interval1 + 3600;
				$cond = "timestamp<=$interval2 AND timestamp>=$interval1"; 
			} else {
				if (isset($starttimestamp))
					$cond = "timestamp>".$starttimestamp;
				   else
					$cond = "timestamp>$day_now";
			}

			$sql = "SELECT * FROM data WHERE station_id=$db_id AND $cond";
			$res = $this->db_query($sql);
			$rows = array();
			while($row=mysqli_fetch_array($res,MYSQLI_ASSOC)) {
				$rows[] = $row;
			}

			return $rows;
		}

		public function get_station_data($station,$year,$month=NULL,$day=NULL,$hour=NULL)
		{
			if (date_default_timezone_get() != 'UTC') date_default_timezone_set('UTC');
			$db_id = (string)$station['db_id'];
			if (!isset($month)) {
				$table_name = 'data_day';
				$interval1 = mktime(0, 0, 0, 1, 1, $year);
				$interval2 = $interval1 + 365*86400;
			} elseif (isset($month) && !isset($day)) {
				$table_name = 'data_day';
				$interval1 = mktime(0, 0, 0, $month, 1, $year);
				$interval2 = $interval1 + 86400*meteonet_utils::get_days_month($month);
			} elseif (isset($month) && isset($day) && !isset($hour)) {
				$table_name = 'data_min';
				$interval1 = mktime(0, 0, 0, $month, $day, $year);
				$interval2 = $interval1 + 86400;
			} elseif (isset($month) && isset($day) && isset($hour)) {
				$table_name = 'data_min';
				$interval1 = mktime(0, 0, 0, $month, $day, $year) + ($hour-1)*3600;
				$interval2 = $interval1 + 3600;
			}
			$sql = "SELECT * FROM $table_name WHERE station_id=$db_id AND timestamp<$interval2 AND timestamp>=$interval1";
			$res = $this->db_query($sql);
			$rows = array();
			while($row=mysqli_fetch_array($res,MYSQL_ASSOC)) {
				$rows[] = $row;
			}
			
			return $rows;
		}

		public function get_station_data_timestamp($station_db_id,$timestamp,$around_seconds)
		{
			$interval1 = $timestamp - $around_seconds;
			$interval2 = $timestamp + $around_seconds;
			$sql = "SELECT * FROM data WHERE station_id=$station_db_id AND timestamp<$interval2 AND timestamp>=$interval1";
			$res = $this->db_query($sql);
			$rows = array();
			while($row=mysqli_fetch_array($res,MYSQL_ASSOC)) {
				$rows[] = $row;
			}
			if (count($rows)==0) {
				$sql = "SELECT * FROM data_min WHERE station_id=$station_db_id AND timestamp<$interval2 AND timestamp>=$interval1";
				$res = $this->db_query($sql);
				while($row=mysqli_fetch_array($res,MYSQL_ASSOC)) {
					$rows[] = $row;
				}
			}
			return $rows;
		}

		public function get_webcam_data($station,$year,$month,$day,$hour=NULL)
		{
			if (date_default_timezone_get() != 'UTC') date_default_timezone_set('UTC');
			$db_id = (string)$station['db_id'];
			$table_name = 'webcam';
			if (!isset($hour)) {
				$interval1 = mktime(0, 0, 0, $month, $day, $year);
				$interval2 = $interval1 + 86400;
			} else {
				$interval1 = mktime(0, 0, 0, $month, $day, $year) + ($hour-1)*3600;
				$interval2 = $interval1 + 3600;
			}
			$sql = "SELECT `timestamp` FROM $table_name WHERE webcam_id=$db_id AND timestamp<$interval2 AND timestamp>=$interval1";
			$res = $this->db_query($sql);
			$rows = array();
			while($row=mysqli_fetch_array($res,MYSQL_ASSOC)) {
				$rows[] = $row;
			}
			return $rows;
		}


		function get_webcam_image($webcam,$timestamp,$resized=false,$file=NULL,$data=NULL)
		{
			$db_id = (string)$webcam['db_id'];
			$id = (string)$webcam['id'];

			$file1 = METEONET_SERVER_FILE_DIR . "/cache/images/webcam_".$id."_".$timestamp;
			$file2 = METEONET_SERVER_FILE_DIR . "/cache/images/webcam_".$id."_".$timestamp."_resized";

			if (file_exists($file1) and !$resized and !isset($file))
				return file_get_contents($file1);
			   elseif (file_exists($file2) and $resized and !isset($file))
				return file_get_contents($file2);
			   else {
				if (isset($file)) {
					$file1=$file;$file2=$file;
				}
				if (!isset($data)) {
					$db_id = (string)$webcam['db_id'];
					$sql = "SELECT data FROM webcam WHERE timestamp=".$timestamp." AND webcam_id=".$db_id;
					$res=$this->db_query($sql);
					$row=mysqli_fetch_array($res,MYSQL_ASSOC);
					mysqli_free_result($res);
					$data = $row['data'];
					unset($row);
				}
				if (!$resized) {
					file_put_contents($file1,$data);
					unset($row);

					$meteo_str = "";
					if ((string)$webcam->edit['use']=='yes')
						if ((string)$webcam->edit->meteodata['use']=='yes') {

							$station_data_id = (string)$webcam->edit->meteodata['station'];
							$station_data = $this->get_station_by_id($station_data_id);
							$station_data_db_id = (int)$station_data['db_id'];
							$data_timestamps = $this->get_station_data_timestamp($station_data_db_id,$timestamp,500);
							$diff = 501;
							if (count($data_timestamps)>0) {
								foreach ($data_timestamps as $data_item)
								{
									if (abs($data_item['timestamp']-$timestamp)<$diff) {
										$diff_item = $data_item; $diff = $data_item['timestamp'];
									}
								}
								$meteo_str = "Temperatura:".$diff_item['temp']."°C Umidita':".$diff_item['rh']."% Pressione:".$diff_item['press']."hPa";	
							} else $meteo_str = "Dati meteo non disponibili";
						}

					return meteonet_utils::process_image($file1,$webcam,$timestamp,$meteo_str);
				} else {
					file_put_contents($file2,$data);
					unset($row);
					meteonet_utils::process_image($file2,$webcam,$timestamp,"");
					return meteonet_utils::resize_image($file2);
				}

			 }
		}

		function get_webcam_last_timestamp($webcam)
		{
			$webcam_id = (string)$webcam['id'];
			if ($last_timestamp = $this->cache->get_val("last_webcam_timestamp_".$webcam_id))
				return $last_timestamp;
			  else {
				$db_id = (string)$webcam['db_id'];
				$sql = "SELECT timestamp FROM webcam WHERE webcam_id = ".$db_id ." ORDER BY timestamp DESC LIMIT 0,1";
				$res=$this->db_query($sql);
				$row=mysqli_fetch_array($res,MYSQL_ASSOC);
				return $row['timestamp'];
			}
		}


		function get_station_last_data($station)
		{
			$station_id = (string)$station['id'];
			if ($last_data = $this->cache->get_val("last_station_".$station_id))
				return $last_data;
			  else {
				$db_id = (string)$station['db_id'];
				$sql = "SELECT * FROM data WHERE station_id = ".$db_id ." ORDER BY timestamp DESC LIMIT 0,1";
				$res=$this->db_query($sql);
				$row=mysqli_fetch_array($res,MYSQL_ASSOC);
				return $row;
			}
		}

		function get_station_last_statistics($station)
		{
			$station_id = (string)$station['id'];
			if (!$last_stats = $this->cache->get_val("last_station_statistics_".$station_id))
			{
				$this->log("regenerating statistics for ". (string)$station['id']);
				$ts = meteonet_utils::get_basetime(meteonet_utils::utc_time(),'day');
				$array = $this->get_station_data_today($station,NULL);
				if (count($array)>0) {
					$last_stats = meteonet_utils::compute_fields($array,$ts,NULL);
					$this->cache->set_val("last_station_statistics_".$station_id,$last_stats);
					return $last_stats;
				} else return array();
			} else return $last_stats;
		}

		function get_station_month_statistics($station)
		{
			$station_id = (string)$station['id'];
			$date = getdate(meteonet_utils::utc_time());
			$now_month = $date["mon"];
			$now_day = $date["mday"];
			$now_year = $date["year"];
			$init_array = $this->get_station_data($station,$now_year,$now_month,NULL,NULL);
			return meteonet_utils::compute_fields($init_array,0,NULL);
		}


		function get_station_year_statistics($station)
		{
			$station_id = (string)$station['id'];
			$date = getdate(meteonet_utils::utc_time());
			$now_month = $date["mon"];
			$now_day = $date["mday"];
			$now_year = $date["year"];
			$init_array = $this->get_station_data($station,$now_year,NULL,NULL,NULL);
			return meteonet_utils::compute_fields($init_array,0,NULL);
		}

		function get_stations_last()
		{
			$array = array();
			foreach ($this->net_config->stations->station as $station) {
				$station_id = (string)$station['id'];
				$array[$station_id] = array("data"=>$this->get_station_last_data($station),
					"statistics"=>$this->get_station_last_statistics($station));
			}
			return $array;
		}

		function get_webcams_last_timestamp()
		{
			$array = array();
			foreach ($this->net_config->webcams->webcam as $webcam) {
				$webcam_id = (string)$webcam['id'];
				$array[$webcam_id] = $this->get_webcam_last_timestamp($webcam);
			}
			return $array;
		}

		/* return array with months-number of day or with day-number of day with records of webcam with id=webcam_id */
		private function get_table_exist_records($table_name,$column_name,$db_id,$year,$month=NULL,$day=NULL)
		{
			if (date_default_timezone_get() != 'UTC') date_default_timezone_set('UTC');
			if (!isset($year)) {
				$year_records = array();
				$base_year = 2012; //TODO cambiare 
				$this_year = date("Y");
				$interval1 = $base_year;
				$years = array();
				for ($i=$base_year;$i<=$this_year;$i++)
				{
					$interval1 = mktime(0, 0, 0, 1, 1, $i);
					$interval2 = $interval1 + 365*86400;
					$sql = "SELECT timestamp FROM $table_name WHERE timestamp<$interval2 AND timestamp>=$interval1 " .
						"AND $column_name=$db_id LIMIT 0,1";
					$res = $this->db_query($sql);
					$num_rows = mysqli_num_rows($res);
					if ($num_rows > 0)
						$years[] = $i;
				}
				return $years;
			} elseif (!isset($month))
			{
				$months_records = array();
				$base_year = mktime(0, 0, 0, 1, 1, $year);
				$interval1 = $base_year;
				for ($i=1;$i<=12;$i++)
				{
					$days = meteonet_utils::get_days_month($i);
					$interval2 = $interval1 + $days * 86400;
					$sql = "SELECT timestamp FROM $table_name WHERE timestamp<$interval2 AND timestamp>=$interval1 " .
						"AND $column_name=$db_id LIMIT 0,1";
					$res = $this->db_query($sql);
					$num_rows = mysqli_num_rows($res);
					if ($num_rows > 0)
						$months_records[$i] = true;
					  else
						$months_records[$i] = false;
					$interval1 = $interval2;
				}
				return $months_records;
			} elseif (isset($month) && !isset($day)) {
				$interval1 = mktime(0, 0, 0, $month, 1, $year);
				$interval2 = $interval1 + meteonet_utils::get_days_month($month)*86400;
				$sql = "SELECT DISTINCT `timestamp`-MOD(`timestamp`,86400) as day FROM $table_name WHERE ".
				"$column_name=$db_id AND timestamp<$interval2 AND timestamp>=$interval1";
				$res = $this->db_query($sql); 
				$return = array();
				while($row = mysqli_fetch_array($res,MYSQL_ASSOC)) {
					$day_timestamp = $row['day'];
					$day = ($day_timestamp  - $interval1) / 86400 + 1;
					$return[$day] = true;
				}
				$day_month = meteonet_utils::get_days_month($month);
				for ($i=1;$i<=$day_month;$i++)
					if (!(isset($return[$i])))
						$return[$i] = false;
				return $return;
			} elseif (isset($month) && isset($day)) {
				$interval1 = mktime(0, 0, 0, $month, $day, $year);
				$interval2 = $interval1 + 86400;
				$sql = "SELECT DISTINCT `timestamp`-MOD(`timestamp`,3600) as hour FROM $table_name WHERE ".
				"$column_name=$db_id AND timestamp<$interval2 AND timestamp>=$interval1";
				$res = $this->db_query($sql); 
				$return = array();
				while($row = mysqli_fetch_array($res,MYSQL_ASSOC)) {
					$hour_timestamp = $row['hour'];
					$hour = ($hour_timestamp  - $interval1) / 3600 + 1;
					$return[$hour] = true;
				}
				for ($i=1;$i<=24;$i++)
					if (!(isset($return[$i])))
						$return[$i] = false;
				return $return;
			}
		}

		function get_webcam_exist_records($webcam,$year,$month=NULL,$day=NULL)
		{
			$db_id = (string)$webcam['db_id'];
			return $this->get_table_exist_records('webcam','webcam_id',$db_id,$year,$month,$day);
		}

		function get_station_exist_records($station,$year,$month=NULL,$day=NULL)
		{
			$db_id = (string)$station['db_id'];
			if (!isset($month))
				return $this->get_table_exist_records('data_day','station_id',$db_id,$year,NULL,NULL);
			elseif (isset($month) && !isset($day))
				return $this->get_table_exist_records('data_day','station_id',$db_id,$year,$month,NULL);
			elseif (isset($month) && isset($day))
				return $this->get_table_exist_records('data_min','station_id',$db_id,$year,$month,$day);
		}

		// hours
		function get_station_exist_records_today($station)
		{
			$db_id = (string)$station['db_id'];
			if (date_default_timezone_get() != 'UTC') date_default_timezone_set('UTC');
			$date = getdate(meteonet_utils::utc_time());
			$month = $date["mon"];
			$day = $date["mday"];
			$year = $date["year"];

			return $this->get_table_exist_records('data','station_id',$db_id,$year,$month,$day);
		}

		function open_lock()
		{
			$fileLock =  METEONET_SERVER_FILE_DIR . "/lock_update"; 
			$now = time();
			if (file_exists($fileLock)) {
				// check if a lock was no correcly closed
				$t = unserialize(file_get_contents($fileLock));
				if ((time() - $t) >  60) {
					file_put_contents($fileLock,serialize($now));
					return true;
				}
				return false;
			}
			file_put_contents($fileLock,serialize($now));
			return true;
		}

		function close_lock()
		{
			$fileLock =  METEONET_SERVER_FILE_DIR . "/lock_update"; 
			unlink($fileLock);
		}

		function pre_update_db()
		{
			$dir = "/home/meteogar/public_html/webcam";
			$d = dir($dir);
			while (false !== ($entry = $d->read())) {
				if (substr($entry,0,14)=="webcam-foresta")
				{
					$filename = $dir . "/" . $entry;
					$filename_parts = explode("-",$entry);
					$time = explode("_",$filename_parts[2]); $time = $time[0];
					//echo $time ."\n";
					if (!copy($filename, METEONET_SERVER_FILE_DIR . "ftp/" . "webcam_foresta_$time.jpg")) {
					    $this->log("pre_update_db: failed to copy $file\n");
					} else unlink($filename);
				}
			}
			$d->close();
		}

		function update_db()
		{
			/* file structure
				meteo_$station_$unixtime.txt
				start_unixtime|temp_avg|temp_min|temp_max|rh|press|wind_avg|wind_max|wind_dir|rain_year|EE

				- per ogni stazione cerca i file corrispondenti e poi li interpreta a aggiunge alla tabella data
			*/

			$last_update = $this->cache->get_val("last_update");

			if ($last_update && $last_update > time()-60)
			{
				return false;
			}

			if (!$this->open_lock()) {
				$this->log("update_db: locked");
				return false;
			}

			$this->pre_update_db();
			$this->log("update_db: starting updating");
			$d = dir(METEONET_SERVER_FILE_DIR."ftp/");
			while (false !== ($entry = $d->read())) {
				$filename_parts = explode("_",$entry);
				$filename = METEONET_SERVER_FILE_DIR . "ftp/" . $entry;
			   	if ($filename_parts[0] == "meteo")
				{
					foreach ($this->net_config->stations->station as $station) {
						$station_id = (string)$station['id'];
						if ($station_id == $filename_parts[1])
						{
							$this->log("update_db $station_id: parsing ".file_get_contents($filename));
							$array_records = meteonet_utils::parse_file($filename);
							if ($array_records) {
								foreach ($array_records as $array_values) {
									$this->db_query(
									  meteonet_utils::sql_insert1((string)$station['db_id'],$array_values));
									$this->cache->set_val("last_station_" . $station_id,$array_values);
									$old_statistics = $this->cache->get_val("last_station_statistics_" . $station_id);
									if ($old_statistics) {
										 $new_statistics = meteonet_utils::compute_statistics(
										 	 $array_values,$old_statistics);

										 //$this->log("update_db $station_id: update statistics" . print_r($old_statistics,true));
										 meteonet_utils::get_basetime($array_values['timestamp'],'day');
										 $this->cache->set_val("last_station_statistics_" . $station_id,$new_statistics);
									}
								}
							} 
							unlink($filename);
						}
					}
				}
			   	if ($filename_parts[0] == "webcam")
				{
					foreach ($this->net_config->webcams->webcam as $webcam) {
						$webcam_id = (string)$webcam['id'];
						if ($webcam_id == $filename_parts[1])
						{
							//echo "webcam_found - filename:" .  $entry . " webcam:" . $webcam_id;
							
							list($timestamp,$ext) = explode (".", $filename_parts[2]);
							/*if (!is_integer($timestamp)) {
								//echo "invalid filename, break"; break;
							}*/
							if ($ext!='jpg' and $ext!='JPG') {
								//echo "invalid extension, break"; break;
							}
							if ($jpg = meteonet_utils::load_jpg(METEONET_SERVER_FILE_DIR . "ftp/" . $entry)) {
								$sql = "INSERT INTO webcam (timestamp,webcam_id,data) VALUES (".
									"$timestamp,".(string)$webcam['db_id'].",'".addslashes($jpg)."')";
								$this->cache->set_val("last_webcam_timestamp_" . $webcam_id,$timestamp);
								$this->db_query($sql);
								unlink($filename);
								$this->log("update_db: jpg file ".METEONET_SERVER_FILE_DIR ."webcam/webcam_".$webcam_id.".jpg");
								$this->get_webcam_image($webcam,$timestamp,false,METEONET_SERVER_FILE_DIR ."webcam/webcam_".$webcam_id.".jpg",$jpg);
								unset($jpg);
							} else
								$this->log("update_db: jpg file ". $entry ." not valid");
							
						}
					}
				}
			}
			$d->close();
			$this->cache->set_val("last_update",time());

			$this->close_lock();
		}

		function commit_compute_db_meteo($station_id,$table,$array,$basetime)
		{
			$sql_check = "SELECT * FROM data_$table WHERE `station_id`=$station_id AND `timestamp`=$basetime LIMIT 0,1";
			if (!$result=$this->db_query($sql_check))
				$this->log("commit_compute_db: query error");
			$num_rows = mysqli_num_rows($result);
			if ($num_rows<1) {
				$array_values = meteonet_utils::compute_fields($array,$basetime);
				$sql = "INSERT INTO data_$table (station_id,timestamp,temp,temp_min,temp_max,".
				"rh,rh_min,rh_max,press,press_min,press_max,wind_speed,wind_gust,wind_dir,".
				"rain_year,dewpoint,dewpoint_min,dewpoint_max,windchill,windchill_min,windchill_max,n) VALUES (".
				$station_id.",".
				$array_values['timestamp'].",".
				(isset($array_values['temp'])?$array_values['temp']:"NULL").",".
				(isset($array_values['temp_min'])?$array_values['temp_min']:"NULL").",".
				(isset($array_values['temp_max'])?$array_values['temp_max']:"NULL").",".
				(isset($array_values['rh'])?$array_values['rh']:"NULL").",".
				(isset($array_values['rh_min'])?$array_values['rh_min']:"NULL").",".
				(isset($array_values['rh_max'])?$array_values['rh_max']:"NULL").",".
				(isset($array_values['press'])?$array_values['press']:"NULL").",".
				(isset($array_values['press_min'])?$array_values['press_min']:"NULL").",".
				(isset($array_values['press_max'])?$array_values['press_max']:"NULL").",".
				(isset($array_values['wind_speed'])?$array_values['wind_speed']:"NULL").",".
				(isset($array_values['wind_gust'])?$array_values['wind_gust']:"NULL").",".
				(isset($array_values['wind_dir'])?$array_values['wind_dir']:"NULL").",".
				(isset($array_values['rain_year'])?$array_values['rain_year']:"NULL").",".
				(isset($array_values['dewpoint'])?$array_values['dewpoint']:"NULL").",".
				(isset($array_values['dewpoint_min'])?$array_values['dewpoint_min']:"NULL").",".
				(isset($array_values['dewpoint_max'])?$array_values['dewpoint_max']:"NULL").",".
				(isset($array_values['windchill'])?$array_values['windchill']:"NULL").",".
				(isset($array_values['windchill_min'])?$array_values['windchill_min']:"NULL").",".
				(isset($array_values['windchill_max'])?$array_values['windchill_max']:"NULL").",".
				$array_values['n'].")";
				if (!$this->db_query($sql))
					$this->log("commit_compute_db_meteo: query error");
			 } else {
				$init_row = mysqli_fetch_row($result);
				if (!is_null($init_row['n'])) {
				$array_values = meteonet_utils::compute_fields($array,$basetime,$init_row);
				$sql = "UPDATE data_$table SET station_id=$station_id, ".
				"timestamp=".$array_values['timestamp'].",".
				"temp=".(isset($array_values['temp'])?$array_values['temp']:"NULL").",".
				"temp_min=".(isset($array_values['temp_min'])?$array_values['temp_min']:"NULL").",".
				"temp_max=".(isset($array_values['temp_max'])?$array_values['temp_max']:"NULL").",".
				"rh=".(isset($array_values['rh'])?$array_values['rh']:"NULL").",".
				"rh_min=".(isset($array_values['rh_min'])?$array_values['rh_min']:"NULL").",".
				"rh_max=".(isset($array_values['rh_max'])?$array_values['rh_max']:"NULL").",".
				"press=".(isset($array_values['press'])?$array_values['press']:"NULL").",".
				"press_min=".(isset($array_values['press_min'])?$array_values['press_min']:"NULL").",".
				"press_max=".(isset($array_values['press_max'])?$array_values['press_max']:"NULL").",".
				"wind_speed=".(isset($array_values['wind_speed'])?$array_values['wind_speed']:"NULL").",".
				"wind_gust=".(isset($array_values['wind_gust'])?$array_values['wind_gust']:"NULL").",".
				"wind_dir=".(isset($array_values['wind_dir'])?$array_values['wind_dir']:"NULL").",".
				"rain_year=".(isset($array_values['rain_year'])?$array_values['rain_year']:"NULL").",".
				"dewpoint=".(isset($array_values['dewpoint'])?$array_values['dewpoint']:"NULL").",".
				"dewpoint_min=".(isset($array_values['dewpoint_min'])?$array_values['dewpoint_min']:"NULL").",".
				"dewpoint_max=".(isset($array_values['dewpoint_max'])?$array_values['dewpoint_max']:"NULL").",".
				"windchill=".(isset($array_values['windchill'])?$array_values['windchill']:"NULL").",".
				"windchill_min=".(isset($array_values['windchill_min'])?$array_values['windchill_min']:"NULL").",".
				"windchill_max=".(isset($array_values['windchill_max'])?$array_values['windchill_max']:"NULL").",".
				"n=".$array_values['n'].")";		
				if (!$this->db_query($sql))
					$this->log("commit_compute_db_meteo: query error");
				} else $this->log("commit_compute_db_meteo: error in updating row n=NULL");
			}	
			$this->log("commit_compute_db_meteo: query='".$sql."'");
			return $array_values;
		}

		function compute_db_meteo()
		{
			$compute_intervals = array(5,60*24);
			$now = meteonet_utils::utc_time();
			$now_basetime = meteonet_utils::get_basetime($now,'day');
			
			foreach ($this->net_config->stations->station as $station) {
				$station_id = (string)$station['db_id'];
				$i=0;
				while ($i<10)
				{
					$sql = "SELECT timestamp FROM data WHERE station_id=$station_id ".
						"AND timestamp<$now_basetime ORDER BY timestamp ASC LIMIT 0,1";
					$res = $this->db_query($sql);

					if (mysqli_num_rows($res)<1)
						break;

					$row = mysqli_fetch_row($res); $time = $row[0];
					$day_basetime = meteonet_utils::get_basetime($time,'day');
					$this->log("compute_db_meteo: computing day basetime ".date("jS \of F Y h:i:s A",meteonet_utils::local_time($day_basetime)));
					$day_basetime2 = $day_basetime + 86400;
					$sql = "SELECT * FROM data WHERE station_id=$station_id AND timestamp<$day_basetime2 ".
					"AND timestamp>=$day_basetime ORDER BY timestamp ASC";

					$res = $this->db_query($sql); 
					$return = array(); $day_return = array();

					$first = true; $rows = array();
					while($row=mysqli_fetch_array($res,MYSQL_ASSOC)) {
						$rows[] = $row;
					}
					mysqli_free_result($res);
					foreach ($rows as $row) {
						$mytime = $row['timestamp'];
						if ($first) { 
							$basetime = meteonet_utils::get_basetime($mytime,'5minute');
							$first = false;
						}
						if (($mytime-$basetime)<300)
						{
							$return[] = $row;
						} else {
							$compute_return = $this->commit_compute_db_meteo($station_id,'min',$return,$basetime);
							$return = array(); $return[] = $row;
							$day_return[] = $compute_return;
							$basetime = meteonet_utils::get_basetime($mytime,'5minute');
       						}
   					}
					if (!empty($return))
					{
						$compute_return = $this->commit_compute_db_meteo($station_id,'min',$return,$basetime);
						$day_return[] = $compute_return;
						$return = array();
					}

					$rows = array();
					$day_compute_return = $this->commit_compute_db_meteo($station_id,'day',$day_return,$day_basetime);
					$sql = "DELETE FROM data WHERE station_id=$station_id AND timestamp<$day_basetime2 ".
					"AND timestamp>=$day_basetime";
					$this->db_query($sql);

					$i=$i+1;
				} // end-while
			}//end-foreach
		}//end-function

		function log($txt)
		{
			meteonet_utils::log("meteonet_core - " . $txt,METEONET_SERVER_FILE_DIR . "/log.txt");
		}

		function compute_db_webcam($year,$month,$day)
		{
			$date = getdate(meteonet_utils::utc_time());
			$now_month = $date["mon"];
			$now_day = $date["mday"];
			$now_year = $date["year"];
			if ($year == $now_year && $day == $now_day && $year == $now_year) {
				$this->log('compute_db_webcam: cannot compute for today');
				return false;
			}

			if (date_default_timezone_get() != 'UTC') date_default_timezone_set('UTC');
			$day_basetime = mktime(0, 0, 0, $month, $day, $year);
			$interval1 = $day_basetime;
			$interval2 = $interval1 + 86400;

			foreach ($this->net_config->webcams->webcam as $webcam) {
				$webcam_id = (string)$webcam['db_id'];
				$shot_hour = array();

				$sql = "SELECT timestamp FROM webcam WHERE webcam_id=$webcam_id ".
					"AND timestamp>=$interval1 AND timestamp<$interval2 ORDER BY timestamp ASC";
				$res = $this->db_query($sql);

				$rows = array();
				while($row=mysqli_fetch_array($res,MYSQL_ASSOC)) {
					$rows[] = $row;
				}

				mysqli_free_result($res);

				// aggregate shots in array of hours
				foreach ($rows as $row)
				{
					$shot_time = $row['timestamp'];
					$hour = meteonet_utils::get_basetime($shot_time,'hour');
					$shot_hour[$hour][] = $shot_time;
				}
				echo "shots for " . (string)$webcam['id']."\n";
				print_r($shot_hour);

				// delete first hour from array of shot to delete
				foreach ($shot_hour as $hour=>$shots)
					unset($shot_hour[$hour][0]);



				// delete on db remaining shots
				foreach ($shot_hour as $hour=>$shots) 
					foreach ($shots as $shot) {
						$sql = "DELETE FROM webcam WHERE webcam_id=$webcam_id AND timestamp=".$shot;
						echo $sql."\n";
						$res = $this->db_query($sql);
					}	
			}
		}


	}
?>
