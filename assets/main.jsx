import React from 'react';
import { createRoot } from 'react-dom/client';
import './styles/app.css';
import App from './react/App.jsx';
import { initNative } from './native.js';

// Activa los plugins nativos (StatusBar, deep link, Haptics) si la app del
// anfitrión se ejecuta dentro de la WebView de Capacitor. No-op en navegador.
initNative();

console.log("🚀 BingoEnCasa: Iniciando aplicación React...");

const rootElement = document.getElementById('root');

if (rootElement) {
    console.log("✅ Nodo #root encontrado, montando App...");
    createRoot(rootElement).render(<App />);
} else {
    console.error("❌ No se encontró el nodo #root");
}
