# BingoEnCasa

Aplicacion web para jugar Bingo online con dos modos de juego: Modo Bombo (Clasico) y Modo Fiesta (Online).

## Stack Tecnologico

- **Backend**: Symfony 8.1 (PHP 8.5)
- **Frontend**: React 19, Vite, Tailwind CSS 4
- **Base de Datos**: PostgreSQL 18
- **Assets**: Vite con vite-plugin-symfony

## Estructura del Proyecto

```
bingoencasa/
├── assets/              # Assets frontend (React, CSS, Stimulus)
│   ├── controllers/     # Controladores Stimulus
│   ├── react/           # Componentes React
│   └── styles/          # Estilos CSS/Tailwind
├── bin/                 # Consola Symfony
├── config/              # Configuracion Symfony
├── docker/              # Configuracion Docker
│   ├── nginx/           # Config Nginx
│   └── php/             # Dockerfile PHP
├── migrations/          # Migraciones Doctrine
├── public/              # Web root
├── src/                 # Codigo PHP
│   ├── Command/         # Comandos de consola
│   ├── Controller/      # Controladores
│   ├── Entity/          # Entidades Doctrine
│   ├── Repository/      # Repositorios
│   └── Service/         # Servicios
└── templates/           # Plantillas Twig
```

## Desarrollo Local con Docker

### Requisitos
- Docker y Docker Compose

### Iniciar el entorno

```bash
# Copiar variables de entorno
cp .env.example .env

# Editar .env con tus valores
nano .env

# Iniciar contenedores
docker compose up -d

# Instalar dependencias PHP
docker compose exec php composer install

# Instalar dependencias Node.js
docker compose exec vite npm install

# Ejecutar migraciones
docker compose exec php php bin/console doctrine:migrations:migrate

# Crear usuario admin
docker compose exec php php bin/console app:create-admin
```

### Acceso

- **Aplicacion**: http://localhost:8163
- **Vite Dev Server**: http://localhost:5175 (HMR)
- **PostgreSQL**: localhost:54320

### Comandos utiles

```bash
# Ver logs
docker compose logs -f

# Acceder a consola PHP
docker compose exec php bash

# Limpiar cache
docker compose exec php php bin/console cache:clear

# Detener contenedores
docker compose down

# Detener y eliminar volumenes
docker compose down -v
```

## Desarrollo Local sin Docker

### Requisitos
- PHP 8.5 con extensiones: intl, pdo_pgsql, zip, mbstring
- Composer 2.x
- Node.js 24+
- PostgreSQL 18+

### Instalacion

```bash
# Instalar dependencias
composer install
npm install

# Configurar variables de entorno
cp .env.example .env
# Editar .env con tu DATABASE_URL

# Ejecutar migraciones
php bin/console doctrine:migrations:migrate

# Compilar assets (desarrollo)
npm run dev

# En otra terminal, iniciar servidor PHP
php -S localhost:8000 -t public
```

## Despliegue en Produccion

### Docker Compose (Produccion)

```bash
# Usar docker-compose.prod.yml
docker compose -f docker-compose.prod.yml up -d
```

### Pasos generales de produccion (manual)

```bash
# Instalar dependencias (sin dev)
composer install --no-dev --optimize-autoloader
npm ci

# Compilar assets
npm run build

# Ejecutar migraciones
php bin/console doctrine:migrations:migrate --no-interaction

# Sembrar el pool si está vacío
php bin/console app:init-pool

# Limpiar y calentar cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

## Entidades Principales

| Entidad | Descripcion |
|---------|-------------|
| User | Usuarios admin del sistema |
| Room | Salas de juego |
| Game | Partidas de bingo |
| Player | Jugadores en una partida |
| Card | Cartones de bingo |
| Draw | Numeros sorteados |
| Claim | Reclamaciones (Linea, Bingo) |

## Comandos de Consola

```bash
# Crear usuario administrador
php bin/console app:create-admin

# Limpiar cache
php bin/console cache:clear

# Ejecutar migraciones
php bin/console doctrine:migrations:migrate

# Generar nueva migracion
php bin/console make:migration

# Liberar salas sin actividad (por defecto, 12 horas)
php bin/console app:rooms:release-stale --hours=12
```

En producción, `app:rooms:release-stale` va como tarea programada de Coolify
sobre el servicio `php`, cada hora (`0 * * * *`). Sin ella, una sala que el
anfitrión abandona se queda ocupada hasta que alguien la libera desde el panel.

Las sesiones se guardan en Postgres (tabla `sessions`), así que un despliegue o
un reinicio no echa al anfitrión ni a los jugadores de una partida en curso.

## Tests

```bash
# Una vez: base de datos de test
docker compose exec -e APP_ENV=test php php bin/console doctrine:database:create --if-not-exists
docker compose exec -e APP_ENV=test php php bin/console doctrine:migrations:migrate -n

# Lanzar los tests
docker compose exec -e APP_ENV=test -e XDEBUG_MODE=off php php bin/phpunit
```

`tests/Concurrency` lanza procesos PHP en paralelo contra Postgres para
comprobar que dos peticiones a la vez no reparten la misma sala, los mismos
cartones o la misma bola, ni dejan dos reclamaciones pendientes.

## Variables de Entorno

| Variable | Descripcion | Ejemplo |
|----------|-------------|---------|
| APP_ENV | Entorno (dev/prod) | `prod` |
| APP_DEBUG | Debug activo | `0` |
| APP_SECRET | Clave secreta Symfony | `abc123...` |
| DATABASE_URL | Conexion base de datos | `postgresql://user:pass@host:5432/db?serverVersion=18&charset=utf8` |
| TRUSTED_PROXIES | IPs de proxies confiables | `127.0.0.1` |

## Utilidades Incluidas

- Entidades Doctrine con ULID: `Room`, `Player`, `Game`, `Draw`, `Card`, `Claim` y `User`
- Generador de cartones en PHP (`src/Service/BingoCardGenerator.php`)
- Impresion de cartones PDF con diseño profesional (logo B, numero unico, logo Thrax.es, marca BINGOENCASA.ES)
- Comando `app:create-admin` para provisionar usuarios del panel
- Controlador Stimulus `utility` para copiados rapidos y confirmaciones

## App movil (Capacitor)

El Modo Fiesta (la vista del jugador en el movil) esta preparado para empaquetarse
como app nativa con **Capacitor**.

### Ya integrado en el codigo

- **Vibracion** al cantarse un numero de tu carton, al reclamar y al ganar
  (`navigator.vibrate` en navegador/Android; Capacitor Haptics en iOS).
- **Pantalla siempre encendida** durante la partida (Screen Wake Lock API).
- **Areas seguras** (notch) en la barra de acciones del jugador.
- **Barra de estado** oscura y **deep link** del QR (`/fiesta/unirse`) cuando se
  ejecuta dentro de Capacitor (`assets/native.js`).

Estos helpers son no-op en navegador web y se activan solos dentro de la WebView
nativa, por lo que el mismo build sirve para web y app.

### Generar las plataformas nativas

Requiere una maquina con Android Studio (Android) y/o Xcode (iOS). No se puede
hacer dentro de este entorno Docker/WSL.

```bash
# Dependencias ya declaradas en package.json (@capacitor/core, cli, app,
# haptics, status-bar). Instalalas y añade las plataformas:
npm install
npx cap add android
npx cap add ios

# Compila los assets web y sincroniza con los proyectos nativos
npm run build
npx cap sync
```

Como la vista del jugador la sirve Symfony (Twig), en el dispositivo la app debe
apuntar al sitio publicado. Configura el destino con la variable de entorno del
orquestador (no se hardcodea) añadiendo `server.url` a `capacitor.config.json`
en el build de produccion, por ejemplo `https://bingoencasa.es`.

### Builds firmados

- **Android**: abre `android/` en Android Studio, genera un keystore y un App
  Bundle firmado (Build > Generate Signed Bundle).
- **iOS**: abre `ios/App/App.xcworkspace` en Xcode, configura el equipo de firma
  y archiva para subir a App Store Connect.

## Licencia

[GNU AGPL v3.0 o posterior](LICENSE). Si modificas el código y lo ofreces como
servicio en red, tienes que publicar tus cambios bajo la misma licencia.
