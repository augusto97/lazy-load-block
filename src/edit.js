/**
 * Componente de edición del bloque Lazy Load
 */

import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    InspectorControls,
    MediaUpload,
    MediaUploadCheck,
} from '@wordpress/block-editor';
import {
    PanelBody,
    TextControl,
    TextareaControl,
    SelectControl,
    ToggleControl,
    Button,
    Placeholder,
    Notice,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Componente Edit
 */
export default function Edit({ attributes, setAttributes }) {
    const {
        htmlContent,
        triggerText,
        triggerType,
        placeholderText,
        showPlaceholder,
        placeholderImage,
        autoLoadOnVisible,
        containerWidth,
        containerHeight,
        allowScripts,
    } = attributes;

    const [showPreview, setShowPreview] = useState(false);

    const blockProps = useBlockProps({
        className: 'lazy-load-block-editor',
    });

    // Detectar si hay un iframe en el contenido
    const hasIframe = htmlContent.toLowerCase().includes('<iframe');
    const hasScript = htmlContent.toLowerCase().includes('<script');

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Configuración del Trigger', 'lazy-load-block')} initialOpen={true}>
                    <TextControl
                        label={__('Texto del botón/enlace', 'lazy-load-block')}
                        value={triggerText}
                        onChange={(value) => setAttributes({ triggerText: value })}
                        help={__('Texto que aparece en el botón para cargar el contenido', 'lazy-load-block')}
                    />
                    <SelectControl
                        label={__('Tipo de trigger', 'lazy-load-block')}
                        value={triggerType}
                        options={[
                            { label: __('Botón', 'lazy-load-block'), value: 'button' },
                            { label: __('Enlace', 'lazy-load-block'), value: 'link' },
                        ]}
                        onChange={(value) => setAttributes({ triggerType: value })}
                    />
                </PanelBody>

                <PanelBody title={__('Placeholder', 'lazy-load-block')} initialOpen={true}>
                    <ToggleControl
                        label={__('Mostrar texto de placeholder', 'lazy-load-block')}
                        checked={showPlaceholder}
                        onChange={(value) => setAttributes({ showPlaceholder: value })}
                    />
                    {showPlaceholder && (
                        <TextControl
                            label={__('Texto del placeholder', 'lazy-load-block')}
                            value={placeholderText}
                            onChange={(value) => setAttributes({ placeholderText: value })}
                        />
                    )}
                    <MediaUploadCheck>
                        <MediaUpload
                            onSelect={(media) => setAttributes({ placeholderImage: media.url })}
                            allowedTypes={['image']}
                            value={placeholderImage}
                            render={({ open }) => (
                                <div className="llb-media-upload">
                                    <Button
                                        onClick={open}
                                        variant="secondary"
                                        style={{ marginBottom: '10px' }}
                                    >
                                        {placeholderImage
                                            ? __('Cambiar imagen', 'lazy-load-block')
                                            : __('Seleccionar imagen de placeholder', 'lazy-load-block')}
                                    </Button>
                                    {placeholderImage && (
                                        <>
                                            <img
                                                src={placeholderImage}
                                                alt=""
                                                style={{ maxWidth: '100%', marginBottom: '10px' }}
                                            />
                                            <Button
                                                onClick={() => setAttributes({ placeholderImage: '' })}
                                                variant="link"
                                                isDestructive
                                            >
                                                {__('Eliminar imagen', 'lazy-load-block')}
                                            </Button>
                                        </>
                                    )}
                                </div>
                            )}
                        />
                    </MediaUploadCheck>
                </PanelBody>

                <PanelBody title={__('Dimensiones', 'lazy-load-block')} initialOpen={false}>
                    <TextControl
                        label={__('Ancho del contenedor', 'lazy-load-block')}
                        value={containerWidth}
                        onChange={(value) => setAttributes({ containerWidth: value })}
                        help={__('Ej: 100%, 600px, 50vw', 'lazy-load-block')}
                    />
                    <TextControl
                        label={__('Alto del contenedor', 'lazy-load-block')}
                        value={containerHeight}
                        onChange={(value) => setAttributes({ containerHeight: value })}
                        help={__('Ej: auto, 400px, 50vh', 'lazy-load-block')}
                    />
                </PanelBody>

                <PanelBody title={__('Opciones avanzadas', 'lazy-load-block')} initialOpen={false}>
                    <ToggleControl
                        label={__('Cargar automáticamente al ser visible', 'lazy-load-block')}
                        checked={autoLoadOnVisible}
                        onChange={(value) => setAttributes({ autoLoadOnVisible: value })}
                        help={__('Usa Intersection Observer para cargar cuando el bloque entre en el viewport. Útil para lazy loading real sin clic.', 'lazy-load-block')}
                    />
                    <ToggleControl
                        label={__('Permitir ejecución de scripts', 'lazy-load-block')}
                        checked={allowScripts}
                        onChange={(value) => setAttributes({ allowScripts: value })}
                        help={__('ADVERTENCIA DE SEGURIDAD: Solo activa esto si confías en el código. Los scripts solo se ejecutarán si tienes permisos de administrador (unfiltered_html).', 'lazy-load-block')}
                    />
                    {allowScripts && (
                        <Notice status="warning" isDismissible={false}>
                            {__('Los scripts embebidos se ejecutarán cuando el contenido se cargue. Asegúrate de que el código sea de una fuente confiable.', 'lazy-load-block')}
                        </Notice>
                    )}
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <div className="llb-editor-container">
                    <div className="llb-editor-header">
                        <span className="llb-editor-icon">⚡</span>
                        <span className="llb-editor-title">{__('Lazy Load Block', 'lazy-load-block')}</span>
                        <span className="llb-editor-badge">
                            {hasIframe ? 'iframe' : hasScript ? 'script' : 'HTML'}
                        </span>
                    </div>

                    {hasIframe && (
                        <Notice status="success" isDismissible={false}>
                            {__('¡Perfecto! El iframe NO se cargará hasta que el usuario haga clic. Esto mejorará tu PageSpeed.', 'lazy-load-block')}
                        </Notice>
                    )}

                    <TextareaControl
                        label={__('Contenido HTML (iframe, embed, etc.)', 'lazy-load-block')}
                        value={htmlContent}
                        onChange={(value) => setAttributes({ htmlContent: value })}
                        placeholder={__('Pega aquí el código del iframe, embed o cualquier HTML que quieras cargar de forma diferida...', 'lazy-load-block')}
                        rows={8}
                        className="llb-html-textarea"
                    />

                    {htmlContent && (
                        <div className="llb-preview-section">
                            <Button
                                variant="secondary"
                                onClick={() => setShowPreview(!showPreview)}
                            >
                                {showPreview
                                    ? __('Ocultar vista previa', 'lazy-load-block')
                                    : __('Mostrar vista previa', 'lazy-load-block')}
                            </Button>

                            {showPreview && (
                                <div className="llb-preview-container">
                                    <p className="llb-preview-notice">
                                        {__('Vista previa (en el frontend NO se cargará hasta hacer clic):', 'lazy-load-block')}
                                    </p>
                                    <div
                                        className="llb-preview-content"
                                        dangerouslySetInnerHTML={{ __html: htmlContent }}
                                    />
                                </div>
                            )}
                        </div>
                    )}

                    {!htmlContent && (
                        <Placeholder
                            icon="performance"
                            label={__('Lazy Load Block', 'lazy-load-block')}
                            instructions={__('Pega el código HTML, iframe o embed que quieras cargar de forma diferida. El contenido solo se cargará cuando el usuario haga clic.', 'lazy-load-block')}
                        />
                    )}

                    <div className="llb-editor-footer">
                        <p>
                            <strong>{__('Cómo funciona:', 'lazy-load-block')}</strong> {__('El contenido se guarda codificado y NO se renderiza en el HTML de la página. Solo se inyecta en el DOM cuando el usuario hace clic en el botón.', 'lazy-load-block')}
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
