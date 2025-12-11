/**
 * Lazy Load Block - Frontend JavaScript
 *
 * Este script maneja la carga diferida del contenido.
 * El contenido (iframes, HTML) está codificado en base64 en el atributo data-content
 * y NO se renderiza hasta que el usuario hace clic o el bloque entra en el viewport.
 */

(function() {
    'use strict';

    /**
     * Inicializar todos los bloques de Lazy Load
     */
    function initLazyLoadBlocks() {
        const blocks = document.querySelectorAll('.wp-block-lazy-load-block[data-loaded="false"]');

        blocks.forEach(function(block) {
            initBlock(block);
        });
    }

    /**
     * Inicializar un bloque individual
     * @param {HTMLElement} block - El elemento del bloque
     */
    function initBlock(block) {
        const trigger = block.querySelector('.llb-trigger');
        const placeholder = block.querySelector('.llb-placeholder');
        const isAutoLoad = block.classList.contains('llb-auto-load');

        if (!trigger) return;

        // Event listener para el trigger (clic)
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            loadContent(block);
        });

        // Si es auto-load, usar Intersection Observer
        if (isAutoLoad && 'IntersectionObserver' in window) {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        loadContent(block);
                        observer.unobserve(block);
                    }
                });
            }, {
                rootMargin: '100px', // Cargar 100px antes de que sea visible
                threshold: 0.1
            });

            observer.observe(block);
        }

        // Soporte para teclado (accesibilidad)
        trigger.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                loadContent(block);
            }
        });
    }

    /**
     * Cargar el contenido de un bloque
     * @param {HTMLElement} block - El elemento del bloque
     */
    function loadContent(block) {
        // Evitar cargar múltiples veces
        if (block.getAttribute('data-loaded') === 'true') {
            return;
        }

        const encodedContent = block.getAttribute('data-content');
        const contentContainer = block.querySelector('.llb-content');
        const placeholder = block.querySelector('.llb-placeholder');
        const loader = block.querySelector('.llb-loader');

        if (!encodedContent || !contentContainer) {
            console.error('Lazy Load Block: Missing content or container');
            return;
        }

        // Mostrar loader
        if (placeholder) {
            placeholder.style.display = 'none';
        }
        if (loader) {
            loader.style.display = 'flex';
        }

        // Decodificar el contenido desde base64
        let decodedContent;
        try {
            decodedContent = decodeBase64(encodedContent);
        } catch (e) {
            console.error('Lazy Load Block: Error decoding content', e);
            // Mostrar mensaje de error
            contentContainer.innerHTML = '<p style="color: #d63638;">Error al cargar el contenido.</p>';
            contentContainer.style.display = 'block';
            if (loader) loader.style.display = 'none';
            return;
        }

        // Pequeño delay para mostrar el loader (UX)
        setTimeout(function() {
            // Inyectar el contenido en el DOM
            contentContainer.innerHTML = decodedContent;

            // Ejecutar scripts si los hay
            executeScripts(contentContainer);

            // Ocultar loader y mostrar contenido
            if (loader) {
                loader.style.display = 'none';
            }
            contentContainer.style.display = 'block';

            // Marcar como cargado
            block.setAttribute('data-loaded', 'true');

            // Limpiar el data-content por seguridad (ya no se necesita)
            block.removeAttribute('data-content');

            // Disparar evento personalizado
            block.dispatchEvent(new CustomEvent('llb:loaded', {
                bubbles: true,
                detail: { block: block }
            }));

        }, 150); // 150ms de delay para que el loader sea visible
    }

    /**
     * Decodificar contenido desde base64
     * Maneja correctamente caracteres UTF-8
     * @param {string} encoded - Contenido codificado en base64
     * @returns {string} - Contenido decodificado
     */
    function decodeBase64(encoded) {
        // Decodificar base64 a bytes
        const binaryString = atob(encoded);

        // Convertir bytes a array
        const bytes = new Uint8Array(binaryString.length);
        for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }

        // Decodificar UTF-8
        const decoder = new TextDecoder('utf-8');
        return decoder.decode(bytes);
    }

    /**
     * Ejecutar scripts que fueron inyectados dinámicamente
     * Los scripts insertados via innerHTML no se ejecutan automáticamente
     * @param {HTMLElement} container - Contenedor con los scripts
     */
    function executeScripts(container) {
        const scripts = container.querySelectorAll('script');

        scripts.forEach(function(oldScript) {
            const newScript = document.createElement('script');

            // Copiar atributos
            Array.from(oldScript.attributes).forEach(function(attr) {
                newScript.setAttribute(attr.name, attr.value);
            });

            // Copiar contenido inline si existe
            if (oldScript.textContent) {
                newScript.textContent = oldScript.textContent;
            }

            // Reemplazar el script viejo con el nuevo (esto lo ejecutará)
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    }

    /**
     * API pública para cargar bloques programáticamente
     */
    window.LazyLoadBlock = {
        /**
         * Cargar un bloque específico por ID
         * @param {string} blockId - ID del bloque
         */
        load: function(blockId) {
            const block = document.getElementById(blockId);
            if (block && block.classList.contains('wp-block-lazy-load-block')) {
                loadContent(block);
            }
        },

        /**
         * Cargar todos los bloques que aún no se han cargado
         */
        loadAll: function() {
            const blocks = document.querySelectorAll('.wp-block-lazy-load-block[data-loaded="false"]');
            blocks.forEach(function(block) {
                loadContent(block);
            });
        },

        /**
         * Reinicializar (útil después de cargar contenido via AJAX)
         */
        init: function() {
            initLazyLoadBlocks();
        }
    };

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLazyLoadBlocks);
    } else {
        // DOM ya está listo
        initLazyLoadBlocks();
    }

    // También inicializar después de cargas AJAX (para compatibilidad)
    document.addEventListener('llb:reinit', initLazyLoadBlocks);

})();
