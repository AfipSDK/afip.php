<?php
/**
 * SDK for Factura SDK: electronic invoicing end to end
 *
 * Issuers, certificates, invoices and webhooks of a Factura SDK project.
 * The access token (fsk_dev_… for the sandbox, fsk_prod_… for production)
 * decides the environment.
 *
 * @link https://afipsdk.com/docs/facturasdk/
 *
 * @author 	Afip SDK
 * @package Afip
 **/

#[\AllowDynamicProperties]
class FacturaSDK {
	/**
	 * Base URL of the Factura SDK API
	 *
	 * @var string
	 **/
	var $api_url = 'https://app.afipsdk.com/api/v1';

	/**
	 * The Afip parent Class
	 *
	 * @var Afip
	 **/
	var $afip;

	/**
	 * Access token sent in every request: the access token of Afip
	 *
	 * @var string
	 **/
	var $access_token;

	/**
	 * Environment reported to the API: the one of the token when it is a
	 * Factura SDK token, otherwise the production option
	 *
	 * @var string
	 **/
	var $environment;

	/**
	 * Seconds between polls and polls before giving up: automations (ARCA with
	 * the tax password, verification) take from seconds to a couple of minutes,
	 * an invoice is authorized in the background in seconds
	 **/
	var $automation_interval = 3;
	var $automation_attempts = 60;
	var $invoice_interval = 2;
	var $invoice_attempts = 60;

	function __construct($afip)
	{
		$this->afip = $afip;

		$this->access_token = isset($afip->options['access_token']) ? $afip->options['access_token'] : NULL;

		if ($this->access_token && strpos($this->access_token, 'fsk_prod_') === 0) {
			$this->environment = 'prod';
		} elseif ($this->access_token && strpos($this->access_token, 'fsk_dev_') === 0) {
			$this->environment = 'dev';
		} else {
			$this->environment = $afip->options['production'] === TRUE ? 'prod' : 'dev';
		}
	}

	// Invoices

	/**
	 * Issue an invoice
	 *
	 * By default Factura SDK answers right away with the invoice queued and
	 * authorizes it in ARCA in the background: the result arrives at your
	 * webhook. With `wait` the method polls until the invoice is decided;
	 * with `sync` TRUE in the data Factura SDK decides it within the request.
	 *
	 * @param array $data Invoice data {@see https://afipsdk.com/docs/facturasdk/invoices}
	 * @param array $options idempotency_key (identifier of the sale in your
	 * 	system; repeating it returns the same invoice instead of issuing
	 * 	another one) and wait (default FALSE)
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed The invoice
	 **/
	public function CreateInvoice($data, $options = array())
	{
		$headers = array();

		if (!empty($options['idempotency_key'])) {
			$headers['Idempotency-Key'] = $options['idempotency_key'];
		}

		$invoice = $this->Request(Requests::POST, 'invoices', $data, array(), $headers);

		if (!empty($options['wait']) && $this->IsPending($invoice)) {
			return $this->WaitForInvoice($invoice->id);
		}

		return $invoice;
	}

	/**
	 * Get an invoice with its current status
	 *
	 * @param string $id Id of the invoice
	 * @param boolean $wait Wait until the invoice reaches a final status (default FALSE)
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed The invoice
	 **/
	public function GetInvoice($id, $wait = FALSE)
	{
		if ($wait === TRUE) {
			return $this->WaitForInvoice($id);
		}

		return $this->Request(Requests::GET, 'invoices/'.$id);
	}

	/**
	 * Wait for an invoice to be decided: approved, rejected or failed
	 *
	 * Polls the invoice every 2 seconds for up to 2 minutes. The invoice keeps
	 * its course in Factura SDK if the wait ends; the result still arrives at
	 * your webhook
	 *
	 * @param string $id Id of the invoice
	 *
	 * @throws FacturaSDKError if the API answers an error
	 * @throws Exception if the invoice is still pending after the wait
	 *
	 * @return mixed The invoice
	 **/
	public function WaitForInvoice($id)
	{
		$attempts = $this->invoice_attempts;

		while ($attempts-- > 0) {
			$invoice = $this->Request(Requests::GET, 'invoices/'.$id);

			if (!$this->IsPending($invoice)) {
				return $invoice;
			}

			sleep($this->invoice_interval);
		}

		throw new Exception('Error: Waiting for too long');
	}

	/**
	 * List invoices, newest first, with cursor pagination
	 *
	 * @param array $params Filters: issuer_cuit, status, invoice_type, start, end, limit, cursor
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed data, next_cursor and total_count
	 **/
	public function GetInvoices($params = array())
	{
		return $this->Request(Requests::GET, 'invoices', NULL, $params);
	}

	/**
	 * Cancel an invoice that is still queued. It never gets issued
	 *
	 * @param string $id Id of the invoice
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed The invoice, cancelled
	 **/
	public function CancelInvoice($id)
	{
		return $this->Request(Requests::POST, 'invoices/'.$id.'/cancel');
	}

	// Issuers

	/**
	 * Create an issuer: a tax id that invoices. It is created pending and
	 * invoices once verified
	 *
	 * @param array $data Issuer data {@see https://afipsdk.com/docs/facturasdk/issuers}
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed The issuer
	 **/
	public function CreateIssuer($data)
	{
		return $this->Request(Requests::POST, 'issuers', $data);
	}

	/**
	 * Get an issuer
	 *
	 * @param string $id Id of the issuer
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed The issuer
	 **/
	public function GetIssuer($id)
	{
		return $this->Request(Requests::GET, 'issuers/'.$id);
	}

	/**
	 * List issuers, with cursor pagination
	 *
	 * @param array $params Filters: tax_id, certificate_id, status, limit, cursor
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed data, next_cursor and total_count
	 **/
	public function GetIssuers($params = array())
	{
		return $this->Request(Requests::GET, 'issuers', NULL, $params);
	}

	/**
	 * Update an issuer: only the fields sent change
	 *
	 * @param string $id Id of the issuer
	 * @param array $data Fields to change: fiscal data, sales_point, certificate_id or status
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed The issuer, updated
	 **/
	public function UpdateIssuer($id, $data)
	{
		return $this->Request(Requests::PUT, 'issuers/'.$id, $data);
	}

	/**
	 * Delete an issuer. Its invoices remain
	 *
	 * @param string $id Id of the issuer
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return void
	 **/
	public function DeleteIssuer($id)
	{
		$this->Request(Requests::DELETE, 'issuers/'.$id);
	}

	/**
	 * Get the data of a tax id from its registration certificate in ARCA, to
	 * create the issuer without typing them
	 *
	 * @param string|int $tax_id Tax id (CUIT)
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed tax_id, issuer (the fields found), unresolved, warnings, taxpayer and inscription_proof
	 **/
	public function LookupIssuer($tax_id)
	{
		return $this->Request(Requests::GET, 'issuers/lookup', NULL, array('tax_id' => $tax_id));
	}

	/**
	 * List the sales points of a tax id in ARCA, with its tax password
	 *
	 * It is an automation: with `wait` returns it finished, with the sales
	 * points in `data`
	 *
	 * @param array $data tax_id, username and password
	 * @param boolean $wait Wait for the automation to finish (default TRUE)
	 *
	 * @throws FacturaSDKError if the API answers an error or the automation fails
	 *
	 * @return mixed The automation
	 **/
	public function GetSalesPoints($data, $wait = TRUE)
	{
		return $this->Automation($this->Request(Requests::POST, 'issuers/sales-points', $data), $wait);
	}

	/**
	 * Create in ARCA a sales point for web services for a tax id, with its
	 * tax password
	 *
	 * It is an automation: with `wait` returns it finished, with the sales
	 * point in `data`
	 *
	 * @param array $data tax_id, username, password and optionally vat_condition, number, name, system
	 * @param boolean $wait Wait for the automation to finish (default TRUE)
	 *
	 * @throws FacturaSDKError if the API answers an error or the automation fails
	 *
	 * @return mixed The automation
	 **/
	public function CreateSalesPoint($data, $wait = TRUE)
	{
		return $this->Automation($this->Request(Requests::POST, 'issuers/sales-points/create', $data), $wait);
	}

	/**
	 * Create a certificate for the issuer and assign it
	 *
	 * Uploaded (cert and key in PEM) it answers the certificate. Created in
	 * ARCA (source "automation", with username and password) it is an
	 * automation: with `wait` returns it finished, with the certificate in `data`
	 *
	 * @param string $id Id of the issuer
	 * @param array $data Certificate data {@see https://afipsdk.com/docs/facturasdk/certificates}
	 * @param boolean $wait Wait for the automation to finish (default TRUE)
	 *
	 * @throws FacturaSDKError if the API answers an error or the automation fails
	 *
	 * @return mixed The certificate, or the automation
	 **/
	public function CreateIssuerCertificate($id, $data, $wait = TRUE)
	{
		return $this->Automation($this->Request(Requests::POST, 'issuers/'.$id.'/certificate', $data), $wait);
	}

	/**
	 * Accept in ARCA the delegation of the issuer to the owner of its
	 * certificate and authorize the certificate to invoice for it, with the
	 * tax password of the certificate owner
	 *
	 * It is an automation: with `wait` returns it finished
	 *
	 * @param string $id Id of the issuer
	 * @param array $data username and password of the certificate owner
	 * @param boolean $wait Wait for the automation to finish (default TRUE)
	 *
	 * @throws FacturaSDKError if the API answers an error or the automation fails
	 *
	 * @return mixed The automation
	 **/
	public function CreateIssuerDelegation($id, $data, $wait = TRUE)
	{
		return $this->Automation($this->Request(Requests::POST, 'issuers/'.$id.'/delegation', $data), $wait);
	}

	/**
	 * Verify an issuer: issues a $1 invoice and the credit note that cancels
	 * it, and the issuer becomes active. In production both vouchers are real
	 *
	 * It is an automation: with `wait` returns it finished, with verified,
	 * issuer, invoice and credit_note in `data`
	 *
	 * @param string $id Id of the issuer
	 * @param boolean $wait Wait for the automation to finish (default TRUE)
	 *
	 * @throws FacturaSDKError if the API answers an error or the automation fails
	 *
	 * @return mixed The automation
	 **/
	public function VerifyIssuer($id, $wait = TRUE)
	{
		return $this->Automation($this->Request(Requests::POST, 'issuers/'.$id.'/verify'), $wait);
	}

	// Certificates

	/**
	 * Add a certificate to the project
	 *
	 * Uploaded (cert and key in PEM) it answers the certificate. Created in
	 * ARCA (source "automation", with tax_id, username and password) it is an
	 * automation: with `wait` returns it finished, with the certificate in `data`
	 *
	 * @param array $data Certificate data {@see https://afipsdk.com/docs/facturasdk/certificates}
	 * @param boolean $wait Wait for the automation to finish (default TRUE)
	 *
	 * @throws FacturaSDKError if the API answers an error or the automation fails
	 *
	 * @return mixed The certificate, or the automation
	 **/
	public function CreateCertificate($data, $wait = TRUE)
	{
		return $this->Automation($this->Request(Requests::POST, 'certificates', $data), $wait);
	}

	/**
	 * Get a certificate
	 *
	 * @param string $id Id of the certificate
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed The certificate
	 **/
	public function GetCertificate($id)
	{
		return $this->Request(Requests::GET, 'certificates/'.$id);
	}

	/**
	 * List certificates, with cursor pagination
	 *
	 * @param array $params Filters: tax_id, status, limit, cursor
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed data, next_cursor and total_count
	 **/
	public function GetCertificates($params = array())
	{
		return $this->Request(Requests::GET, 'certificates', NULL, $params);
	}

	/**
	 * Revoke a certificate: it no longer signs
	 *
	 * @param string $id Id of the certificate
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed The certificate, revoked
	 **/
	public function RevokeCertificate($id)
	{
		return $this->Request(Requests::POST, 'certificates/'.$id.'/revoke');
	}

	/**
	 * Authorize a certificate in ARCA for the electronic billing web service
	 * (wsfe), with the tax password of its owner
	 *
	 * It is an automation: with `wait` returns it finished
	 *
	 * @param string $id Id of the certificate
	 * @param array $data password and optionally username
	 * @param boolean $wait Wait for the automation to finish (default TRUE)
	 *
	 * @throws FacturaSDKError if the API answers an error or the automation fails
	 *
	 * @return mixed The automation
	 **/
	public function AuthorizeCertificate($id, $data, $wait = TRUE)
	{
		return $this->Automation($this->Request(Requests::POST, 'certificates/'.$id.'/authorize', $data), $wait);
	}

	// Webhooks

	/**
	 * Create the webhook endpoint: the URL where Factura SDK posts the result
	 * of each invoice. The answer brings the secret, shown only this once
	 *
	 * @param array $data url
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed The endpoint, with its secret
	 **/
	public function CreateWebhookEndpoint($data)
	{
		return $this->Request(Requests::POST, 'webhook-endpoints', $data);
	}

	/**
	 * Get a webhook endpoint
	 *
	 * @param string $id Id of the endpoint
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed The endpoint
	 **/
	public function GetWebhookEndpoint($id)
	{
		return $this->Request(Requests::GET, 'webhook-endpoints/'.$id);
	}

	/**
	 * List webhook endpoints
	 *
	 * @param array $params Filters: status
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed data, next_cursor and total_count
	 **/
	public function GetWebhookEndpoints($params = array())
	{
		return $this->Request(Requests::GET, 'webhook-endpoints', NULL, $params);
	}

	/**
	 * Update a webhook endpoint: url or status (active or inactive)
	 *
	 * @param string $id Id of the endpoint
	 * @param array $data Fields to change
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed The endpoint, updated
	 **/
	public function UpdateWebhookEndpoint($id, $data)
	{
		return $this->Request(Requests::PUT, 'webhook-endpoints/'.$id, $data);
	}

	/**
	 * Generate a new secret for a webhook endpoint. From then on every
	 * delivery is signed with it
	 *
	 * @param string $id Id of the endpoint
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed The endpoint, with the new secret
	 **/
	public function RotateWebhookEndpointSecret($id)
	{
		return $this->Request(Requests::POST, 'webhook-endpoints/'.$id.'/rotate');
	}

	/**
	 * Send a signed test event to a webhook endpoint
	 *
	 * @param string $id Id of the endpoint
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed delivered, http_status, error and the delivery
	 **/
	public function TestWebhookEndpoint($id)
	{
		return $this->Request(Requests::POST, 'webhook-endpoints/'.$id.'/test');
	}

	/**
	 * List the deliveries of a webhook endpoint: what was sent, when and with
	 * what result
	 *
	 * @param string $id Id of the endpoint
	 * @param array $params Filters: status, invoice_id, limit, cursor
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed data, next_cursor and total_count
	 **/
	public function GetWebhookDeliveries($id, $params = array())
	{
		return $this->Request(Requests::GET, 'webhook-endpoints/'.$id.'/deliveries', NULL, $params);
	}

	/**
	 * Verify the signature of a webhook delivery
	 *
	 * The X-FacturaSDK-Signature header is "t=<timestamp>,v1=<signature>",
	 * where v1 is the HMAC-SHA256 of "<timestamp>.<body>" with the secret of
	 * the endpoint. The body has to be the raw one received, byte by byte
	 * (file_get_contents('php://input'))
	 *
	 * @param string $signature Value of the X-FacturaSDK-Signature header
	 * @param string $body Raw body of the request
	 * @param string $secret Secret of the endpoint
	 * @param int $tolerance Seconds the timestamp may differ from now (default 300)
	 *
	 * @return boolean
	 **/
	public function VerifyWebhookSignature($signature, $body, $secret, $tolerance = 300)
	{
		if (!is_string($signature) || !$secret) {
			return FALSE;
		}

		$parts = array();

		foreach (explode(',', $signature) as $part) {
			$pair = explode('=', $part, 2);
			$parts[$pair[0]] = isset($pair[1]) ? $pair[1] : '';
		}

		if (!isset($parts['t']) || !ctype_digit($parts['t'])) {
			return FALSE;
		}

		$timestamp = (int) $parts['t'];
		$received = isset($parts['v1']) ? $parts['v1'] : '';

		$expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

		$fresh = abs(time() - $timestamp) <= $tolerance;

		return $fresh && hash_equals($expected, $received);
	}

	/**
	 * Verify the signature of a webhook delivery and parse its event
	 *
	 * @param string $body Raw body of the request
	 * @param string $signature Value of the X-FacturaSDK-Signature header
	 * @param string $secret Secret of the endpoint
	 * @param int $tolerance Seconds the timestamp may differ from now (default 300)
	 *
	 * @throws FacturaSDKError if the signature is not valid
	 *
	 * @return mixed The event: event_id, type, created and data
	 **/
	public function ConstructWebhookEvent($body, $signature, $secret, $tolerance = 300)
	{
		if (!$this->VerifyWebhookSignature($signature, $body, $secret, $tolerance)) {
			throw new FacturaSDKError('La firma del webhook no es válida');
		}

		return json_decode($body);
	}

	// Automations

	/**
	 * Get an automation
	 *
	 * @param string $id Id of the automation
	 * @param boolean $wait Wait for the automation to finish (default FALSE)
	 *
	 * @throws FacturaSDKError if the API answers an error or the automation finished with an error
	 *
	 * @return mixed The automation: id, status and data
	 **/
	public function GetAutomation($id, $wait = FALSE)
	{
		if ($wait === TRUE) {
			return $this->WaitForAutomation($id);
		}

		return $this->Request(Requests::GET, 'automations/'.$id);
	}

	/**
	 * Wait for an automation to finish
	 *
	 * Polls it every 3 seconds for up to 3 minutes
	 *
	 * @param string $id Id of the automation
	 *
	 * @throws FacturaSDKError if the API answers an error or the automation finished with an error
	 * @throws Exception if the automation is still running after the wait
	 *
	 * @return mixed The automation: id, status and data
	 **/
	public function WaitForAutomation($id)
	{
		$attempts = $this->automation_attempts;

		while ($attempts-- > 0) {
			$automation = $this->Request(Requests::GET, 'automations/'.$id);

			if ($automation->status !== 'in_process') {
				return $automation;
			}

			sleep($this->automation_interval);
		}

		throw new Exception('Error: Waiting for too long');
	}

	/**
	 * Resolve the answer of an endpoint that may start an automation: when it
	 * did (202 with automation_id) and `wait` is set, waits for it to finish
	 *
	 * @param mixed $result Decoded answer of the endpoint
	 * @param boolean $wait Wait for the automation to finish
	 *
	 * @return mixed The answer, or the finished automation
	 **/
	private function Automation($result, $wait)
	{
		if ($wait === TRUE && is_object($result) && isset($result->automation_id)) {
			return $this->WaitForAutomation($result->automation_id);
		}

		return $result;
	}

	/**
	 * Whether the invoice is still queued or processing
	 *
	 * @param mixed $invoice
	 *
	 * @return boolean
	 **/
	private function IsPending($invoice)
	{
		return is_object($invoice) && isset($invoice->status) && in_array($invoice->status, array('queued', 'processing'));
	}

	/**
	 * Send a request to the Factura SDK API
	 *
	 * @param string $method HTTP method
	 * @param string $path Path after /v1/
	 * @param array|null $data Body, sent as JSON
	 * @param array $params Query string parameters
	 * @param array $headers Extra headers
	 *
	 * @throws FacturaSDKError if the API answers an error
	 *
	 * @return mixed Decoded body of the response
	 **/
	private function Request($method, $path, $data = NULL, $params = array(), $headers = array())
	{
		$url = $this->api_url.'/'.$path;

		if (!empty($params)) {
			$url .= '?'.http_build_query($params);
		}

		$headers = array_merge(array(
			'Content-Type' => 'application/json',
			'sdk-version-number' => $this->afip->sdk_version_number,
			'sdk-library' => 'php',
			'sdk-environment' => $this->environment
		), $headers);

		if ($this->access_token) {
			$headers['Authorization'] = 'Bearer '.$this->access_token;
		}

		$request = Requests::request($url, $headers, $data === NULL ? array() : json_encode($data), $method);

		if ($request->success) {
			return $request->body === '' ? NULL : json_decode($request->body);
		}

		throw FacturaSDKError::FromResponse($request);
	}
}
