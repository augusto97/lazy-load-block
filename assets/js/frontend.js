/**
 * Lazy Load Block - Frontend JavaScript
 * Versión: 1.1.0 (con mejoras de seguridad)
 *
 * Este script maneja la carga diferida del contenido.
 * El contenido (iframes, HTML) está codificado en base64 en el atributo data-content
 * y NO se renderiza hasta que el usuario hace clic o el bloque entra en el viewport.
 *
 * SEGURIDAD: Los scripts solo se ejecutan si data-allow-scripts="true"
 * (configurado por el servidor según permisos del usuario)
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
        const allowScripts = block.getAttribute('data-allow-scripts') === 'true';

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
            showError(contentContainer, loader);
            return;
        }

        // Validación básica del contenido decodificado
        if (!isValidContent(decodedContent)) {
            console.error('Lazy Load Block: Invalid content detected');
            showError(contentContainer, loader);
            return;
        }

        // Pequeño delay para mostrar el loader (UX)
        setTimeout(function() {
            // Inyectar el contenido en el DOM
            contentContainer.innerHTML = decodedContent;

            // Ejecutar scripts SOLO si está permitido por el servidor
            if (allowScripts) {
                executeScripts(contentContainer);
            } else {
                // Eliminar scripts si no están permitidos (seguridad adicional)
                removeScripts(contentContainer);
            }

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
     * Mostrar mensaje de error
     * @param {HTMLElement} container - Contenedor de contenido
     * @param {HTMLElement} loader - Elemento loader
     */
    function showError(container, loader) {
        container.innerHTML = '<p style="color: #d63638; padding: 20px; text-align: center;">Error al cargar el contenido.</p>';
        container.style.display = 'block';
        if (loader) {
            loader.style.display = 'none';
        }
    }

    /**
     * Validación básica del contenido
     * @param {string} content - Contenido HTML
     * @returns {boolean} - true si el contenido parece válido
     */
    function isValidContent(content) {
        if (typeof content !== 'string') {
            return false;
        }

        // Verificar longitud razonable (máximo 1MB)
        if (content.length > 1048576) {
            return false;
        }

        return true;
    }

    /**
     * Eliminar scripts del contenedor (cuando no están permitidos)
     * @param {HTMLElement} container - Contenedor con posibles scripts
     */
    function removeScripts(container) {
        const scripts = container.querySelectorAll('script');
        scripts.forEach(function(script) {
            script.parentNode.removeChild(script);
        });
    }

    /**
     * Decodificar contenido desde base64
     * Maneja correctamente caracteres UTF-8
     * @param {string} encoded - Contenido codificado en base64
     * @returns {string} - Contenido decodificado
     */
    function decodeBase64(encoded) {
        // Validar que sea base64 válido
        if (!/^[A-Za-z0-9+/]*={0,2}$/.test(encoded)) {
            throw new Error('Invalid base64 string');
        }

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
     * NOTA: Solo se llama si data-allow-scripts="true" (verificado por el servidor)
     * @param {HTMLElement} container - Contenedor con los scripts
     */
    function executeScripts(container) {
        const scripts = container.querySelectorAll('script');

        scripts.forEach(function(oldScript) {
            const newScript = document.createElement('script');

            // Copiar atributos seguros
            var safeAttributes = ['src', 'async', 'defer', 'type', 'charset', 'id'];
            Array.from(oldScript.attributes).forEach(function(attr) {
                if (safeAttributes.indexOf(attr.name) !== -1) {
                    newScript.setAttribute(attr.name, attr.value);
                }
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
            // Sanitizar el ID para prevenir inyección
            if (typeof blockId !== 'string' || !/^llb-[\w-]+$/.test(blockId)) {
                console.error('Lazy Load Block: Invalid block ID');
                return;
            }

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
        },

        /**
         * Obtener versión del script
         */
        version: '1.1.0'
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
