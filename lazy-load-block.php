<?php
/**
 * Plugin Name: Lazy Load Block
 * Description: Bloque de Gutenberg que carga contenido (iframes, HTML) solo cuando el usuario hace clic. Evita peticiones innecesarias en PageSpeed, GTmetrix, etc.
 * Version: 1.4.0
 * Author: Augusto
 * License: GPL v2 or later
 * Text Domain: lazy-load-block
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LAZY_LOAD_BLOCK_VERSION', '1.4.0');
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
 */
function lazy_load_block_allowed_html() {
    $allowed = wp_kses_allowed_html('post');

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

    return apply_filters('lazy_load_block_allowed_html', $allowed);
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

    // Generar ID único para este bloque
    $block_id = 'llb-' . wp_unique_id();

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

    $scripts_allowed = $allow_scripts && current_user_can('unfiltered_html');
    if ($scripts_allowed) {
        $wrapper_classes[] = 'llb-allow-scripts';
    }

    // Preparar datos de configuración de iframe para JS
    $iframe_config = array(
        'width'        => $iframe_width,
        'height'       => $iframe_height,
        'widthTablet'  => $iframe_width_tablet,
        'heightTablet' => $iframe_height_tablet,
        'widthMobile'  => $iframe_width_mobile,
        'heightMobile' => $iframe_height_mobile,
        'aspectRatio'  => $aspect_ratio,
    );

    // Generar CSS responsive inline
    $responsive_css = lazy_load_block_generate_responsive_css($block_id, $iframe_config);

    // Estilos inline del contenedor
    $inline_style = '';
    if ($container_width !== '100%' || $container_height !== 'auto') {
        $inline_style = sprintf('style="width: %s; min-height: %s;"', esc_attr($container_width), esc_attr($container_height));
    }

    $output = '';

    // CSS responsive en un bloque <style>
    if (!empty($responsive_css)) {
        $output .= '<style>' . $responsive_css . '</style>';
    }

    $output .= sprintf(
        '<div id="%s" class="%s" data-content="%s" data-loaded="false" data-allow-scripts="%s" data-iframe-config="%s" %s>',
        esc_attr($block_id),
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
 */
function lazy_load_block_generate_responsive_css($block_id, $config) {
    $css = '';

    // CSS base para iframe
    $base_css = '';
    if (!empty($config['width']) && $config['width'] !== 'auto') {
        $base_css .= 'width: ' . $config['width'] . ';';
    }
    if (!empty($config['height']) && $config['height'] !== 'auto') {
        $base_css .= 'height: ' . $config['height'] . ';';
    }
    if (!empty($config['aspectRatio'])) {
        $base_css .= 'aspect-ratio: ' . $config['aspectRatio'] . ';';
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
        $tablet_css .= 'width: ' . $config['widthTablet'] . ';';
    }
    if (!empty($config['heightTablet'])) {
        $tablet_css .= 'height: ' . $config['heightTablet'] . ';';
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
        $mobile_css .= 'width: ' . $config['widthMobile'] . ';';
    }
    if (!empty($config['heightMobile'])) {
        $mobile_css .= 'height: ' . $config['heightMobile'] . ';';
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
