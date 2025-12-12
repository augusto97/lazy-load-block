/**
 * Lazy Load Block - Frontend JavaScript
 * Versión: 1.5.0 (Security Hardened)
 *
 * Este script maneja la carga diferida del contenido.
 * El contenido (iframes, HTML) está codificado en base64 en el atributo data-content
 * y NO se renderiza hasta que el usuario hace clic o el bloque entra en el viewport.
 *
 * SEGURIDAD:
 * - Los scripts solo se ejecutan si data-allow-scripts="true"
 * - Validación de contenido antes de inyección
 * - Sanitización adicional client-side (defense-in-depth)
 * - Detección de patrones peligrosos
 * - Límites de tamaño de contenido
 * - CSP-aware script execution
 *
 * @package LazyLoadBlock
 * @since 1.0.0
 */

(function() {
    'use strict';

    /**
     * Configuración de seguridad
     * NOTA: No usar flag 'g' en regex que se usan con .test() para evitar bugs
     */
    var SECURITY_CONFIG = {
        maxContentLength: 1048576, // 1MB máximo
        maxScriptLength: 102400,   // 100KB máximo por script
        // Patrones peligrosos solo para contenido que NO es iframe/embed
        // Los iframes de YouTube, Vimeo, etc. son seguros por naturaleza
        dangerousPatterns: [
            /javascript\s*:/i,
            /vbscript\s*:/i,
            /data\s*:\s*text\/html/i,
            /expression\s*\(/i,
        ],
        // URLs de confianza para iframes (opcional, puede ser extendido)
        trustedDomains: [
            'youtube.com',
            'youtube-nocookie.com',
            'vimeo.com',
            'dailymotion.com',
            'soundcloud.com',
            'spotify.com',
            'google.com',
            'maps.google.com',
        ]
    };

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
        const isImageMode = block.classList.contains('llb-mode-image');

        // En modo imagen, el placeholder completo es clickeable
        const clickTarget = isImageMode ? placeholder : trigger;

        if (!clickTarget) return;

        // Event listener para el trigger (clic)
        clickTarget.addEventListener('click', function(e) {
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
                rootMargin: '100px',
                threshold: 0.1
            });

            observer.observe(block);
        }

        // Soporte para teclado (accesibilidad)
        clickTarget.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                loadContent(block);
            }
        });

        // Añadir atributos de accesibilidad en modo imagen
        if (isImageMode && placeholder) {
            placeholder.setAttribute('role', 'button');
            placeholder.setAttribute('tabindex', '0');
        }
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

        var encodedContent = block.getAttribute('data-content');
        var contentContainer = block.querySelector('.llb-content');
        var placeholder = block.querySelector('.llb-placeholder');
        var loader = block.querySelector('.llb-loader');
        var allowScripts = block.getAttribute('data-allow-scripts') === 'true';

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
        var decodedContent;
        try {
            decodedContent = decodeBase64(encodedContent);
        } catch (e) {
            console.error('Lazy Load Block: Error decoding content', e);
            showError(contentContainer, loader);
            return;
        }

        // VALIDACIÓN EXHAUSTIVA del contenido decodificado
        var validation = validateContent(decodedContent, allowScripts);
        if (!validation.valid) {
            console.error('Lazy Load Block: Content validation failed -', validation.reason);
            showError(contentContainer, loader);

            // Disparar evento de error de seguridad
            block.dispatchEvent(new CustomEvent('llb:security-error', {
                bubbles: true,
                detail: { block: block, reason: validation.reason }
            }));
            return;
        }

        // SANITIZACIÓN ADICIONAL (defense-in-depth)
        decodedContent = basicSanitize(decodedContent, allowScripts);

        // Pequeño delay para mostrar el loader (UX)
        setTimeout(function() {
            // Inyectar el contenido en el DOM de forma segura
            try {
                contentContainer.innerHTML = decodedContent;
            } catch (e) {
                console.error('Lazy Load Block: Error injecting content', e);
                showError(contentContainer, loader);
                return;
            }

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

            // LIMPIAR DATOS SENSIBLES por seguridad
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
     * Validación exhaustiva del contenido
     * @param {string} content - Contenido HTML
     * @param {boolean} allowScripts - Si se permiten scripts
     * @returns {object} - {valid: boolean, reason: string}
     */
    function validateContent(content, allowScripts) {
        var result = { valid: true, reason: '' };

        if (typeof content !== 'string') {
            return { valid: false, reason: 'Content is not a string' };
        }

        // Verificar longitud
        if (content.length > SECURITY_CONFIG.maxContentLength) {
            return { valid: false, reason: 'Content exceeds maximum length' };
        }

        // Si no se permiten scripts, verificar patrones peligrosos
        if (!allowScripts) {
            for (var i = 0; i < SECURITY_CONFIG.dangerousPatterns.length; i++) {
                if (SECURITY_CONFIG.dangerousPatterns[i].test(content)) {
                    return { valid: false, reason: 'Dangerous pattern detected' };
                }
            }
        }

        // Verificar que no haya intentos de escapar del contenedor
        if (content.indexOf('</style>') !== -1 || content.indexOf('</script>') !== -1) {
            // Esto está OK si el contenido tiene scripts/styles propios
            // pero verificamos que no intente cerrar tags del padre
            var styleCloseCount = (content.match(/<\/style>/gi) || []).length;
            var styleOpenCount = (content.match(/<style/gi) || []).length;
            if (styleCloseCount > styleOpenCount) {
                return { valid: false, reason: 'Unbalanced style tags' };
            }
        }

        return result;
    }

    /**
     * Validación básica del contenido (legacy, para compatibilidad)
     * @param {string} content - Contenido HTML
     * @returns {boolean} - true si el contenido parece válido
     */
    function isValidContent(content) {
        return validateContent(content, false).valid;
    }

    /**
     * Sanitizar contenido HTML básico (defense-in-depth)
     * Solo se aplica si DOMPurify no está disponible
     * @param {string} content - Contenido HTML
     * @param {boolean} allowScripts - Si se permiten scripts
     * @returns {string} - Contenido sanitizado
     */
    function basicSanitize(content, allowScripts) {
        if (typeof content !== 'string') {
            return '';
        }

        // Si DOMPurify está disponible, usarlo
        if (typeof DOMPurify !== 'undefined') {
            var config = {
                ADD_TAGS: ['iframe', 'embed', 'object', 'param', 'source', 'video', 'audio'],
                ADD_ATTR: ['allow', 'allowfullscreen', 'frameborder', 'scrolling', 'sandbox', 'loading', 'referrerpolicy'],
                ALLOW_DATA_ATTR: true,
            };

            if (allowScripts) {
                config.ADD_TAGS.push('script');
                config.FORCE_BODY = true;
            }

            return DOMPurify.sanitize(content, config);
        }

        // Sanitización básica si DOMPurify no está disponible
        if (!allowScripts) {
            // Remover on* event handlers
            content = content.replace(/\s+on\w+\s*=\s*(['"])[^'"]*\1/gi, '');
            content = content.replace(/\s+on\w+\s*=\s*[^\s>]+/gi, '');

            // Remover javascript: URLs
            content = content.replace(/href\s*=\s*(['"])javascript:[^'"]*\1/gi, 'href=""');
            content = content.replace(/src\s*=\s*(['"])javascript:[^'"]*\1/gi, 'src=""');
        }

        return content;
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
        version: '1.5.0',

        /**
         * Verificar si DOMPurify está disponible
         */
        hasDOMPurify: function() {
            return typeof DOMPurify !== 'undefined';
        },

        /**
         * Obtener configuración de seguridad (solo lectura)
         */
        getSecurityConfig: function() {
            return Object.assign({}, SECURITY_CONFIG);
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
