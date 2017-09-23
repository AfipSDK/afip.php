<?php
/**
 * Software Development Kit for AFIP Web Services
 * 
 * This first Afip SDK release is intended only for some items 
 * in WSFE specification, another items can be added later
 *
 * @link http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf WSFE Specification
 *
 * @author 	Ivan Muñoz
 * @package Afip
 * @version 0.1
 **/
class Afip {

	/**
	 * File name for the WSDL corresponding to WSAA
	 *
	 * @var string
	 **/
	var $WSAA_WSDL;
	/**
	 * File name for the WSDL corresponding to WSFE
	 *
	 * @var string
	 **/
	var $WSFE_WSDL;

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
	 * Afip resources folder
	 *
	 * @var string
	 **/
	var $RES_FOLDER;

	/**
	 * The passphrase (if any) to sign
	 *
	 * @var string
	 **/
	var $PASSPHRASE = 'xxxxx';

	/**
	 * The url to get WSAA token
	 *
	 * @var string
	 **/
	var $WSAA_URL;

	/**
	 * The url to WSFE
	 *
	 * @var string
	 **/
	var $WSFE_URL;

	/**
	 * The CUIL to use
	 *
	 * @var int
	 **/
	var $CUIL;

	function __construct($options = array())
	{
		ini_set("soap.wsdl_cache_enabled", "0");

		if (!isset($options['CUIT'])) {
			throw new Exception("CUIT field is required in options array");
		}
		else{
			$this->CUIT = $options['CUIT'];
		}

		if (!isset($options['production'])) {
			$options['production'] = FALSE;
		}

		$dir_name = dirname(__FILE__);

		$this->RES_FOLDER 	= $dir_name.'/Afip_res/';
		$this->CERT 		= $this->RES_FOLDER.'cert';
		$this->PRIVATEKEY 	= $this->RES_FOLDER.'key';

		$this->WSAA_WSDL 	= $this->RES_FOLDER.'wsaa.wsdl';
		if ($options['production'] === TRUE) {
			$this->WSFE_WSDL 	= $this->RES_FOLDER.'wsfe1-production.wsdl';
			$this->WSAA_URL 	= 'https://wsaa.afip.gov.ar/ws/services/LoginCms';
			$this->WSFE_URL 	= 'https://servicios1.afip.gov.ar/wsfev1/service.asmx';
		}
		else{
			$this->WSFE_WSDL 	= $this->RES_FOLDER.'wsfe1.wsdl';
			$this->WSAA_URL 	= 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms';
			$this->WSFE_URL 	= 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx';
		}

		if (!file_exists($this->CERT)) 
			throw new Exception("Failed to open ".$this->CERT."\n", 1);
		if (!file_exists($this->PRIVATEKEY)) 
			throw new Exception("Failed to open ".$this->PRIVATEKEY."\n", 2);
		if (!file_exists($this->WSAA_WSDL)) 
			throw new Exception("Failed to open ".$this->WSAA_WSDL."\n", 3);
		if (!file_exists($this->WSFE_WSDL)) 
			throw new Exception("Failed to open ".$this->WSFE_WSDL."\n", 3);
	}

	/**
	 * Gets last voucher number 
	 * 
	 * Asks to Afip servers for number of the last voucher created for
	 * certain sales point and voucher type {@see WSFE Specification 
	 * item 4.15} 
	 *
	 * @since 0.1
	 *
	 * @param int $sales_point 	Sales point to ask for last voucher  
	 * @param int $type 		Voucher type to ask for last voucher 
	 *
	 * @return int 
	 **/
	public function GetLastVoucher($sales_point, $type)
	{
		$req = array(
			'PtoVta' 	=> $sales_point,
			'CbteTipo' 	=> $type
			);

		return $this->ExecuteRequest('wsfe', 'FECompUltimoAutorizado', $req)->CbteNro;
	}

	/**
	 * Create a voucher from AFIP
	 *
	 * Send to AFIP servers request for create a voucher and assign 
	 * CAE to them {@see WSFE Specification item 4.1}
	 * 
	 * @since 0.1
	 *
	 * @param array $data Voucher parameters {@see WSFE Specification 
	 * 	item 4.1.3}, some arrays were simplified for easy use {@example 
	 * 	examples/CreateVoucher.php Example with all allowed
	 * 	 attributes}
	 * @param bool $return_response if is TRUE returns complete response  
	 * 	from AFIP
	 * 
	 * @return array if $return_response is set to FALSE returns 
	 * 	[CAE => CAE assigned to voucher, CAEFchVto => Expiration date 
	 * 	for CAE (yyyy-mm-dd)] else returns complete response from 
	 * 	AFIP {@see WSFE Specification item 4.1.3}
	**/
	public function CreateVoucher($data, $return_response = FALSE)
	{
		$req = array(
			'FeCAEReq' => array(
				'FeCabReq' => array(
					'CantReg' 	=> $data['CantReg'],
					'PtoVta' 	=> $data['PtoVta'],
					'CbteTipo' 	=> $data['CbteTipo']
					),
				'FeDetReq' => array( 
					'FECAEDetRequest' => &$data
				)
			)
		);

		unset($data['CantReg']);
		unset($data['PtoVta']);
		unset($data['CbteTipo']);

		if (isset($data['Tributos'])) 
			$data['Tributos'] = array('Tributo' => $data['Tributos']);

		if (isset($data['Iva'])) 
			$data['Iva'] = array('AlicIva' => $data['Iva']);

		if (isset($data['Opcionales'])) 
			$data['Opcionales'] = array('Opcional' => $data['Opcionales']);

		$results = $this->ExecuteRequest('wsfe', 'FECAESolicitar', $req);

		if ($return_response === TRUE) {
			return $results;
		}
		else{
			return array(
				'CAE' 		=> $results->FeDetResp->FECAEDetResponse->CAE,
				'CAEFchVto' => $this->FormatDate($results->FeDetResp->FECAEDetResponse->CAEFchVto),
			);
		}
	}

	/**
	 * Create next voucher from AFIP
	 *
	 * This method combines Afip::GetLastVoucher and Afip::CreateVoucher
	 * for create the next voucher
	 *
	 * @since 0.1
	 *
	 * @param array $data Same to $data in Afip::CreateVoucher except that
	 * 	don't need CbteDesde and CbteHasta attributes
	 *
	 * @return array [CAE => CAE assigned to voucher, CAEFchVto => Expiration 
	 * 	date for CAE (yyyy-mm-dd), voucher_number => Number assigned to 
	 * 	voucher]
	**/
	public function CreateNextVoucher($data)
	{
		$last_voucher = $this->GetLastVoucher($data['PtoVta'], $data['CbteTipo']);
		
		$voucher_number = $last_voucher+1;

		$data['CbteDesde'] = $voucher_number;
		$data['CbteHasta'] = $voucher_number;

		$res 					= $this->CreateVoucher($data);
		$res['voucher_number'] 	= $voucher_number;

		return $res;
	}

	/**
	 * Get complete voucher information
	 *
	 * Asks to AFIP servers for complete information of voucher {@see WSFE 
	 * Specification item 4.19}
	 *
	 * @since 0.1
	 *
	 * @param int $number 		Number of voucher to get information
	 * @param int $sales_point 	Sales point of voucher to get information
	 * @param int $type 			Type of voucher to get information
	 *
	 * @return array|null returns array with complete voucher information 
	 * 	{@see WSFE Specification item 4.19} or null if there not exists 
	**/
	public function GetVoucherInfo($number, $sales_point, $type)
	{
		$req = $this->GetWSInitialRequest('wsfe'); 
		$req = array(
			'FeCompConsReq' => array(
				'CbteNro' 	=> $number,
				'PtoVta' 	=> $sales_point,
				'CbteTipo' 	=> $type
			)
		);

		try {
			$result = $this->ExecuteRequest('wsfe', 'FECompConsultar', $req);
		} catch (Exception $e) {
			if ($e->getCode() == 602) 
				return NULL;
			else
				throw $e;
		}

		return $result->ResultGet;
	}

	/**
	 * Asks to AFIP Servers for voucher types availables {@see WSFE 
	 * Specification item 4.4}
	 *
	 * @since 0.1
	 *
	 * @return array All voucher types availables
	**/
	public function GetVoucherTypes()
	{
		return $this->ExecuteRequest('wsfe', 'FEParamGetTiposCbte')->ResultGet->CbteTipo;
	}

	/**
	 * Asks to AFIP Servers for voucher concepts availables {@see WSFE 
	 * Specification item 4.5}
	 *
	 * @since 0.1
	 *
	 * @return array All voucher concepts availables
	**/
	public function GetConceptTypes()
	{
		return $this->ExecuteRequest('wsfe', 'FEParamGetTiposConcepto')->ResultGet->ConceptoTipo;
	}

	/**
	 * Asks to AFIP Servers for document types availables {@see WSFE 
	 * Specification item 4.6}
	 *
	 * @since 0.1
	 *
	 * @return array All document types availables
	**/
	public function GetDocumentTypes()
	{
		return $this->ExecuteRequest('wsfe', 'FEParamGetTiposDoc')->ResultGet->DocTipo;
	}

	/**
	 * Asks to AFIP Servers for aliquot availables {@see WSFE 
	 * Specification item 4.7}
	 *
	 * @since 0.1
	 *
	 * @return array All aliquot availables
	**/
	public function GetAliquotTypes()
	{
		return $this->ExecuteRequest('wsfe', 'FEParamGetTiposIva')->ResultGet->IvaTipo;
	}

	/**
	 * Asks to AFIP Servers for currencies availables {@see WSFE 
	 * Specification item 4.8}
	 *
	 * @since 0.1
	 *
	 * @return array All currencies availables
	**/
	public function GetCurrenciesTypes()
	{
		return $this->ExecuteRequest('wsfe', 'FEParamGetTiposMonedas')->ResultGet->Moneda;
	}

	/**
	 * Asks to AFIP Servers for voucher optional data available {@see WSFE 
	 * Specification item 4.9}
	 *
	 * @since 0.1
	 *
	 * @return array All voucher optional data available
	**/
	public function GetOptionsTypes()
	{
		return $this->ExecuteRequest('wsfe', 'FEParamGetTiposOpcional')->ResultGet->OpcionalTipo;
	}

	/**
	 * Asks to AFIP Servers for tax availables {@see WSFE 
	 * Specification item 4.10}
	 *
	 * @since 0.1
	 *
	 * @return array All tax availables
	**/
	public function GetTaxTypes()
	{
		return $this->ExecuteRequest('wsfe', 'FEParamGetTiposTributos')->ResultGet->TributoTipo;
	}

	/**
	 * Change date from AFIP used format (yyyymmdd) to yyyy-mm-dd
	 *
	 * @since 0.1
	 *
	 * @param string|int date to format
	 *
	 * @return string date in format yyyy-mm-dd
	**/
	public function FormatDate($date)
	{
		return date_format(DateTime::CreateFromFormat('Ymd', $date.''), 'Y-m-d');
	}

	/**
	 * Sends request to AFIP servers
	 * 
	 * @since 0.1
	 *
	 * @param string 	$service 	AFIP Web Service to send request 
	 * @param string 	$operation 	SOAP operation to do 
	 * @param array 	$params 	Parameters to send
	 *
	 * @throws Exception if $service is not implemented yet
	 *
	 * @return mixed Operation results 
	 **/
	public function ExecuteRequest($service, $operation, $params = array())
	{
		if ($service != 'wsfe') {
			throw new Exception('Service not implemented yet');
		}

		if (!isset($this->{'client_'.$service})) {
			$this->{'client_'.$service} = new SoapClient($this->WSFE_WSDL, array(
				'soap_version' 	=> SOAP_1_2,
				'location' 		=> $this->WSFE_URL,
				'trace' 		=> 1,
				'exceptions' 	=> 0
			)); 
		}

		$params = array_replace($this->GetWSInitialRequest($service), $params); 

		$results = $this->{'client_'.$service}->{$operation}($params);

		$this->_CheckErrors($operation, $results);

		return $results->{$operation.'Result'};
	}

	/**
	 * Make default request parameters for WS with Auth headers
	 * 
	 * @since 0.1
	 *
	 * @param string $service Service to use 
	 *
	 * @return array Request parameters  
	 **/
	private function GetWSInitialRequest($service)
	{
		$ta = $this->GetServiceTA($service);

		return array(
			'Auth' => array( 
				'Token' => $ta->token,
				'Sign' 	=> $ta->sign,
				'Cuit' 	=> $this->CUIT
				)
		);
	}

	/**
	 * Check if occurs an error on Web Service request
	 * 
	 * @since 0.1
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

		$res = $results->{$operation.'Result'};

		if ($operation == 'FECAESolicitar') {
			if (isset($res->FeDetResp->FECAEDetResponse->Observaciones) && $res->FeDetResp->FECAEDetResponse->Resultado != 'A') {
				$res->Errors = new StdClass();
				$res->Errors->Err = $res->FeDetResp->FECAEDetResponse->Observaciones->Obs;
			}
		}

		if (isset($res->Errors)) {
			$err = is_array($res->Errors->Err) ? $res->Errors->Err[0] : $res->Errors->Err;
			throw new Exception('('.$err->Code.') '.$err->Msg, $err->Code);
		}
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
	 * @return WSAA_TA Token Autorization for AFIP Web Service 
	**/
	private function GetServiceTA($service, $continue = TRUE)
	{
		if (file_exists($this->RES_FOLDER.'TA-'.$service.'.xml')) {
			$TA = new SimpleXMLElement(file_get_contents($this->RES_FOLDER.'TA-'.$service.'.xml'));

			$actual_time 		= new DateTime(date('c',date('U')+600));
			$expiration_time 	= new DateTime($TA->header->expirationTime);

			if ($actual_time < $expiration_time) 
				return new WSAA_TA($TA->credentials->token, $TA->credentials->sign);
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
		$TRA->asXML($this->RES_FOLDER.'TRA-'.$service.'.xml');

		//Signing TRA
		$STATUS = openssl_pkcs7_sign($this->RES_FOLDER."TRA-".$service.".xml", $this->RES_FOLDER."TRA-".$service.".tmp", "file://".$this->CERT,
			array("file://".$this->PRIVATEKEY, $this->PASSPHRASE),
			array(),
			!PKCS7_DETACHED
		);
		if (!$STATUS) {return FALSE;}
		$inf = fopen($this->RES_FOLDER."TRA-".$service.".tmp", "r");
		$i = 0;
		$CMS="";
		while (!feof($inf)) {
			$buffer=fgets($inf);
			if ( $i++ >= 4 ) {$CMS.=$buffer;}
		}
		fclose($inf);
		unlink($this->RES_FOLDER."TRA-".$service.".xml");
		unlink($this->RES_FOLDER."TRA-".$service.".tmp");

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

		if (file_put_contents($this->RES_FOLDER.'TA-'.$service.'.xml', $TA)) 
			return TRUE;
		else
			throw new Exception('Error writing "TA-'.$service.'.xml"', 5);
	}	
}

/**
 * WSAA Token Autorization
 *
 * @package Afip
 * @author 	Ivan Muñoz
 **/
class WSAA_TA {
	/**
	 * WSAA Token
	 *
	 * @var string
	 **/
	var $token;

	/**
	 * WSAA Sign
	 *
	 * @var string
	 **/
	var $sign;

	function __construct($token, $sign)
	{
		$this->token = $token;
		$this->sign = $sign;
	}
}
