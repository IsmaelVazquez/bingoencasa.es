# Changelog

Todos los cambios notables del proyecto **BingoEnCasa** se documentan en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y sigue el esquema de versionado `vX.YY.YY`.

Las entradas están escritas para el cliente: en lenguaje natural y sin detalles técnicos.

---

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
