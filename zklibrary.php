<?php
//error_reporting(0);

define('CMD_CONNECT', 1000);
define('CMD_EXIT', 1001);
define('CMD_ENABLEDEVICE', 1002);
define('CMD_DISABLEDEVICE', 1003);
define('CMD_RESTART', 1004);
define('CMD_POWEROFF', 1005);
define('CMD_SLEEP', 1006);
define('CMD_RESUME', 1007);
define('CMD_TEST_TEMP', 1011);
define('CMD_TESTVOICE', 1017);
define('CMD_VERSION', 1100);
define('CMD_CHANGE_SPEED', 1101);

define('CMD_ACK_OK', 2000);
define('CMD_ACK_ERROR', 2001);
define('CMD_ACK_DATA', 2002);
define('CMD_PREPARE_DATA', 1500);
define('CMD_DATA', 1501);

define('CMD_USER_WRQ', 8);
define('CMD_USERTEMP_RRQ', 9);
define('CMD_USERTEMP_WRQ', 10);
define('CMD_OPTIONS_RRQ', 11);
define('CMD_OPTIONS_WRQ', 12);
define('CMD_ATTLOG_RRQ', 13);
define('CMD_CLEAR_DATA', 14);
define('CMD_CLEAR_ATTLOG', 15);
define('CMD_DELETE_USER', 18);
define('CMD_DELETE_USERTEMP', 19);
define('CMD_CLEAR_ADMIN', 20);
define('CMD_ENABLE_CLOCK', 57);
define('CMD_STARTVERIFY', 60);
define('CMD_STARTENROLL', 61);
define('CMD_CANCELCAPTURE', 62);
define('CMD_STATE_RRQ', 64);
define('CMD_WRITE_LCD', 66);
define('CMD_CLEAR_LCD', 67);

define('CMD_GET_TIME', 201);
define('CMD_SET_TIME', 202);

define('USHRT_MAX', 65535);

define('LEVEL_USER', 0);          // 0000 0000
define('LEVEL_ENROLLER', 2);       // 0000 0010
define('LEVEL_MANAGER', 12);      // 0000 1100
define('LEVEL_SUPERMANAGER', 14); // 0000 1110


class ZKLibrary {
	public $ip = null;
	public $port = null;
	public $socket = null;
	public $protocol = null;
	public $session_id = 0;
	public $received_data = '';
	public $start_data = 0;
	public $user_data = array();
	public $attendance_data = array();
	public $timeout_sec = 5;
	public $timeout_usec = 500000;

	public function __construct($ip = null, $port = null, $protocol = 'UDP')
	{
		if($ip != null)
		{
			$this->ip = $ip;
		}
		if($port != null)
		{
			$this->port = $port;
		}
		$this->protocol = $protocol;
		if ($protocol == 'TCP') {
			$this->start_data = 8;
			$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			//$this->setTimeout($this->sec, $this->usec);
			socket_connect($this->socket, $ip, $port);
		}
		else {
			$this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			$this->setTimeout($this->sec, $this->usec);
		}
	}
	public function __destruct()
	{
		unset($this->received_data);
		unset($this->user_data);
		unset($this->attendance_data);
	}
	public function send($buf) {
		if ($this->protocol == 'TCP') {
			socket_write($this->socket, $buf, strlen($buf));
		}
		else {
			socket_sendto($this->socket, $buf, strlen($buf), 0, $this->ip, $this->port);
		}
	}
	public function recv($length = 1024) {
		$data = '';
		if ($this->protocol == 'TCP') {
			$data = socket_read($this->socket, $length);
		}
		else {
			socket_recvfrom($this->socket, $data, $length, 0, $this->ip, $this->port);
		}
		return $data;
	}
	public function connect($ip = null, $port = 4370)
	{
		if($ip != null)
		{
			$this->ip = $ip;
		}
		if($port != null)
		{
			$this->port = $port;
		}
		if($this->ip == null || $this->port == null)
		{
			return false;
		}
		$command = CMD_CONNECT;
		$command_string = '';
		$chksum = 0;
		$session_id = 0;
		$reply_id = -1 + USHRT_MAX;
		$buf = $this->createHeader($command, $chksum, $session_id, $reply_id, $command_string);
		$this->send($buf);
		try
		{
			$this->received_data = $this->recv();
			if(strlen($this->received_data)>0)
			{
				$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6', substr($this->received_data, $this->start_data, 8));
				$this->session_id = hexdec($u['h6'].$u['h5']);
				return $this->checkValid($this->received_data);
			}
			else
			{
				return FALSE;
			}
		}
		catch(ErrorException $e)
		{
			return FALSE;
		}
		catch(exception $e)
		{
			return FALSE;
		}
	}
	public function disconnect()
	{
		if($this->ip == null || $this->port == null)
		{
			return false;
		}
		$command = CMD_EXIT;
		$command_string = '';
		$chksum = 0;
		$session_id = $this->session_id;
		$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($this->received_data, $this->start_data, 8));
		$reply_id = hexdec( $u['h8'].$u['h7'] );
		$buf = $this->createHeader($command, $chksum, $session_id, $reply_id, $command_string);
		$this->send($buf);
		try
		{
			$this->received_data = $this->recv();
			return $this->checkValid($this->received_data);
		}
		catch(ErrorException $e)
		{
			return FALSE;
		}
		catch(Exception $e)
		{
			return FALSE;
		}
	}
	public function setTimeout($sec = 0, $usec = 0)
	{
		if($sec != 0)
		{
			$this->timeout_sec = $sec;
		}
		if($usec != 0)
		{
			$this->timeout_usec = $usec;
		}
		$timeout = array('sec'=>$this->timeout_sec, 'usec'=>$this->timeout_usec);
		socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, $timeout);
	}
	public function ping($timeout = 1)
	{
		$time1 = microtime(true);
		$pfile = fsockopen($this->ip, $this->port, $errno, $errstr, $timeout);
		if(!$pfile)
		{
			return 'down';
		}
		$time2 = microtime(true);
		fclose($pfile);
		return round((($time2 - $time1) * 1000), 0);
	}
	private function reverseHex($input)
	{
		$output = '';
		for($i=strlen($input); $i>=0; $i--)
		{
			$output .= substr($input, $i, 2);
			$i--;
		}
		return $output;
	}
	private function encodeTime($time)
	{
		$str = str_replace(array(":", " "), array("-", "-"), $time);
		$arr = explode("-", $str);
		$year = @$arr[0]*1;
		$month = ltrim(@$arr[1], '0')*1;
		$day = ltrim(@$arr[2], '0')*1;
		$hour = ltrim(@$arr[3], '0')*1;
		$minute = ltrim(@$arr[4], '0')*1;
		$second = ltrim(@$arr[5], '0')*1;
		$data = (($year % 100) * 12 * 31 + (($month - 1) * 31) + $day - 1) * (24 * 60 * 60) + ($hour * 60 + $minute) * 60 + $second;
		return $data;
	}
	private function decodeTime($data)
	{
		$second = $data % 60;
		$data = $data / 60;
		$minute = $data % 60;
		$data = $data / 60;
		$hour = $data % 24;
		$data = $data / 24;
		$day = $data % 31+1;
		$data = $data / 31;
		$month = $data % 12+1;
		$data = $data / 12;
		$year = floor( $data + 2000 );
		$d = date("Y-m-d H:i:s", strtotime($year.'-'.$month.'-'.$day.' '.$hour.':'.$minute.':'.$second));
		return $d;

	}
	private function checkSum($p)
	{
		/* This function calculates the chksum of the packet to be sent to the time clock */
		$l = count($p);
		$chksum = 0;
		$i = $l;
		$j = 1;
		while($i > 1)
		{
			$u = unpack('S', pack('C2', $p['c'.$j], $p['c'.($j+1)]));
			$chksum += $u[1];
			if($chksum > USHRT_MAX)
			{
				$chksum -= USHRT_MAX;
			}
			$i-=2;
			$j+=2;
		}
		if($i)
		{
			$chksum = $chksum + $p['c'.strval(count($p))];
		}
		while ($chksum > USHRT_MAX)
		{
			$chksum -= USHRT_MAX;
		}
		if ( $chksum > 0 )
		{
			$chksum = -($chksum);
		}
		else
		{
			$chksum = abs($chksum);
		}
		$chksum -= 1;
		while ($chksum < 0)
		{
			$chksum += USHRT_MAX;
		}
		return pack('S', $chksum);
	}
	function createHeader($command, $chksum, $session_id, $reply_id, $command_string)
	{
		$buf = pack('SSSS', $command, $chksum, $session_id, $reply_id).$command_string;
		$len = strlen($buf);
		$buf = unpack('C'.(8+strlen($command_string)).'c', $buf);
		$u = unpack('S', $this->checkSum($buf));
		if(is_array($u))
		{
			while(list($key) = each($u))
			{
				$u = $u[$key];
				break;
			}
		}
		$chksum = $u;
		$reply_id += 1;
		if($reply_id >= USHRT_MAX)
		{
			$reply_id -= USHRT_MAX;
		}
		if ($this->protocol == 'TCP') {
            $buf = pack('SSLSSSS', 20560, 32130, $len, $command, $chksum, $session_id, $reply_id);
        }
        else {
            $buf = pack('SSSS', $command, $chksum, $session_id, $reply_id);
        }
		return $buf.$command_string;
	}
	private function checkValid($reply)
	{
		$u = unpack('H2h1/H2h2', substr($reply, $this->start_data, 8));
		$command = hexdec( $u['h2'].$u['h1'] );
		if ($command == CMD_ACK_OK)
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	function execCommand($command, $command_string = '', $offset_data = 8)
	{
		$chksum = 0;
		$offset_data += $this->start_data;
		$session_id = $this->session_id;
		$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr( $this->received_data, $this->start_data, 8) );
		$reply_id = hexdec( $u['h8'].$u['h7'] );
		$buf = $this->createHeader($command, $chksum, $session_id, $reply_id, $command_string);
		$this->send($buf);
		try
		{
			$this->received_data = $this->recv();
			$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6', substr( $this->received_data, $this->start_data, 8 ) );
			$this->session_id =  hexdec( $u['h6'].$u['h5'] );
			return substr($this->received_data, $offset_data);
		}
		catch(ErrorException $e)
		{
			return FALSE;
		}
		catch(exception $e)
		{
			return FALSE;
		}
	}
	private function getSizeUser()
	{
		$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($this->received_data, $this->start_data, 8));
		$command = hexdec($u['h2'].$u['h1']);
		if($command == CMD_PREPARE_DATA)
		{
			$u = unpack('H2h1/H2h2/H2h3/H2h4', substr($this->received_data, $this->start_data + 8, 4));
			$size = hexdec($u['h4'].$u['h3'].$u['h2'].$u['h1']);
			return $size;
		}
		else
		{
			return FALSE;
		}
	}
	private function getSizeAttendance()
	{
		$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($this->received_data, $this->start_data, 8));
		$command = hexdec($u['h2'].$u['h1'] );
		if($command == CMD_PREPARE_DATA)
		{
			$u = unpack('H2h1/H2h2/H2h3/H2h4', substr( $this->received_data, $this->start_data + 8, 4));
			$size = hexdec($u['h4'].$u['h3'].$u['h2'].$u['h1']);
			return $size;
		}
		else
		{
			return FALSE;
		}
	}
	private function getSizeTemplate()
	{
		$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($this->received_data, $this->start_data, 8));
		$command = hexdec($u['h2'].$u['h1'] );
		if($command == CMD_PREPARE_DATA)
		{
			$u = unpack('H2h1/H2h2/H2h3/H2h4', substr( $this->received_data, $this->start_data + 8, 4));
			$size = hexdec($u['h4'].$u['h3'].$u['h2'].$u['h1']);
			return $size;
		}
		else
		{
			return FALSE;
		}
	}
	public function restartDevice()
	{
		$command = CMD_RESTART;
		$command_string = chr(0).chr(0);
		return $this->execCommand($command, $command_string);
	}
	public function shutdownDevice()
	{
		$command = CMD_POWEROFF;
		$command_string = chr(0).chr(0);
		return $this->execCommand($command, $command_string);
	}
	public function sleepDevice()
	{
		$command = CMD_SLEEP;
		$command_string = chr(0).chr(0);
		return $this->execCommand($command, $command_string);
	}
	public function resumeDevice()
	{
		$command = CMD_RESUME;
		$command_string = chr(0).chr(0);
		return $this->execCommand($command, $command_string);
	}
	public function changeSpeed($speed = 0)
	{
		if($speed != 0)
		{
			$speed = 1;
		}
		$command = CMD_CHANGE_SPEED;
		$byte = chr($speed);
		$command_string = $byte;
		return $this->execCommand($command, $command_string);
	}
	public function writeLCD($rank, $text)
	{
		$command = CMD_WRITE_LCD;
		$byte1 = chr((int)($rank % 256));
		$byte2 = chr((int)($rank >> 8));
		$byte3 = chr(0);
		$command_string = $byte1.$byte2.$byte3.' '.$text;
		return $this->execCommand($command, $command_string);
	}
	public function clearLCD()
	{
		$command = CMD_CLEAR_LCD;
		return $this->execCommand($command);
	}
	public function testVoice()
	{
		$command = CMD_TESTVOICE;
		$command_string = chr(0).chr(0);
		return $this->execCommand($command, $command_string);
	}
	public function getVersion()
	{
		$command = CMD_VERSION;
		return $this->execCommand($command);
	}
	public function getOSVersion($net = true)
	{
		$command = CMD_OPTIONS_RRQ;
		$command_string = '~OS';
		$return = $this->execCommand($command, $command_string);
		if($net)
		{
			$arr = explode("=", $return, 2);
			return $arr[1];
		}
		else
		{
			return $return;
		}
	}
	public function setOSVersion($osVersion)
	{
		$command = CMD_OPTIONS_WRQ;
		$command_string = '~OS='.$osVersion;
		return $this->execCommand($command, $command_string);
	}
	public function getPlatform($net = true)
	{
		$command = CMD_OPTIONS_RRQ;
		$command_string = '~Platform';
		$return = $this->execCommand($command, $command_string);
		if($net)
		{
			$arr = explode("=", $return, 2);
			return $arr[1];
		}
		else
		{
			return $return;
		}
	}
	public function setPlatform($patform)
	{
		$command = CMD_OPTIONS_RRQ;
		$command_string = '~Platform='.$patform;
		return $this->execCommand($command, $command_string);
	}
	public function getFirmwareVersion($net = true)
	{
		$command = CMD_OPTIONS_RRQ;
		$command_string = '~ZKFPVersion';
		$return = $this->execCommand($command, $command_string);
		if($net)
		{
			$arr = explode("=", $return, 2);
			return $arr[1];
		}
		else
		{
			return $return;
		}
	}
	public function setFirmwareVersion($firmwareVersion)
	{
		$command = CMD_OPTIONS_WRQ;
		$command_string = '~ZKFPVersion='.$firmwareVersion;
		return $this->execCommand($command, $command_string);
	}
	public function getWorkCode($net = true)
	{
		$command = CMD_OPTIONS_RRQ;
		$command_string = 'WorkCode';
		$return = $this->execCommand($command, $command_string);
		if($net)
		{
			$arr = explode("=", $return, 2);
			return $arr[1];
		}
		else
		{
			return $return;
		}
	}
	public function setWorkCode($workCode)
	{
		$command = CMD_OPTIONS_WRQ;
		$command_string = 'WorkCode='.$workCode;
		return $this->execCommand($command, $command_string);
	}
	public function getSSR($net = true)
	{
		$command = CMD_OPTIONS_PRQ;
		$command_string = '~SSR';
		$return = $this->execCommand($command, $command_string);
		if($net)
		{
			$arr = explode("=", $return, 2);
			return $arr[1];
		}
		else
		{
			return $return;
		}
	}
	public function setSSR($ssr)
	{
		$command = CMD_OPTIONS_WRQ;
		$command_string = '~SSR='.$ssr;
		return $this->execCommand($command, $command_string);
	}
	public function getPinWidth()
	{
		$command = CMD_GET_PINWIDTH;
		$command = CMD_OPTIONS_PRQ;
		$command_string = '~PIN2Width';
		$return = $this->execCommand($command, $command_string);
		if($net)
		{
			$arr = explode("=", $return, 2);
			return $arr[1];
		}
		else
		{
			return $return;
		}
	}
	public function setPinWidth($pinWidth)
	{
		$command = CMD_OPTIONS_WRQ;
		$command_string = '~PIN2Width='.$pinWidth;
		return $this->execCommand($command, $command_string);
	}
	public function getFaceFunctionOn($net = true)
	{
		$command = CMD_OPTIONS_RRQ;
		$command_string = 'FaceFunOn';
		$return = $this->execCommand($command, $command_string);
		if($net)
		{
			$arr = explode("=", $return, 2);
			return $arr[1];
		}
		else
		{
			return $return;
		}
	}
	public function setFaceFunctionOn($faceFunctionOn)
	{
		$command = CMD_OPTIONS_WRQ;
		$command_string = 'FaceFunOn='.$faceFunctionOn;
		return $this->execCommand($command, $command_string);
	}
	public function getSerialNumber($net = true)
	{
		$command = CMD_OPTIONS_RRQ;
		$command_string = '~SerialNumber';
		$return = $this->execCommand($command, $command_string);
		if($net)
		{
			$arr = explode("=", $return, 2);
			return $arr[1];
		}
		else
		{
			return $return;
		}
	}
	public function setSerialNumber($serialNumber)
	{
		$command = CMD_OPTIONS_WRQ;
		$command_string = '~SerialNumber='.$serialNumber;
		return $this->execCommand($command, $command_string);
	}
	public function getDeviceName($net = true)
	{
		$command = CMD_OPTIONS_RRQ;
		$command_string = '~DeviceName';
		$return = $this->execCommand($command, $command_string);
		if($net)
		{
			$arr = explode("=", $return, 2);
			return $arr[1];
		}
		else
		{
			return $return;
		}
	}
	public function setDeviceName($deviceName)
	{
		$command = CMD_OPTIONS_WRQ;
		$command_string = '~DeviceName='.$deviceName;
		return $this->execCommand($command, $command_string);
	}
	public function getTime()
	{
		// resolution = 1 minute
		$command = CMD_GET_TIME;
		return $this->decodeTime(hexdec($this->reverseHex(bin2hex($this->execCommand($command)))));
	}
	public function setTime($t)
	{
		// resolution = 1 second
		$command = CMD_SET_TIME;
		$command_string = pack('I', $this->encodeTime($t));
		return $this->execCommand($command, $command_string);
	}
	public function enableDevice()
	{
		$command = CMD_ENABLEDEVICE;
		return $this->execCommand($command);
	}
	public function disableDevice()
	{
		$command = CMD_DISABLEDEVICE;
		$command_string = chr(0).chr(0);
		return $this->execCommand($command, $command_string);
	}
	public function enableClock($mode = 0)
	{
		$command = CMD_ENABLE_CLOCK;
		$command_string = chr($mode);
		return $this->execCommand($command, $command_string);
	}
	public function getSelectedUser($uid, $finger)
	{
		$command = CMD_USERTEMP_RRQ;
		$byte1 = chr((int)($uid % 256));
		$byte2 = chr((int)($uid >> 8));
		$command_string = $byte1.$byte2.chr($finger);
		return $this->execCommand($command, $command_string);
	}
	public function getUser()
	{
		if ($this->protocol == 'TCP') {
			$command = 1503;
	        $command_string = pack('CCLLC', 1, 9, 1280, 0, 0);
	        $chksum = 0;
	        $session_id = $this->session_id;
	        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($this->received_data, $this->start_data, 8));
	        $ucs = unpack('H' . (strlen($command_string) * 2), substr($command_string, 0));
	        $udat = unpack('H' . (strlen($this->received_data) * 2), substr($this->received_data, 0));
	        $reply_id = hexdec($u['h8'] . $u['h7']);
	        $buf = $this->createHeader($command, $chksum, $session_id, $reply_id, $command_string);
	        $this->send($buf);
	        $this->received_data = $this->recv();
	        $udat = unpack('H' . (strlen($this->received_data) * 2), substr($this->received_data, 0));
	        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($this->received_data, $this->start_data, 8));
	        $reply_id = hexdec($u['h8'] . $u['h7']);
	        $comando = hexdec($u['h2'] . $u['h1']);

	        if ($comando == CMD_ACK_OK) {
	            $u = unpack('H2h1/H2h2', substr($this->received_data, 17, 2));
	            $size = hexdec($u['h2'] . $u['h1']);
	        } else {
	            $u = unpack('H2h1/H2h2', substr($this->received_data, 16, 2));
	            $size = hexdec($u['h2'] . $u['h1']);
	        }

	        if ($size > 1024) {
	            $buf = $this->createHeader(1504, $chksum, $session_id, $reply_id, pack('LL', 0, $size));
	            $this->send($buf);
	        }
		}
		else {
			$command = CMD_USERTEMP_RRQ;
			$command_string = chr(5);
			$chksum = 0;
			$session_id = $this->session_id;
			$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr( $this->received_data, $this->start_data, 8) );
			$reply_id = hexdec( $u['h8'].$u['h7'] );
			$buf = $this->createHeader($command, $chksum, $session_id, $reply_id, $command_string);
			$this->send($buf);
		}

		try
		{
			if ($this->protocol == 'TCP') {
				if ($size > 1024) {
	                $this->received_data = $this->recv();
	                $bytes = $size;
	                $bytes2 = 0;
	                $tembytes = $bytes;

	                if ($bytes) {

	                    array_push($this->user_data, substr($this->received_data, 16));
	                    $bytes -= strlen($this->received_data);

	                    while ($bytes > 0) {
	                        $received_data = $this->recv();
	                        $bytes2 += strlen($received_data);
	                        $longitud = strlen($received_data);
	                        if ($bytes2 > 1024) {
	                            if (substr($received_data, 0, 2) == 'PP') {
	                                $received_data = substr($received_data, 16);
	                                $bytes2 -= 1024;
	                            } else {
	                                $received_data = substr($received_data, 0, $longitud - ($bytes2 - 1024)) . substr($received_data, $longitud - ($bytes2 - 1024) + 16);
	                                $bytes2 -= 1024;
	                            }
	                        }
	                        array_push($this->user_data, substr($received_data, 0));
	                        $bytes -= strlen($received_data);

	                        if (strlen($received_data) == 0) {
	                            $bytes = 0;
	                        }
	                    }
	                    $this->session_id = hexdec($u['h6'] . $u['h5']);
	                    $received_data = $this->recv();
	                }

	                array_push($this->user_data, substr($this->user_data, 0));
	                if (count($this->user_data) > 0) {
	                    $this->user_data[0] = substr($this->user_data[0], 8);
	                    $this->user_data[1] = substr($this->user_data[1], 8);
	                }
	            } else {
	                array_push($this->user_data, substr($this->received_data, 0));
	                $u = unpack('H' . (strlen($this->received_data) * 2), substr($this->received_data, 0));
	                $size -= strlen($this->received_data);
	                if ($size > 0) {
	                	$size += 12;
	                }

	                while ($size > 0) {
	                    $received_data = $this->recv();
	                    $u = unpack('H' . (strlen($received_data) * 2), substr($received_data, 0));
	                    array_push($this->user_data, substr($received_data, 0));
	                    $size -= strlen($received_data);

	                    if (strlen($received_data) == 0) {
	                        $size = 0;
	                    }
	                }

	                $this->user_data[0] = substr($this->user_data[0], 8);
	            }
			}
			else {
				$this->received_data = $this->recv();
				$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6', substr( $this->received_data, $this->start_data, 8 ) );
				$bytes = $this->getSizeUser();
				if($bytes)
				{
					while($bytes > 0)
					{
						$received_data = $this->recv(1032);
						array_push( $this->user_data, $received_data);
						$bytes -= 1024;
					}
					$this->session_id =  hexdec( $u['h6'].$u['h5'] );
					$received_data = $this->recv();
				}
			}
			
			$users = array();
			if(count($this->user_data) > 0)
			{
				if ($this->protocol != 'TCP') {
					for($x=0; $x<count($this->user_data); $x++)
					{
						if ($x > 0)
						{
							$this->user_data[$x] = substr($this->user_data[$x], 8);
						}
					}
				}
				$user_data = implode('', $this->user_data);
				
				$user_data = substr($user_data, 11);
				 
				while(strlen($user_data) > 72)
				{
					$u = unpack('H144', substr($user_data, 0, 72));
					$u1 = hexdec(substr($u[1], 2, 2));
					$u2 = hexdec(substr($u[1], 4, 2));
					$uid = $u1+($u2*256);                               // 2 byte
					$role = hexdec(substr($u[1], 6, 2)).' ';            // 1 byte
					$password = hex2bin(substr( $u[1], 8, 16 )).' ';    // 8 byte
					$name = hex2bin(substr($u[1], 24, 74 )). ' ';      // 37 byte
					$userid = hex2bin(substr($u[1], 98, 72)).' ';      // 36 byte
					$passwordArr = explode(chr(0), $password, 2);       // explode to array
					$password = $passwordArr[0];                        // get password
					$useridArr = explode(chr(0), $userid, 2);           // explode to array
					$userid = $useridArr[0];                            // get user ID
					$nameArr = explode(chr(0), $name, 3);               // explode to array
					$name = $nameArr[0];                                // get name
					if($name == "")
					{
						$name = $uid;
					}
					$users[$uid] = array($userid, $name, intval($role), $password);
					$user_data = substr($user_data, 72);
				}
			}
			return $users;
			
		}
		catch(ErrorException $e)
		{
			return FALSE;
		}
		catch(exception $e)
		{
			return FALSE;
		}
	}
	public function getUserTemplateAll($uid)
	{
		$template = array();
		$j = 0;
		for($i = 5; $i<10; $i++, $j++)
		{
			$template[$j] = $this->getUserTemplate($uid, $i);
		}
		for($i = 4; $i>=0; $i--, $j++)
		{
			$template[$j] = $this->getUserTemplate($uid, $i);
		}
		return $template;
	}
	public function getUserTemplate($uid, $finger)
	{
		
		$template_data = '';
		$this->user_data = array();
		$command = CMD_USERTEMP_RRQ;
		$byte1 = chr((int)($uid % 256));
		$byte2 = chr((int)($uid >> 8));
		$command_string = $byte1.$byte2.chr($finger);
		$chksum = 0;
		$session_id = $this->session_id;
		$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr( $this->received_data, $this->start_data, 8) );
		$reply_id = hexdec( $u['h8'].$u['h7'] );
		$buf = $this->createHeader($command, $chksum, $session_id, $reply_id, $command_string);
		$this->send($buf);
		try
		{
			$this->received_data = $this->recv();
			
			
			
			$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6', substr( $this->received_data, $this->start_data, 8 ) );
			$bytes = $this->getSizeTemplate();
				
			
			if($bytes)
			{
				while($bytes > 0)
				{
					$received_data = $this->recv(1032);
					
					
					array_push( $this->user_data, $received_data);
					$bytes -= 1024;
				}
				$this->session_id =  hexdec( $u['h6'].$u['h5'] );
				//$received_data = $this->recv();
			}
			
			$template_data = array();
			if(count($this->user_data) > 0)
			{
				
				for($x=0; $x<count($this->user_data); $x++)
				{
					if ($x == 0)
					{
						$this->user_data[$x] = substr($this->user_data[$x], 8);
					}
					else
					{
						$this->user_data[$x] = substr($this->user_data[$x], 8);
					}
				}
				$user_data = implode('', $this->user_data);
				$template_size = strlen($user_data)+6;
				$prefix = chr($template_size%256).chr(round($template_size/256)).$byte1.$byte2.chr($finger).chr(1);
				$user_data = $prefix.$user_data;
				if(strlen($user_data) > 6)
				{
					$valid = 1;
					$template_data = array($template_size, $uid, $finger, $valid, $user_data);
				}
			}
			return $template_data;
			
		}
		catch(ErrorException $e)
		{
			return FALSE;
		}
		catch(exception $e)
		{
			return FALSE;
		}
	}
	public function getUserData()
	{
		$uid = 1;
		$command = CMD_USERTEMP_RRQ;
		$command_string = chr(5);
		$chksum = 0;
		$session_id = $this->session_id;
		$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($this->received_data, $this->start_data, 8));
		$reply_id = hexdec( $u['h8'].$u['h7'] );
		$buf = $this->createHeader($command, $chksum, $session_id, $reply_id, $command_string);
		$this->send($buf);
		try
		{
			$this->received_data = $this->recv();
			$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6', substr($this->received_data, $this->start_data, 8));
			$bytes = $this->getSizeUser();
			if($bytes)
			{
				while($bytes > 0)
				{
					$received_data = $this->recv(1032);
					array_push($this->user_data, $received_data);
					$bytes -= 1024;
				}
				$this->session_id =  hexdec($u['h6'].$u['h5']);
				$received_data = $this->recv();
			}
			$users = array();
			$retdata = "";
			if(count($this->user_data) > 0)
			{
				for($x=0; $x<count($this->user_data); $x++)
				{
					if ($x > 0)
					{
						$this->user_data[$x] = substr($this->user_data[$x], 8);
					}
					if($x > 0)
					{
						$retdata .= substr($this->user_data[$x], 0);
					}
					else
					{
						$retdata .= substr($this->user_data[$x], 12);
					}
				}
			}
			return $retdata;
		}
		catch(ErrorException $e)
		{
			return FALSE;
		}
		catch(exception $e)
		{
			return FALSE;
		}
	}
	public function setUser($uid, $userid, $name, $password, $role)
	{
		$uid = (int) $uid;
		$role = (int) $role;
		if($uid > USHRT_MAX)
		{
			return FALSE;
		}
		if($role > 255) $role = 255;
		$name = substr($name, 0, 28);
		$command = CMD_USER_WRQ;
		$byte1 = chr((int)($uid % 256));
		$byte2 = chr((int)($uid >> 8));
		$command_string = $byte1.$byte2.chr($role).str_pad($password, 8, chr(0)).str_pad($name, 28, chr(0)).str_pad(chr(1), 9, chr(0)).str_pad($userid, 8, chr(0)).str_repeat(chr(0),16);
		return $this->execCommand($command, $command_string);
	}
	public function setUserTemplate($data,$uid)
	{
		$command = CMD_USERTEMP_WRQ;
		$command_string = $data;
		//$length = ord(substr($command_string, 0, 1)) + ord(substr($command_string, 1, 1))*256;
		$byte1 = chr((int)($uid % 256));
		$byte2 = chr((int)($uid >> 8));
		//$command_string = $byte1.$byte2.chr($role).str_pad($password, 8, chr(0)).str_pad($name, 28, chr(0)).str_pad(chr(1), 9, chr(0)).str_pad($userid, 8, chr(0)).str_repeat(chr(0),16);
		
		return $this->execCommand($command, $command_string);
		/*
		$chksum = 0;
		$session_id = $this->session_id;
		$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr( $this->received_data, $this->start_data, 8) );
		$reply_id = hexdec( $u['h8'].$u['h7'] );
		$buf = $this->createHeader($command, $chksum, $session_id, $reply_id, $command_string);
		$this->send($buf);
		try
		{
			$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6', substr( $this->received_data, $this->start_data, 8 ) );
			$this->session_id = hexdec( $u['h6'].$u['h5'] );
			return substr( $this->received_data, 8 );
		}
		catch(ErrorException $e)
		{
			return FALSE;
		}
		catch(exception $e)
		{
			return FALSE;
		}
		*/
	}
	public function clearData()
	{
		$command = CMD_CLEAR_DATA;
		return $this->execCommand($command);
	}
	public function clearUser()
	{
		$command = CMD_CLEAR_DATA;
		return $this->execCommand($command);
	}
	public function deleteUser($uid)
	{
		$command = CMD_DELETE_USER;
		$byte1 = chr((int)($uid % 256));
		$byte2 = chr((int)($uid >> 8));
		$command_string = $byte1.$byte2;
		return $this->execCommand($command, $command_string);
	}
	public function deleteUserTemp($uid, $finger)
	{
		$command = CMD_DELETE_USERTEMP;
		$byte1 = chr((int)($uid % 256));
		$byte2 = chr((int)($uid >> 8));
		$command_string = $byte1.$byte2.chr($finger);
		return $this->execCommand($command, $command_string);
	}
	public function clearAdmin()
	{
		$command = CMD_CLEAR_ADMIN;
		return $this->execCommand($command);
	}
	public function testUserTemplate($uid, $finger)
	{
		$command = CMD_TEST_TEMP;
		$byte1 = chr((int)($uid % 256));
		$byte2 = chr((int)($uid >> 8));
		$command_string = $byte1.$byte2.chr($finger);
		$u =  unpack('H2h1/H2h2', $this->execCommand($command, $command_string));
		$ret = hexdec( $u['h2'].$u['h1'] );
		return ($ret == CMD_ACK_OK)?1:0;
	}
	public function startVerify($uid)
	{
		$command = CMD_STARTVERIFY;
		$byte1 = chr((int)($uid % 256));
		$byte2 = chr((int)($uid >> 8));
		$command_string = $byte1.$byte2;
		return $this->execCommand($command, $command_string);
	}
	public function startEnroll($uid, $finger)
	{
		$command = CMD_STARTENROLL;
		$byte1 = chr((int)($uid % 256));
		$byte2 = chr((int)($uid >> 8));
		$command_string = $byte1.$byte2.chr($finger);
		return $this->execCommand($command, $command_string);
	}
	public function cancelCapture()
	{
		$command = CMD_CANCELCAPTURE;
		return $this->execCommand($command);
	}
	public function getAttendance()
	{
		if ($this->protocol == 'TCP') {
			$command = 1503;
	        $command_string = pack('CCLLC', 1, 13, 0, 0, 0);
	        $chksum = 0;
	        $session_id = $this->session_id;
	        $patron = "";
	        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($this->received_data, $this->start_data, 8));
	        $ucs = unpack('H' . (strlen($command_string) * 2), substr($command_string, 0));
	        $udat = unpack('H' . (strlen($this->received_data) * 2), substr($this->received_data, 0));
	        $reply_id = hexdec($u['h8'] . $u['h7']);
	        $buf = $this->createHeader($command, $chksum, $session_id, $reply_id, $command_string);
	        $this->send($buf);
	        $this->received_data = $this->recv();
	        $udat = unpack('H' . (strlen($this->received_data) * 2), substr($this->received_data, 0));
	        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($this->received_data, $this->start_data, 8));
	        $reply_id = hexdec($u['h8'] . $u['h7']);
	        $comando = hexdec($u['h2'] . $u['h1']);

	        if ($comando == CMD_ACK_OK) {
	            $u = unpack('H2h1/H2h2/H2h3/H2h4', substr($this->received_data, 17, 4));
	            $size = hexdec($u['h4'] . $u['h3'] . $u['h2'] . $u['h1']);
	        } else {
	            $u = unpack('H2h1/H2h2/H2h3/H2h4', substr($this->received_data, 16, 4));
	            $size = hexdec($u['h4'] . $u['h3'] . $u['h2'] . $u['h1']);
	        }

	        if ($size > 1024) {
	            $buf = $this->createHeader(1504, $chksum, $session_id, $reply_id, pack('LL', 0, $size));
	            $this->send($buf);
	        }
		}
		else {
			$command = CMD_ATTLOG_RRQ;
			$command_string = '';
			$chksum = 0;
			$session_id = $this->session_id;
			$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($this->received_data, $this->start_data, 8));
			$reply_id = hexdec($u['h8'].$u['h7']);
			$buf = $this->createHeader($command, $chksum, $session_id, $reply_id, $command_string);
			$this->send($buf);
		}

		try
		{
			if ($this->protocol == 'TCP') {
				if ($size > 1024) {
	                $this->received_data = $this->recv();
	                $bytes = $this->getSizeAttendance();
	                $lonreceived_data = strlen($this->received_data);
	                $bytes2 = 0;
	                $tembytes = $bytes;

	                if ($lonreceived_data > 24) {
	                    array_push($this->attendance_data, substr($this->received_data, 24));
	                    $bytes3 = ($lonreceived_data - 24);
	                    $bytes2 = $lonreceived_data - 24;
	                    $bytes -= $bytes3;
	                }

	                if ($bytes) {
	                    while ($bytes > 0) {
	                        $received_data = $this->recv();
	                        $bytes2 += strlen($received_data);
	                        $longitud = strlen($received_data);
	                        if ($bytes2 > 1024) {
	                            if (substr($received_data, 0, 2) == 'PP') {
	                                $received_data = substr($received_data, 16);
	                                $bytes2 -= 1024;
	                            } else {
	                                $received_data = substr($received_data, 0, $longitud - ($bytes2 - 1024)) . substr($received_data, $longitud - ($bytes2 - 1024) + 16);
	                                $bytes2 -= 1024;
	                            }
	                        }
	                        array_push($this->attendance_data, substr($received_data, 0));
	                        $bytes -= strlen($received_data);

	                        if (strlen($received_data) == 0) {
	                            $bytes = 0;
	                        }
	                    }
	                    $this->session_id = hexdec($u['h6'] . $u['h5']);
	                    $received_data = $this->recv();
	                }
	                if ($lonreceived_data > 24) {
	                    array_push($this->attendance_data, substr($this->received_data, 0, 24));
	                } else {
	                    array_push($this->attendance_data, substr($this->received_data, 0));
	                }
	                if (count($this->attendance_data) > 0) {
	                    $this->attendance_data[0] = substr($this->attendance_data[0], 8);
	                }
	            } else {

	                $ssize = $size;

	                $sizerecibido = 0;

	                array_push($this->attendance_data, substr($this->received_data, 8));

	                if ($size > 0) {
	                    $u = unpack('H' . (strlen($this->received_data) * 2), substr($this->received_data, 0));
	                    $size -= strlen($this->received_data);

	                    $sizerecibido += strlen($this->received_data);

	                    while ($size > 0) {
	                        $received_data = $this->recv();
	                        $u = unpack('H' . (strlen($received_data) * 2), substr($received_data, 0));
	                        array_push($this->attendance_data, substr($received_data, 0));
	                        $size -= strlen($received_data);

	                        $sizerecibido += strlen($received_data);

	                        if (strlen($received_data) == 0) {
	                            $size = 0;
	                        }
	                    }

	                    if ($sizerecibido <> ($ssize + 20)) {
	                        $received_data = $this->recv();
	                        $u = unpack('H' . (strlen($received_data) * 2), substr($received_data, 0));
	                        array_push($this->attendance_data, substr($received_data, 0));

	                        $sizerecibido += strlen($received_data);
	                    }
	                }
	            }
			}
			else {
				$this->received_data = $this->recv();
				$bytes = $this->getSizeAttendance();
				if($bytes)
				{
					while($bytes > 0)
					{
						$received_data = $this->recv(1032);
						array_push($this->attendance_data, $received_data);
						$bytes -= 1024;
					}
					$this->session_id = hexdec($u['h6'].$u['h5']);
					$received_data = $this->recv();
				}
			}
			
			$attendance = array();
			if(count($this->attendance_data) > 0)
			{
				if ($this->protocol != 'TCP') {
					for($x=0; $x<count($this->attendance_data); $x++)
					{
						if($x > 0)
						{
							$this->attendance_data[$x] = substr($this->attendance_data[$x], 8);
						}
					}
				}
				$attendance_data = implode('', $this->attendance_data);
				$attendance_data = substr($attendance_data, 10);
				while(strlen($attendance_data) > 40)
				{
					$u = unpack('H80', substr($attendance_data, 0, 40));
					$u1 = hexdec(substr($u[1], 4, 2));
					$u2 = hexdec(substr($u[1], 6, 2));
					$uid = $u1+($u2*256);
					$id = str_replace("\0", '', hex2bin(substr($u[1], 8, 16)));
					$state = hexdec(substr( $u[1], 56, 2 ) );
					$timestamp = $this->decodeTime(hexdec($this->reverseHex(substr($u[1], 58, 8))));
					array_push($attendance, array($uid, $id, $state, $timestamp));
					$attendance_data = substr($attendance_data, 40 );
				}
			}
			return $attendance;
		}
		catch(ErrorException $e)
		{
			return FALSE;
		}
		catch(exception $e)
		{
			return FALSE;
		}

	}
	public function clearAttendance()
	{
		$command = CMD_CLEAR_ATTLOG;
		return $this->execCommand($command);
	}

}
?>
