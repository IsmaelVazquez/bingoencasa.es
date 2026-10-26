// Entrada de estilos del Modo Fiesta (páginas Twig del jugador).
// Comparte el sistema visual oscuro con la app React vía el build de Vite,
// en lugar de cargar Tailwind desde el CDN.
import './styles/fiesta.css';
import { initNative } from './native.js';

initNative();
