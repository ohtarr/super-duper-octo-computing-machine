//Custom API to interface to Observium
//Observium functions used :
//dbFetchRows
//set_entity_attrib
//

<?php

// do not authenticate requests to this call
//define("NO_AUTHENTICATION",1);
// always allow access from everywhere
header('Access-Control-Allow-Origin: *');
//header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Accept, Content-Type");
// never cache anything
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
// set our content type so the browser knows its json
header("Content-Type: application/json");
header("Accept: application/json");

include("/opt/observium/includes/sql-config.inc.php");

include_once("/opt/observium/html/includes/functions.inc.php");

function microtimeTicks(){
        $ticks = explode(' ', microtime());
        // Return the sum of the two numbers (double precision number)
        return $ticks[0] + $ticks[1];
}

function quitApi($RESPONSE){
	$end = microtimeTicks();         // get the current microtime for performance tracking
	$RESPONSE['time'] = $end - $start;                      // calculate the total time we executed
	exit(json_encode($RESPONSE));
}

function get_devices(){
	$query = "SELECT * FROM `devices` ";
//	$query .= $where . $query_permitted . $sort;
	$results = dbFetchRows($query);
	foreach ($results as $key => $device){
		$array[$device[device_id]] = $device;
	}
	ksort($array);
	//$object = (object) $array;

	return $array;
}

$start = microtimeTicks();       // get the current microtime for performance tracking

/*
if( !isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on')    {
        header("HTTP/1.1 301 Moved Permanently");                       // Enforce HTTPS for all traffic
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit(); 
}

/**/

$RESPONSE = [];

/*
if ($_SERVER['REQUEST_METHOD'] != 'POST') {             // Handle non-post requests as an error
    $RESPONSE['success']= false;
    $RESPONSE['message']  = "Request method not supported";
        $end = microtimeTicks();         // get the current microtime for performance tracking
        $RESPONSE['time'] = $end - $start;                      // calculate the total time we executed
    exit(json_encode($RESPONSE));
}
/**/
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	$devices = get_devices();
	if ($_GET[hostname])
	{
		foreach($devices as $id => $device){
			if ($_GET[hostname] == $device[hostname]){

				$RESPONSE['success'] = true;
				$RESPONSE['data'] = $device;
				$RESPONSE['message']      = "Device ID " . $id . " named " . $device[hostname] . " returned!";
				quitApi($RESPONSE);
			}
		}
		$RESPONSE['success'] = false;
		$RESPONSE['message']      = "Device " . $_GET[hostname] . " not found!";
		quitApi($RESPONSE);
	} elseif ($_GET[id])
	{
		if($devices[$_GET[id]]){
			$RESPONSE['success'] = true;
			$RESPONSE['data'] = $devices[$_GET[id]];
			$RESPONSE['message']      = "Device ID " . $devices[$_GET[id]][device_id] . " named " . $devices[$_GET[id]][hostname] . " returned!";
			quitApi($RESPONSE);
		} else {
			$RESPONSE['success'] = false;
			$RESPONSE['message']      = "Device ID " . $_GET[id] . " not found!";
			quitApi($RESPONSE);
		}
	} else {
		$RESPONSE['success'] = true;
		$RESPONSE['data'] = $devices;
		$RESPONSE['message']      = "All devices returned!";
		quitApi($RESPONSE);
	}

}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$POSTED = (array) json_decode(file_get_contents("php://input"));


	if ($POSTED["debug"] == 1){
		$RESPONSE['debug']['POST_params'] = $POSTED;
	}

	if (empty($POSTED)){
			$RESPONSE['success']= false;
			$RESPONSE['message']  = "No POST data found!";
			quitApi($RESPONSE);
	}

	//Required Parameters

	if (empty($POSTED["action"])) {
		$RESPONSE['success']= false;
		$RESPONSE['message']      = "Missing or empty parameter ->{action}<-";
		quitApi($RESPONSE);
	}

	if ($POSTED["action"] == "add_device") {
		$params = ["hostname"];

		foreach ($params as $param){
				if ( empty($POSTED[$param])) {  // Handle missing actions as an error
						$RESPONSE['success']= false;
						$RESPONSE['message']      = "Missing or empty parameter ->{$param}<-";
						quitApi($RESPONSE);
				}
		}

		try{
			$hostname = $POSTED["hostname"];
			$device_id = add_device($hostname);

			if ($POSTED['debug'] == 1){
				$RESPONSE['debug']['api_return'] = $device_id;
			}

			if ($device_id == false){
				$RESPONSE['success'] = false;
				$RESPONSE['message'] = $POSTED['action'] . " failed to return valid device ID.";
			} else {
				$RESPONSE['success'] = true;
				$RESPONSE['message'] = $POSTED['action'] . " returned valid device ID: " . $device_id;

				//If device is an ACCESS SWITCH, disable PORTS module.
				$reg = "/^\D{5}\S{3}.*(sw[api]|SW[API])[0-9]{2,4}.*$/";                   //regex to match ACCESS switches only
				if (preg_match($reg,$hostname, $hits)){
					//$RESPONSE[test] = "test!";
					set_entity_attrib("device", $device_id, "discover_ports", 0);
				}
				//shell_exec('../../discovery.php -h ' . $POSTED["hostname"] . ' >> /dev/null &');
			}

			quitApi($RESPONSE);
		}catch (\Exception $e) {
				// catch exceptions as BAD data
				$RESPONSE['success'] = false;
				$RESPONSE['message'] = "Caught exception {$e->getMessage()}\n";
				quitApi($RESPONSE);
		}



	} elseif ($POSTED["action"] == "delete_device") {
		$params = ["hostname"];

		foreach ($params as $param){
				if ( empty($POSTED[$param])) {  // Handle missing actions as an error
						$RESPONSE['success']= false;
						$RESPONSE['message']      = "Missing or empty parameter ->{$param}<-";
						quitApi($RESPONSE);
				}
		}

		try{

			$device_id = get_device_id_by_hostname($POSTED["hostname"]);

			if ($device_id){
				$delete = delete_device($device_id, true);

				if ($POSTED['debug'] == 1){
					$RESPONSE['debug']['api_return'] = $delete;
				}

				if ($delete){
					$RESPONSE['success'] = true;
					$RESPONSE['message'] = $POSTED['action'] . " successfully deleted device id: " . $device_id;

				} else {
					$RESPONSE['success'] = false;
					$RESPONSE['message'] = $POSTED['action'] . " failed to delete device id: " . $device_id;
				}
				quitApi($RESPONSE);

			} else {
				$RESPONSE['success'] = false;
				$RESPONSE['message'] = $POSTED['action'] . " returned no valid device ID.";
				if ($POSTED['debug'] == 1){
					$RESPONSE['debug']['api_return'] = $device_id;
				}
				quitApi($RESPONSE);
			}

		}catch (\Exception $e) {
			// catch exceptions as BAD data
			$RESPONSE['success'] = false;
			$RESPONSE['message'] = "Caught exception {$e->getMessage()}\n";
			quitApi($RESPONSE);
		}

	} else {
		$RESPONSE['success']= false;
		$RESPONSE['message']      = "Unsupported Action!";
		quitApi($RESPONSE);
	}
}

//exit(json_encode($RESPONSE));                           // terminate and respond with json
