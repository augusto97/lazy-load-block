/**
 * Componente de edición del bloque Lazy Load
 * Versión con soporte responsive para iframes
 */

import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    InspectorControls,
    BlockControls,
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
    ToolbarGroup,
    ToolbarButton,
    ColorPicker,
    TabPanel,
    __experimentalText as Text,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { code, seen, desktop, tablet, mobile } from '@wordpress/icons';

export default function Edit({ attributes, setAttributes }) {
    const {
        htmlContent,
        triggerType,
        triggerText,
        placeholderImage,
        showPlayIcon,
        playIconColor,
        placeholderText,
        showPlaceholder,
        autoLoadOnVisible,
        containerWidth,
        containerHeight,
        iframeWidth,
        iframeHeight,
        iframeWidthTablet,
        iframeHeightTablet,
        iframeWidthMobile,
        iframeHeightMobile,
        aspectRatio,
        allowScripts,
    } = attributes;

    const [viewMode, setViewMode] = useState('preview');

    const blockProps = useBlockProps({
        className: 'lazy-load-block-editor',
    });

    const isImageMode = triggerType === 'image';

    // Detectar si hay un iframe en el contenido
    const hasIframe = htmlContent && htmlContent.toLowerCase().includes('<iframe');

    return (
        <>
            {/* Barra de herramientas flotante */}
            <BlockControls>
                <ToolbarGroup>
                    <ToolbarButton
                        icon={code}
                        label={__('Editar código', 'lazy-load-block')}
                        isPressed={viewMode === 'code'}
                        onClick={() => setViewMode('code')}
                    />
                    <ToolbarButton
                        icon={seen}
                        label={__('Vista previa', 'lazy-load-block')}
                        isPressed={viewMode === 'preview'}
                        onClick={() => setViewMode('preview')}
                    />
                </ToolbarGroup>
            </BlockControls>

            {/* Panel lateral */}
            <InspectorControls>
                {/* Código HTML */}
                <PanelBody title={__('Código HTML', 'lazy-load-block')} initialOpen={true}>
                    <TextareaControl
                        value={htmlContent}
                        onChange={(value) => setAttributes({ htmlContent: value })}
                        placeholder={__('Pega aquí el código HTML, iframe o embed...', 'lazy-load-block')}
                        rows={6}
                        help={__('Este contenido NO se cargará hasta que el usuario haga clic.', 'lazy-load-block')}
                    />
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
                </PanelBody>

                {/* Icono de Play */}
                {placeholderImage && triggerType === 'image' && (
                    <PanelBody title={__('Icono de Play', 'lazy-load-block')} initialOpen={true}>
                        <ToggleControl
                            label={__('Mostrar icono de play', 'lazy-load-block')}
                            checked={showPlayIcon}
                            onChange={(value) => setAttributes({ showPlayIcon: value })}
                        />
                        {showPlayIcon && (
                            <>
                                <Text style={{ marginBottom: '8px', display: 'block' }}>
                                    {__('Color del icono', 'lazy-load-block')}
                                </Text>
                                <ColorPicker
                                    color={playIconColor}
                                    onChange={(color) => setAttributes({ playIconColor: color })}
                                    enableAlpha={true}
                                />
                            </>
                        )}
                    </PanelBody>
                )}

                {/* Dimensiones del iframe/contenido - Responsive */}
                <PanelBody title={__('Tamaño del contenido', 'lazy-load-block')} initialOpen={hasIframe}>
                    <SelectControl
                        label={__('Proporción (aspect ratio)', 'lazy-load-block')}
                        value={aspectRatio}
                        options={[
                            { label: __('Personalizado', 'lazy-load-block'), value: '' },
                            { label: '16:9 (Video HD)', value: '16/9' },
                            { label: '4:3 (Video clásico)', value: '4/3' },
                            { label: '1:1 (Cuadrado)', value: '1/1' },
                            { label: '9:16 (Vertical)', value: '9/16' },
                            { label: '21:9 (Ultrawide)', value: '21/9' },
                        ]}
                        onChange={(value) => setAttributes({ aspectRatio: value })}
                        help={__('Si seleccionas una proporción, el alto se ajusta automáticamente.', 'lazy-load-block')}
                    />

                    <TabPanel
                        className="llb-responsive-tabs"
                        tabs={[
                            {
                                name: 'desktop',
                                title: __('Desktop', 'lazy-load-block'),
                                icon: desktop,
                            },
                            {
                                name: 'tablet',
                                title: __('Tablet', 'lazy-load-block'),
                                icon: tablet,
                            },
                            {
                                name: 'mobile',
                                title: __('Móvil', 'lazy-load-block'),
                                icon: mobile,
                            },
                        ]}
                    >
                        {(tab) => {
                            if (tab.name === 'desktop') {
                                return (
                                    <div style={{ paddingTop: '15px' }}>
                                        <TextControl
                                            label={__('Ancho', 'lazy-load-block')}
                                            value={iframeWidth}
                                            onChange={(value) => setAttributes({ iframeWidth: value })}
                                            placeholder="100%"
                                            help="100%, 800px, 50vw..."
                                        />
                                        {!aspectRatio && (
                                            <TextControl
                                                label={__('Alto', 'lazy-load-block')}
                                                value={iframeHeight}
                                                onChange={(value) => setAttributes({ iframeHeight: value })}
                                                placeholder="400px"
                                                help="400px, 50vh, auto..."
                                            />
                                        )}
                                    </div>
                                );
                            }
                            if (tab.name === 'tablet') {
                                return (
                                    <div style={{ paddingTop: '15px' }}>
                                        <Text style={{ marginBottom: '10px', display: 'block', color: '#757575' }}>
                                            {__('Pantallas < 1024px. Deja vacío para usar valores de Desktop.', 'lazy-load-block')}
                                        </Text>
                                        <TextControl
                                            label={__('Ancho', 'lazy-load-block')}
                                            value={iframeWidthTablet}
                                            onChange={(value) => setAttributes({ iframeWidthTablet: value })}
                                            placeholder={iframeWidth || '100%'}
                                        />
                                        {!aspectRatio && (
                                            <TextControl
                                                label={__('Alto', 'lazy-load-block')}
                                                value={iframeHeightTablet}
                                                onChange={(value) => setAttributes({ iframeHeightTablet: value })}
                                                placeholder={iframeHeight || '400px'}
                                            />
                                        )}
                                    </div>
                                );
                            }
                            if (tab.name === 'mobile') {
                                return (
                                    <div style={{ paddingTop: '15px' }}>
                                        <Text style={{ marginBottom: '10px', display: 'block', color: '#757575' }}>
                                            {__('Pantallas < 768px. Deja vacío para heredar.', 'lazy-load-block')}
                                        </Text>
                                        <TextControl
                                            label={__('Ancho', 'lazy-load-block')}
                                            value={iframeWidthMobile}
                                            onChange={(value) => setAttributes({ iframeWidthMobile: value })}
                                            placeholder={iframeWidthTablet || iframeWidth || '100%'}
                                        />
                                        {!aspectRatio && (
                                            <TextControl
                                                label={__('Alto', 'lazy-load-block')}
                                                value={iframeHeightMobile}
                                                onChange={(value) => setAttributes({ iframeHeightMobile: value })}
                                                placeholder={iframeHeightTablet || iframeHeight || '300px'}
                                            />
                                        )}
                                    </div>
                                );
                            }
                            return null;
                        }}
                    </TabPanel>
                </PanelBody>

                {/* Tipo de trigger */}
                <PanelBody title={__('Tipo de activador', 'lazy-load-block')} initialOpen={false}>
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

                {/* Dimensiones del contenedor */}
                <PanelBody title={__('Contenedor', 'lazy-load-block')} initialOpen={false}>
                    <TextControl
                        label={__('Ancho del contenedor', 'lazy-load-block')}
                        value={containerWidth}
                        onChange={(value) => setAttributes({ containerWidth: value })}
                        help="100%, 600px, 50vw..."
                    />
                    <TextControl
                        label={__('Alto mínimo del contenedor', 'lazy-load-block')}
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

            {/* Bloque en el editor */}
            <div {...blockProps}>
                {viewMode === 'code' ? (
                    <div className="llb-editor-code">
                        <textarea
                            value={htmlContent}
                            onChange={(e) => setAttributes({ htmlContent: e.target.value })}
                            placeholder={__('Pega aquí el código HTML, iframe o embed...', 'lazy-load-block')}
                            rows={6}
                        />
                    </div>
                ) : (
                    <div className="llb-editor-preview">
                        {isImageMode && placeholderImage ? (
                            <div className="llb-preview-image-mode">
                                <img src={placeholderImage} alt="" />
                                {showPlayIcon && (
                                    <div
                                        className="llb-preview-play"
                                        style={{ backgroundColor: playIconColor || 'rgba(0,0,0,0.7)' }}
                                    >
                                        <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z" fill="#fff"/></svg>
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
                                <button type="button">{triggerText || __('Cargar contenido', 'lazy-load-block')}</button>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
