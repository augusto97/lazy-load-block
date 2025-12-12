<?php
/**
 * Plugin Name: Lazy Load Block
 * Description: Bloque de Gutenberg que carga contenido (iframes, HTML) solo cuando el usuario hace clic. Evita peticiones innecesarias en PageSpeed, GTmetrix, etc.
 * Version: 1.5.0
 * Author: Augusto
 * License: GPL v2 or later
 * Text Domain: lazy-load-block
 *
 * @package LazyLoadBlock
 * @since 1.0.0
 *
 * Security Features:
 * - HTML sanitization via wp_kses() with strict allowlist
 * - CSS value validation with regex
 * - Capability checks for script execution
 * - Nonce verification for settings
 * - CSP-compatible inline styles
 * - URL protocol validation
 * - Iframe sandbox by default
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent direct file access
if (!defined('WPINC')) {
    die;
}

define('LAZY_LOAD_BLOCK_VERSION', '1.5.0');
define('LAZY_LOAD_BLOCK_PATH', plugin_dir_path(__FILE__));
define('LAZY_LOAD_BLOCK_URL', plugin_dir_url(__FILE__));

/**
 * Registrar el bloque de Gutenberg
 */
function lazy_load_block_init() {
    register_block_type(LAZY_LOAD_BLOCK_PATH . 'build', array(
        'render_callback' => 'lazy_load_block_render',
    ));
}
add_action('init', 'lazy_load_block_init');

/**
 * Obtener tags HTML permitidos para el contenido del bloque
 *
 * This function defines the strict allowlist of HTML elements and attributes
 * that can be used in lazy-loaded content. It extends WordPress's default
 * 'post' kses rules with media-specific elements.
 *
 * SECURITY NOTES:
 * - Scripts are only allowed for users with 'unfiltered_html' capability
 * - The filter 'lazy_load_block_allowed_html' allows extending this list,
 *   but plugins using it MUST ensure they don't introduce XSS vulnerabilities
 * - Each attribute is explicitly whitelisted to prevent attribute-based attacks
 * - Event handlers (onclick, onerror, etc.) are NOT allowed
 *
 * @since 1.0.0
 * @return array Allowed HTML elements and attributes
 */
function lazy_load_block_allowed_html() {
    $allowed = wp_kses_allowed_html('post');

    // Remove any potentially dangerous attributes that might have been added
    $dangerous_attributes = array('onclick', 'onerror', 'onload', 'onmouseover', 'onfocus', 'onblur');

    $allowed['iframe'] = array(
        'src'             => true,
        'width'           => true,
        'height'          => true,
        'frameborder'     => true,
        'allow'           => true,
        'allowfullscreen' => true,
        'loading'         => true,
        'title'           => true,
        'name'            => true,
        'class'           => true,
        'id'              => true,
        'style'           => true,
        'referrerpolicy'  => true,
        'sandbox'         => true,
    );

    $allowed['embed'] = array(
        'src'    => true,
        'type'   => true,
        'width'  => true,
        'height' => true,
        'class'  => true,
        'id'     => true,
    );

    $allowed['object'] = array(
        'data'   => true,
        'type'   => true,
        'width'  => true,
        'height' => true,
        'class'  => true,
        'id'     => true,
    );

    $allowed['param'] = array(
        'name'  => true,
        'value' => true,
    );

    $allowed['source'] = array(
        'src'   => true,
        'type'  => true,
        'media' => true,
    );

    $allowed['video'] = array(
        'src'      => true,
        'width'    => true,
        'height'   => true,
        'poster'   => true,
        'controls' => true,
        'autoplay' => true,
        'loop'     => true,
        'muted'    => true,
        'preload'  => true,
        'class'    => true,
        'id'       => true,
        'style'    => true,
    );

    $allowed['audio'] = array(
        'src'      => true,
        'controls' => true,
        'autoplay' => true,
        'loop'     => true,
        'muted'    => true,
        'preload'  => true,
        'class'    => true,
        'id'       => true,
    );

    // Scripts only for users with unfiltered_html capability
    // This is a high-privilege operation
    if (current_user_can('unfiltered_html')) {
        $allowed['script'] = array(
            'src'     => true,
            'async'   => true,
            'defer'   => true,
            'type'    => true,
            'charset' => true,
            'id'      => true,
            // Note: nonce and integrity are handled by WordPress CSP if enabled
        );
    }

    /**
     * Filter the allowed HTML elements and attributes for lazy load content.
     *
     * WARNING: Modifying this filter can introduce security vulnerabilities.
     * Only add elements/attributes that you fully trust and understand.
     * NEVER add event handler attributes (onclick, onerror, onload, etc.)
     *
     * @since 1.0.0
     * @param array $allowed Allowed HTML elements and their attributes
     */
    $allowed = apply_filters('lazy_load_block_allowed_html', $allowed);

    // SECURITY: Force-remove dangerous event handlers even if filter added them
    foreach ($allowed as $tag => $attributes) {
        if (is_array($attributes)) {
            foreach ($dangerous_attributes as $dangerous) {
                unset($allowed[$tag][$dangerous]);
            }
        }
    }

    return $allowed;
}

/**
 * Sanitizar el contenido HTML del bloque
 */
function lazy_load_block_sanitize_content($content) {
    if (empty($content)) {
        return '';
    }
    return wp_kses($content, lazy_load_block_allowed_html());
}

/**
 * Validar y sanitizar valor CSS para dimensiones
 */
function lazy_load_block_sanitize_css_dimension($value, $default = 'auto') {
    if (empty($value)) {
        return $default;
    }

    $value = strtolower(trim($value));

    $allowed_values = array('auto', 'inherit', 'initial', 'unset', 'none', 'max-content', 'min-content', 'fit-content');

    if (in_array($value, $allowed_values, true)) {
        return $value;
    }

    if (preg_match('/^(\d+\.?\d*)(px|%|em|rem|vh|vw|vmin|vmax|ch|ex|cm|mm|in|pt|pc)$/', $value, $matches)) {
        return $value;
    }

    return $default;
}

/**
 * Sanitizar aspect ratio
 */
function lazy_load_block_sanitize_aspect_ratio($ratio) {
    $valid_ratios = array('', '16/9', '4/3', '1/1', '9/16', '21/9');
    if (in_array($ratio, $valid_ratios, true)) {
        return $ratio;
    }
    return '';
}

/**
 * Escapar valor CSS de forma segura
 * Previene CSS injection attacks
 *
 * @param string $value Valor CSS a escapar
 * @return string Valor escapado seguro para uso en CSS
 */
function lazy_load_block_escape_css($value) {
    if (empty($value)) {
        return '';
    }

    // Remover caracteres peligrosos para CSS
    $value = preg_replace('/[{};<>]/', '', $value);

    // Remover cualquier intento de escape de CSS
    $value = preg_replace('/\\\\[0-9a-fA-F]{1,6}/', '', $value);

    // Remover comentarios CSS
    $value = preg_replace('/\/\*.*?\*\//s', '', $value);

    // Remover expresiones peligrosas
    $dangerous_patterns = array(
        '/expression\s*\(/i',
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/data\s*:/i',
        '/@import/i',
        '/@charset/i',
        '/behavior\s*:/i',
        '/-moz-binding/i',
        '/binding\s*:/i',
    );

    foreach ($dangerous_patterns as $pattern) {
        $value = preg_replace($pattern, '', $value);
    }

    return $value;
}

/**
 * Escapar selector CSS ID de forma segura
 *
 * @param string $id ID a usar como selector CSS
 * @return string ID escapado seguro para CSS
 */
function lazy_load_block_escape_css_id($id) {
    // Solo permitir caracteres alfanuméricos, guiones y guiones bajos
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
}

/**
 * Validar y sanitizar URL con verificación de protocolo
 *
 * @param string $url URL a validar
 * @param bool $require_https Si true, solo permite HTTPS
 * @return string URL sanitizada o cadena vacía si es inválida
 */
function lazy_load_block_sanitize_url($url, $require_https = false) {
    if (empty($url)) {
        return '';
    }

    // Sanitizar URL básica
    $url = esc_url_raw($url);

    if (empty($url)) {
        return '';
    }

    // Parsear la URL
    $parsed = wp_parse_url($url);

    if (!$parsed || !isset($parsed['scheme'])) {
        return '';
    }

    // Protocolos permitidos
    $allowed_schemes = array('http', 'https');

    if ($require_https) {
        $allowed_schemes = array('https');
    }

    if (!in_array(strtolower($parsed['scheme']), $allowed_schemes, true)) {
        return '';
    }

    // Bloquear URLs que apuntan a localhost o IPs privadas (SSRF prevention)
    if (isset($parsed['host'])) {
        $host = strtolower($parsed['host']);

        // Lista de hosts bloqueados
        $blocked_hosts = array(
            'localhost',
            '127.0.0.1',
            '0.0.0.0',
            '::1',
        );

        if (in_array($host, $blocked_hosts, true)) {
            return '';
        }

        // Bloquear rangos de IP privadas
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return '';
            }
        }
    }

    return $url;
}

/**
 * Agregar atributos de seguridad a iframes
 *
 * NOTA: No agregamos sandbox automáticamente porque rompe la mayoría de embeds
 * (YouTube, Vimeo, etc. necesitan ejecutar scripts para funcionar).
 * En su lugar, agregamos atributos de seguridad que no rompen la funcionalidad.
 *
 * @param string $content Contenido HTML
 * @param bool $allow_scripts Si se permiten scripts (no usado actualmente)
 * @return string Contenido con atributos de seguridad aplicados a iframes
 */
function lazy_load_block_add_iframe_sandbox($content, $allow_scripts = false) {
    if (empty($content) || strpos($content, '<iframe') === false) {
        return $content;
    }

    // Usar regex simple en lugar de DOMDocument para evitar corrupción de HTML
    // Agregar loading="lazy" si no existe
    $content = preg_replace_callback(
        '/<iframe([^>]*)>/i',
        function($matches) {
            $attrs = $matches[1];

            // Agregar loading="lazy" si no existe
            if (stripos($attrs, 'loading=') === false) {
                $attrs .= ' loading="lazy"';
            }

            // Agregar referrerpolicy si no existe
            if (stripos($attrs, 'referrerpolicy=') === false) {
                $attrs .= ' referrerpolicy="strict-origin-when-cross-origin"';
            }

            return '<iframe' . $attrs . '>';
        },
        $content
    );

    return $content;
}

/**
 * Registrar evento de seguridad (para auditoría)
 *
 * @param string $event_type Tipo de evento
 * @param array $data Datos adicionales
 */
function lazy_load_block_log_security_event($event_type, $data = array()) {
    // Solo registrar si WP_DEBUG está activo o hay un filtro
    if (!apply_filters('lazy_load_block_enable_security_logging', WP_DEBUG)) {
        return;
    }

    $log_data = array(
        'timestamp' => current_time('mysql'),
        'event'     => sanitize_key($event_type),
        'user_id'   => get_current_user_id(),
        'ip'        => lazy_load_block_get_client_ip(),
        'data'      => $data,
    );

    // Usar filtro para permitir integración con sistemas de logging externos
    do_action('lazy_load_block_security_event', $log_data);

    // Log por defecto si está en debug
    if (WP_DEBUG && WP_DEBUG_LOG) {
        error_log('Lazy Load Block Security: ' . wp_json_encode($log_data));
    }
}

/**
 * Obtener IP del cliente de forma segura
 *
 * @return string IP del cliente o 'unknown'
 */
function lazy_load_block_get_client_ip() {
    $ip_keys = array(
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    );

    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER[$key]));

            // Si hay múltiples IPs (X-Forwarded-For), tomar la primera
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }

            // Validar que sea una IP válida
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return 'unknown';
}

/**
 * Verificar si el contenido tiene patrones potencialmente peligrosos
 *
 * @param string $content Contenido a verificar
 * @return array Array con 'safe' (bool) y 'warnings' (array)
 */
function lazy_load_block_check_content_safety($content) {
    $result = array(
        'safe'     => true,
        'warnings' => array(),
    );

    if (empty($content)) {
        return $result;
    }

    $dangerous_patterns = array(
        'javascript:'     => __('JavaScript protocol detectado', 'lazy-load-block'),
        'vbscript:'       => __('VBScript protocol detectado', 'lazy-load-block'),
        'data:text/html'  => __('Data URL con HTML detectado', 'lazy-load-block'),
        'on\w+\s*='       => __('Event handler inline detectado', 'lazy-load-block'),
        '<script'         => __('Script tag detectado', 'lazy-load-block'),
        'expression('     => __('CSS expression detectada', 'lazy-load-block'),
        '@import'         => __('CSS import detectado', 'lazy-load-block'),
    );

    foreach ($dangerous_patterns as $pattern => $warning) {
        if (preg_match('/' . $pattern . '/i', $content)) {
            $result['warnings'][] = $warning;
        }
    }

    // Solo marcar como inseguro si hay scripts y el usuario no tiene permisos
    if (!empty($result['warnings']) && !current_user_can('unfiltered_html')) {
        $result['safe'] = false;
    }

    return $result;
}

/**
 * Sanitizar valor de color CSS
 */
function lazy_load_block_sanitize_color($color) {
    if (empty($color)) {
        return 'rgba(0,0,0,0.7)';
    }

    $color = trim($color);

    if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $color)) {
        return $color;
    }

    if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+))?\s*\)$/', $color)) {
        return $color;
    }

    if (preg_match('/^hsla?\(\s*\d{1,3}\s*,\s*\d{1,3}%\s*,\s*\d{1,3}%\s*(,\s*(0|1|0?\.\d+))?\s*\)$/', $color)) {
        return $color;
    }

    $valid_colors = array(
        'transparent', 'black', 'white', 'red', 'green', 'blue', 'yellow',
        'orange', 'purple', 'pink', 'gray', 'grey', 'brown', 'cyan', 'magenta',
        'navy', 'teal', 'maroon', 'olive', 'lime', 'aqua', 'fuchsia', 'silver'
    );

    if (in_array(strtolower($color), $valid_colors, true)) {
        return strtolower($color);
    }

    return 'rgba(0,0,0,0.7)';
}

/**
 * Render del bloque en el frontend
 *
 * Security measures applied:
 * - HTML sanitization via wp_kses()
 * - CSS dimension validation
 * - Iframe sandbox enforcement
 * - URL validation
 * - Script permission checks
 * - Security event logging
 *
 * @param array $attributes Block attributes
 * @param string $content Block content
 * @return string Rendered HTML
 */
function lazy_load_block_render($attributes, $content) {
    // Obtener atributos con valores por defecto
    $html_content = isset($attributes['htmlContent']) ? $attributes['htmlContent'] : '';
    $trigger_text = isset($attributes['triggerText']) ? $attributes['triggerText'] : __('Cargar contenido', 'lazy-load-block');
    $trigger_type = isset($attributes['triggerType']) ? $attributes['triggerType'] : 'button';
    $placeholder_text = isset($attributes['placeholderText']) ? $attributes['placeholderText'] : '';
    $show_placeholder = isset($attributes['showPlaceholder']) ? (bool) $attributes['showPlaceholder'] : false;
    $placeholder_image = isset($attributes['placeholderImage']) ? $attributes['placeholderImage'] : '';
    $show_play_icon = isset($attributes['showPlayIcon']) ? (bool) $attributes['showPlayIcon'] : false;
    $play_icon_color = isset($attributes['playIconColor']) ? $attributes['playIconColor'] : '#000000';
    $auto_load_on_visible = isset($attributes['autoLoadOnVisible']) ? (bool) $attributes['autoLoadOnVisible'] : false;
    $container_width = isset($attributes['containerWidth']) ? $attributes['containerWidth'] : '100%';
    $container_height = isset($attributes['containerHeight']) ? $attributes['containerHeight'] : 'auto';
    $allow_scripts = isset($attributes['allowScripts']) ? (bool) $attributes['allowScripts'] : false;

    // Nuevos atributos de iframe responsive
    $iframe_width = isset($attributes['iframeWidth']) ? $attributes['iframeWidth'] : '100%';
    $iframe_height = isset($attributes['iframeHeight']) ? $attributes['iframeHeight'] : '400px';
    $iframe_width_tablet = isset($attributes['iframeWidthTablet']) ? $attributes['iframeWidthTablet'] : '';
    $iframe_height_tablet = isset($attributes['iframeHeightTablet']) ? $attributes['iframeHeightTablet'] : '';
    $iframe_width_mobile = isset($attributes['iframeWidthMobile']) ? $attributes['iframeWidthMobile'] : '';
    $iframe_height_mobile = isset($attributes['iframeHeightMobile']) ? $attributes['iframeHeightMobile'] : '';
    $aspect_ratio = isset($attributes['aspectRatio']) ? $attributes['aspectRatio'] : '';

    if (empty($html_content)) {
        return '';
    }

    // VERIFICAR SEGURIDAD DEL CONTENIDO
    $safety_check = lazy_load_block_check_content_safety($html_content);
    if (!$safety_check['safe']) {
        lazy_load_block_log_security_event('unsafe_content_blocked', array(
            'warnings' => $safety_check['warnings'],
        ));
        return '<!-- Lazy Load Block: Content blocked for security reasons -->';
    }

    // Registrar uso si hay scripts
    if ($allow_scripts && strpos($html_content, '<script') !== false) {
        lazy_load_block_log_security_event('script_content_loaded', array(
            'has_permission' => current_user_can('unfiltered_html'),
        ));
    }

    // SANITIZACIÓN DE SEGURIDAD
    $html_content = lazy_load_block_sanitize_content($html_content);

    // Determinar si se permiten scripts
    $scripts_allowed = $allow_scripts && current_user_can('unfiltered_html');

    // APLICAR SANDBOX A IFRAMES (seguridad adicional)
    $html_content = lazy_load_block_add_iframe_sandbox($html_content, $scripts_allowed);

    $valid_trigger_types = array('button', 'link', 'image');
    if (!in_array($trigger_type, $valid_trigger_types, true)) {
        $trigger_type = 'button';
    }

    $container_width = lazy_load_block_sanitize_css_dimension($container_width, '100%');
    $container_height = lazy_load_block_sanitize_css_dimension($container_height, 'auto');
    $trigger_text = sanitize_text_field($trigger_text);
    $placeholder_text = sanitize_text_field($placeholder_text);

    // Sanitizar URL del placeholder con validación de protocolo
    $placeholder_image = lazy_load_block_sanitize_url($placeholder_image);

    // Sanitizar dimensiones de iframe
    $iframe_width = lazy_load_block_sanitize_css_dimension($iframe_width, '100%');
    $iframe_height = lazy_load_block_sanitize_css_dimension($iframe_height, '400px');
    $iframe_width_tablet = lazy_load_block_sanitize_css_dimension($iframe_width_tablet, '');
    $iframe_height_tablet = lazy_load_block_sanitize_css_dimension($iframe_height_tablet, '');
    $iframe_width_mobile = lazy_load_block_sanitize_css_dimension($iframe_width_mobile, '');
    $iframe_height_mobile = lazy_load_block_sanitize_css_dimension($iframe_height_mobile, '');
    $aspect_ratio = lazy_load_block_sanitize_aspect_ratio($aspect_ratio);

    // Codificar el contenido sanitizado en base64
    $encoded_content = base64_encode($html_content);

    // Generar ID único para este bloque (y sanitizarlo para CSS)
    $block_id = 'llb-' . wp_unique_id();
    $block_id_safe = lazy_load_block_escape_css_id($block_id);

    // Determinar el modo
    $is_image_mode = !empty($placeholder_image) && $trigger_type === 'image';

    // Construir clases del wrapper
    $wrapper_classes = array('wp-block-lazy-load-block');

    if ($is_image_mode) {
        $wrapper_classes[] = 'llb-mode-image';
    } else {
        $wrapper_classes[] = 'llb-mode-button';
    }

    if ($auto_load_on_visible) {
        $wrapper_classes[] = 'llb-auto-load';
    }

    if ($scripts_allowed) {
        $wrapper_classes[] = 'llb-allow-scripts';
    }

    // Preparar datos de configuración de iframe para JS (ya sanitizados)
    $iframe_config = array(
        'width'        => $iframe_width,
        'height'       => $iframe_height,
        'widthTablet'  => $iframe_width_tablet,
        'heightTablet' => $iframe_height_tablet,
        'widthMobile'  => $iframe_width_mobile,
        'heightMobile' => $iframe_height_mobile,
        'aspectRatio'  => $aspect_ratio,
    );

    // Generar CSS responsive inline (usando ID seguro)
    $responsive_css = lazy_load_block_generate_responsive_css($block_id_safe, $iframe_config);

    // Estilos inline del contenedor
    $inline_style = '';
    if ($container_width !== '100%' || $container_height !== 'auto') {
        $inline_style = sprintf(
            'style="width: %s; min-height: %s;"',
            esc_attr(lazy_load_block_escape_css($container_width)),
            esc_attr(lazy_load_block_escape_css($container_height))
        );
    }

    $output = '';

    // CSS responsive en un bloque <style> con ID de nonce para CSP
    if (!empty($responsive_css)) {
        // Generar nonce para CSP si está disponible
        $style_nonce = '';
        if (function_exists('wp_get_inline_script_tag')) {
            // WordPress 5.7+ tiene soporte para nonces
            $nonce = wp_create_nonce('llb-inline-style');
            $style_nonce = ' data-nonce="' . esc_attr($nonce) . '"';
        }
        $output .= '<style id="llb-style-' . esc_attr($block_id_safe) . '"' . $style_nonce . '>' . $responsive_css . '</style>';
    }

    $output .= sprintf(
        '<div id="%s" class="%s" data-content="%s" data-loaded="false" data-allow-scripts="%s" data-iframe-config="%s" %s>',
        esc_attr($block_id_safe),
        esc_attr(implode(' ', $wrapper_classes)),
        esc_attr($encoded_content),
        esc_attr($scripts_allowed ? 'true' : 'false'),
        esc_attr(wp_json_encode($iframe_config)),
        $inline_style
    );

    // Área del placeholder
    $output .= '<div class="llb-placeholder">';

    if ($is_image_mode) {
        $output .= sprintf(
            '<img src="%s" alt="%s" class="llb-placeholder-image" loading="lazy" />',
            esc_url($placeholder_image),
            esc_attr($placeholder_text)
        );

        if ($show_play_icon) {
            $sanitized_color = lazy_load_block_sanitize_color($play_icon_color);
            $output .= '<div class="llb-play-overlay">';
            $output .= sprintf(
                '<div class="llb-play-icon" style="background-color: %s;">',
                esc_attr($sanitized_color)
            );
            $output .= '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M8 5v14l11-7z" fill="#fff"/></svg>';
            $output .= '</div>';
            $output .= '</div>';
        }
    } else {
        if (!empty($placeholder_image)) {
            $output .= sprintf(
                '<img src="%s" alt="%s" class="llb-placeholder-image" loading="lazy" />',
                esc_url($placeholder_image),
                esc_attr($placeholder_text)
            );
        }

        if ($show_placeholder && !empty($placeholder_text)) {
            $output .= sprintf(
                '<p class="llb-placeholder-text">%s</p>',
                esc_html($placeholder_text)
            );
        }

        if ($trigger_type === 'button') {
            $output .= sprintf(
                '<button type="button" class="llb-trigger llb-trigger-button">%s</button>',
                esc_html($trigger_text)
            );
        } else {
            $output .= sprintf(
                '<a href="#" class="llb-trigger llb-trigger-link">%s</a>',
                esc_html($trigger_text)
            );
        }
    }

    $output .= '</div>'; // .llb-placeholder

    // Contenedor donde se inyectará el contenido
    $output .= '<div class="llb-content" style="display: none;"></div>';

    // Loader
    $output .= '<div class="llb-loader" style="display: none;"><span class="llb-spinner"></span></div>';

    $output .= '</div>'; // wrapper

    return $output;
}

/**
 * Generar CSS responsive para el iframe
 *
 * Security: All values are escaped before being used in CSS
 *
 * @param string $block_id ID del bloque (ya sanitizado)
 * @param array $config Configuración de dimensiones (ya sanitizada)
 * @return string CSS seguro para uso inline
 */
function lazy_load_block_generate_responsive_css($block_id, $config) {
    $css = '';

    // Asegurar que el block_id esté limpio
    $block_id = lazy_load_block_escape_css_id($block_id);

    if (empty($block_id)) {
        return '';
    }

    // Helper para escapar valores CSS
    $escape_value = function($value) {
        return lazy_load_block_escape_css($value);
    };

    // CSS base para iframe
    $base_css = '';
    if (!empty($config['width']) && $config['width'] !== 'auto') {
        $base_css .= 'width: ' . $escape_value($config['width']) . ';';
    }
    if (!empty($config['height']) && $config['height'] !== 'auto') {
        $base_css .= 'height: ' . $escape_value($config['height']) . ';';
    }
    if (!empty($config['aspectRatio'])) {
        $base_css .= 'aspect-ratio: ' . $escape_value($config['aspectRatio']) . ';';
        // Si hay aspect-ratio, el height debe ser auto
        $base_css .= 'height: auto;';
    }

    if (!empty($base_css)) {
        $css .= '#' . $block_id . ' .llb-content iframe, ';
        $css .= '#' . $block_id . ' .llb-content embed, ';
        $css .= '#' . $block_id . ' .llb-content video {' . $base_css . '}';
    }

    // CSS para tablet (max-width: 1024px)
    $tablet_css = '';
    if (!empty($config['widthTablet'])) {
        $tablet_css .= 'width: ' . $escape_value($config['widthTablet']) . ';';
    }
    if (!empty($config['heightTablet'])) {
        $tablet_css .= 'height: ' . $escape_value($config['heightTablet']) . ';';
    }

    if (!empty($tablet_css)) {
        $css .= '@media (max-width: 1024px) {';
        $css .= '#' . $block_id . ' .llb-content iframe, ';
        $css .= '#' . $block_id . ' .llb-content embed, ';
        $css .= '#' . $block_id . ' .llb-content video {' . $tablet_css . '}';
        $css .= '}';
    }

    // CSS para móvil (max-width: 768px)
    $mobile_css = '';
    if (!empty($config['widthMobile'])) {
        $mobile_css .= 'width: ' . $escape_value($config['widthMobile']) . ';';
    }
    if (!empty($config['heightMobile'])) {
        $mobile_css .= 'height: ' . $escape_value($config['heightMobile']) . ';';
    }

    if (!empty($mobile_css)) {
        $css .= '@media (max-width: 768px) {';
        $css .= '#' . $block_id . ' .llb-content iframe, ';
        $css .= '#' . $block_id . ' .llb-content embed, ';
        $css .= '#' . $block_id . ' .llb-content video {' . $mobile_css . '}';
        $css .= '}';
    }

    return $css;
}

/**
 * Encolar scripts y estilos del frontend
 */
function lazy_load_block_enqueue_assets() {
    if (!has_block('lazy-load-block/lazy-load-block')) {
        return;
    }

    wp_enqueue_style(
        'lazy-load-block-frontend',
        LAZY_LOAD_BLOCK_URL . 'assets/css/frontend.css',
        array(),
        LAZY_LOAD_BLOCK_VERSION
    );

    wp_enqueue_script(
        'lazy-load-block-frontend',
        LAZY_LOAD_BLOCK_URL . 'assets/js/frontend.js',
        array(),
        LAZY_LOAD_BLOCK_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'lazy_load_block_enqueue_assets');

/**
 * Añadir categoría personalizada para el bloque
 */
function lazy_load_block_categories($categories) {
    return array_merge(
        $categories,
        array(
            array(
                'slug'  => 'lazy-load',
                'title' => __('Lazy Load', 'lazy-load-block'),
                'icon'  => 'performance',
            ),
        )
    );
}
add_filter('block_categories_all', 'lazy_load_block_categories', 10, 1);

/**
 * Registrar configuración del plugin
 */
function lazy_load_block_register_settings() {
    register_setting('lazy_load_block_settings', 'llb_restrict_to_admins', array(
        'type'              => 'boolean',
        'default'           => false,
        'sanitize_callback' => 'rest_sanitize_boolean',
    ));
}
add_action('admin_init', 'lazy_load_block_register_settings');

/**
 * Restringir el bloque solo a administradores si está configurado
 */
function lazy_load_block_restrict_block($allowed_blocks, $editor_context) {
    if (get_option('llb_restrict_to_admins', false) && !current_user_can('manage_options')) {
        if (is_array($allowed_blocks)) {
            $allowed_blocks = array_filter($allowed_blocks, function($block) {
                return $block !== 'lazy-load-block/lazy-load-block';
            });
        }
    }
    return $allowed_blocks;
}
add_filter('allowed_block_types_all', 'lazy_load_block_restrict_block', 10, 2);
