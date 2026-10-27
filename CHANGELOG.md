# Changelog

Todos los cambios notables del proyecto **BingoEnCasa** se documentan en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y sigue el esquema de versionado `vX.YY.YY`.

Las entradas están escritas para el cliente: en lenguaje natural y sin detalles técnicos.

---

## [v1.02.00] - 2026-10-27

### Añadido

- Si el anfitrión recarga la página o se le cierra el navegador, vuelve a su sala. Si ya había empezado la partida, la recupera en pausa con todas las bolas cantadas, y sigue al pulsar "Continuar".
- Para cantar ¡LÍNEA! o ¡BINGO! hay que tocar dos veces. El primer toque pide confirmación durante 3 segundos, así un toque sin querer ya no detiene la partida.
- El jugador ve un aviso cuando pierde la conexión y otro cuando su canto no llega al anfitrión.
- Cuando el anfitrión cierra la sala, los jugadores ven que se ha cerrado en lugar de quedarse esperando.
- La pantalla del anfitrión ya no se apaga mientras canta bolas.

### Corregido

- Los botones de cantar ya no se pierden un toque cuando llega una bola nueva justo al pulsarlos.
- Un jugador cuyo canto se rechazaba se quedaba para siempre en "El anfitrión está revisando tu cartón". Ahora vuelve a la partida.
- El sorteo clásico ya no se queda parado en algunos móviles cuando la voz no termina de hablar.
- Un fallo puntual del servidor ya no congela la pantalla del jugador. Con la pestaña oculta, el móvil deja de consultar la partida y ahorra batería.
- Salir de la sala de espera o de una partida devuelve la sala para el siguiente anfitrión.

## [v1.01.10] - 2026-10-26

### Corregido

- En el Modo Fiesta, dos anfitriones que abren sala a la vez ya no pueden recibir la misma sala, y dos partidas a la vez ya no pueden repartir los mismos cartones.
- Pulsar dos veces "sacar bola" ya no puede sacar la misma bola dos veces.
- Si varios jugadores cantan a la vez, solo queda en revisión el primer canto; los demás reciben el aviso de esperar.
- Dos invitados que entran a la vez ya no pueden coger el mismo apodo ni pasarse del máximo de jugadores de la sala.
- Una partida terminada ya no se puede volver a arrancar, y no se puede cantar antes de que empiece. Rechazar un canto ya no arranca una partida que estaba parada.
- Las bolas ya no salen desordenadas en el historial cuando se sacan dos en el mismo segundo.
- Al liberar una sala, desde el panel o al terminar, se borran sus jugadores y su última partida. El siguiente anfitrión la recibe vacía y los jugadores antiguos ya no pueden verla.
- Liberar una sala ya no devuelve al pool cartones que está usando otra sala.
- La limpieza de salas inactivas ya no libera salas con una partida en marcha: cuenta la última actividad y no la hora en que se abrió la sala.

### Mejorado

- El anfitrión y los jugadores ya no se quedan fuera de la partida cuando se publica una versión nueva o se reinicia el servidor. Además, la sesión aguanta 12 horas sin actividad, antes solo 24 minutos.
- Las salas abandonadas se pueden liberar solas con una tarea programada.

## [v1.01.09] - 2026-10-26

### Eliminado

- Se quitan la versión antigua de la web y la documentación y los scripts del alojamiento anterior (Plesk), que ya no se usaban.

## [v1.01.08] - 2026-10-26

### Corregido

- Vuelven el logo de BingoEnCasa y el de Thrax, que faltaban en la versión publicada del código.

## [v1.01.07] - 2026-10-26

### Seguridad

- En el Modo Fiesta solo el anfitrión que creó la sala puede sacar bolas, aprobar o rechazar cantos, terminar la partida y liberar la sala. Antes bastaba con conocer el código de la sala.
- Cada jugador solo ve sus cartones y solo puede cantar con ellos. Nadie puede entrar con el apodo de otro jugador.
- Se limitan los intentos de PIN fallidos y las salas que se pueden abrir seguidas desde una misma conexión.
- Los apodos se limpian y se muestran como texto, para que nadie pueda meter código en la pantalla de los demás.
- Las acciones del panel de administración van protegidas contra peticiones falsificadas desde otras webs.

### Correcciones

- El acceso al panel de administración vuelve a funcionar con la base de datos PostgreSQL.
- Liberar una sala después de una partida con cantos ya no da error.

### Mantenimiento

- Se retiran ficheros de herramientas de desarrollo y código antiguo que ya no se usaba.

## [v1.01.06] - 2026-10-26

### Mantenimiento

- Se actualizan las librerías de la aplicación a sus últimas versiones compatibles para cerrar fallos de seguridad conocidos. Sin cambios en la aplicación.

## [v1.01.05] - 2026-10-07

### Mantenimiento

- La imagen y la integración continua pasan a Node 26. Sin cambios en la aplicación.

## [v1.01.04] - 2026-10-05

### Mantenimiento

- El entorno de pruebas ya no publica puertos en todas las interfaces del servidor: la web y los paneles solo por la VPN (10.10.10.1) y la base de datos solo en local. Sin cambios en la aplicación.

## [v1.01.03] - 2026-06-17

### Mejoras
- Mejoras internas y de mantenimiento para agilizar las actualizaciones.

---

## [v1.01.02] - 2026-06-17

### Mejoras
- Mejoras internas y de mantenimiento para el despliegue en producción.

---

## [v1.01.01] - 2026-06-17

### Mejoras
- Mejoras internas y correcciones técnicas para dejar el despliegue a punto.

---

## [v1.01.00] - 2026-06-17

> El Modo Fiesta ya funciona de principio a fin, con un aspecto nuevo y pensado para el móvil.

### Novedades
- El juego online del Modo Fiesta funciona completo: los invitados cantan línea o bingo desde su móvil, el anfitrión lo confirma en su pantalla y la partida termina anunciando al ganador.
- Durante la partida la pantalla del jugador no se apaga y el móvil vibra cuando sale uno de tus números y al ganar.
- La aplicación está preparada para publicarse como app de móvil (iOS y Android).

### Mejoras
- Nuevo aspecto visual oscuro y uniforme en toda la aplicación.
- Corregidos fallos que impedían que las jugadas de línea y bingo se validaran y terminaran correctamente.

---

## [v1.00.01] - 2026-06-17

> Punto de partida del control de versiones del proyecto.

### Novedades
- Se empieza a llevar un registro de versiones y novedades de la aplicación.
