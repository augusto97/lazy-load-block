/**
 * Componente de edición del bloque Lazy Load
 * Versión minimalista
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
    ButtonGroup,
    Notice,
    __experimentalText as Text,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

export default function Edit({ attributes, setAttributes }) {
    const {
        htmlContent,
        triggerType,
        triggerText,
        placeholderImage,
        showPlayIcon,
        placeholderText,
        showPlaceholder,
        autoLoadOnVisible,
        containerWidth,
        containerHeight,
        allowScripts,
    } = attributes;

    const [viewMode, setViewMode] = useState('code'); // 'code' o 'preview'

    const blockProps = useBlockProps({
        className: 'lazy-load-block-editor',
    });

    const isImageMode = triggerType === 'image';

    return (
        <>
            {/* Panel lateral con todas las configuraciones */}
            <InspectorControls>
                {/* Información del bloque */}
                <PanelBody title={__('Cómo funciona', 'lazy-load-block')} initialOpen={false}>
                    <Text>
                        {__('El contenido HTML que coloques aquí NO se cargará hasta que el usuario haga clic. Esto mejora tu PageSpeed porque el navegador no hace peticiones HTTP al contenido oculto.', 'lazy-load-block')}
                    </Text>
                </PanelBody>

                {/* Imagen de placeholder */}
                <PanelBody title={__('Imagen de placeholder', 'lazy-load-block')} initialOpen={true}>
                    <MediaUploadCheck>
                        <MediaUpload
                            onSelect={(media) => setAttributes({ placeholderImage: media.url })}
                            allowedTypes={['image']}
                            value={placeholderImage}
                            render={({ open }) => (
                                <div>
                                    {placeholderImage ? (
                                        <>
                                            <img
                                                src={placeholderImage}
                                                alt=""
                                                style={{ width: '100%', marginBottom: '10px', borderRadius: '4px' }}
                                            />
                                            <ButtonGroup>
                                                <Button variant="secondary" onClick={open}>
                                                    {__('Cambiar', 'lazy-load-block')}
                                                </Button>
                                                <Button
                                                    variant="tertiary"
                                                    isDestructive
                                                    onClick={() => setAttributes({ placeholderImage: '' })}
                                                >
                                                    {__('Eliminar', 'lazy-load-block')}
                                                </Button>
                                            </ButtonGroup>
                                        </>
                                    ) : (
                                        <Button variant="secondary" onClick={open}>
                                            {__('Seleccionar imagen', 'lazy-load-block')}
                                        </Button>
                                    )}
                                </div>
                            )}
                        />
                    </MediaUploadCheck>

                    {placeholderImage && (
                        <ToggleControl
                            label={__('Mostrar icono de play', 'lazy-load-block')}
                            checked={showPlayIcon}
                            onChange={(value) => setAttributes({ showPlayIcon: value })}
                            style={{ marginTop: '15px' }}
                        />
                    )}
                </PanelBody>

                {/* Tipo de trigger */}
                <PanelBody title={__('Tipo de activador', 'lazy-load-block')} initialOpen={true}>
                    <SelectControl
                        label={__('¿Cómo se activa la carga?', 'lazy-load-block')}
                        value={triggerType}
                        options={[
                            { label: __('Imagen clickeable (limpio)', 'lazy-load-block'), value: 'image' },
                            { label: __('Botón', 'lazy-load-block'), value: 'button' },
                            { label: __('Enlace', 'lazy-load-block'), value: 'link' },
                        ]}
                        onChange={(value) => setAttributes({ triggerType: value })}
                    />

                    {triggerType !== 'image' && (
                        <TextControl
                            label={__('Texto del botón/enlace', 'lazy-load-block')}
                            value={triggerText}
                            onChange={(value) => setAttributes({ triggerText: value })}
                        />
                    )}

                    {triggerType !== 'image' && (
                        <>
                            <ToggleControl
                                label={__('Mostrar texto descriptivo', 'lazy-load-block')}
                                checked={showPlaceholder}
                                onChange={(value) => setAttributes({ showPlaceholder: value })}
                            />
                            {showPlaceholder && (
                                <TextControl
                                    label={__('Texto descriptivo', 'lazy-load-block')}
                                    value={placeholderText}
                                    onChange={(value) => setAttributes({ placeholderText: value })}
                                />
                            )}
                        </>
                    )}
                </PanelBody>

                {/* Dimensiones */}
                <PanelBody title={__('Dimensiones', 'lazy-load-block')} initialOpen={false}>
                    <TextControl
                        label={__('Ancho', 'lazy-load-block')}
                        value={containerWidth}
                        onChange={(value) => setAttributes({ containerWidth: value })}
                        help="100%, 600px, 50vw..."
                    />
                    <TextControl
                        label={__('Alto mínimo', 'lazy-load-block')}
                        value={containerHeight}
                        onChange={(value) => setAttributes({ containerHeight: value })}
                        help="auto, 400px, 50vh..."
                    />
                </PanelBody>

                {/* Opciones avanzadas */}
                <PanelBody title={__('Avanzado', 'lazy-load-block')} initialOpen={false}>
                    <ToggleControl
                        label={__('Auto-cargar al ser visible', 'lazy-load-block')}
                        checked={autoLoadOnVisible}
                        onChange={(value) => setAttributes({ autoLoadOnVisible: value })}
                        help={__('Carga automáticamente cuando el bloque entra en el viewport.', 'lazy-load-block')}
                    />
                    <ToggleControl
                        label={__('Permitir scripts', 'lazy-load-block')}
                        checked={allowScripts}
                        onChange={(value) => setAttributes({ allowScripts: value })}
                    />
                    {allowScripts && (
                        <Notice status="warning" isDismissible={false}>
                            {__('Solo para administradores con permisos.', 'lazy-load-block')}
                        </Notice>
                    )}
                </PanelBody>
            </InspectorControls>

            {/* Bloque en el editor - minimalista */}
            <div {...blockProps}>
                <div className="llb-editor-minimal">
                    {/* Toggle código/preview */}
                    <div className="llb-editor-toggle">
                        <ButtonGroup>
                            <Button
                                variant={viewMode === 'code' ? 'primary' : 'secondary'}
                                onClick={() => setViewMode('code')}
                                size="small"
                            >
                                {__('Código', 'lazy-load-block')}
                            </Button>
                            <Button
                                variant={viewMode === 'preview' ? 'primary' : 'secondary'}
                                onClick={() => setViewMode('preview')}
                                size="small"
                                disabled={!htmlContent && !placeholderImage}
                            >
                                {__('Vista previa', 'lazy-load-block')}
                            </Button>
                        </ButtonGroup>
                    </div>

                    {/* Contenido según el modo */}
                    {viewMode === 'code' ? (
                        <TextareaControl
                            value={htmlContent}
                            onChange={(value) => setAttributes({ htmlContent: value })}
                            placeholder={__('Pega aquí el código HTML, iframe o embed...', 'lazy-load-block')}
                            rows={6}
                            className="llb-code-editor"
                        />
                    ) : (
                        <div className="llb-preview-area">
                            {isImageMode && placeholderImage ? (
                                <div className="llb-preview-image-mode">
                                    <img src={placeholderImage} alt="" />
                                    {showPlayIcon && (
                                        <div className="llb-preview-play">
                                            <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z" fill="currentColor"/></svg>
                                        </div>
                                    )}
                                </div>
                            ) : placeholderImage ? (
                                <div className="llb-preview-button-mode">
                                    <img src={placeholderImage} alt="" />
                                    {showPlaceholder && placeholderText && <p>{placeholderText}</p>}
                                    <button type="button">{triggerText}</button>
                                </div>
                            ) : (
                                <div className="llb-preview-button-mode">
                                    {showPlaceholder && placeholderText && <p>{placeholderText}</p>}
                                    <button type="button">{triggerText}</button>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
