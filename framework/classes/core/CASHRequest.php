<?php

namespace CASHMusic\Core;

use CASHMusic\Core\CASHData as CASHData;
use CASHMusic\Core\CASHDaemon as CASHDaemon;

/**
 * The CASHRequest / CASHResponse relationship is the core of the CASH framework.
 * CASHRequest looks for direct or indirect (POST/GET) requests for CASH resources
 * then determines the correct Plant to instantiate in order to fulfill the request
 * and return a proper CASHResponse.
 *
 * @package platform.org.cashmusic
 * @author CASH Music
 * @link http://cashmusic.org/
 *
 * Copyright (c) 2013, CASH Music
 * Licensed under the GNU Lesser General Public License version 3.
 * See http://www.gnu.org/licenses/lgpl-3.0.html
 *
 *
 * This file is generously sponsored by Paul Lightfoot
 *
 */class CASHRequest extends CASHData {
	public static $version = 9;

	protected $request_method,
			  $plant_array=array(),
			  $total_requests = 0,
			  $plant,
			  $user,
              $api = false;
	public $request = false,
		   $response;

	/**
	 * Sets object parameters, calls detectRequest(), and attempts to initialize
	 * the proper Plant
	 *
	 * @param $direct_request boolean [default: false] - can only be set when
	 *        called directly, so set to true to indicate direct request method
	 * @param $method string
	 * @param $authorized_user boolean
	 * @param $api boolean
	 */
	public function __construct($direct_request=false,$method='direct',$authorized_user=false,$api=false,$http_method=false) {
		if ($direct_request) {
			// skip detect on direct requests
			$this->request = $direct_request;
			$this->request_method = $method;
			$this->user = $authorized_user;
			$this->api = $api;
		} else {
			if ($direct_request !== null) {
				// use an environment variable so we only run the detected request once
				if (!getenv('cashmusic_detected_request')) {
					$this->detectRequest();
					putenv('cashmusic_detected_request=1');
				}
			}
		}
		if ($this->request) {
			$this->processRequest($this->request,$this->request_method,$http_method);
		}
		// garbage collection daemon. 1.5% chance of running.
		if (rand(10,1000) <= 15) {			
			$gc = new CASHDaemon();
		}
	}

	public function getVersion() {
		return self::$version;
	}

	public function processRequest($request,$method='direct',$http_method=false) {
        $namespace = '\CASHMusic\Plants\\';
		// found something, let's make sure it's legit and do work
		if (is_array($request)) {
			$this->request = $request;
			$this->request_method = $method;
			$requested_plant = strtolower(trim($this->request['cash_request_type']));
			unset($this->request['cash_request_type']);
			if ($requested_plant != '' && count($this->request) > 0) {
				$this->plant_array = self::buildPlantArray();
				if (isset($this->plant_array[$requested_plant])) {
					//$filename = substr_replace($this->plant_array[$requested_plant], '', -4);
					$directory = str_replace("Plant", "", $this->plant_array[$requested_plant]).'\\';

					$class_name = $namespace.$directory.$this->plant_array[$requested_plant];
					$this->plant = new $class_name($this->request_method,$this->request);

					$this->response = $this->plant->processRequest($this->api, $http_method);
				}
			}
		}
	}

	public function setAuthorizedUser($user) {
		$this->user = $user;
		return $this->user;
	}

	/**
	 * Determines the method used to make the Seed request, setting $this->request
	 * and $this->request_method
	 *
	 * @return void
	 */protected function detectRequest() {
		if (!$this->request) {

			// determine correct request source
			if (isset($_POST['cash_request_type'])) {
				$this->request = $_POST;
				$this->request_method = 'post';
			} else if (isset($_GET['cash_request_type'])) {
				$this->request = $_GET;
				$this->request_method = 'get';
			}  /*
				* Removed command-line support for easier testing until there's
				* a proper reason/method anyway...
				*
				* 	else if (php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR'])) {
				* 	if (count($_SERVER['argv']) > 1) {
				* 		print_r($_SERVER['argv']);
				* 		$this->request = $_SERVER['argv'];
				* 		$this->request_method = 'commandline';
				* 	}
				* }
				*/
		}
	}

	/**
	 * Builds an associative array of all Plant class files in /classes/plants/
	 * stored as $this->plant_array and used to initialize the appropriate class
	 * based on the cash_request_type
	 *
	 * @return array|boolean;
	 */
	public static function buildPlantArray() {
		$plant_array = [];
		if ($plant_dir = opendir(CASH_PLATFORM_ROOT.'/classes/plants/')) {
			while (false !== ($file = readdir($plant_dir))) {
				if (strpos($file, ".") === false) {
					$tmpKey = strtolower($file);
					$plant_array["$tmpKey"] = $file."Plant";
				}
			}
			closedir($plant_dir);

			return $plant_array;
		}

		return false;
	}
} // END class
?>
