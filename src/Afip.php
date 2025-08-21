<?php
/**
 * Software Development Kit for ARCA web services
 * 
 * @link https://afipsdk.com/
 *
 * @author 	Afip SDK ayuda@afipsdk.com
 * @package Afip
 **/

if (!defined('SOAP_1_1')) {
	define('SOAP_1_1', 1);
}

if (!defined('SOAP_1_2')) {
	define('SOAP_1_2', 2);
}

include_once __DIR__.'/libs/Requests/Requests.php';

Requests::register_autoloader();

#[\AllowDynamicProperties]
class Afip {
	/**
	 * SDK version
	 **/
	var $sdk_version_number = '1.2.0';

	/**
	 * X.509 certificate in PEM format
	 *
	 * @var string
	 **/
	var $CERT;

	/**
	 * Private key correspoding to CERT (PEM)
	 *
	 * @var string
	 **/
	var $PRIVATEKEY;

	/**
	 * Tax id to use
	 *
	 * @var int
	 **/
	var $CUIT;

	/**
	 * Implemented Web Services
	 *
	 * @var array[string]
	 **/
	var $implemented_ws = array(
		'ElectronicBilling',
		'RegisterScopeFour',
		'RegisterScopeFive',
		'RegisterInscriptionProof',
		'RegisterScopeTen',
		'RegisterScopeThirteen'
	);

	/**
	 * Afip options
	 **/
	var $options;

	function __construct($options)
	{
		ini_set("soap.wsdl_cache_enabled", "0");

		if (!isset($options['CUIT'])) {
			$this->CUIT = NULL;
		} else {
			$this->CUIT = $options['CUIT'];
		}

		if (!isset($options['production'])) {
			$options['production'] = FALSE;
		}

		if (!isset($options['cert'])) {
			$options['cert'] = NULL;
		}

		if (!isset($options['key'])) {
			$options['key'] = NULL;
		}

		$this->options = $options;

		$this->CERT 		= $options['cert'];
		$this->PRIVATEKEY 	= $options['key'];
	}

	/**
	 * Gets token authorization for an AFIP Web Service
	 *
	 * @param string $service Service for token authorization
	 * @param boolean $force Force to create a new token 
	 * authorization even if it is not expired
	 *
	 * @throws Exception if an error occurs
	 *
	 * @return TokenAuthorization Token Authorization for AFIP Web Service 
	**/
	public function GetServiceTA($service, $force = FALSE)
	{
		// Prepare data to for request
		$data = array(
			'environment' => $this->options['production'] === TRUE ? "prod" : "dev",
			'wsid' => $service,
			'tax_id' => $this->options['CUIT'],
			'force_create' => $force
		);

		// Add cert if is set
		if (isset($this->CERT)) {
			$data['cert'] = $this->CERT;
		}

		// Add key is is set
		if ($this->PRIVATEKEY) {
			$data['key'] = $this->PRIVATEKEY;
		}

		$headers = array(
			'Content-Type' => 'application/json',
			'sdk-version-number' => $this->sdk_version_number,
			'sdk-library' => 'php',
			'sdk-environment' => $this->options['production'] === TRUE ? "prod" : "dev"
		);

		if (isset($this->options['access_token'])) {
			$headers['Authorization'] = 'Bearer '.$this->options['access_token'];
		}

		$request = Requests::post('https://app.afipsdk.com/api/v1/afip/auth', $headers, json_encode($data));

		if ($request->success) {
			$decoded_res = json_decode($request->body);

			//Return response
			return new TokenAuthorization($decoded_res->token, $decoded_res->sign);
		}
		else {
			$error_message = $request->body;

			try {
				$json_res = json_decode($request->body);

				if (isset($json_res->message)) {
					$error_message = $json_res->message;
				}
			} catch (Exception $e) {}

			throw new Exception($error_message);
		}
	}

	/**
	 * Get last request and last response XML
	 **/
	public function GetLastRequestXML()
	{
		$headers = array(
			'sdk-version-number' => $this->sdk_version_number,
			'sdk-library' => 'php',
			'sdk-environment' => $this->options['production'] === TRUE ? "prod" : "dev"
		);

		if (isset($this->options['access_token'])) {
			$headers['Authorization'] = 'Bearer '.$this->options['access_token'];
		}

		$request = Requests::get('https://app.afipsdk.com/api/v1/afip/requests/last-xml', $headers);

		if ($request->success) {
			$decoded_res = json_decode($request->body);

			//Return response
			return $decoded_res;
		}
		else {
			$error_message = $request->body;

			try {
				$json_res = json_decode($request->body);

				if (isset($json_res->message)) {
					$error_message = $json_res->message;
				}
			} catch (Exception $e) {}

			throw new Exception($error_message);
		}
	}

	/**
	 * Create generic Web Service
	 * 
	 * @param string $service Web Service name
	 * @param array $options Web Service options
	 *
	 * @throws Exception if an error occurs
	 *
	 * @return AfipWebService New AFIP Web Service 
	 **/
	public function WebService($service, $options = array())
	{
		$options['service'] = $service;
		$options['generic'] = TRUE;

		return new AfipWebService($this, $options);
	}

	/**
	 * Create AFIP cert
	 * 
	 * @deprecated Use Automation instead
	 *
	 * @param string $username Username used in AFIP page
	 * @param string $password Password used in AFIP page
	 * @param string $alias Alias for the cert
	 **/
	public function CreateCert($username, $password, $alias)
	{
		// Prepare data to for request
		$data = array(
			'environment' => $this->options['production'] === TRUE ? "prod" : "dev",
			'tax_id' => $this->options['CUIT'],
			'username' => $username,
			'password' => $password,
			'alias' => $alias
		);

		$headers = array(
			'Content-Type' => 'application/json',
			'sdk-version-number' => $this->sdk_version_number,
			'sdk-library' => 'php',
			'sdk-environment' => $this->options['production'] === TRUE ? "prod" : "dev"
		);

		if (isset($this->options['access_token'])) {
			$headers['Authorization'] = 'Bearer '.$this->options['access_token'];
		}

		// Wait for max 120 seconds
		$retry = 24;

		while ($retry-- >= 0) {
			// Execute request
			$request = Requests::post('https://app.afipsdk.com/api/v1/afip/certs', $headers, json_encode($data));
			
			if ($request->success) {
				$decoded_res = json_decode($request->body);
				
				if ($decoded_res->status === 'complete') {
					return $decoded_res->data;
				}

				if (isset($decoded_res->long_job_id)) {
					$data['long_job_id'] = $decoded_res->long_job_id;
				}

				// Wait 5 seconds
				sleep(5);
			}
			else {
				$error_message = $request->body;
	
				try {
					$json_res = json_decode($request->body);
	
					if (isset($json_res->message)) {
						$error_message = $json_res->message;
					}
				} catch (Exception $e) {}
	
				throw new Exception($error_message);
			}
		}

		throw new Exception('Error: Waiting for too long');
		
	}

	/**
	 * Create authorization to use a web service
	 * 
	 * @deprecated Use Automation instead
	 *
	 * @param string $username Username used in AFIP page
	 * @param string $password Password used in AFIP page
	 * @param string $alias Cert alias
	 * @param string $wsid Web service id
	 **/
	public function CreateWSAuth($username, $password, $alias, $wsid)
	{
		// Prepare data to for request
		$data = array(
			'environment' => $this->options['production'] === TRUE ? "prod" : "dev",
			'tax_id' => $this->options['CUIT'],
			'username' => $username,
			'password' => $password,
			'wsid' => $wsid,
			'alias' => $alias
		);

		$headers = array(
			'Content-Type' => 'application/json',
			'sdk-version-number' => $this->sdk_version_number,
			'sdk-library' => 'php',
			'sdk-environment' => $this->options['production'] === TRUE ? "prod" : "dev"
		);

		if (isset($this->options['access_token'])) {
			$headers['Authorization'] = 'Bearer '.$this->options['access_token'];
		}
		
		// Wait for max 120 seconds
		$retry = 24;

		while ($retry-- >= 0) {
			// Execute request
			$request = Requests::post('https://app.afipsdk.com/api/v1/afip/ws-auths', $headers, json_encode($data));

			if ($request->success) {
				$decoded_res = json_decode($request->body);
				
				if ($decoded_res->status === 'complete') {
					return $decoded_res->data;
				}

				if (isset($decoded_res->long_job_id)) {
					$data['long_job_id'] = $decoded_res->long_job_id;
				}

				// Wait 5 seconds
				sleep(5);
			}
			else {
				$error_message = $request->body;
	
				try {
					$json_res = json_decode($request->body);
	
					if (isset($json_res->message)) {
						$error_message = $json_res->message;
					}
				} catch (Exception $e) {}
	
				throw new Exception($error_message);
			}
		}

		throw new Exception('Error: Waiting for too long');
	}

	/**
	 * Create automation
	 *
	 * @since 1.2.0
	 * 
	 * @param string $automation Name of the automation
	 * @param array $params Parameters to send to the automation
	 * @param boolean $wait Wait for the automation to finish (default TRUE)
	 *
	 * @throws Exception if an error occurs
	 *
	 * @return mixed Automation result data
	 **/
	public function CreateAutomation($automation, $params, $wait = TRUE)
	{
		if (!isset($params['cuit'])) {
			$params['cuit'] = $this->options['CUIT'];
		}

		// Prepare data to for request
		$data = array(
			'automation' => $automation,
			'params' => $params
		);

		$headers = array(
			'Content-Type' => 'application/json',
			'sdk-version-number' => $this->sdk_version_number,
			'sdk-library' => 'php',
			'sdk-environment' => $this->options['production'] === TRUE ? "prod" : "dev"
		);

		if (isset($this->options['access_token'])) {
			$headers['Authorization'] = 'Bearer '.$this->options['access_token'];
		}
		
		// Execute request
		$request = Requests::post('https://app.afipsdk.com/api/v1/automations', $headers, json_encode($data));

		if ($request->success) {
			$decoded_res = json_decode($request->body);
			
			if (!$wait || $decoded_res->status === 'complete') {
				return $decoded_res;
			}

			return $this->GetAutomationDetails($decoded_res->id, $wait);
		}
		else {
			$error_message = $request->body;

			try {
				$json_res = json_decode($request->body);

				if (isset($json_res->message)) {
					$error_message = $json_res->message;
				}
			} catch (Exception $e) {}

			throw new Exception($error_message);
		}
	}

	/**
	 * Get automation details
	 *
	 * @since 1.2.0
	 * 
	 * @param string $id Id of the automation
	 * @param boolean $wait Wait for the automation to finish (default FALSE)
	 *
	 * @throws Exception if an error occurs
	 *
	 * @return mixed Automation result data
	 **/
	public function GetAutomationDetails($id, $wait = FALSE)
	{
		$headers = array(
			'Content-Type' => 'application/json',
			'sdk-version-number' => $this->sdk_version_number,
			'sdk-library' => 'php',
			'sdk-environment' => $this->options['production'] === TRUE ? "prod" : "dev"
		);

		if (isset($this->options['access_token'])) {
			$headers['Authorization'] = 'Bearer '.$this->options['access_token'];
		}
		
		// Wait for max 120 seconds
		$retry = 24;

		while ($retry-- >= 0) {
			// Execute request
			$request = Requests::get('https://app.afipsdk.com/api/v1/automations/'.$id, $headers);

			if ($request->success) {
				$decoded_res = json_decode($request->body);
				
				if (!$wait || $decoded_res->status === 'complete') {
					return $decoded_res;
				}

				// Wait 5 seconds
				sleep(5);
			}
			else {
				$error_message = $request->body;
	
				try {
					$json_res = json_decode($request->body);
	
					if (isset($json_res->message)) {
						$error_message = $json_res->message;
					}
				} catch (Exception $e) {}
	
				throw new Exception($error_message);
			}
		}

		throw new Exception('Error: Waiting for too long');
	}

	public function __get($property)
	{
		if (in_array($property, $this->implemented_ws)) {
			if (isset($this->{$property})) {
				return $this->{$property};
			} else {
				$file = __DIR__.'/Class/'.$property.'.php';
				if (!file_exists($file)) 
					throw new Exception("Failed to open ".$file."\n", 1);

				include_once $file;

				return ($this->{$property} = new $property($this));
			}
		} else {
			return $this->{$property};
		}
	}
}

/**
 * Token Authorization
 **/
class TokenAuthorization {
	/**
	 * Authorization and authentication web service Token
	 *
	 * @var string
	 **/
	var $token;

	/**
	 * Authorization and authentication web service Sign
	 *
	 * @var string
	 **/
	var $sign;

	function __construct($token, $sign)
	{
		$this->token 	= $token;
		$this->sign 	= $sign;
	}
}

/**
 * Base class for AFIP web services 
**/
#[\AllowDynamicProperties]
class AfipWebService {
	/**
	 * Web service SOAP version
	 *
	 * @var intenger
	 **/
	var $soap_version;

	/**
	 * File name for the Web Services Description Language
	 *
	 * @var string
	 **/
	var $WSDL;
	
	/**
	 * The url to web service
	 *
	 * @var string
	 **/
	var $URL;

	/**
	 * File name for the Web Services Description 
	 * Language in test mode
	 *
	 * @var string
	 **/
	var $WSDL_TEST;

	/**
	 * The url to web service in test mode
	 *
	 * @var string
	 **/
	var $URL_TEST;
	
	/**
	 * The Afip parent Class
	 *
	 * @var Afip
	 **/
	var $afip;
	
	/**
	 * Class options
	 *
	 * @var object
	 **/
	var $options;

	function __construct($afip, $options = array())
	{
		$this->afip = $afip;
		$this->options = $options;

		if (isset($options['WSDL'])) {
			$this->WSDL = $options['WSDL'];
		}

		if (isset($options['URL'])) {
			$this->URL = $options['URL'];
		}

		if (isset($options['WSDL_TEST'])) {
			$this->WSDL_TEST = $options['WSDL_TEST'];
		}

		if (isset($options['URL_TEST'])) {
			$this->URL_TEST = $options['URL_TEST'];
		}

		if (isset($options['generic']) && $options['generic'] === TRUE) {
			if (!isset($options['service'])) {
				throw new Exception("service field is required in options");
			}

			if (!isset($options['soap_version'])) {
				$options['soap_version'] = SOAP_1_2;
			}

			$this->soap_version = $options['soap_version'];
		}
	}

	/**
	 * Get Web Service Token Authorization from WSAA
	 * 
	 * @param boolean force Force to create a new token 
	 * authorization even if it is not expired
	 * 
	 * @return TokenAuthorization Token Authorization for AFIP Web Service 
	 **/
	public function GetTokenAuthorization($force = FALSE)
	{
		return $this->afip->GetServiceTA($this->options['service'], $force);
	}

	/**
	 * Sends request to AFIP servers
	 * 
	 * @since 0.6
	 *
	 * @param string 	$method 	SOAP method to execute
	 * @param array 	$params 	Parameters to send
	 *
	 * @return mixed Operation results 
	 **/
	public function ExecuteRequest($method, $params = array())
	{
		// Prepare data to for request
		$data = array(
			'method' => $method,
			'params' => $params,
			'environment' => $this->afip->options['production'] === TRUE ? "prod" : "dev",
			'wsid' => $this->options['service'],
			'url' => $this->afip->options['production'] === TRUE ? $this->URL : $this->URL_TEST,
			'wsdl' => $this->afip->options['production'] === TRUE ? $this->WSDL : $this->WSDL_TEST,
			'soap_v_1_2' => $this->soap_version === SOAP_1_2
		);

		$headers = array(
			'Content-Type' => 'application/json',
			'sdk-version-number' => $this->afip->sdk_version_number,
			'sdk-library' => 'php',
			'sdk-environment' => $this->afip->options['production'] === TRUE ? "prod" : "dev"
		);

		if (isset($this->afip->options['access_token'])) {
			$headers['Authorization'] = 'Bearer '.$this->afip->options['access_token'];
		}

		$request = Requests::post('https://app.afipsdk.com/api/v1/afip/requests', $headers, json_encode($data));

		if ($request->success) {
			$decoded_res = json_decode($request->body);
			
			//Return response
			return $decoded_res;
		}
		else {
			$error_message = $request->body;

			throw new Exception($error_message);
		}

		return $results;
	}
}
