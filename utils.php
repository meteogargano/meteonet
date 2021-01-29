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
	class meteonet_utils {
		static $timezone = null;

		function utc_time($time=NULL) 
		{
			$old = date_default_timezone_get(); 
			date_default_timezone_set('UTC');
			if (isset($time))
				$ret = date("U",mktime(gmdate("H",$time), gmdate("i",$time), gmdate("s",$time), gmdate("m",$time), gmdate("d",$time), gmdate("Y",$time) ));
			  else
				$ret = date("U",mktime(gmdate("H"), gmdate("i"), gmdate("s"), gmdate("m"), gmdate("d"), gmdate("Y") ));

			date_default_timezone_set($old);
			return $ret;
		}

		function local_time($time)
		{
			if (self::$timezone == null)
			{ 
				date_default_timezone_set('Europe/Rome');
				self::$timezone = time()-self::utc_time();
//				echo date_default_timezone_get();
			} 
			return $time+self::$timezone;
		}

		function get_basetime($time,$period) //period=minute|hour|day
		{
			switch ($period) {
				case 'minute': $intval = 300; break;
				case '5minute': $intval = 300; break;
				case 'hour': $intval = 3600; break;
				case 'day': $intval = 86400; break;
			}
  			return ($time-fmod($time,$intval)); //1352160000
		}

		function get_days_month($month)
		{
			if ($month==2) { 
				if ($year % 4 == 0 && ($year % 100 != 0 || $year % 400 == 0)) // leap year?
					$days = $days + 1;
				$days = $days + 28;
			} else 
				if ($month<=7)
					$days = $days + (fmod($month,2) ? 31 : 30);
				   else
					$days = $days + (fmod($month+1,2) ? 31 : 30);
			return $days;
		}

		function parse_file($filename) 
		{
			$arr = array();
			$lines = file($filename);
			$n_lines = 0;
			foreach ($lines as $line) {
				$ret = array();
				$n_lines++;
				$pieces = explode("|",$line);
				$n = count($pieces);
				if (substr($pieces[$n-1],0,2)!="EE") {
					return false;
				}
				$ret['timestamp']= (($pieces[0]==="NULL")?NULL:$pieces[0]);
				$ret['temp']= (($pieces[1]==="NULL")?NULL:$pieces[1]);
				$ret['rh']= (($pieces[2]==="NULL")?NULL:$pieces[2]);
				$ret['press']= (($pieces[3]==="NULL")?NULL:$pieces[3]);
				$ret['wind_speed']= (($pieces[4]==="NULL")?NULL:$pieces[4]);
				$ret['wind_gust']= (($pieces[5]==="NULL")?NULL:$pieces[5]);
				$ret['wind_dir']= (($pieces[6]==="NULL")?NULL:$pieces[6]);
				$ret['rain_year']= (($pieces[7]==="NULL")?NULL:$pieces[7]);
				// http://www.ajdesigner.com/phphumidity/dewpoint_equation_dewpoint_temperature.php
				$ret['dewpoint'] = pow($ret['rh']/100,1/8)*(112+0.9*$ret['temp'])+0.1*$ret['temp']-112;
				//$ret['dewpoint']= $ret['temp'] - (100-$ret['rh'])/5;
				if (!is_null($ret['wind_speed'])){
					if ($ret['wind_speed']<5)
						$ret['windchill']=$ret['temp'];
					else {
						$v = pow($ret['wind_speed'],0.16);
						//http://www.islandnet.com/~see/weather/life/windchill.htm
						$ret['windchill']=13.112 + 0.6215*$ret['temp'] - 11.37*$v+0.3965*$ret['temp']*$v;
					}
				} else
					$ret['windchill']=NULL;
				if ($ret['temp']>50 || $ret['temp']<-30) { $ret['temp'] = NULL; $ret['dewpoint'] = NULL; $ret['windchill'] = NULL; }
				if ($ret['rh']>100) { $ret['rh'] = NULL; $ret['dewpoint'] = NULL; }
				if ($ret['press']<960 || $ret['press']>1060) $ret['press'] = NULL;
				if ($ret['rain_year']<0 || $ret['rain_year']>3000) $ret['rain_year'] = NULL;
				$arr[] = $ret;
			}
			if ($n_lines==0) return false;
			return $arr;
		}

		function create_meteo_string($array)
		{
			$str = "";
			foreach ($array as $ret) {
				if ($str != "") $str += "\n";
				$str +=	$ret['timestamp']."|".$ret['temp']."|".$ret['rh']."|".$ret['press']."|".
					$ret['wind_speed']."|".$ret['wind_gust']."|".$ret['wind_dir']."|".$ret['rain_year']."|EE";
			}
		}

		function sql_insert1($station_id,$array_values)
		{
			$sql = "INSERT INTO data (station_id,timestamp,temp,".
				"rh,press,wind_speed,wind_gust,wind_dir,rain_year,dewpoint,windchill) VALUES (".
				$station_id.",".
				$array_values['timestamp'].",".
				(isset($array_values['temp'])?$array_values['temp']:"NULL").",".
				(isset($array_values['rh'])?$array_values['rh']:"NULL").",".
				(isset($array_values['press'])?$array_values['press']:"NULL").",".
				(isset($array_values['wind_speed'])?$array_values['wind_speed']:"NULL").",".
				(isset($array_values['wind_gust'])?$array_values['wind_gust']:"NULL").",".
				(isset($array_values['wind_dir'])?$array_values['wind_dir']:"NULL").",".
				(isset($array_values['rain_year'])?$array_values['rain_year']:"NULL").",".
				(isset($array_values['dewpoint'])?$array_values['dewpoint']:"NULL").",".
				(isset($array_values['windchill'])?$array_values['windchill']:"NULL").")";
			return $sql;
		}

		function sql_insert2($station_id,$table,$array_values)
		{
			// $table = min|day
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
			return $sql;		
		}

		function literal2deg($winddir)
		{
			switch ($winddir) {
				case "E": $windd=90; break;
				case "N": $windd=0; break;
				case "NE": $windd=45; break;
				case "NNE": $windd=22; break;
				case "ENE": $windd=67; break;
				case "W": $windd=270; break;
				case "ESE": $winddd=112; break;
				case "NNW": $windd=337; break;
				case "NW": $windd=315; break;
				case "WNW": $windd=292; break;
				case "S": $windd=180; break;
				case "SSW": $windd=202; break;
				case "SW": $windd=225; break;
				case "WSW": $windd=247; break;
				case "SSE": $windd=157; break;
				case "SE": $windd=135; break;
			}
			return $windd;
		}
		function deg2literal($winddir) //TODO migliorare
		{
			if ($winddir>=0 && $winddir<22)	$windd = "N"; 
			elseif ($winddir>=22 && $winddir<45)	$windd = "NNE"; 
			elseif ($winddir>=45& $winddir<67) $windd = "NE"; 
			elseif ($winddir>=67 && $winddir<90)	$windd = "ENE"; 
			elseif ($winddir>=90 && $winddir<112)	$windd = "E"; 
			elseif ($winddir>=112 && $winddir<135)	$windd = "ESE"; 
			elseif ($winddir>=135 && $winddir<157)	$windd = "SE"; 
			elseif ($winddir>=157 && $winddir<180)	$windd = "SSE"; 
			elseif ($winddir>=180 && $winddir<202)	$windd = "S"; 
			elseif ($winddir>=202 && $winddir<225)	$windd = "SSW"; 
			elseif ($winddir>=225 && $winddir<247)	$windd = "SW"; 
			elseif ($winddir>=247 && $winddir<270)	$windd = "WSW"; 
			elseif ($winddir>=270 && $winddir<292)	$windd = "W"; 
			elseif ($winddir>=292 && $winddir<315)	$windd = "WNW"; 
			elseif ($winddir>=315 && $winddir<337)	$windd = "NW"; 
			elseif ($winddir>=337 && $winddir<360)	$windd = "NNW";
			 
			return $windd;
		}

		function compute_fields($array,$basetime,$init=NULL)
		{
			if (isset($init)) {
			  // nel caso in cui il vettore init proviene dalla tabella data e non da data_min/data_day
			  if (!isset($init['temp_min'])) $init['temp_min'] = $init['temp'];
			  if (!isset($init['temp_max'])) $init['temp_max'] = $init['temp'];
			  if (!isset($init['press_min'])) $init['press_min'] = $init['press'];
			  if (!isset($init['press_max'])) $init['press_max'] = $init['press'];
			  if (!isset($init['rh_min'])) $init['rh_min'] = $init['rh'];
			  if (!isset($init['rh_max'])) $init['rh_max'] = $init['rh'];
			  if (!isset($init['windchill_min'])) $init['windchill_min'] = $init['windchill'];
			  if (!isset($init['windchill_max'])) $init['windchill_max'] = $init['windchill'];
			  if (!isset($init['dewpoint_min'])) $init['dewpoint_min'] = $init['dewpoint'];
			  if (!isset($init['dewpoint_max'])) $init['dewpoint_max'] = $init['dewpoint'];
			  //if (!isset($init['n'])) $init['n'] = 0;
			  $n = $init['n'];
			  $temp_avg = $init['temp']*$n; $temp_min = $init['temp_min']; $temp_max = $init['temp_max'];
			  $press_avg = $init['press']*$n; $press_min = $init['press_min']; $press_max = $init['press_max'];
			  $rh_avg = $init['rh']*$n; $rh_min = $init['rh_min']; $rh_max = $init['rh_max'];
			  $windchill_avg=$init['windchill']*$n;$windchill_min=$init['windchill_min'];$windchill_max = $init['windchill_max'];
			  $dewpoint_avg = $init['dewpoint']*$n; $dewpoint_min = $init['dewpoint_min']; $dewpoint_max = $init['dewpoint_max'];
			  $wind_speed = $init['wind_speed']*$n; $wind_gust = $init['wind_gust']; $wind_dir = $init['wind_dir']*$n;
			  $rain_year = $init['rain_year'];
			  $rain_init = $init['rain_init'];
			} else {
			  $temp_avg = 0; $temp_min = 100; $temp_max = -100;
			  $press_avg = 0; $press_min = 10000; $press_max = -100;
			  $rh_avg = 0; $rh_min = 100; $rh_max = -100;
			  $windchill_avg = 0; $windchill_min = 100; $windchill_max = -100;
			  $dewpoint_avg = 0; $dewpoint_min = 100; $dewpoint_max = -100;
			  $wind_speed = 0; $wind_gust = 0; $wind_dir = 0;
			  $rain_year = 0; $rain_init = 10000;
			  $n=0;
			} 

			$null = array('temp'=>false,'rh'=>false,'rain_year'=>false,'press'=>false,'wind_speed'=>false,
				'wind_gust'=>false,'wind_dir'=>false,'windchill'=>false,'dewpoint'=>false);

			foreach ($array as $row)
			{
			$n++;
			if (is_null($row['temp'])) {$null['temp']=true;$null['dewpoint']= true;$null['windchill']= true;}
			if (is_null($row['rh'])) { $null['rh']=true; $null['dewpoint']= true;}
			if (is_null($row['wind_speed'])) {$null['wind_speed']=true;$null['windchill']= true;}
			if (is_null($row['wind_gust'])) $null['wind_gust']=true;
			if (is_null($row['wind_dir'])) $null['wind_dir']=true;

			if (!$null['temp']) $temp_avg = $temp_avg + $row['temp'];
			if (!$null['press']) $press_avg = $press_avg + $row['press'];
			if (!$null['rh']) $rh_avg = $rh_avg + $row['rh'];
			if (!$null['windchill']) $windchill_avg = $windchill_avg + $row['windchill'];
			if (!$null['dewpoint']) $dewpoint_avg = $dewpoint_avg + $row['dewpoint'];
			if (!$null['wind_speed']) $wind_speed = $wind_speed + $row['wind_speed'];
			if (!$null['wind_dir']) $wind_dir = $wind_dir + $row['wind_dir'];

			if (!$null['temp']) $old_temp_min = (isset($row['temp_min']) ? $row['temp_min'] : $row['temp']);
			if (!$null['press']) $old_press_min = (isset($row['press_min']) ? $row['press_min'] : $row['press']);
			if (!$null['rh']) $old_rh_min = (isset($row['rh_min']) ? $row['rh_min'] : $row['rh']);
			if (!$null['windchill']) $old_windchill_min = (isset($row['windchill_min']) ? $row['windchill_min'] : $row['windchill']);
			if (!$null['dewpoint']) $old_dewpoint_min = (isset($row['dewpoint_min']) ? $row['dewpoint_min'] : $row['dewpoint']);

			if (!$null['temp']) $old_temp_max = (isset($row['temp_max']) ? $row['temp_max'] : $row['temp']);
			if (!$null['press']) $old_press_max = (isset($row['press_max']) ? $row['press_max'] : $row['press']);
			if (!$null['rh']) $old_rh_max = (isset($row['rh_max']) ? $row['rh_max'] : $row['rh']);
			if (!$null['windchill'])$old_windchill_max=(isset($row['windchill_max'])?$row['windchill_max']:$row['windchill']);
			if (!$null['dewpoint']) $old_dewpoint_max = (isset($row['dewpoint_max'])?$row['dewpoint_max'] : $row['dewpoint']);

			if (!$null['temp']) $temp_min = ($temp_min>$old_temp_min ? $old_temp_min : $temp_min);
			if (!$null['press']) $press_min = ($press_min>$old_press_min ? $old_press_min : $press_min);
			if (!$null['rh']) $rh_min = ($rh_min>$old_rh ? $old_rh_min : $rh_min);
			if (!$null['windchill']) $windchill_min = ($windchill_min>$old_windchill_min ? $old_windchill_min:$windchill_min);
			if (!$null['dewpoint']) $dewpoint_min = ($dewpoint_min>$old_dewpoint_min ? $old_dewpoint_min : $dewpoint_min);
			if (!$null['temp']) $temp_max = ($temp_max<$old_temp_max ? $old_temp_max : $temp_max);
			if (!$null['press']) $press_max = ($press_max<$old_press_max ? $old_press_max : $press_max);
			if (!$null['rh']) $rh_max = ($rh_max<$old_rh_max ? $old_rh_max : $rh_max);
			if (!$null['windchill']) $windchill_max = ($windchill_max<$old_windchill_max?$old_windchill_max : $windchill_max);
			if (!$null['dewpoint']) $dewpoint_max = ($dewpoint_max<$old_dewpoint_max?$old_dewpoint_max:$dewpoint_max);

			if (!$null['rain_year']) {
				$rain_year = ($rain_year<$row['rain_year'] ? $row['rain_year'] : $rain_year);
				$rain_init = ($rain_init>$row['rain_year'] ? $row['rain_year'] : $rain_init);
			}
			if (!$null['wind_gust']) $wind_gust = ($wind_gust<$row['wind_gust'] ? $row['wind_gust'] : $wind_gust);
			}
			$ris = array("temp"=>NULL,"temp_min"=>NULL, "temp_max"=>NULL,
					"press"=>NULL,"press_min"=>NULL, "press_max"=>NULL, 
					"dewpoint"=>NULL,"dewpoint_min"=>NULL, "dewpoint_max"=>NULL, 
					"rh"=>NULL,"rh_min"=>NULL, "rh_max"=>NULL, 
					"windchill"=>NULL,"windchill_min"=>NULL, "windchill_max"=>NULL, 
					"wind_speed"=>NULL,"wind_gust"=>NULL, "wind_dir"=>NULL, "rain_year"=>NULL,"rain_init"=>NULL);
			if (!$null['temp']) {$ris['temp'] = $temp_avg / $n; $ris['temp_min']=$temp_min;$ris['temp_max']=$temp_max;}
			if (!$null['press']) {$ris['press'] = $press_avg / $n; $ris['press_min']=$press_min;$ris['press_max']=$press_max;}
			if (!$null['dewpoint']) {$ris['dewpoint'] = $dewpoint_avg / $n;$ris['dewpoint_min']=$dewpoint_min;$ris['dewpoint_max']=$dewpoint_max;}
			if (!$null['rh']) { $ris['rh'] = $rh_avg / $n;$ris['rh_min']=$rh_min;$ris['rh_max']=$rh_max;}
			if (!$null['windchill']) {$ris['windchill'] = $windchill_avg / $n;$ris['windchill_min']=$windchill_min;$ris['windchill_max']=$windchill_max;}
			if (!$null['wind_speed']) {$ris['wind_speed'] = $wind_speed / $n;}
			if (!$null['wind_dir']) {$ris['wind_dir'] = $wind_dir / $n;}
			if (!$null['wind_gust']) {$ris['wind_gust'] = $wind_gust;}
			if (!$null['rain_year']) {$ris['rain_year'] = $rain_year;$ris['rain_init'] = $rain_init;}

			$ris['n'] = $n;
			$ris['timestamp']=$basetime;
			return $ris;
		}

		function load_jpg($filename)
		{
			$jpgdata = file_get_contents($filename);
		// http://stackoverflow.com/questions/1459882/check-manually-for-jpeg-end-of-file-marker-ffd9-in-php-to-catch-truncation-e
			if (substr($jpgdata,-2)!="\xFF\xD9") {
			  echo 'Bad file'; return false;
			}
			return $jpgdata;
		}

		function log($txt,$file=NULL)
		{
			$data = date(DATE_RFC822) . ": " . $txt . "\n";
			if (!isset($file)) echo $data; 
			  else
			    file_put_contents($file ,$data,FILE_APPEND);
		}

		function resize_image($img_src)
		{
			define('THUMBNAIL_IMAGE_MAX_WIDTH', 600);
			define('THUMBNAIL_IMAGE_MAX_HEIGHT', 600);

			list($source_image_width, $source_image_height, $source_image_type) = getimagesize($img_src);

			$source_gd_image = imagecreatefromjpeg($img_src);
    			$source_aspect_ratio = $source_image_width / $source_image_height;
    			$thumbnail_aspect_ratio = THUMBNAIL_IMAGE_MAX_WIDTH / THUMBNAIL_IMAGE_MAX_HEIGHT;
    			if ($source_image_width <= THUMBNAIL_IMAGE_MAX_WIDTH && $source_image_height <= THUMBNAIL_IMAGE_MAX_HEIGHT) {
        			$thumbnail_image_width = $source_image_width;
        			$thumbnail_image_height = $source_image_height;
    			} elseif ($thumbnail_aspect_ratio > $source_aspect_ratio) {
        			$thumbnail_image_width = (int) (THUMBNAIL_IMAGE_MAX_HEIGHT * $source_aspect_ratio);
        			$thumbnail_image_height = THUMBNAIL_IMAGE_MAX_HEIGHT;
    			} else {
        			$thumbnail_image_width = THUMBNAIL_IMAGE_MAX_WIDTH;
        			$thumbnail_image_height = (int) (THUMBNAIL_IMAGE_MAX_WIDTH / $source_aspect_ratio);
    			}
    			$thumbnail_gd_image = imagecreatetruecolor($thumbnail_image_width, $thumbnail_image_height);
    			imagecopyresampled($thumbnail_gd_image, $source_gd_image, 0, 0, 0, 0, $thumbnail_image_width, $thumbnail_image_height, $source_image_width, $source_image_height);
    			imagejpeg($thumbnail_gd_image, $img_src, 90);
    			imagedestroy($source_gd_image);
    			imagedestroy($thumbnail_gd_image);
    			return file_get_contents($img_src);
		}

		function img_text($text,$x,$y,$img,$color,$color_shadow=NULL)
		{
			define('FONT','ARIALN.TTF');
			define('FONT_SIZE',17);
			$fonts_path = METEONET_SERVER_FILE_DIR ."fonts/";

			if (isset($color_shadow))
				imagettftext($img, FONT_SIZE, 0, $x+2, $y+2,$color_shadow, $fonts_path . FONT, $text);

			imagettftext($img, FONT_SIZE, 0, $x, $y,$color, $fonts_path . FONT, $text);

		}

		function pixelizate($img,$width,$height)
		{
			$newImg = imagecreatetruecolor($width,$height);
			imagecopyresized($newImg,$img,0,0,0,0,round($width / 10),round($height / 10),$width,$height);
			//Create 100% version ... blow it back up to it's initial size:
			//$newImg2 = imagecreatetruecolor($width,$height);
			imagecopyresized($img,$newImg,0,0,0,0,$width,$height,round($width / 10),round($height / 10));
			imagedestroy($newImg);
		}

		function process_image($img_file,$webcam,$timestamp,$meteo_str)
		{
			date_default_timezone_set('Europe/Rome');
			define('TEXTROW_HEIGHT', 30);
			define('LOGO_MARGIN',30);
			$edit_config = $webcam->edit;
			if ($edit_config['use']=='no')
				return file_get_contents($img_file);
			list($src_width, $src_height) = getimagesize($img_file);
			$dst_width = $src_width-(int)$edit_config->crop['left']-(int)$edit_config->crop['right'];
			$dst_height = $src_height-(int)$edit_config->crop['top']-(int)$edit_config->crop['bottom']; 
			$dst_img = imagecreatetruecolor ($dst_width,$dst_height+TEXTROW_HEIGHT);
			$src_img = imagecreatefromjpeg($img_file);
			imagecopy ($dst_img , $src_img , 0,0 , (int)$edit_config->crop['left'], 
			   (int)$edit_config->crop['top'], $dst_width,$dst_height);
			imagedestroy($src_img);
			if (isset($edit_config->pixelization)) {
				$p_left=(int)$edit_config->pixelization['left'];
				$p_right=(int)$edit_config->pixelization['right'];
				$p_top=(int)$edit_config->pixelization['top'];
				$p_bottom=(int)$edit_config->pixelization['bottom'];
				$pxl_img = imagecreatetruecolor($p_right-$p_left,$p_bottom-$p_top);
				imagecopy($pxl_img,$dst_img,0,0,$p_left,$p_top,$p_right-$p_left,$p_bottom-$p_top);
				meteonet_utils::pixelizate($pxl_img,$p_right-$p_left,$p_bottom-$p_top);
				imagecopy ($dst_img,$pxl_img,$p_left,$p_top,0,0,$p_right-$p_left,$p_bottom-$p_top);
				imagedestroy($pxl_img);
			}
			$logo_main = METEONET_SERVER_FILE_DIR . "webcam_logo/logo.png";
			if (file_exists($logo_main)) {
				list($logo_width, $logo_height) = getimagesize($logo_main);
				$logo_img = imagecreatefrompng($logo_main);
				imagecopy ($dst_img , $logo_img , $dst_width-$logo_width-LOGO_MARGIN, LOGO_MARGIN ,
				 0,0, $logo_width,$logo_height);
				imagedestroy($logo_img);
			}
			$logo = METEONET_SERVER_FILE_DIR . "webcam_logo/".(string)$webcam['id'].".png";
			if (file_exists($logo)) {
				list($logo_width, $logo_height) = getimagesize($logo);
				$logo_img = imagecreatefrompng($logo);
				imagecopy ($dst_img , $logo_img , $dst_width-$logo_width-LOGO_MARGIN, 
				   $dst_height-$logo_height-LOGO_MARGIN ,0,0, $logo_width,$logo_height);
				imagedestroy($logo_img);
			}
			$color_white = imagecolorallocate ($dst_img ,255,255,255);
			$color_black = imagecolorallocate ($dst_img ,0,0,0);
			meteonet_utils::img_text((string)$edit_config->title,LOGO_MARGIN,LOGO_MARGIN,$dst_img,$color_white,$color_black);
			meteonet_utils::img_text( date("d/m/y H:i:s",$timestamp),LOGO_MARGIN,LOGO_MARGIN*2,$dst_img,$color_white,$color_black);

			meteonet_utils::img_text($meteo_str,LOGO_MARGIN,$dst_height+TEXTROW_HEIGHT-5,$dst_img,$color_white);
			imagejpeg($dst_img, $img_file, 90);
			imagedestroy($dst_img);
			date_default_timezone_set('UTC');
			return file_get_contents($img_file);
		}


		function compute_statistics($array_values,$old_statistics)
		{
			// controlla se il giorno è lo stesso, altrimenti resetta
			$ret = array();
			$ts = meteonet_utils::get_basetime($array_values['timestamp'],'day');
			$old_ts = 0;
			if (isset($old_statistics['timestamp']))
			  	$old_ts = meteonet_utils::get_basetime($old_statistics['timestamp'],'day');

			if ($old_ts < $ts || $ts==0)
			{
				$ret = meteonet_utils::compute_fields(array($array_values),$ts);
			} elseif ($old_ts == $ts) {
				$ret = meteonet_utils::compute_fields(array($array_values),$ts,$old_statistics);
			} else
				return $old_statistics;
			return $ret;
		}
	}
?>
