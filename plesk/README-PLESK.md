# Despliegue en Plesk

## Requisitos del Servidor

- PHP 8.5 con extensiones: intl, pdo_pgsql, zip, opcache, mbstring, xml
- PostgreSQL 18+
- Node.js 24+ (para compilar assets)
- Composer 2.x

## Paso 1: Configurar el Dominio en Plesk

1. Crear un nuevo dominio o subdominio en Plesk
2. Configurar PHP:
   - Version: 8.5
   - Modo: FPM application
3. Establecer el Document Root: `public`

## Paso 2: Subir el Codigo

```bash
# Opcion A: Git (recomendado)
cd /var/www/vhosts/tu-dominio.com/
git clone https://github.com/tu-usuario/bingoencasa.git httpdocs

# Opcion B: FTP/SFTP
# Sube todos los archivos a httpdocs/
```

## Paso 3: Configurar Variables de Entorno

Crear archivo `.env.local` en la raiz del proyecto:

```bash
cd /var/www/vhosts/tu-dominio.com/httpdocs
cp .env.plesk .env.local
nano .env.local
```

Editar con tus valores reales:

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=tu_clave_secreta_muy_larga_aqui_32_caracteres

DATABASE_URL="postgresql://usuario_bd:password_bd@127.0.0.1:5432/nombre_bd?serverVersion=18&charset=utf8"

# Si usas Cloudflare u otro CDN/proxy:
TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR

# Si es Plesk directo sin CDN:
# TRUSTED_PROXIES=127.0.0.1
```

## Paso 4: Instalar Dependencias

Conectar por SSH al servidor:

```bash
cd /var/www/vhosts/tu-dominio.com/httpdocs

# Instalar dependencias PHP
composer install --no-dev --optimize-autoloader

# Instalar dependencias Node.js y compilar assets
npm ci
npm run build

# Ejecutar migraciones de base de datos
php bin/console doctrine:migrations:migrate --no-interaction

# Limpiar cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

## Paso 5: Configurar Permisos

```bash
# Permisos para cache y logs
chmod -R 775 var/
chown -R usuario:psacln var/

# Permisos para public/build
chmod -R 755 public/build/
```

## Paso 6: Configurar Nginx en Plesk

En Plesk > Dominio > Apache & nginx Settings, agregar en "Additional nginx directives":

```nginx
location / {
    try_files $uri /index.php$is_args$args;
}

location ~ ^/index\.php(/|$) {
    fastcgi_pass unix:/run/php-fpm/www.sock;
    fastcgi_split_path_info ^(.+\.php)(/.*)$;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param DOCUMENT_ROOT $document_root;
    internal;
}

location ~ \.php$ {
    return 404;
}

client_max_body_size 64M;

gzip on;
gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript image/svg+xml;
```

## Paso 7: Crear Usuario Admin

```bash
php bin/console app:create-admin
```

## Actualizaciones Futuras

```bash
cd /var/www/vhosts/tu-dominio.com/httpdocs

# Actualizar codigo
git pull origin main

# Actualizar dependencias
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# Ejecutar migraciones
php bin/console doctrine:migrations:migrate --no-interaction

# Limpiar cache
php bin/console cache:clear --env=prod
```

## Optimizacion de Rendimiento (Modo Fiesta)

El Modo Fiesta usa polling HTTP para sincronizar jugadores en tiempo real. Para evitar saturar el servidor, sigue estas configuraciones:

### PHP-FPM Settings

En Plesk > Dominio > **PHP Settings** > "PHP-FPM settings":

```ini
; === CONFIGURACION PARA SERVIDOR 12 CPU / 32GB RAM ===

; Modo de proceso: dynamic ajusta workers segun demanda
pm = dynamic

; Maximo de procesos PHP simultaneos (12 CPUs x 4 = 48)
pm.max_children = 50

; Procesos iniciales al arrancar
pm.start_servers = 10

; Minimo de procesos en espera
pm.min_spare_servers = 5

; Maximo de procesos en espera
pm.max_spare_servers = 15

; Reiniciar proceso despues de X peticiones (evita memory leaks)
pm.max_requests = 500

; Timeout de peticiones (en segundos)
request_terminate_timeout = 60
```

### OPcache Settings

En Plesk > Dominio > **PHP Settings** > pestaña "Performance":

```ini
; === OPCACHE - OBLIGATORIO PARA PRODUCCION ===

; Activar OPcache
opcache.enable = 1

; Memoria para cache de scripts (MB)
opcache.memory_consumption = 128

; Maximo de archivos en cache
opcache.max_accelerated_files = 10000

; Validar archivos cada X segundos (0 = nunca en produccion)
opcache.revalidate_freq = 0

; Guardar comentarios (necesario para Symfony)
opcache.save_comments = 1

; Interned strings buffer
opcache.interned_strings_buffer = 16
```

### APCu (Cache de Rate Limiting - Opcional)

Si quieres rate limiting real para evitar abusos:

1. En Plesk > **Tools & Settings** > **PHP Settings** (nivel servidor)
2. Buscar e instalar la extension **APCu**
3. Reiniciar PHP-FPM

Configuracion de APCu en php.ini:
```ini
apc.enabled = 1
apc.shm_size = 32M
apc.ttl = 7200
apc.enable_cli = 0
```

### Apache MPM (Avanzado)

Si usas Apache en lugar de Nginx, en Plesk > **Tools & Settings** > **Apache Web Server Settings**:

```apache
; Cambiar de prefork a event MPM (mas eficiente)
; Esto requiere acceso root al servidor

<IfModule mpm_event_module>
    StartServers             3
    MinSpareThreads         75
    MaxSpareThreads        250
    ThreadsPerChild         25
    MaxRequestWorkers      400
    MaxConnectionsPerChild   0
</IfModule>
```

### Limites de Uso Estimados

Con la configuracion recomendada:

| Metrica | Limite Estimado |
|---------|-----------------|
| Jugadores simultaneos | 50-100 |
| Salas activas | 5-10 |
| Peticiones/segundo | 100-200 |
| Memoria RAM usada | 1-2 GB |

Para escalar mas alla de estos limites:
1. Aumentar `pm.max_children`
2. Instalar Redis para cache de sesiones
3. Considerar WebSockets (Mercure) para eliminar polling

### PostgreSQL

En Plesk crea la base en **Databases** con PostgreSQL 18 y un usuario con permisos sobre esa base. `pdo_pgsql` tiene que estar activo en el PHP del dominio. El volumen de Docker de desarrollo no se migra solo: si ya habia datos en MariaDB, hay que exportarlos aparte (por ejemplo con pgloader) antes de tirar el volumen antiguo.

### Verificar Configuracion

Despues de aplicar cambios:

```bash
# Verificar PHP-FPM esta corriendo
systemctl status php-fpm

# Ver procesos PHP activos
ps aux | grep php-fpm | wc -l

# Ver configuracion OPcache
php -i | grep opcache

# Test de carga basico (instalar ab si no existe)
ab -n 100 -c 10 https://tu-dominio.com/api/salas/TEST123/lobby
```

---

## Solucion de Problemas

### Error 500
- Verificar logs: `tail -f var/log/prod.log`
- Verificar permisos de var/

### Assets no cargan
- Verificar que npm run build se ejecuto correctamente
- Verificar que public/build/ existe y tiene archivos

### Error de base de datos
- Verificar DATABASE_URL en .env.local
- Probar conexion: `php bin/console doctrine:database:create --if-not-exists`

### Servidor lento / 502 Bad Gateway
- Verificar `pm.max_children` en PHP-FPM
- Revisar logs: `tail -f /var/log/php-fpm/error.log`
- Aumentar `request_terminate_timeout` si hay timeouts

### Muchos errores 304 en logs
- Esto es NORMAL y significa que el cache funciona correctamente
- Los clientes reciben "Not Modified" cuando no hay cambios

### Rate limiting no funciona
- Verificar que APCu esta instalado: `php -m | grep apcu`
- En Symfony, configurar `config/packages/rate_limiter.yaml`
