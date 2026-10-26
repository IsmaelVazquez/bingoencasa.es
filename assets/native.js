// Integración nativa opcional con Capacitor.
//
// Todo es no-op en navegador web: el juego usa Web APIs (navigator.vibrate,
// Screen Wake Lock) que ya funcionan en el móvil. Cuando la app corre dentro de
// la WebView de Capacitor (iOS/Android), estos helpers mejoran la experiencia
// con los plugins nativos (Haptics, KeepAwake, StatusBar) y el deep link del QR.

function isNative() {
    const cap = window.Capacitor;
    return !!(cap && typeof cap.isNativePlatform === 'function' && cap.isNativePlatform());
}

async function nativeHaptic(kind) {
    if (!isNative()) return false;
    try {
        const { Haptics, ImpactStyle, NotificationType } = await import('@capacitor/haptics');
        if (kind === 'win') {
            await Haptics.notification({ type: NotificationType.Success });
        } else {
            await Haptics.impact({ style: ImpactStyle.Light });
        }
        return true;
    } catch (e) {
        return false;
    }
}

// El "pantalla siempre encendida" se cubre con la Screen Wake Lock API
// (navigator.wakeLock), soportada en la WebView de Capacitor en iOS 16.4+ y
// Android. Se deja este hook por si se quiere añadir el plugin nativo más
// adelante para versiones antiguas.
async function nativeKeepAwake(_on) {
    return;
}

export async function initNative() {
    // Expuesto para el script inline de la página del jugador (Twig).
    window.bingoNative = { haptic: nativeHaptic, keepAwake: nativeKeepAwake, isNative };

    if (!isNative()) return;

    try {
        const { StatusBar, Style } = await import('@capacitor/status-bar');
        await StatusBar.setStyle({ style: Style.Dark });
        await StatusBar.setBackgroundColor({ color: '#0a0b0f' });
    } catch (e) { /* sin plugin StatusBar */ }

    try {
        const { App } = await import('@capacitor/app');
        // Deep link del QR (universal link o esquema personalizado) → unirse a la sala.
        App.addListener('appUrlOpen', ({ url }) => {
            try {
                const u = new URL(url);
                if (u.pathname.includes('/fiesta/unirse')) {
                    window.location.href = u.pathname + u.search;
                }
            } catch (e) { /* url no parseable */ }
        });
    } catch (e) { /* sin plugin App */ }
}
