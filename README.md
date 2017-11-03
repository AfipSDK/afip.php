# AFIP SDK
SDK Para los Web Services de AFIP (Argentina)

## Acerca de
El SDK fue programado con la intención de facilitar el acceso a los Web services de la AFIP.
**Esta primera versión del SDK solo cubre algunos items de la especificación del Web Service de Facturacion Electronica (wsfe1)** pero en el futuro se irán agregando los que restan, hasta tener el SDK completo.
Es un software libre en el que cualquier programador puede contribuir.

*Este software y sus desarroladores no tienen ninguna relación con la AFIP.* 

## Instalación
1. Clonarlo con `git clone` o descargar el repositorio desde [aqui](https://github.com/ivanalemunioz/afip-php/archive/master.zip "Dercargar repositorio").
2. Copiar el contenido de la carpeta *res* a tu aplicación.
3. Remplazar *Afip_res/cert* por tu certificado provisto por AFIP y *Afip_res/key* por la clave generada. 

Ir a http://www.afip.gob.ar/ws/paso4.asp para obtener mas información de como generar la clave y certificado

## Como usarlo
Lo primero es incluir el SDK en tu aplication
````php
include 'Afip.php';
````

Luego creamos una instancia de la clase Afip pasandole un Array como parámetro. 
Opciones disponibles: 
* **CUIT** *(int)* El CUIT a usar en los Web Services
* **production** *(bool)* (default FALSE) (Opcional) TRUE para usar los Web Services en modo produccion
````php
$afip = new Afip(array('CUIT' => 20111111112));
````
Una vez realizado esto podemos comenzar a usar el SDK

> Nota: Aquí hablaremos de comprobante indistintamente si es una factura, nota de crédito, etc 

### Métodos disponibles para Facturación Electrónica (wsfe)
1. [Obtener número del último comprobante creado *(FECompUltimoAutorizado)*](#obtener-numero-del-ultimo-comprobante-creado)
2. [Crear y asignar CAE a un comprobante *(FECAESolicitar)*](#crear-y-asignar-cae-a-un-comprobante)
3. [Crear y asignar CAE a siguiente comprobante *(FECompUltimoAutorizado + FECAESolicitar)*](#crear-y-asignar-cae-a-siguiente-comprobante)
4. [Obtener información de un comprobante *(FECompConsultar)*](#obtener-informacion-de-un-comprobante)

La especificación de este Web Service se encuentra disponible en http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf

#### Obtener número del último comprobante creado
Debemos utilizar el método `GetLastVoucher` con los parámetros punto de venta y tipo de comprobante que queremos consultar.
````php
$last_voucher = $afip->GetLastVoucher(1,6) //Devuelve el número del último comprobante creado para el punto de venta 1 y el tipo de comprobante 6 (Factura B)
````
#### Crear y asignar CAE a un comprobante 
Debemos utilizar el método `CreateVoucher` pasándole como parámetro un Array con los detalles del comprobante y si queremos tener la respuesta completa enviada por el WS debemos pasarle como segundo parámetro TRUE, en caso de no enviarle el segundo parámetro nos devolverá como respuesta `array(CAE => CAE asignado el comprobante, CAEFchVto => Fecha de vencimiento del CAE (yyyy-mm-dd))`.
````php
$data = array(
	'CantReg' 	=> 1, // Cantidad de comprobantes a registrar
	'PtoVta' 	=> 1, // Punto de venta
	'CbteTipo' 	=> 6, // Tipo de comprobante (ver tipos disponibles) 
	'Concepto' 	=> 1, // Concepto del Comprobante: (1)Productos, (2)Servicios, (3)Productos y Servicios
	'DocTipo' 	=> 80, // Tipo de documento del comprador (ver tipos disponibles)
	'DocNro' 	=> 20111111112, // Número de documento del comprador
	'CbteDesde' 	=> 1, // Número de comprobante o numero del primer comprobante en caso de ser mas de uno
	'CbteHasta' 	=> 1, // Número de comprobante o numero del último comprobante en caso de ser mas de uno
	'CbteFch' 	=> intval(date('Ymd')), // (Opcional) Fecha del comprobante (yyyymmdd) o fecha actual si es nulo
	'ImpTotal' 	=> 184.05, // Importe total del comprobante
	'ImpTotConc' 	=> 0, // Importe neto no gravado
	'ImpNeto' 	=> 150, // Importe neto gravado
	'ImpOpEx' 	=> 0, // Importe exento de IVA
	'ImpIVA' 	=> 26.25, //Importe total de IVA
	'ImpTrib' 	=> 7.8, //Importe total de tributos
	'FchServDesde' 	=> NULL, // (Opcional) Fecha de inicio del servicio (yyyymmdd), obligatorio para Concepto 2 y 3
	'FchServHasta' 	=> NULL, // (Opcional) Fecha de fin del servicio (yyyymmdd), obligatorio para Concepto 2 y 3
	'FchVtoPago' 	=> NULL, // (Opcional) Fecha de vencimiento del servicio (yyyymmdd), obligatorio para Concepto 2 y 3
	'MonId' 	=> 'PES', //Tipo de moneda usada en el comprobante (ver tipos disponibles)('PES' para pesos argentinos) 
	'MonCotiz' 	=> 1, // Cotización de la moneda usada (1 para pesos argentinos)  
	'CbtesAsoc' 	=> array( // (Opcional) Comprobantes asociados
		array(
			'Tipo' 		=> 6, // Tipo de comprobante (ver tipos disponibles) 
			'PtoVta' 	=> 1, // Punto de venta
			'Nro' 		=> 1, // Numero de comprobante
			'Cuit' 		=> 20111111112 // (Opcional) Cuit del emisor del comprobante
			)
		),
	'Tributos' 	=> array( // (Opcional) Tributos asociados al comprobante
		array(
			'Id' 		=>  99, // Id del tipo de tributo (ver tipos disponibles) 
			'Desc' 		=> 'Ingresos Brutos', // (Opcional) Descripción
			'BaseImp' 	=> 150, // Base imponible para el tributo
			'Alic' 		=> 5.2, // Alícuota
			'Importe' 	=> 7.8 // Importe del tributo
		)
	), 
	'Iva' 		=> array( // (Opcional) Alícuotas asociadas al comprobante
		array(
			'Id' 		=> 5, // Id del tipo de IVA (ver tipos disponibles) 
			'BaseImp' 	=> 100, // Base imponible
			'Importe' 	=> 21 // Importe 
		)
	), 
	'Opcionales' 	=> array( // (Opcional) Campos auxiliares
		array(
			'Id' 		=> 17, // Código de tipo de opción (ver tipos disponibles) 
			'Valor' 	=> 2 // Valor 
		)
	), 
	'Compradores' 	=> array( // (Opcional) Detalles de los clientes del comprobante 
		array(
			'DocTipo' 	=> 80, // Tipo de documento (ver tipos disponibles) 
			'DocNro' 	=> 20111111112, // Número de documento
			'Porcentaje' 	=> 100 // Porcentaje de titularidad del comprador
		)
	)
);

$res = $afip->CreateVoucher($data);

$res['CAE']; //CAE asignado el comprobante
$res['CAEFchVto']; //Fecha de vencimiento del CAE (yyyy-mm-dd)
````

Para mas información acerca de este método ver el item 4.1 de la [especificación del Web service](http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf)

#### Crear y asignar CAE a siguiente comprobante 

Debemos utilizar el método `CreateNextVoucher` pasándole como parámetro un Array con los detalles del comprobante al igual que el método `CreateVoucher`, nos devolverá como respuesta array(CAE => CAE asignado al comprobante, CAEFchVto => Fecha de vencimiento del CAE (yyyy-mm-dd), voucher_number => Número asignado al comprobante).
````php
$res = $afip->CreateNextVoucher($data);

$res['CAE']; //CAE asignado el comprobante
$res['CAEFchVto']; //Fecha de vencimiento del CAE (yyyy-mm-dd)
$res['voucher_number']; //Número asignado al comprobante
````
#### Obtener información de un comprobante
Con este método podemos obtener toda la información relacionada a un comprobante o simplemente saber si el comprobante existe, debemos ejecutar el método `GetVoucherInfo` pasándole como parámetros el número de comprobante, el punto de venta y el tipo de comprobante, nos devolverá un Array con toda la información del comprobante o NULL si el comprobante no existe.
````php
$voucher_info = $afip->GetVoucherInfo(1,1,6); //Devuelve la información del comprobante 1 para el punto de venta 1 y el tipo de comprobante 6 (Factura B)

if($voucher_info === NULL){
    echo 'El comprobante no existe';
}
else{
    echo 'Esta es la información del comprobante:';
    echo '<pre>';
    print_r($voucher_info);
    echo '</pre>';
}
````

Para mas información acerca de este método ver el item 4.19 de la [especificación del Web service](http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf)

### Métodos para obtener los tipos de datos disponibles en WSFE
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

Para mas información acerca de este método ver el item 4.4 de la [especificación del Web service](http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf)
#### Obtener tipos de conceptos disponibles
````php
$concept_types = $afip->GetConceptTypes();
````

Para mas información acerca de este método ver el item 4.5 de la [especificación del Web service](http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf)
#### Obtener tipos de documentos disponibles
````php
$document_types = $afip->GetDocumentTypes();
````

Para mas información acerca de este método ver el item 4.6 de la [especificación del Web service](http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf)
#### Obtener tipos de alícuotas disponibles
````php
$aloquot_types = $afip->GetAliquotTypes();
````

Para mas información acerca de este método ver el item 4.7 de la [especificación del Web service](http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf)
#### Obtener tipos de monedas disponibles 
````php
$currencies_types = $afip->GetCurrenciesTypes();
````

Para mas información acerca de este método ver el item 4.8 de la [especificación del Web service](http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf)
#### Obtener tipos de opciones disponibles para el comprobante
````php
$option_types = $afip->GetOptionsTypes();
````

Para mas información acerca de este método ver el item 4.9 de la [especificacion del Web service](http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf)
#### Obtener tipos de tributos disponibles
````php
$tax_types = $afip->GetTaxTypes();
````

Para mas información acerca de este método ver el item 4.10 de la [especificación del Web service](http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf)

### Otros métodos disponibles en el SDK
1. [Transformar formato de fecha que utiliza AFIP (yyyymmdd) a yyyy-mm-dd](#transformar-formato-de-fecha-que-utiliza-afip-yyyymmdd-a-yyyy-mm-dd)
2. [Enviar consulta al Web Service](#enviar-consulta-al-web-service)

#### Transformar formato de fecha que utiliza AFIP (yyyymmdd) a yyyy-mm-dd
Para esto utilizaremos el método `FormatDate` pasándole la fecha como parámetro
````php
$date = $afip->FormatDate('19970508'); //Nos devuelve 1997-05-08
````

#### Enviar consulta al Web Service
Podemos utilizar este método para enviar otras consultas al Web Service, para esto utilizaremos el método `ExecuteRequest` pasándole como primer parámetro el Web Service a ejecutar, segundo parámetro la operación a realizar y como tercer parámetro le pasaremos los parámetros que serán enviados el Web Service (excepto el parámetro 'Auth' que es agregado automáticamente)
````php
$response = $afip->ExecuteRequest('wsfe', 'FEParamGetCotizacion', array('MonId' => 'DOL')); //Ejecuta la operación FEParamGetCotizacion del wsfe 

echo 'La cotización de la moneda es:';
echo '<pre>';
print_r($response->ResultGet);
echo '</pre>';
````


## Soporte y contacto 

ivanalemunioz@gmail.com
