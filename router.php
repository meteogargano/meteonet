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
	class meteonet_router {
		var $router_dir;
		var $ser;
		var $ftp_files;
		var $net;
		var $ftp;
		function meteonet_router()
		{
			$this->router_dir = METEONET_ROUTER_FILE_DIR;
			$this->log("Starting router");
			$this->load_ser();
			
			$this->ftp_files = array();
			$this->net = new meteonet_core();
		}

		function save_ser_val($key,$val)
		{
			$this->ser[$key] = $val;
		}

		function load_ser_val($key)
		{
			if (isset($this->ser[$key])) return $this->ser[$key]; else return false;
		}


		function load_ser()
		{
			if (file_exists($this->router_dir . "/serialized.dat"))
				$this->ser = unserialize(file_get_contents($this->router_dir . "/serialized.dat"));
			   else
				$this->ser = array();
		}


		function save_ser() {
			$str = serialize($this->ser);
			file_put_contents($this->router_dir . "/serialized.dat",$str);
		}

		function log($txt)
		{
			meteonet_utils::log($txt,$this->router_dir . "/log.txt");
		}

		function add_ftp_file($filename,$content)
		{
			$this->ftp_files[] = $filename;
			file_put_contents($this->router_dir ."/ftp/".$filename,$content);
		}

		function upload_ftp()
		{
			if (empty($this->ftp_files)) {
				$this->log("warning: no files to upload");
				return false;
			}
			$conn_id = ftp_connect(METEONET_ROUTER_FTP_HOST);

			if (!$conn_id) { $this->log("Error: unable to connect to " . METEONET_ROUTER_FTP_HOST); return false; } 
			$login_result = ftp_login ($conn_id, METEONET_ROUTER_FTP_USER, METEONET_ROUTER_FTP_PASSWORD);
			if (!$login_result) { $this->log("Error: unable to login to " . METEONET_ROUTER_FTP_HOST); return false; }
			ftp_pasv ($conn_id, true) ;

			foreach ($this->ftp_files as $filename) {
				$file = $this->router_dir . "/ftp/" . $filename;
				$fp = fopen($file, 'r');
				if (!ftp_fput($conn_id, $filename, $fp, FTP_BINARY)) {
				    $this->log("Error: unable to upload $file");
				} else
					unlink($file);
				fclose($fp);
			}
			ftp_close($conn_id);
		}

		function load_url($url)
		{
			// create a new cURL resource
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$output = curl_exec($ch);
			curl_close($ch);
			return $output;
		}

		function retrieve_meteo_meteoplug($station_id,$xml) {
			$key = "old_meteoplug_$station_id";
			$output = $this->load_url((string)$xml->url);
			if ($output) {
				//$this->log("Output from meteoplug:" . $output);
				$lines =  explode("\n",$output);;
				$pieces = explode("|",$lines[0]);
				$t=$pieces[2];
				$ret = array();
				$hour = substr($t,0,2); $min = substr($t,3,2); $day=substr($t,10,2); 
				$mo = substr($t,13,2); $ye = substr($t,16,4);
				$ret['timestamp'] = meteonet_utils::utc_time(mktime($hour,$min,0,$mo,$day,$ye));
				$ret['temp']=$pieces[5];
				$ret['rh'] = $pieces[19];
				$ret['press'] = $pieces[23];
				$ret['wind_speed'] = $pieces[26];
				$ret['wind_gust'] = $pieces[26];
				$ret['wind_dir'] = meteonet_utils::literal2deg($pieces[25]);
				$ret['rain_year'] = $pieces[35];

			
				if ($this->load_ser_val($key)==$ret['timestamp']) {
					$this->log("Error in retrieving $station_id: same timestamp!");
					return false;
				}

				$this->save_ser_val($key,$ret['timestamp']);
				$str = meteonet_utils::create_meteo_string(array($ret));
				$this->add_ftp_file("meteo_$station_id"."_".$ret['timestamp'].".txt",$str);
			} else {
				$this->log("error: could not load");
				return false; 
			}
		}

		function retrieve_meteo_wddailylog($station_id,$xml) {
			$key = "old_wddailylog_$station_id";
			$output = $this->load_url((string)$xml->url);
			if (!$output) {
				$this->log("error: could not load");
				return false; 
			}
			$lines =  explode("\n",$output);
			$n = 0;
			$last_timestamp = $this->load_ser_val($key);
			$this->log("last timestamp" . $last_timestamp);			
			$new_timestamp = $last_timestamp;

			$records = array();
			foreach ($lines as $line) {
				//$this->log("line :".$line);
				if ($n++ == 0) continue;
				$ret = array();
				$pieces_ee = preg_split('/\s+/', $line);
				$pieces_ee1 = array_diff($pieces_ee, array('')); // remove empty elements
				$pieces = array();
				foreach($pieces_ee1 as $p) { $pieces[]= $p; } // avoid problems about key value (sometime the first key is 1 not 0)
				//$this->log(print_r($pieces,true));
				if (count($pieces)<16) continue;
				$day = $pieces[0];
				$mo = $pieces[1];
				$ye = $pieces[2];
				$hour = $pieces[3];
				$min = $pieces[4];
				//$this->log(print_r($pieces,true)); return;
				$ret['timestamp'] = meteonet_utils::utc_time(mktime($hour,$min,0,$mo,$day,$ye));
				$ret['temp']=$pieces[5];
				$ret['rh'] = $pieces[6];
				$ret['press'] = $pieces[8];
				$ret['wind_speed'] = $pieces[9];
				$ret['wind_gust'] = $pieces[10];
				$ret['wind_dir'] = $pieces[11]; //meteonet_utils::literal2deg($pieces[25]);
				$ret['rain_year'] = $pieces[15];

				if (!$last_timestamp || $ret['timestamp'] > $last_timestamp) {
					//$this->log("LINE $n ".$line);
					$records[] = $ret;
					$new_timestamp = $ret['timestamp'];
				}
			}
			$this->save_ser_val($key,$new_timestamp);

			if (count($records)>0) {
				$str = meteonet_utils::create_meteo_string($records);
				$this->add_ftp_file("meteo_$station_id"."_".$new_timestamp.".txt",$str);
			} else return false;
		}

		function retrieve_webcam_url($webcam_id,$xml) {
			$key = "old_webcam_url_md5_$webcam_id";
			$cont = $this->load_url((string)$xml->url);
			$md5 = md5($cont);
			if ($this->load_ser_val($key))
				if ($this->load_ser_val($key)==$md5) {
					echo("Error in retrieving $webcam_id url: same md5!");
					return false;
				}
			$time = meteonet_utils::utc_time();
			$this->save_ser_val($key,$md5);
			$this->add_ftp_file("webcam_$webcam_id"."_".$time.".jpg",$cont);
		}

		function check_time($id,$interval_min) {
			$key = "interval_".$id;
			$old_time = $this->load_ser_val($key);
			if (!$old_time || ($old_time + $interval_min*60 < time())) {
				$this->save_ser_val($key,time());
				return true;
			}
			$this->log("check false (oldtime=".date("Y-m-d H:i:s",$old_time).", interval =$interval_min)");
			
		}

		function process() {
			/* per ogni webcam-stazione che lo prevede prendi i dati tramite apposita funzione, controlla che siano diversi dai precedenti, li salva in file e li manda con ftp */
			
			// riprendi file serializzato
			$net_config = $this->net->net_config;
			
			foreach ($net_config->stations->station as $station)
			{
				if ((string)$station->router['use']=='yes')
				{
					$station_id = (string)$station['id'];
					$this->log("processing station:".$station_id.", ".(string)$station->router['type']);
					//if (!$this->check_time('station_'.$station_id,(string)$station->router['interval'])) {
					//	$this->log("station time check is false");break; }
					switch ((string)$station->router['type']) {
						case 'meteoplug':
							$this->retrieve_meteo_meteoplug($station_id,$station->router);
							break;
						case 'wddailylog':
							$this->retrieve_meteo_wddailylog($station_id,$station->router);
							break;
					}
				}
			}
			foreach ($net_config->webcams->webcam as $webcam)
			{
				if ((string)$webcam->router['use']=='yes')
				{
					$webcam_id = (string)$webcam['id'];
					$this->log("processing webcam:".$webcam_id.", ".(string)$webcam->router['type']);
					if (!$this->check_time('webcam_'.$webcam_id,(string)$webcam->router['interval'])){
						$this->log("webcam time check is false"); }
					else {
						switch ((string)$webcam->router['type']) {
							case 'url':
								$this->retrieve_webcam_url((string)$webcam['id'],$webcam->router);
								break;
						}
					}
				}
			}
			$this->upload_ftp();
			$this->save_ser();
		}

	}

?>
