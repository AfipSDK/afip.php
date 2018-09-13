<?php
/**
 * SDK for AFIP Electronic Billing (wsfe1)
 * 
 * @link http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf WS Specification
 *
 * @author 	Ivan Muñoz
 * @package Afip
 * @version 0.7
 **/

class ElectronicBilling extends AfipWebService {

	var $soap_version 	= SOAP_1_2;
	var $WSDL 			= 'wsfe-production.wsdl';
	var $URL 			= 'https://servicios1.afip.gov.ar/wsfev1/service.asmx';
	var $WSDL_TEST 		= 'wsfe.wsdl';
	var $URL_TEST 		= 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx';

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

}

