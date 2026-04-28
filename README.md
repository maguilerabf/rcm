# RCM

App interna en **Symfony 7** + **Vue 3** + **PostgreSQL 16** para procesar reportes
operacionales de salud. Pensada para volúmenes medianos (~75k filas) con import
acelerado en Python.

**Módulos**:

1. **Identificación Sectores** — sube Reporte Telesalud (xlsx) + Reporte Inscritos
   (xlsx 75k+ filas), cruza por RUN+DV, vista paginada de coincidencias con sector,
   descarga xlsx (con celda de sector coloreada), envío por correo con adjunto.
2. **Duplicados Inscritos** — detecta personas con distinto RUN+DV pero mismo
   nombre/apellidos/fecha (cuando RUN provisorio pasa a permanente). Coincidencias
   completas (4/4) y parciales (apellidos+fecha + token compartido en nombres).
   Normalización: unaccent + Y↔I.

**Stack**:

- **PHP 8.3** + **Symfony 7.4** + Doctrine ORM
- **PostgreSQL 16** con extensión `unaccent`
- **Vue 3** + Vite 5 + Pinia + Tailwind 3 + Headless UI + Heroicons
- **openspout** (xlsx PHP) + **openpyxl** (xlsx Python, 15× más rápido en 75k filas)
- **Symfony Messenger** (cola Doctrine, sin Redis)
- **Symfony Mailer** + Gmail SMTP (App Password)

---

## Modos de despliegue

| | Local | Producción |
|---|---|---|
| **Backend** | Docker Compose | Nativo Ubuntu 22.04 |
| **Reverse proxy** | nginx (container) | Caddy (auto SSL Let's Encrypt) |
| **Worker** | container `worker` | systemd service `rcm-worker` |
| **DB** | Postgres en container | Postgres 16 nativo |

Ver [DEPLOY.md](DEPLOY.md) para el deploy a producción.

---

## Setup local (Docker)

Requisitos: Docker Desktop con WSL2 (Windows) o Docker nativo (Linux/macOS).

### 1. Clonar y configurar

```bash
git clone https://github.com/maguilera89/rcm.git
cd rcm
cp .env.docker.example .env
```

Edita `.env`:
- Genera `APP_SECRET`: `openssl rand -hex 32`
- Define `POSTGRES_PASSWORD`
- Configura `MAILER_DSN` con tu Gmail + App Password (ver más abajo)

> Si estás en Windows con WSL, **trabaja desde el filesystem de WSL** (`~/proyectos/rcm`),
> no desde `C:\` — los bind mounts NTFS son ~5× más lentos y los uploads de 27MB demoran.

### 2. Levantar

```bash
docker compose up -d --build
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:user:create tu@correo.cl 'PasswordSegura' Tu Apellido
```

### 3. Acceso

- App: <http://localhost:8080>
- DB: `localhost:5432` (user/pass del `.env`)

---

## Configurar Gmail App Password

1. **2FA activado**: <https://myaccount.google.com/security>
2. **App Password**: <https://myaccount.google.com/apppasswords>
3. En `.env`:
   ```
   # Importante: el @ del email se URL-encodea como %40
   MAILER_DSN=smtp://tucuenta%40gmail.com:abcdefghijklmnop@smtp.gmail.com:587
   MAILER_FROM="RCM <tucuenta@gmail.com>"
   ```
4. Reiniciar (no `restart` — usar `up` para que tome la env nueva):
   ```bash
   docker compose up -d --force-recreate php worker
   docker compose restart nginx
   ```

---

## Comandos útiles

```bash
# Logs
docker compose logs -f php
docker compose logs -f worker

# Bash dentro del container
docker compose exec php sh

# Crear usuario
docker compose exec php php bin/console app:user:create email@x.cl 'pass' Nombre Apellido

# Cleanup manual de almacenamiento (chequea umbral 10GB → limpia hasta 5GB)
docker compose exec php php bin/console app:cleanup:storage --dry-run   # solo preview
docker compose exec php php bin/console app:cleanup:storage             # ejecutar

# Migraciones nuevas
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

---

## Performance import (75k filas)

| Versión | Tiempo |
|---|---|
| openspout xlsx (PHP puro) | ~7.5 min |
| Python `openpyxl` → CSV → Postgres `COPY` | **28s** (15× más rápido) |

El Python script [`app/scripts/xlsx_to_csv.py`](app/scripts/xlsx_to_csv.py) auto-detecta
la fila del header (los xlsx oficiales traen ~17 filas de metadata previas) y escribe
CSV UTF-8. Luego openspout PHP lo lee y `pgsqlCopyFromArray` mete los lotes a la BD.
Si Python no está disponible, fallback automático al parser PHP.

---

## Auto-cleanup de almacenamiento

Cuando el total `(tablas BD + uploads en disco)` supera **10 GB**, se borran los
import_jobs **más antiguos** (no-activos, no en proceso) hasta volver a **5 GB**.

Disparado:
- Después de cada import exitoso (chequeo barato si no hace falta)
- Vía cron diario en producción (safety net)
- Manualmente: `php bin/console app:cleanup:storage`

Configurable en `.env`:
```
STORAGE_THRESHOLD_BYTES=10737418240
STORAGE_TARGET_BYTES=5368709120
```

---

## Estructura

```
rcm/
├── DEPLOY.md                ← guía deploy nativo Ubuntu 22.04
├── README.md
├── docker-compose.yml       ← dev por defecto
├── docker-compose.prod.yml  ← override prod (opcional, si se usara Docker)
├── docker/                  ← Dockerfile + nginx + php config
├── data/DATOS.md            ← spec de datos (módulo Identificación Sectores)
└── app/
    ├── composer.json
    ├── package.json
    ├── vite.config.js
    ├── tailwind.config.js
    ├── scripts/xlsx_to_csv.py   ← Python parser
    ├── src/
    │   ├── Controller/Api/
    │   ├── Entity/
    │   ├── Service/             ← importers, exporter, mailer, cleanup
    │   ├── Message/             ← jobs async
    │   └── MessageHandler/
    ├── assets/                  ← Vue 3 SPA
    │   ├── App.vue, app.js
    │   ├── views/, components/, router/, stores/, utils/
    ├── templates/base.html.twig ← shell HTML
    ├── config/                  ← Symfony config
    └── public/                  ← document root + build/ de Vite
```

---

## Sensibilidad de datos

Los datos contienen información personal protegida por **Ley 19.628** y **Ley 20.584**:

- `.gitignore` ignora `*.csv`, `*.xlsx`, `var/uploads/`, todos los `.env*`
- Credenciales SMTP/DB sólo en `.env` (nunca en código ni en commits)
- HTTPS obligatorio en producción (Caddy auto-renueva certs Let's Encrypt)
- Política de retención: cleanup automático mantiene almacenamiento ≤ 10 GB
