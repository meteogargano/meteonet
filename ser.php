<?php
	class meteonet_ser
	{
		private $ser;
		private $file;
		function __construct($file)
		{
			$this->file = $file;
			$this->load_ser();
		}

		function __destruct()
		{
			$this->save_ser();
		}

		function set_val($key,$val)
		{
			$this->ser[$key] = $val;
		}

		function get_val($key)
		{
			if (isset($this->ser[$key])) return $this->ser[$key]; else return false;
		}


		private function load_ser()
		{
			if (file_exists($this->file))
				$this->ser = unserialize(file_get_contents($this->file));
			   else
				$this->ser = array();
		}


		private function save_ser() {
			$str = serialize($this->ser);
			file_put_contents($this->file,$str);
		}
	}

?>
