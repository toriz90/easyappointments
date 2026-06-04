# Arquitectura — Easy Appointments (citas.honeywhale.com.mx)

> Servidor: Contabo VPS. Documenta la configuración "de dos directorios"
> de esta instalación para no perderse en futuros despliegues.

## Los dos directorios que colaboran

Esta instalación separa **código** de **infraestructura** en dos rutas distintas:

| Ruta | Qué es | Versionado |
|---|---|---|
| `/root/easyapp-custom/` | Repo de **código** PHP (este repo, GitHub `toriz90/easyappointments`). Es lo que se despliega. | Sí (git) |
| `/root/docker-compose-files/easy-appointments/` | **Infraestructura**: el `docker-compose.yml` que crea los contenedores + el `.env` con credenciales. | No (solo Contabo) |

El compose **monta** `/root/easyapp-custom` como bind-mount dentro de los
contenedores `nginx` (solo lectura) y `fpm` (lectura-escritura), en
`/var/www/html`. El código no vive dentro de la imagen: vive en el host
y se monta. Por eso el deploy es un `git pull` + purga de OPcache.

## Servicios (red interna `easyapp-internal`)

- **nginx** (`easy-appointments-nginx`): único que publica puerto -> `8015:80`, detrás de Cloudflare Tunnel.
- **fpm** (`easy-appointments-fpm`): la app PHP. Lee credenciales y SMTP del entorno.
- **easyapp-db** (`easy-appointments-db`): MariaDB 11.6. **Sin puerto al host** — solo red interna.
- **backup** (`easy-appointments-backup`): offen/docker-volume-backup. Diario 3:00 am, retención 14 días -> `/root/backups/easy-appointments`.

## Despliegue (actualizar código)

    cd /root/easyapp-custom
    git pull origin main
    docker exec easy-appointments-fpm kill -USR2 1   # purga OPcache
    # Cloudflare: Purge Everything (opcional, la API no se cachea)

## Credenciales

Viven **solo** en `/root/docker-compose-files/easy-appointments/.env`
(permisos 600, fuera de git). El compose las referencia por variable:
`MYSQL_ROOT_PASSWORD`, `DB_USERNAME`, `DB_PASSWORD`, `MAIL_SMTP_USER`,
`MAIL_SMTP_PASS`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.

Para cambiar una credencial: editar el `.env` y `docker compose up -d`
(solo recrea `fpm`/`db`, sin downtime de la BD).

## Notas / trampas conocidas

- El `docker-compose.yml` **dentro de** `/root/easyapp-custom/` está
  OBSOLETO (dice `mysql:8.0`, puerto `3306`, credenciales débiles) y
  **no gobierna nada**. Ignorar o borrar para evitar confusión.
- La API REST se sirve bajo `/index.php/api/v1/...` (no `/api/v1/...`).
- La salida de la API está saneada: `/settings` usa allowlist; providers
  y webhooks no exponen credenciales (ver `AUDITORIA_API_SEGURIDAD.md`).
