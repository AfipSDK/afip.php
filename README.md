

<!-- PROJECT SHIELDS -->
[![Packagist][packagist-shield]](https://packagist.org/packages/afipsdk/afip.php)
[![Contributors][contributors-shield]](https://github.com/afipsdk/afip.php/graphs/contributors)
[![Closed issues][issues-shield]](https://github.com/afipsdk/afip.php/issues)
[![License][license-shield]](https://github.com/afipsdk/afip.php/blob/master/LICENSE)


<!-- PROJECT LOGO -->
<br />
<p align="center">
  <a href="https://github.com/afipsdk/afip.php">
    <img src="https://github.com/afipsdk/afipsdk.github.io/blob/master/images/logo-colored.png" alt="Logo" width="130" height="130">
  </a>

  <h3 align="center">Afip.php</h3>

  <p align="center">
    Librería para conectarse a los Web Services de AFIP
    <br />
    <a href="https://github.com/afipsdk/afip.php/wiki"><strong>Explorar documentación »</strong></a>
    <br />
    <br />
    <a href="https://github.com/afipsdk/afip.php/issues">Reportar un bug</a>
  </p>
</p>
<p align="center">
    <img src="https://github.com/afipsdk/afipsdk.github.io/blob/master/images/implementation.png" alt="Implementation">
</p>

<!-- TABLE OF CONTENTS -->
## Tabla de contenidos

* [Acerca del proyecto](#acerca-del-proyecto)
* [Guía de inicio](#guía-de-inicio)
  * [Instalación](#instalaci%C3%B3n)
  * [Como usarlo](#como-usarlo)
* [Web Services](#web-services)
  * [Factura electrónica](#factura-electr%C3%B3nica)
  * [Padrón alcance 4](#padr%C3%B3n-alcance-4)
  * [Padrón alcance 5](#padr%C3%B3n-alcance-5)
  * [Padrón alcance 10](#padr%C3%B3n-alcance-10)
  * [Padrón alcance 13](#padr%C3%B3n-alcance-13)
* [Migración](#migraci%C3%B3n)
* [Proyectos relacionados](#proyectos-relacionados)
* [Afip SDK PRO 🚀](#afip-sdk-pro-)
* [Licencia](#licencia)
* [Contacto](#contacto)



<!-- ABOUT THE PROJECT -->
## Acerca del proyecto

Esta librería fue creada con la intención de ayudar a los programadores a usar los Web Services de AFIP sin romperse la cabeza ni perder tiempo tratando de entender la complicada documentación que AFIP provee. Ademas forma parte de [Afip SDK](https://afipsdk.com/).


<!-- START GUIDE -->
## Guía de inicio

### Instalación

#### Via Composer

```
composer require afipsdk/afip.php
```

#### Via Manual
1. Clonarlo con `git clone` o descargar el repositorio desde [aqui](https://github.com/AfipSDK/afip.php/archive/v0.6.0.zip "Descargar repositorio").
2. Copiar el contenido de la carpeta *res* a tu aplicación.

**Importante** 
* Remplazar `Afip_res/cert` por tu certificado provisto por AFIP y `Afip_res/key` por la clave generada. 
* Procuren que la carpeta `Afip_res` no sea accesible desde internet ya que allí se guardara toda la informacion para acceder a los web services, **ademas esta carpeta deberá tener permisos de escritura**.

Ir a http://www.afip.gob.ar/ws/documentacion/certificados.asp para obtener mas información de como generar la clave y certificado.

Si no pueden seguir la complicada documentación de AFIP para obtener el certificado pueden obtener [Afip SDK PRO](#necesitas-ayuda-) donde se explica cómo obtener los certificados fácilmente.

### Como usarlo

Si lo instalaste manualmente lo primero es incluir el SDK en tu aplicación
````php
include 'ruta/a/la/libreria/src/Afip.php';
````

Luego creamos una instancia de la clase Afip pasandole un Array como parámetro.
````php
$afip = new Afip(array('CUIT' => 20111111112));
````


Para más información acerca de los parámetros que se le puede pasar a la instancia new `Afip()` consulte sección [Primeros pasos](https://github.com/afipsdk/afip.php/wiki/Primeros-pasos#como-usarlo) de la documentación

Una vez realizado esto podemos comenzar a usar el SDK con los Web Services disponibles


<!-- WEB SERVICES -->
## Web Services

Si necesitas más información de cómo utilizar algún web service echa un vistazo a la [documentación completa de afip.php](https://github.com/afipsdk/afip.php/wiki)

**Además si necesitas usar otro web service que aún no está disponible aquí podes utilizar esta librería como base para que se te haga más fácil, pronto haremos un tutorial explicando paso a paso como hacerlo, pero por el momento te recomendamos comenzar haciendo una copia y modificando el código de [consulta al padrón alcance 5](https://github.com/afipsdk/afip.php/blob/master/src/Class/RegisterScopeFive.php)**

### Factura electrónica
Podes encontrar la documentación necesaria para utilizar la [facturación electrónica](https://github.com/afipsdk/afip.php/wiki/Facturaci%C3%B3n-Electr%C3%B3nica) 👈 aquí

### Padrón alcance 4
El Servicio Web de Consulta de Padrón denominado A4 ha quedado limitado para Organismos Públicos, si lo necesitas puedes leer la documentación de [consulta al padrón de AFIP alcance 4](https://github.com/afipsdk/afip.php/wiki/Consulta-al-padron-de-AFIP-alcance-4)

### Padrón alcance 5
Quienes usaban el padrón A4 pueden utilizar este padrón en modo de remplazo, si queres saber cómo echa un vistazo a la documentación de [consulta al padrón de AFIP alcance 5](https://github.com/afipsdk/afip.php/wiki/Consulta-al-padron-de-AFIP-alcance-5)

### Padrón alcance 10
Si tenes que utilizar este web service también está disponible dentro de la librería, su documentación se encuentra en [consulta al padrón de AFIP alcance 10](https://github.com/afipsdk/afip.php/wiki/Consulta-al-padron-de-AFIP-alcance-10)

### Padrón alcance 13
Si debes consultar por el CUIT de una persona física tendrás que utilizar este web service, su documentación se encuentra disponible en la wiki de [consulta al padrón de AFIP alcance 13](https://github.com/AfipSDK/afip.php/wiki/Consulta-al-padron-de-AFIP-alcance-13)


<!-- MIGRATION -->
### Migración
¿Necesitas migrar de versión de la librería?

Pueden encontrar el tutorial correspondiente aquí 👇
- [Migrar de v0.1 a v0.5](https://github.com/afipsdk/afip.php/wiki/Migrar-de-v0.1-a-v0.5)


<!-- RELATED PROJECTS-->
### Proyectos relacionados

#### Libreria para Javascript
Si necesitas acceder los web services de AFIP en **Javascript** podes utilizar [Afip.js](https://github.com/afipsdk/afip.js)

#### Bundle para Symfony
Si necesitas utilizar los web services de Afip en _Symfony_ podes utilizar este [bundle](https://github.com/gonzakpo/afip)

<!-- AFIP SDK PRO -->
### Afip SDK PRO 🚀

¿Quieres implementarlo de forma rápida y fiable? Obtén Afip SDK PRO que incluye soporte y ayuda personalizada por 6 meses donde te ayudaremos integrar los web services de Afip con tu aplicación, y una amplia documentación con ejemplos, tutoriales, implementación en Frameworks y plataformas, y mucho más.


**[Saber más](https://afipsdk.com/pro.html)**


<!-- LICENCE -->
### Licencia
Distribuido bajo la licencia MIT. Vea `LICENSE` para más información.


<!-- CONTACT -->
### Contacto
Afip SDK - afipsdk@gmail.com

Link del proyecto: [https://github.com/afipsdk/afip.php](https://github.com/afipsdk/afip.php)


_Este software y sus desarrolladores no tienen ninguna relación con la AFIP._

<!-- MARKDOWN LINKS & IMAGES -->
[packagist-shield]: https://img.shields.io/packagist/dt/afipsdk/afip.php.svg??logo=php&?logoColor=white
[contributors-shield]: https://img.shields.io/github/contributors/afipsdk/afip.php.svg?color=orange
[issues-shield]: https://img.shields.io/github/issues-closed-raw/afipsdk/afip.php.svg?color=blueviolet
[license-shield]: https://img.shields.io/github/license/afipsdk/afip.php.svg?color=blue

