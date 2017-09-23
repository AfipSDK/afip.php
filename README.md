# AFIP SDK
SDK Para los Web Services de AFIP (Argentina)

## Acerca de
El SDK fue programado con la intencion de facilitar el acceso a los Web services de la AFIP.
**Esta primera version del SDK solo cubre algunos items de la especificacion del Web Service de Facturacion Electronica (wsfe1)** pero en el futuro se iran agregando los que restan, hasta tener el SDK completo.
Es un software libre en el que cualquier programador puede contribuir.

*Este software y sus desarroladores no tienen ninguna relacion con la AFIP.* 

## Instalacion
1. Clonarlo con `git clone` o descargar el repositorio desde [aqui](https://github.com/ivanalemunioz/afip-php/archive/master.zip "Dercargar repositorio").
2. Copiar el contenido de la carpeta *res* a tu aplicacion.
3. Remplazar *Afip_res/cert* por tu certificado provisto por AFIP y *Afip_res/key* por la clave generada. 

Ir a http://www.afip.gob.ar/ws/paso4.asp para obtener mas informacion de como generar la clave y certificado

## Como usarlo
Lo primero es incluir el SDK en tu aplication
````php
include 'Afip.php';
````

Luego creamos una instancia de la clase Afip pasandole un Array como parametro. 
Opciones disponibles: 
* **CUIT** *(int)* El CUIT a usar en los Web Services
* **production** *(bool)* (default FALSE) (Opcional) TRUE para usar los Web Services en modo produccion
````php
$afip = new Afip(array('CUIT' => 20111111112));
````
Una vez realizado esto podemos comenzar a usar el SDK

> Nota: Aqui hablaremos de comprobante indistintamente si es una factura, nota de credito, etc 

### Metodos disponibles para Facturacion Electronica (wsfe)
1. [Obtener numero del ultimo comprobante creado *(FECompUltimoAutorizado)*](#obtener-numero-del-ultimo-comprobante-creado)
2. [Crear y asignar CAE a un comprobante *(FECAESolicitar)*](#crear-y-asignar-cae-a-un-comprobante)
3. [Crear y asignar CAE a siguiente comprobante *(FECompUltimoAutorizado + FECAESolicitar)*](#crear-y-asignar-cae-a-siguiente-comprobante)
4. [Obtener informacion de un comprobante *(FECompConsultar)*](#obtener-informacion-de-un-comprobante)

La especificacion de este Web Service se encuentra disponible en http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf

#### Obtener numero del ultimo comprobante creado
Debemos utilizar el metodo `GetLastVoucher` con los parametros punto de venta y tipo de comprobante que queremos consultar.
````php
$last_voucher = $afip->GetLastVoucher(1,6) //Devuelve el numero del ultimo comprobante creado para el punto de venta 1 y el tipo de comprobante 6 (Factura B)
````
#### Crear y asignar CAE a un comprobante 
Debemos utilizar el metodo `CreateVoucher` pasandole como parametro un Array con los detalles del comprobante y si queremos tener la respuesta completa enviada por el WS debemos pasarle como segundo parametro TRUE, en caso de no enviarle el segundo parametro nos devolvera como respuesta `array(CAE => CAE asignado el comprobante, CAEFchVto => Fecha de vencimiento del CAE (yyyy-mm-dd))`.
````php
$data = array(
	'CantReg' 		=> 1, // Cantidad de items del/los comprobante/s
	'PtoVta' 		=> 1, // Punto de venta
	'CbteTipo' 		=> 6, // Tipo de comprobante (ver tipos disponibles) 
	'Concepto' 		=> 1, // Concepto del Comprobante: (1)Productos, (2)Servicios, (3)Productos y Servicios
	'DocTipo' 		=> 80, // Tipo de documento del comprador (ver tipos disponibles)
	'DocNro' 		=> 20111111112, // Numero de documento del comprador
	'CbteDesde' 	=> 1, // Numero de comprobante o numero del primer comprobante en caso de ser mas de uno
	'CbteHasta' 	=> 1, // Numero de comprobante o numero del ultimo comprobante en caso de ser mas de uno
	'CbteFch' 		=> intval(date('Ymd')), // (Opcional) Fecha del comprobante (yyyymmdd) o fecha actual si es nulo
	'ImpTotal' 		=> 184.05, // Importe total del comprobante
	'ImpTotConc' 	=> 0, // Importe neto no gravado
	'ImpNeto' 		=> 150, // Importe neto gravado
	'ImpOpEx' 		=> 0, // Importe exento de IVA
	'ImpIVA' 		=> 26.25, //Importe total de IVA
	'ImpTrib' 		=> 7.8, //Importe total de tributos
	'FchServDesde' 	=> NULL, // (Opcional) Fecha de inicio del servicio (yyyymmdd), obligatorio para Concepto 2 y 3
	'FchServHasta' 	=> NULL, // (Opcional) Fecha de fin del servicio (yyyymmdd), obligatorio para Concepto 2 y 3
	'FchVtoPago' 	=> NULL, // (Opcional) Fecha de vencimiento del servicio (yyyymmdd), obligatorio para Concepto 2 y 3
	'MonId' 		=> 'PES', //Tipo de moneda usada en el comprobante (ver tipos disponibles)('PES' para pesos argentinos) 
	'MonCotiz' 		=> 1, // Cotización de la moneda usada (1 para pesos argentinos)  
	'CbtesAsoc' 	=> array( // (Opcional) Comprobantes asociados
		array(
			'Tipo' 		=> 6, // Tipo de comprobante (ver tipos disponibles) 
			'PtoVta' 	=> 1, // Punto de venta
			'Nro' 		=> 1, // Numero de comprobante
			'Cuit' 		=> 20111111112 // (Opcional) Cuit del emisor del comprobante
			)
		),
	'Tributos' 		=> array( // (Opcional) Tributos asociados al comprobante
		array(
			'Id' 		=>  99, // Id del tipo de tributo (ver tipos disponibles) 
			'Desc' 		=> 'Ingresos Brutos', // (Opcional) Descripcion
			'BaseImp' 	=> 150, // Base imponible para el tributo
			'Alic' 		=> 5.2, // Alícuota
			'Importe' 	=> 7.8 // Importe del tributo
		)
	), 
	'Iva' 			=> array( // (Opcional) Alícuotas asociadas al comprobante
		array(
			'Id' 		=> 5, // Id del tipo de IVA (ver tipos disponibles) 
			'BaseImp' 	=> 100, // Base imponible
			'Importe' 	=> 21 // Importe 
		)
	), 
	'Opcionales' 	=> array( // (Opcional) Campos auxiliares
		array(
			'Id' 		=> 17, // Codigo de tipo de opcion (ver tipos disponibles) 
			'Valor' 	=> 2 // Valor 
		)
	), 
	'Compradores' 	=> array( // (Opcional) Detalles de los clientes del comprobante 
		array(
			'DocTipo' 		=> 80, // Tipo de documento (ver tipos disponibles) 
			'DocNro' 		=> 20111111112, // Numero de documento
			'Porcentaje' 	=> 100 // Porcentaje de titularidad del comprador
		)
	)
);

$res = $afip->CreateVoucher($data);

$res['CAE']; //CAE asignado el comprobante
$res['CAEFchVto']; //Fecha de vencimiento del CAE (yyyy-mm-dd)
````

Para mas informacion acerca de este metodo ver el item 4.1 de la [especificacion del Web service](http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf)

#### Crear y asignar CAE a siguiente comprobante 

Debemos utilizar el metodo `CreateNextVoucher` pasandole como parametro un Array con los detalles del comprobante al igual que el metodo `CreateVoucher`, nos devolvera como respuesta array(CAE => CAE asignado al comprobante, CAEFchVto => Fecha de vencimiento del CAE (yyyy-mm-dd), voucher_number => Numero asignado al comprobante).
````php
$res = $afip->CreateNextVoucher($data);

$res['CAE']; //CAE asignado el comprobante
$res['CAEFchVto']; //Fecha de vencimiento del CAE (yyyy-mm-dd)
$res['voucher_number']; //Numero asignado al comprobante
````
#### Obtener informacion de un comprobante
Con este metodo podemos obtener toda la informacion relacionada a un comprobante o simplemente saber si el comprobante existe, debemos ejecutar el metodo `GetVoucherInfo` pasandole como parametros el numero de comprobante, el punto de venta y el tipo de comprobante, nos devolvera un Array con toda la informacion del comprobante o NULL si el comprobante no existe.
````php
$voucher_info = $afip->GetVoucherInfo(1,1,6) //Devuelve la informacion del comprobante 1 para el punto de venta 1 y el tipo de comprobante 6 (Factura B)

if($voucher_info === NULL){
    echo 'El comprobante no existe';
}
else{
    echo 'Esta es la informacion del comprobante:';
    echo '<pre>';
    print_r($voucher_info);
    echo '</pre>';
}
````

Para mas informacion acerca de este metodo ver el item 4.19 de la [especificacion del Web service](http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf)

### Metodos para obtener los tipos de datos disponibles en WSFE
1. [Obtener tipos de comprobantes disponibles *(FEParamGetTiposCbte)*](#obtener-tipos-de-comprobantes-disponibles)
2. [Obtener tipos de conceptos disponibles *(FEParamGetTiposConcepto)*](#obtener-tipos-de-conceptos-disponibles)
3. [Obtener tipos de documentos disponibles *(FEParamGetTiposDoc)*](#obtener-tipos-de-documentos-disponibles)
4. [Obtener tipos de alícuotas disponibles *(FEParamGetTiposIva)*](#obtener-tipos-de-al%C3%ADcuotas-disponibles)
5. [Obtener tipos de monedas disponibles  *(FEParamGetTiposMonedas)*](#obtener-tipos-de-monedas-disponibles)
6. [Obtener tipos de opciones disponibles para el comprobante *(FEParamGetTiposOpcional)*](#obtener-tipos-de-opciones-disponibles-para-el-comprobante)
7. [Obtener tipos de tributos disponibles *(FEParamGetTiposTributos)*](#obtener-tipos-de-tributos-disponibles)

#### Obtener tipos de comprobantes disponibles

````php
$voucher_types = $afip->GetVoucherTypes();
````
#### Obtener tipos de conceptos disponibles
````php
$concept_types = $afip->GetConceptTypes();
````
#### Obtener tipos de documentos disponibles
````php
$document_types = $afip->GetDocumentTypes();
````
#### Obtener tipos de alícuotas disponibles
````php
$aloquot_types = $afip->GetAliquotTypes();
````
#### Obtener tipos de monedas disponibles 
````php
$currencies_types = $afip->GetCurrenciesTypes();
````
#### Obtener tipos de opciones disponibles para el comprobante
````php
$option_types = $afip->GetOptionsTypes();
````
#### Obtener tipos de tributos disponibles
````php
$tax_types = $afip->GetTaxTypes();
````

### Otros metodos disponibles en el SDK
1. [Transformar formato de fecha que utiliza AFIP (yyyymmdd) a yyyy-mm-dd](#transformar-formato-de-fecha-que-utiliza-afip-yyyymmdd-a-yyyy-mm-dd)
2. [Enviar consulta al Web Service](#enviar-consulta-al-web-service)

#### Transformar formato de fecha que utiliza AFIP (yyyymmdd) a yyyy-mm-dd
Para esto utilizaremos el metodo `FormatDate` pasandole la fecha como parmetro
````php
$date = $afip->FormatDate(19970508); //Nos devuelve 1997-05-08
````

#### Enviar consulta al Web Service
Podemos utilizar este metodo para enviar otras consultas al Web Service, para esto utilizaremos el metodo `ExecuteRequest` pasandole como primer parametro el Web Service a ejecutar, segundo parametro la operacion a realizar y como tercer parametro le pasaremos los parametros que seran enviados el Web Service (excepto el parametro 'Auth' que es agregado automaticamente)
````php
$response = $afip->ExecuteRequest('wsfe', 'FEParamGetCotizacion', array('MonId' => 'DOL')); //Ejecuta la operacion FEParamGetCotizacion del wsfe 

echo 'La cotizacion de la moneda es:';
echo '<pre>';
print_r($response->ResultGet);
echo '</pre>';
````

