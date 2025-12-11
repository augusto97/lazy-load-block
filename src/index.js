/**
 * Lazy Load Block
 * Bloque de Gutenberg que carga contenido solo al hacer clic
 */

import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import save from './save';
import metadata from './block.json';
import './editor.scss';

/**
 * Icono personalizado del bloque
 */
const blockIcon = (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor"/>
    </svg>
);

/**
 * Registrar el bloque
 */
registerBlockType(metadata.name, {
    icon: blockIcon,
    edit: Edit,
    save,
});
