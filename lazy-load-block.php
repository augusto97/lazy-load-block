<?php
/**
 * Plugin Name: Lazy Load Block
 * Description: Bloque de Gutenberg que carga contenido (iframes, HTML) solo cuando el usuario hace clic. Evita peticiones innecesarias en PageSpeed, GTmetrix, etc.
 * Version: 1.0.0
 * Author: Augusto
 * License: GPL v2 or later
 * Text Domain: lazy-load-block
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LAZY_LOAD_BLOCK_VERSION', '1.0.0');
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
 * Render del bloque en el frontend
 * El contenido NO se renderiza directamente - se guarda en data-content codificado
 */
function lazy_load_block_render($attributes, $content) {
    // Obtener atributos con valores por defecto
    $html_content = isset($attributes['htmlContent']) ? $attributes['htmlContent'] : '';
    $trigger_text = isset($attributes['triggerText']) ? $attributes['triggerText'] : __('Cargar contenido', 'lazy-load-block');
    $trigger_type = isset($attributes['triggerType']) ? $attributes['triggerType'] : 'button';
    $placeholder_text = isset($attributes['placeholderText']) ? $attributes['placeholderText'] : __('Haz clic para cargar', 'lazy-load-block');
    $show_placeholder = isset($attributes['showPlaceholder']) ? $attributes['showPlaceholder'] : true;
    $placeholder_image = isset($attributes['placeholderImage']) ? $attributes['placeholderImage'] : '';
    $auto_load_on_visible = isset($attributes['autoLoadOnVisible']) ? $attributes['autoLoadOnVisible'] : false;
    $container_width = isset($attributes['containerWidth']) ? $attributes['containerWidth'] : '100%';
    $container_height = isset($attributes['containerHeight']) ? $attributes['containerHeight'] : 'auto';

    if (empty($html_content)) {
        return '';
    }

    // Codificar el contenido en base64 para que NO se parsee ni ejecute
    $encoded_content = base64_encode($html_content);

    // Generar ID único para este bloque
    $block_id = 'llb-' . wp_unique_id();

    // Construir el HTML del placeholder/trigger
    $wrapper_classes = 'wp-block-lazy-load-block';
    if ($auto_load_on_visible) {
        $wrapper_classes .= ' llb-auto-load';
    }

    $output = sprintf(
        '<div id="%s" class="%s" data-content="%s" data-loaded="false" style="width: %s; min-height: %s;">',
        esc_attr($block_id),
        esc_attr($wrapper_classes),
        esc_attr($encoded_content),
        esc_attr($container_width),
        esc_attr($container_height)
    );

    // Área del placeholder (se muestra antes de cargar)
    $output .= '<div class="llb-placeholder">';

    // Imagen de placeholder si existe
    if (!empty($placeholder_image)) {
        $output .= sprintf(
            '<img src="%s" alt="%s" class="llb-placeholder-image" loading="lazy" />',
            esc_url($placeholder_image),
            esc_attr($placeholder_text)
        );
    }

    // Texto del placeholder
    if ($show_placeholder && !empty($placeholder_text)) {
        $output .= sprintf(
            '<p class="llb-placeholder-text">%s</p>',
            esc_html($placeholder_text)
        );
    }

    // Trigger (botón o enlace)
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
