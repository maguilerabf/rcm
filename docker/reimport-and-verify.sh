#!/usr/bin/env bash
set -euo pipefail
cd /mnt/c/Users/Bookforce/Projects/RCM

echo "--- limpiar datos viejos ---"
docker compose exec -T db psql -U rcm -d rcm <<SQL
DELETE FROM inscrito;
DELETE FROM telesalud_solicitud;
DELETE FROM import_job;
DELETE FROM messenger_messages;
SQL

echo "--- cache:clear ---"
docker compose exec -T php php bin/console cache:clear --no-ansi 2>&1 | tail -2

INSCRITOS_FILE=$(docker compose exec -T php sh -c 'ls /srv/app/var/uploads/inscritos/*.xlsx 2>/dev/null | head -1')
TELESALUD_FILE=$(docker compose exec -T php sh -c 'ls /srv/app/var/uploads/telesalud/*.xlsx 2>/dev/null | head -1')

echo "telesalud file: $TELESALUD_FILE"
echo "inscritos file: $INSCRITOS_FILE"

if [[ -z "${TELESALUD_FILE// }" || -z "${INSCRITOS_FILE// }" ]]; then
    echo "ERROR: faltan archivos en disco. Sube de nuevo desde la UI."
    exit 1
fi

echo "--- reimport telesalud ---"
docker compose exec -T php php bin/console app:debug:reimport telesalud "$TELESALUD_FILE" --no-ansi

echo "--- reimport inscritos (con COPY) ---"
docker compose exec -T php php bin/console app:debug:reimport inscritos "$INSCRITOS_FILE" --no-ansi

echo "--- conteos finales ---"
docker compose exec -T db psql -U rcm -d rcm -c "
  SELECT 'telesalud' AS t, COUNT(*) FROM telesalud_solicitud
  UNION ALL SELECT 'inscritos con run_dv no vacio', COUNT(*) FROM inscrito WHERE run_dv IS NOT NULL AND run_dv <> ''
  UNION ALL SELECT 'inscritos total', COUNT(*) FROM inscrito;
"

echo "--- coincidencias (cruce SQL real) ---"
docker compose exec -T db psql -U rcm -d rcm -c "
  SELECT COUNT(*) AS coincidencias_full
  FROM telesalud_solicitud s
  INNER JOIN inscrito i ON i.run_dv = s.identificador_norm
  WHERE s.tipo_identificador = 'run';
"

echo "--- ejemplo de 5 coincidencias ---"
docker compose exec -T db psql -U rcm -d rcm -c "
  SELECT s.num_identificador, s.nombre, s.apellido_paterno, i.sector
  FROM telesalud_solicitud s
  INNER JOIN inscrito i ON i.run_dv = s.identificador_norm
  WHERE s.tipo_identificador = 'run'
  LIMIT 5;
"
