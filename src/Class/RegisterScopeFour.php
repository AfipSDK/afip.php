<?php
/**
 * SDK for AFIP Register Scope Four (ws_sr_padron_a4)
 * 
 * @link https://docs.afipsdk.com/
 *
 * @author 	Afip SDK
 * @package Afip
 * @version 1.0
 **/

class RegisterScopeFour extends AfipWebService {

	var $soap_version 	= SOAP_1_1;
	var $WSDL 			= 'ws_sr_padron_a4-production.wsdl';
	var $URL 			= 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA4';
	var $WSDL_TEST 		= 'ws_sr_padron_a4.wsdl';
	var $URL_TEST 		= 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA4';

	function __construct($afip) {
        parent::__construct($afip, array('service' => 'ws_sr_padron_a4'));
    }

	/**
	 * Asks to web service for servers status {@see WS 
	 * Specification item 3.1}
	 *
	 * @since 1.0
	 *
	 * @return object { appserver => Web Service status, 
	 * dbserver => Database status, authserver => Autentication 
	 * server status}
	**/
	public function GetServerStatus()
	{
		return $this->ExecuteRequest('dummy');
	}

	/**
	 * Asks to web service for taxpayer details {@see WS 
	 * Specification item 3.2}
	 *
	 * @since 1.0
	 *
	 * @throws Exception if exists an error in response 
	 *
	 * @return object|null if taxpayer does not exists, return null,  
	 * if it exists, returns persona property of response {@see 
	 * WS Specification item 3.2.2}
	**/
	public function GetTaxpayerDetails($identifier)
	{
		$ta = $this->afip->GetServiceTA('ws_sr_padron_a4');
		
		$params = array(
			'token' 			=> $ta->token,
			'sign' 				=> $ta->sign,
			'cuitRepresentada' 	=> $this->afip->CUIT,
			'idPersona' 		=> $identifier
		);

		try {
			return $this->ExecuteRequest('getPersona', $params)->persona;
		} catch (Exception $e) {
			if (strpos($e->getMessage(), 'No existe') !== FALSE)
				return NULL;
			else
				throw $e;
		}
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
		$this->options = array('service' => 'ws_sr_padron_a4');

		$results = parent::ExecuteRequest($operation, $params);

		return $results->{$operation == 'getPersona' ? 'personaReturn' : 'return'};
	}
}

