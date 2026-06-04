# Deploy — Easy Appointments (Honey Whale)

Proceso de despliegue real y actual en producción.
URL: https://citas.honeywhale.com.mx

> Reemplaza al antiguo SYNC_INSTRUCTIONS.md (que describía NAS + Docker Swarm).
> La producción actual corre en Contabo con docker compose y bind mount.

## Arquitectura

El despliegue está separado en dos directorios en Contabo:

- Código: /root/easyapp-custom (este repo git, montado en los contenedores)
- Infraestructura: /root/docker-compose-files/easy-appointments (compose, nginx/, php/ — sin git)

El código se monta como bind mount:
- nginx: /root/easyapp-custom:/var/www/html:ro
- fpm:   /root/easyapp-custom:/var/www/html

Por eso un git pull en /root/easyapp-custom se refleja de inmediato en los
contenedores, sin reconstruir imágenes.

### Servicios
- nginx (nginx:alpine) — expone 8015:80, lo alcanza Cloudflare Tunnel
- fpm (alextselegidis/easyappointments:latest) — código real vía bind mount
- easyapp-db (mariadb:11.6) — datos en volumen db-data
- backup (offen/docker-volume-backup) — diario 03:00, retención 14 días

## Proceso de deploy (actualizar código)

    cd /root/easyapp-custom
    git pull origin main
    docker exec easy-appointments-fpm kill -USR2 1
    # Purgar cache en panel Cloudflare: Caching > Purge Everything

No requiere docker compose up ni rebuild. El kill -USR2 limpia el OPcache.

## Cambios de infraestructura (compose, nginx, php)

    cd /root/docker-compose-files/easy-appointments
    docker compose up -d

## Backups
- Automático diario 03:00, retención 14 días, en /root/backups/easy-appointments
- Respalda db-data y /root/easyapp-custom
- Manual: docker exec easy-appointments-backup backup

## PENDIENTE de seguridad
El docker-compose.yml de infraestructura tiene credenciales en texto plano
(contraseñas MariaDB y app-password SMTP de Gmail). Conviene moverlas a un
.env no versionado y rotarlas.

## Por qué no hay GitHub Actions
El deploy es un simple git pull sobre bind mount, sin build ni migraciones.
Automatizarlo (patrón whalehub) daría beneficio marginal.
