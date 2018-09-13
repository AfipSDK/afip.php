<?php
/**
 * Software Development Kit for AFIP web services
 * 
 * This release of Afip SDK is intended to facilitate 
 * the integration to other different web services that 
 * Electronic Billing   
 *
 * @link http://www.afip.gob.ar/ws/ AFIP Web Services documentation
 *
 * @author 	Ivan Muñoz ivanalemunioz@gmail.com
 * @package Afip
 * @version 0.5
 **/

class Afip {
	/**
	 * File name for the WSDL corresponding to WSAA
	 *
	 * @var string
	 **/
	var $WSAA_WSDL;

	/**
	 * The url to get WSAA token
	 *
	 * @var string
	 **/
	var $WSAA_URL;

	/**
	 * File name for the X.509 certificate in PEM format
	 *
	 * @var string
	 **/
	var $CERT;

	/**
	 * File name for the private key correspoding to CERT (PEM)
	 *
	 * @var string
	 **/
	var $PRIVATEKEY;

	/**
	 * The passphrase (if any) to sign
	 *
	 * @var string
	 **/
	var $PASSPHRASE;

	/**
	 * Afip resources folder
	 *
	 * @var string
	 **/
	var $RES_FOLDER;

	/**
	 * Afip ta folder
	 *
	 * @var string
	 **/
	var $TA_FOLDER;

	/**
	 * The CUIT to use
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
		'RegisterScopeTen'
	);

	function __construct($options)
	{
		ini_set("soap.wsdl_cache_enabled", "0");


		if (!isset($options['CUIT'])) {
			throw new Exception("CUIT field is required in options array");
		} else {
			$this->CUIT = $options['CUIT'];
		}

		if (!isset($options['production'])) {
			$options['production'] = FALSE;
		}

		if (!isset($options['passphrase'])) {
			$options['passphrase'] = 'xxxxx';
		}

		if (!isset($options['cert'])) {
			$options['cert'] = 'cert';
		}

		if (!isset($options['key'])) {
			$options['key'] = 'key';
		}

		if (!isset($options['res_folder'])) {
			$this->RES_FOLDER = __DIR__.'/Afip_res/';
		} else {
			$this->RES_FOLDER = $options['res_folder'];
		}

		if (!isset($options['ta_folder'])) {
			$this->TA_FOLDER = __DIR__.'/Afip_res/';
		} else {
			$this->TA_FOLDER = $options['ta_folder'];
		}

		$this->PASSPHRASE = $options['passphrase'];

		$this->options = $options;

		$this->CERT 		= $this->RES_FOLDER.$options['cert'];
		$this->PRIVATEKEY 	= $this->RES_FOLDER.$options['key'];

		$this->WSAA_WSDL 	= __DIR__.'/Afip_res/'.'wsaa.wsdl';
		if ($options['production'] === TRUE) {
			$this->WSAA_URL = 'https://wsaa.afip.gov.ar/ws/services/LoginCms';
		} else {
			$this->WSAA_URL = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms';
		}

		if (!file_exists($this->CERT)) 
			throw new Exception("Failed to open ".$this->CERT."\n", 1);
		if (!file_exists($this->PRIVATEKEY)) 
			throw new Exception("Failed to open ".$this->PRIVATEKEY."\n", 2);
		if (!file_exists($this->WSAA_WSDL)) 
			throw new Exception("Failed to open ".$this->WSAA_WSDL."\n", 3);
	}

	/**
	 * Gets token authorization for an AFIP Web Service
	 *
	 * @since 0.1
	 *
	 * @param string $service Service for token authorization
	 *
	 * @throws Exception if an error occurs
	 *
	 * @return TokenAutorization Token Autorization for AFIP Web Service 
	**/
	public function GetServiceTA($service, $continue = TRUE)
	{
		if (file_exists($this->TA_FOLDER.'TA-'.$this->options['CUIT'].'-'.$service.'.xml')) {
			$TA = new SimpleXMLElement(file_get_contents($this->TA_FOLDER.'TA-'.$this->options['CUIT'].'-'.$service.'.xml'));

			$actual_time 		= new DateTime(date('c',date('U')+600));
			$expiration_time 	= new DateTime($TA->header->expirationTime);

			if ($actual_time < $expiration_time) 
				return new TokenAutorization($TA->credentials->token, $TA->credentials->sign);
			else if ($continue === FALSE)
				throw new Exception("Error Getting TA", 5);
		}

		if ($this->CreateServiceTA($service)) 
			return $this->GetServiceTA($service, FALSE);
	}

	/**
	 * Create an TA from WSAA
	 *
	 * Request to WSAA for a tokent authorization for service and save this
	 * in a xml file
	 *
	 * @since 0.1
	 *
	 * @param string $service Service for token authorization
	 *
	 * @throws Exception if an error occurs creating token authorization
	 *
	 * @return true if token authorization is created success
	**/
	private function CreateServiceTA($service)
	{
		//Creating TRA
		$TRA = new SimpleXMLElement(
		'<?xml version="1.0" encoding="UTF-8"?>' .
		'<loginTicketRequest version="1.0">'.
		'</loginTicketRequest>');
		$TRA->addChild('header');
		$TRA->header->addChild('uniqueId',date('U'));
		$TRA->header->addChild('generationTime',date('c',date('U')-600));
		$TRA->header->addChild('expirationTime',date('c',date('U')+600));
		$TRA->addChild('service',$service);
		$TRA->asXML($this->TA_FOLDER.'TRA-'.$this->options['CUIT'].'-'.$service.'.xml');

		//Signing TRA
		$STATUS = openssl_pkcs7_sign($this->TA_FOLDER."TRA-".$this->options['CUIT'].'-'.$service.".xml", $this->TA_FOLDER."TRA-".$this->options['CUIT'].'-'.$service.".tmp", "file://".$this->CERT,
			array("file://".$this->PRIVATEKEY, $this->PASSPHRASE),
			array(),
			!PKCS7_DETACHED
		);
		if (!$STATUS) {return FALSE;}
		$inf = fopen($this->TA_FOLDER."TRA-".$this->options['CUIT'].'-'.$service.".tmp", "r");
		$i = 0;
		$CMS="";
		while (!feof($inf)) {
			$buffer=fgets($inf);
			if ( $i++ >= 4 ) {$CMS.=$buffer;}
		}
		fclose($inf);
		unlink($this->TA_FOLDER."TRA-".$this->options['CUIT'].'-'.$service.".xml");
		unlink($this->TA_FOLDER."TRA-".$this->options['CUIT'].'-'.$service.".tmp");

		//Request TA to WSAA
		$client = new SoapClient($this->WSAA_WSDL, array(
		'soap_version'   => SOAP_1_2,
		'location'       => $this->WSAA_URL,
		'trace'          => 1,
		'exceptions'     => 0
		)); 
		$results=$client->loginCms(array('in0'=>$CMS));
		if (is_soap_fault($results)) 
			throw new Exception("SOAP Fault: ".$results->faultcode."\n".$results->faultstring."\n", 4);

		$TA = $results->loginCmsReturn;

		if (file_put_contents($this->TA_FOLDER.'TA-'.$this->options['CUIT'].'-'.$service.'.xml', $TA)) 
			return TRUE;
		else
			throw new Exception('Error writing "TA-'.$this->options['CUIT'].'-'.$service.'.xml"', 5);
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

				include $file;

				return ($this->{$property} = new $property($this));
			}
		} else {
			return $this->{$property};
		}
	}
}

/**
 * Token Autorization
 *
 * @since 0.1
 *
 * @package Afip
 * @author 	Ivan Muñoz
 **/
class TokenAutorization {
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
 *
 * @since 0.5
 *
 * @package Afip
 * @author 	Ivan Muñoz
**/
class AfipWebService
{
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
	
	function __construct($afip)
	{
		$this->afip = $afip;

		if ($this->afip->options['production'] === TRUE) {
			$this->WSDL = __DIR__.'/Afip_res/'.$this->WSDL;
		} else {
			$this->WSDL = __DIR__.'/Afip_res/'.$this->WSDL_TEST;
			$this->URL 	= $this->URL_TEST;
		}

		if (!file_exists($this->WSDL)) 
			throw new Exception("Failed to open ".$this->WSDL."\n", 3);
	}

	/**
	 * Sends request to AFIP servers
	 * 
	 * @since 1.0
	 *
	 * @param string 	$operation 	SOAP operation to do 
	 * @param array 	$params 	Parameters to send
	 *
	 * @return mixed Operation results 
	 **/
	public function ExecuteRequest($operation, $params = array())
	{
		if (!isset($this->soap_client)) {
			$this->soap_client = new SoapClient($this->WSDL, array(
				'soap_version' 	=> $this->soap_version,
				'location' 		=> $this->URL,
				'trace' 		=> 1,
				'exceptions' 	=> 0
			)); 
		}

		$results = $this->soap_client->{$operation}($params);

		$this->_CheckErrors($operation, $results);

		return $results;
	}

	/**
	 * Check if occurs an error on Web Service request
	 * 
	 * @since 1.0
	 *
	 * @param string 	$operation 	SOAP operation to check 
	 * @param mixed 	$results 	AFIP response
	 *
	 * @throws Exception if exists an error in response 
	 * 
	 * @return void 
	 **/
	private function _CheckErrors($operation, $results)
	{
		if (is_soap_fault($results)) 
			throw new Exception("SOAP Fault: ".$results->faultcode."\n".$results->faultstring."\n", 4);
	}
}
