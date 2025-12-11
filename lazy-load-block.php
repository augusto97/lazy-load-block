<?php
/**
 * Plugin Name: Lazy Load Block
 * Description: Bloque de Gutenberg que carga contenido (iframes, HTML) solo cuando el usuario hace clic. Evita peticiones innecesarias en PageSpeed, GTmetrix, etc.
 * Version: 1.1.0
 * Author: Augusto
 * License: GPL v2 or later
 * Text Domain: lazy-load-block
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LAZY_LOAD_BLOCK_VERSION', '1.2.0');
define('LAZY_LOAD_BLOCK_PATH', plugin_dir_path(__FILE__));
define('LAZY_LOAD_BLOCK_URL', plugin_dir_url(__FILE__));

/**
 * Registrar el bloque de Gutenberg
 */
function lazy_load_block_init() {
    // Registrar el bloque con render dinámico
    register_block_type(LAZY_LOAD_BLOCK_PATH . 'build', array(
        'render_callback' => 'lazy_load_block_render',
    ));
}
add_action('init', 'lazy_load_block_init');

/**
 * Obtener tags HTML permitidos para el contenido del bloque
 * Extiende wp_kses_post con tags adicionales para embeds
 *
 * @return array Lista de tags y atributos permitidos
 */
function lazy_load_block_allowed_html() {
    // Obtener los tags permitidos por defecto en posts
    $allowed = wp_kses_allowed_html('post');

    // Añadir iframe con atributos seguros
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

    // Añadir embed
    $allowed['embed'] = array(
        'src'    => true,
        'type'   => true,
        'width'  => true,
        'height' => true,
        'class'  => true,
        'id'     => true,
    );

    // Añadir object
    $allowed['object'] = array(
        'data'   => true,
        'type'   => true,
        'width'  => true,
        'height' => true,
        'class'  => true,
        'id'     => true,
    );

    // Añadir param (para object)
    $allowed['param'] = array(
        'name'  => true,
        'value' => true,
    );

    // Añadir source (para video/audio)
    $allowed['source'] = array(
        'src'   => true,
        'type'  => true,
        'media' => true,
    );

    // Permitir scripts SOLO si el usuario tiene la capacidad 'unfiltered_html'
    // (normalmente solo administradores y super admins)
    if (current_user_can('unfiltered_html')) {
        $allowed['script'] = array(
            'src'     => true,
            'async'   => true,
            'defer'   => true,
            'type'    => true,
            'charset' => true,
            'id'      => true,
        );
    }

    /**
     * Filtro para permitir que otros plugins modifiquen los tags permitidos
     *
     * @param array $allowed Tags y atributos permitidos
     */
    return apply_filters('lazy_load_block_allowed_html', $allowed);
}

/**
 * Sanitizar el contenido HTML del bloque
 *
 * @param string $content Contenido HTML sin sanitizar
 * @return string Contenido HTML sanitizado
 */
function lazy_load_block_sanitize_content($content) {
    if (empty($content)) {
        return '';
    }

    // Usar wp_kses con nuestra lista de tags permitidos
    $sanitized = wp_kses($content, lazy_load_block_allowed_html());

    return $sanitized;
}

/**
 * Validar y sanitizar valor CSS para dimensiones
 * Solo permite valores seguros como: 100%, 500px, 50vh, 50vw, auto, inherit
 *
 * @param string $value Valor CSS a validar
 * @param string $default Valor por defecto si no es válido
 * @return string Valor CSS sanitizado
 */
function lazy_load_block_sanitize_css_dimension($value, $default = 'auto') {
    if (empty($value)) {
        return $default;
    }

    // Convertir a minúsculas y quitar espacios
    $value = strtolower(trim($value));

    // Lista de valores permitidos literales
    $allowed_values = array('auto', 'inherit', 'initial', 'unset', 'none', 'max-content', 'min-content', 'fit-content');

    if (in_array($value, $allowed_values, true)) {
        return $value;
    }

    // Patrón para valores numéricos con unidades (100px, 50%, 10em, 5rem, 50vh, 50vw)
    // Solo permite números (enteros o decimales) seguidos de unidades válidas
    if (preg_match('/^(\d+\.?\d*)(px|%|em|rem|vh|vw|vmin|vmax|ch|ex|cm|mm|in|pt|pc)$/', $value, $matches)) {
        return $value;
    }

    // Si no coincide con ningún patrón válido, devolver el valor por defecto
    return $default;
}

/**
 * Render del bloque en el frontend
 * El contenido NO se renderiza directamente - se guarda en data-content codificado
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
    $auto_load_on_visible = isset($attributes['autoLoadOnVisible']) ? (bool) $attributes['autoLoadOnVisible'] : false;
    $container_width = isset($attributes['containerWidth']) ? $attributes['containerWidth'] : '100%';
    $container_height = isset($attributes['containerHeight']) ? $attributes['containerHeight'] : 'auto';
    $allow_scripts = isset($attributes['allowScripts']) ? (bool) $attributes['allowScripts'] : false;

    if (empty($html_content)) {
        return '';
    }

    // SANITIZACIÓN DE SEGURIDAD
    $html_content = lazy_load_block_sanitize_content($html_content);

    $valid_trigger_types = array('button', 'link', 'image');
    if (!in_array($trigger_type, $valid_trigger_types, true)) {
        $trigger_type = 'button';
    }

    $container_width = lazy_load_block_sanitize_css_dimension($container_width, '100%');
    $container_height = lazy_load_block_sanitize_css_dimension($container_height, 'auto');
    $trigger_text = sanitize_text_field($trigger_text);
    $placeholder_text = sanitize_text_field($placeholder_text);
    $placeholder_image = esc_url_raw($placeholder_image);

    // Codificar el contenido sanitizado en base64
    $encoded_content = base64_encode($html_content);

    // Generar ID único para este bloque
    $block_id = 'llb-' . wp_unique_id();

    // Determinar el modo: imagen (limpio) o botón (tradicional)
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

    $scripts_allowed = $allow_scripts && current_user_can('unfiltered_html');
    if ($scripts_allowed) {
        $wrapper_classes[] = 'llb-allow-scripts';
    }

    // Estilos inline solo si son necesarios
    $inline_style = '';
    if ($container_width !== '100%' || $container_height !== 'auto') {
        $inline_style = sprintf('style="width: %s; min-height: %s;"', esc_attr($container_width), esc_attr($container_height));
    }

    $output = sprintf(
        '<div id="%s" class="%s" data-content="%s" data-loaded="false" data-allow-scripts="%s" %s>',
        esc_attr($block_id),
        esc_attr(implode(' ', $wrapper_classes)),
        esc_attr($encoded_content),
        esc_attr($scripts_allowed ? 'true' : 'false'),
        $inline_style
    );

    // Área del placeholder - clickeable completo
    $output .= '<div class="llb-placeholder">';

    if ($is_image_mode) {
        // MODO IMAGEN: Solo la imagen, clickeable completa
        $output .= sprintf(
            '<img src="%s" alt="%s" class="llb-placeholder-image" loading="lazy" />',
            esc_url($placeholder_image),
            esc_attr($placeholder_text)
        );

        // Icono de play opcional
        if ($show_play_icon) {
            $output .= '<div class="llb-play-overlay">';
            $output .= '<div class="llb-play-icon">';
            $output .= '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M8 5v14l11-7z"/></svg>';
            $output .= '</div>';
            $output .= '</div>';
        }
    } else {
        // MODO BOTÓN: Imagen opcional + texto + botón
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

    // Contenedor donde se inyectará el contenido (vacío inicialmente)
    $output .= '<div class="llb-content" style="display: none;"></div>';

    // Loader/spinner
    $output .= '<div class="llb-loader" style="display: none;"><span class="llb-spinner"></span></div>';

    $output .= '</div>'; // wrapper

    return $output;
}

/**
 * Encolar scripts y estilos del frontend
 */
function lazy_load_block_enqueue_assets() {
    // Solo cargar si hay bloques de este tipo en la página
    if (!has_block('lazy-load-block/lazy-load-block')) {
        return;
    }

    // CSS del frontend
    wp_enqueue_style(
        'lazy-load-block-frontend',
        LAZY_LOAD_BLOCK_URL . 'assets/css/frontend.css',
        array(),
        LAZY_LOAD_BLOCK_VERSION
    );

    // JavaScript del frontend
    wp_enqueue_script(
        'lazy-load-block-frontend',
        LAZY_LOAD_BLOCK_URL . 'assets/js/frontend.js',
        array(),
        LAZY_LOAD_BLOCK_VERSION,
        true // Cargar en el footer
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
    // Si la opción está activada y el usuario no es admin, quitar el bloque
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
