# Datos de referencia — Módulo "Identificación Sectores"

Este documento describe los archivos de datos del módulo y la lógica de cruce.
Léelo antes de diseñar el modelo de datos o el flujo de importación.

---

## Archivos provistos

| Archivo | Filas | Columnas | Tamaño | Uso |
|---|---:|---:|---:|---|
| `reporte_solicitudes.csv` | 1.909 | 48 | 750 KB | Equivalente al **"Reporte telesalud"** que sube el usuario |
| `padron_inscritos.csv` | 75.206 | 75 | 43 MB | Equivalente al **"Reporte Inscritos"** que sube el usuario |
| `reporte_solicitudes_sample.csv` | 100 | 48 | – | Inspección rápida de esquema |
| `padron_inscritos_sample.csv` | 200 | 75 | – | Inspección rápida de esquema |

Los originales son `.xlsx` — la app real recibe xlsx por upload y los parsea. Estos CSV son
para **seed de la BD** y **fixtures de test**. No commitear los completos al repo (datos personales).

Encoding: UTF-8. Separador: coma. Todos los valores como string (RUNs y teléfonos preservan dígitos sin `.0`).

---

## Lógica de cruce — lo crítico

> Cruzar `reporte_solicitudes."Nº identificador"` contra `padron_inscritos."RUN" + "DV"` (concatenados).

El `Nº identificador` del reporte de telesalud **ya viene como RUN+DV concatenado sin separador**.
En el padrón vienen separados en dos columnas. Hay que construir la clave de cruce en el padrón.

### Reglas de normalización (aplicar a ambos lados antes de comparar)

1. En el padrón: `key = trim(RUN) + trim(DV)`
2. `upper()` ambos lados (el dígito verificador puede ser `K`)
3. `trim()` espacios
4. En telesalud: **filtrar previamente** `Tipo identificador = 'run'`. Los `'plac'` (pasaporte) no se cruzan.

### Ejemplos verificados con los datos reales

| Telesalud `Nº identificador` | Padrón `RUN` | Padrón `DV` | Cruza |
|---|---|---|---|
| `266578040` | `26657804` | `0` | sí |
| `26038919K` | `26038919` | `K` | sí |
| `415294204` | `41529420` | `4` | sí |
| `198480339` (tipo=plac) | – | – | no, pasaporte |

### Resultado esperado del cruce (validado)

Sobre los archivos provistos:
- 1.909 solicitudes totales
- 1.884 con `Tipo identificador = run`
- **1.840 cruzan** con el padrón → estas van a la vista resumen
- 44 RUN no aparecen en el padrón
- 25 pasaportes no aplicables al cruce

La vista resumen muestra: **todas las columnas de telesalud + columna `SECTOR` del padrón**.
Sectores reales en los datos: `Sector Azul`, `Sector Rojo`, `Sector Verde`.

---

## Esquemas

### `reporte_solicitudes` (telesalud) — 48 columnas

Columnas a persistir y mostrar:

| Columna CSV | Tipo | Notas |
|---|---|---|
| `ID` | int | ID externo, único por solicitud |
| `Cesfam` | str | nombre del centro |
| `Prioridad` | int (1–4) | |
| `Código seguimiento` | str | |
| `Información adicional` | text | comentario libre del paciente |
| `Fecha solicitud` | datetime | formato `YYYY-MM-DD HH:MM:SS` |
| `Género` | enum | `FEMENINO` / `MASCULINO` |
| `Nombre`, `Apellido paterno`, `Apellido materno`, `Nombre social` | str | |
| `Tipo identificador` | enum | `run` / `plac` / otros |
| **`Nº identificador`** | str | **clave de cruce** |
| `Edad` | str | viene como "22 años" → parsear si se requiere ordenar |
| `Email`, `Telefono`, `Dirección` | str | |
| `Tipo prestador` | str | Medicina, Dental, Matrona, Nutrición, Psicología, Enfermería |
| `Motivo consulta`, `Especificidad` | str | |
| `Estado` | enum | `Pendiente` / cerrado / etc. |
| `Fecha cierre`, `Tipo cierre`, `Cerrado por`, `Cargo`, `Profesión`, `Fecha agenda`, `Nota cierre` | mixed | |
| `Derivado a 1..3`, `Derivado por 1..3`, `Cargo 1..3`, `Profesión 1..3`, `Fecha derivación 1..3`, `Nota derivación 1..3` | mixed | hasta 3 niveles de derivación |
| `Contactado` | enum | `Sí` / `No` |
| `Información adicional` (segunda) | text | duplicada del original — puede ignorarse o renombrarse |

### `padron_inscritos` — 75 columnas

Columnas relevantes (las únicas que necesita el módulo):

| Columna CSV | Tipo | Notas |
|---|---|---|
| `ESTABLECIMIENTO` | str | filtro útil |
| **`RUN`** | str | sin DV |
| **`DV`** | str (1 char) | dígito verificador, puede ser `K` |
| `NOMBRES`, `APELLIDO PATERNO`, `APELLIDO MATERNO` | str | |
| `SEXO` | enum | |
| `FECHA DE NACIMIENTO` | str | formato `DD/MM/YYYY` |
| `EDAD AÑOS`, `EDAD MESES`, `EDAD DIAS` | int | |
| **`SECTOR`** | str | **único dato a agregar al cruce** |
| `ESTADO` | enum | `ACTIVO` / `PASIVO` (los pasivos suelen ser registros antiguos; igual cruzan) |
| `SITUACION` | enum | `INSCRITO` / etc. |

Las otras 60+ columnas (alertas administrativas, dirección residencial, dirección familiar,
teléfonos varios, previsión, etc.) **no son necesarias para este módulo**. Pueden persistirse
"crudas" si quieres futuro-aprovecharlas, o ignorarse.

> **Recomendación pragmática:** crea una tabla `padron_inscritos` con solo las ~12 columnas
> relevantes. Importar 75 columnas × 75k filas × 2 imports/día desperdicia espacio y tiempo
> de I/O. Si más adelante alguien las necesita, se agregan.

---

## Recomendaciones de rendimiento (importantes con 75k filas)

### Lectura de xlsx

PhpSpreadsheet **carga todo el workbook en memoria** — para 75k filas necesita ~500 MB+ de RAM
y se demora ~1 minuto. **Usa `openspout/openspout`** (sucesor de `box/spout`):

- Streaming row-by-row, memoria constante
- 5–10× más rápido que PhpSpreadsheet en lectura
- Composer: `composer require openspout/openspout`

### Inserción a la BD

No hagas `persist()` + `flush()` por fila — son 75k roundtrips. Opciones (de mejor a peor):

1. **`COPY` de Postgres** (mejor): escribe el CSV en un tmpfile y `COPY tabla FROM '...' CSV HEADER`. Importa 75k filas en <2s.
2. **Batch insert con DBAL**: `INSERT INTO ... VALUES (...), (...), ...` en lotes de 500–1000. Limpia el EntityManager cada batch (`$em->clear()`).
3. Doctrine ORM normal: ❌ demasiado lento, no usar.

### Cruce

Hazlo en **SQL**, no en PHP:

```sql
-- Asumiendo columna normalizada padron.run_dv (índice único) y solicitudes.identificador_norm
SELECT s.*, p.sector
FROM solicitudes s
LEFT JOIN padron p ON p.run_dv = s.identificador_norm
WHERE s.tipo_identificador = 'run';
```

Crear **índice único** en `padron.run_dv` antes de la importación. Si lo creas después,
la primera consulta tardará pero las siguientes serán instantáneas.

### Procesamiento async

Una importación de 75k filas se demora unos segundos incluso optimizada. Para no bloquear el
HTTP request, usa **Symfony Messenger** con un transporte (Doctrine es suficiente para empezar):

- POST `/import` → guarda el archivo, encola un mensaje `ImportPadronMessage`, responde `202 Accepted`
- Worker consume y actualiza un `ImportJob` con `status` y `progress`
- Front hace polling o usa Mercure/SSE para mostrar progreso en tiempo real

### Vista resumen

Máximo 1909 filas. **Paginación cliente-side** está bien (TanStack Table, AG Grid Community,
o tabla nativa con virtualización). Si quieres servidor, 50–100 filas/página.

### Generación del xlsx de descarga

`openspout` también genera xlsx en streaming. **No** uses PhpSpreadsheet para escribir si son
muchas filas.

### Envío por correo

Symfony Mailer + Gmail SMTP. **Importante para Gmail personal:**
- Necesita "App Password" (la cuenta debe tener 2FA activado)
- DSN: `smtp://USUARIO@gmail.com:APP_PASSWORD@smtp.gmail.com:587`
- Para >100 envíos/día considera SendGrid/Mailgun/Resend (Gmail tiene cuotas)
- Adjuntar el xlsx generado en streaming a un archivo temporal y luego `Email::attachFromPath()`

---

## Stack sugerido

Coherente con lo que pediste:

- **PHP 8.3** + **Symfony 7**
- **PostgreSQL 16** (índices, `COPY`, jsonb si lo necesitas para columnas extra del padrón)
- **Doctrine ORM** + **DBAL** para los inserts masivos
- **openspout** para xlsx (lectura y escritura)
- **Symfony Mailer** + Gmail SMTP
- **Symfony Messenger** (transport: Doctrine, sin Redis/RabbitMQ extra)
- **Tailwind 3** + **Vite**
- Front: si quieres mínima ceremonia y aprovechar Symfony al máximo → **Stimulus + Turbo**.
  Si quieres SPA con buena UX para tablas grandes → **Inertia.js + Vue 3** (hay bundle Symfony oficial).
- **Auth**: `symfony/security-bundle` con form login. Para el login bonito que pides, una sola
  página con Tailwind + el formulario nativo de Symfony.

### Docker

- `docker-compose.yml` con servicios: `app` (php-fpm), `nginx`, `db` (postgres), `node` (solo dev, para Vite)
- En dev: bind mount del código, hot reload con Vite
- En prod: imagen multi-stage que copia `vendor/`, `public/build/`, sin node, sin código fuente JS
- Mismo Dockerfile, distintos targets (`dev`, `prod`)

---

## Sensibilidad de datos

Los archivos contienen datos personales protegidos por **Ley 19.628** (datos personales) y
**Ley 20.584** (derechos del paciente):

- `.gitignore`: `*.csv`, `*.xlsx`, `var/uploads/`, `.env.local`
- Credenciales SMTP en variables de entorno, nunca en el repo
- HTTPS obligatorio en producción
- Considerar política de retención: borrar uploads procesados después de X días
- Logs sin PII (no logear el contenido de los archivos)

---

## Test fixtures sugeridos

Para tests de integración, usa los `*_sample.csv`:
- 100 filas de telesalud + 200 filas de padrón
- Suficiente para validar el cruce, paginación y generación de xlsx
- Tarda <100ms en cargar
