=== Lazy Load Block ===
Contributors: augusto
Tags: lazy load, iframe, performance, pagespeed, gutenberg, block
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bloque de Gutenberg que carga iframes y HTML solo cuando el usuario hace clic. Mejora PageSpeed y Core Web Vitals.

== Description ==

**Lazy Load Block** resuelve el problema de los iframes que generan peticiones HTTP innecesarias incluso cuando están ocultos en modales o popups.

= El Problema =
Cuando colocas un iframe en WordPress (incluso con `loading="lazy"`), el navegador puede hacer las peticiones de red aunque el contenido no sea visible. Herramientas como PageSpeed Insights, GTmetrix y Pingdom detectan estas cargas y penalizan tu puntuación.

= La Solución =
Este plugin almacena el contenido HTML codificado en un atributo `data-*` en lugar de renderizarlo directamente. El contenido **SOLO se inyecta en el DOM cuando el usuario hace clic**.

= Características =

* **Carga real diferida**: El HTML/iframe NO existe en el DOM hasta que se necesita
* **Mejora PageSpeed**: Las herramientas de medición no detectarán las peticiones del iframe
* **Fácil de usar**: Solo pega tu código HTML/iframe en el bloque
* **Personalizable**: Configura el texto del botón, placeholder, imagen de preview
* **Opción auto-load**: Carga automática cuando el bloque entra en el viewport
* **Accesible**: Soporte completo para teclado

= Cómo Usar =

1. En el editor de Gutenberg, añade el bloque "Lazy Load Block"
2. Pega tu código HTML o iframe en el área de texto
3. Personaliza el texto del botón y placeholder en el panel lateral
4. Publica - el contenido solo se cargará cuando el usuario haga clic

= Ideal Para =

* Embeds de YouTube, Vimeo, etc.
* Widgets de redes sociales
* Mapas de Google
* Cualquier iframe o embed pesado
* Contenido de terceros en modales/popups

== Installation ==

1. Sube la carpeta `lazy-load-block` al directorio `/wp-content/plugins/`
2. Activa el plugin desde el menú 'Plugins' en WordPress
3. En el editor de Gutenberg, busca "Lazy Load Block"

== Frequently Asked Questions ==

= ¿Por qué el atributo loading="lazy" no es suficiente? =

El atributo `loading="lazy"` solo difiere la carga cuando el iframe está fuera del viewport. Si el iframe está en el DOM (aunque sea en un modal oculto con `display:none`), el navegador aún puede iniciar las peticiones de red.

= ¿Funciona con cualquier HTML? =

Sí, puedes poner cualquier HTML incluyendo iframes, embeds, scripts y más. El contenido se ejecutará correctamente cuando se cargue.

= ¿Es seguro? =

El contenido se codifica en base64 para prevenir que sea parseado antes de tiempo. Al cargarse, se ejecuta igual que cualquier HTML normal de WordPress.

= ¿Puedo cargar el contenido programáticamente? =

Sí, el plugin expone una API JavaScript:

`
// Cargar un bloque específico por ID
LazyLoadBlock.load('llb-123');

// Cargar todos los bloques
LazyLoadBlock.loadAll();
`

== Changelog ==

= 1.0.0 =
* Versión inicial
* Bloque de Gutenberg completo
* Opción de carga por clic o Intersection Observer
* Soporte para placeholder personalizado
* API JavaScript pública

== Upgrade Notice ==

= 1.0.0 =
Primera versión del plugin.
