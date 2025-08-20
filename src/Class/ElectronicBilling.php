<?php
/**
 * SDK for AFIP Electronic Billing (wsfe1)
 * 
 * @link https://docs.afipsdk.com/
 *
 * @author 	Afip SDK
 * @package Afip
 **/

class ElectronicBilling extends AfipWebService {

	var $soap_version 	= SOAP_1_2;
	var $WSDL 			= 'wsfe-production.wsdl';
	var $URL 			= 'https://servicios1.afip.gov.ar/wsfev1/service.asmx';
	var $WSDL_TEST 		= 'wsfe.wsdl';
	var $URL_TEST 		= 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx';

	function __construct($afip) {
        parent::__construct($afip, array('service' => 'wsfe'));
    }

	/**
	 * Create PDF 
	 * 
	 * Send a request to Afip SDK server to create a PDF
	 *
	 * @param array $data Data for PDF creation
	 **/
	public function CreatePDF($data)
	{
		$headers = array(
			'sdk-version-number' => $this->afip->sdk_version_number,
			'sdk-library' => 'php',
			'sdk-environment' => $this->afip->options['production'] === TRUE ? "prod" : "dev"
		);

		if (isset($this->afip->options['access_token'])) {
			$headers['Authorization'] = 'Bearer '.$this->afip->options['access_token'];
		}

		$request = Requests::post('https://app.afipsdk.com/api/v1/pdfs', $headers, $data);

		if ($request->success) {
			$decoded_res = json_decode($request->body);

			return array(
				"file" => $decoded_res->file,
				"file_name" => $decoded_res->file_name
			);
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
	 * Gets last voucher number 
	 * 
	 * Asks to Afip servers for number of the last voucher created for
	 * certain sales point and voucher type {@see WS Specification 
	 * item 4.15} 
	 *
	 * @since 0.7
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

		return $this->ExecuteRequest('FECompUltimoAutorizado', $req)->CbteNro;
	}

	/**
	 * Create a voucher from AFIP
	 *
	 * Send to AFIP servers request for create a voucher and assign 
	 * CAE to them {@see WS Specification item 4.1}
	 * 
	 * @since 0.7
	 *
	 * @param array $data Voucher parameters {@see WS Specification 
	 * 	item 4.1.3}, some arrays were simplified for easy use {@example 
	 * 	examples/CreateVoucher.php Example with all allowed
	 * 	 attributes}
	 * @param bool $return_response if is TRUE returns complete response  
	 * 	from AFIP
	 * 
	 * @return array if $return_response is set to FALSE returns 
	 * 	[CAE => CAE assigned to voucher, CAEFchVto => Expiration date 
	 * 	for CAE (yyyy-mm-dd)] else returns complete response from 
	 * 	AFIP {@see WS Specification item 4.1.3}
	**/
	public function CreateVoucher($data, $return_response = FALSE)
	{
		$req = array(
			'FeCAEReq' => array(
				'FeCabReq' => array(
					'CantReg' 	=> $data['CbteHasta']-$data['CbteDesde']+1,
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

		if (isset($data['Compradores'])) 
			$data['Compradores'] = array('Comprador' => $data['Compradores']);
		
		if (isset($data['CbtesAsoc'])) 
			$data['CbtesAsoc'] = array('CbteAsoc' => $data['CbtesAsoc']);

		if (isset($data['Iva'])) 
			$data['Iva'] = array('AlicIva' => $data['Iva']);

		if (isset($data['Opcionales'])) 
			$data['Opcionales'] = array('Opcional' => $data['Opcionales']);

		$results = $this->ExecuteRequest('FECAESolicitar', $req);

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
	 * @since 0.7
	 *
	 * @param array $data Same to $data in Afip::CreateVoucher except that
	 * 	don't need CbteDesde and CbteHasta attributes
	 * 
	 * @param bool $return_response if is TRUE returns complete response  
	 * 	from AFIP
	 *
	 * @return array if $return_response is set to false returns 
	 * [CAE => CAE assigned to voucher, CAEFchVto => Expiration 
	 * 	date for CAE (yyyy-mm-dd), voucher_number => Number assigned to 
	 * 	voucher] else returns the complete response (same as in method CreateVoucher)
	 *  and the voucher_number
	**/
	public function CreateNextVoucher($data, $return_response = FALSE)
	{
		$last_voucher = $this->GetLastVoucher($data['PtoVta'], $data['CbteTipo']);
		
		$voucher_number = $last_voucher+1;

		$data['CbteDesde'] = $voucher_number;
		$data['CbteHasta'] = $voucher_number;
		
		$res                   = $this->CreateVoucher($data, $return_response);
		$res['voucher_number'] = $voucher_number;

		return $res;
	}

	/**
	 * Get complete voucher information
	 *
	 * Asks to AFIP servers for complete information of voucher {@see WS 
	 * Specification item 4.19}
	 *
	 * @since 0.7
	 *
	 * @param int $number 		Number of voucher to get information
	 * @param int $sales_point 	Sales point of voucher to get information
	 * @param int $type 			Type of voucher to get information
	 *
	 * @return array|null returns array with complete voucher information 
	 * 	{@see WS Specification item 4.19} or null if there not exists 
	**/
	public function GetVoucherInfo($number, $sales_point, $type)
	{
		$req = array(
			'FeCompConsReq' => array(
				'CbteNro' 	=> $number,
				'PtoVta' 	=> $sales_point,
				'CbteTipo' 	=> $type
			)
		);

		try {
			$result = $this->ExecuteRequest('FECompConsultar', $req);
		} catch (Exception $e) {
			if ($e->getCode() == 602) 
				return NULL;
			else
				throw $e;
		}

		return $result->ResultGet;
	}

	/**
	 * Create CAEA 
	 * 
	 * Send a request to AFIP servers  to create a CAEA
	 *
	 * @param int $period 		Time period
	 * @param int $fortnight	Monthly fortnight (1 or 2)
	 **/
	public function CreateCAEA($period, $fortnight)
	{
		$req = array(
			'Periodo' => $period,
			'Orden' => $fortnight
		);

		return $this->ExecuteRequest('FECAEASolicitar', $req)->ResultGet;
	}

	/**
	 * Get CAEA 
	 * 
	 * Ask to AFIP servers for a CAEA information
	 *
	 * @param int $period 		Time period
	 * @param int $fortnight	Monthly fortnight (1 or 2)
	 **/
	public function GetCAEA($period, $fortnight)
	{
		$req = array(
			'Periodo' => $period,
			'Orden' => $fortnight
		);

		return $this->ExecuteRequest('FECAEAConsultar', $req)->ResultGet;
	}

	/**
	 * Asks to AFIP Servers for sales points availables {@see WS 
	 * Specification item 4.11}
	 *
	 * @return array All sales points availables
	**/
	public function GetSalesPoints()
	{
		return $this->ExecuteRequest('FEParamGetPtosVenta')->ResultGet->PtoVenta;
	}

	/**
	 * Asks to AFIP Servers for voucher types availables {@see WS 
	 * Specification item 4.4}
	 *
	 * @since 0.7
	 *
	 * @return array All voucher types availables
	**/
	public function GetVoucherTypes()
	{
		return $this->ExecuteRequest('FEParamGetTiposCbte')->ResultGet->CbteTipo;
	}

	/**
	 * Asks to AFIP Servers for voucher concepts availables {@see WS 
	 * Specification item 4.5}
	 *
	 * @since 0.7
	 *
	 * @return array All voucher concepts availables
	**/
	public function GetConceptTypes()
	{
		return $this->ExecuteRequest('FEParamGetTiposConcepto')->ResultGet->ConceptoTipo;
	}

	/**
	 * Asks to AFIP Servers for document types availables {@see WS 
	 * Specification item 4.6}
	 *
	 * @since 0.7
	 *
	 * @return array All document types availables
	**/
	public function GetDocumentTypes()
	{
		return $this->ExecuteRequest('FEParamGetTiposDoc')->ResultGet->DocTipo;
	}

	/**
	 * Asks to AFIP Servers for aliquot availables {@see WS 
	 * Specification item 4.7}
	 *
	 * @since 0.7
	 *
	 * @return array All aliquot availables
	**/
	public function GetAliquotTypes()
	{
		return $this->ExecuteRequest('FEParamGetTiposIva')->ResultGet->IvaTipo;
	}

	/**
	 * Asks to AFIP Servers for currencies availables {@see WS 
	 * Specification item 4.8}
	 *
	 * @since 0.7
	 *
	 * @return array All currencies availables
	**/
	public function GetCurrenciesTypes()
	{
		return $this->ExecuteRequest('FEParamGetTiposMonedas')->ResultGet->Moneda;
	}

	/**
	 * Asks to AFIP Servers for voucher optional data available {@see WS 
	 * Specification item 4.9}
	 *
	 * @since 0.7
	 *
	 * @return array All voucher optional data available
	**/
	public function GetOptionsTypes()
	{
		return $this->ExecuteRequest('FEParamGetTiposOpcional')->ResultGet->OpcionalTipo;
	}

	/**
	 * Asks to AFIP Servers for tax availables {@see WS 
	 * Specification item 4.10}
	 *
	 * @since 0.7
	 *
	 * @return array All tax availables
	**/
	public function GetTaxTypes()
	{
		return $this->ExecuteRequest('FEParamGetTiposTributos')->ResultGet->TributoTipo;
	}

	/**
	 * Asks to web service for servers status {@see WS 
	 * Specification item 4.14}
	 *
	 * @since 0.7
	 *
	 * @return object { AppServer => Web Service status, 
	 * DbServer => Database status, AuthServer => Autentication 
	 * server status}
	**/
	public function GetServerStatus()
	{
		return $this->ExecuteRequest('FEDummy');
	}

	/**
	 * Change date from AFIP used format (yyyymmdd) to yyyy-mm-dd
	 *
	 * @since 0.7
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
	 * @since 0.7
	 *
	 * @param string 	$operation 	SOAP operation to do 
	 * @param array 	$params 	Parameters to send
	 *
	 * @return mixed Operation results 
	 **/
	public function ExecuteRequest($operation, $params = array())
	{
		$this->options = array('service' => 'wsfe');

		$params = array_replace($this->GetWSInitialRequest($operation), $params); 

		$results = parent::ExecuteRequest($operation, $params);

		$this->_CheckErrors($operation, $results);

		return $results->{$operation.'Result'};
	}

	/**
	 * Make default request parameters for most of the operations
	 * 
	 * @since 0.7
	 *
	 * @param string $operation SOAP Operation to do 
	 *
	 * @return array Request parameters  
	 **/
	private function GetWSInitialRequest($operation)
	{
		if ($operation == 'FEDummy') {
			return array();
		}

		$ta = $this->afip->GetServiceTA('wsfe');

		return array(
			'Auth' => array( 
				'Token' => $ta->token,
				'Sign' 	=> $ta->sign,
				'Cuit' 	=> $this->afip->CUIT
				)
		);
	}

	/**
	 * Check if occurs an error on Web Service request
	 * 
	 * @since 0.7
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
		$res = $results->{$operation.'Result'};
		
		if ($operation == 'FECAESolicitar' && isset($res->FeDetResp)) {
			if (is_array($res->FeDetResp->FECAEDetResponse)) {
				$res->FeDetResp->FECAEDetResponse = $res->FeDetResp->FECAEDetResponse[0];
			}

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

}

