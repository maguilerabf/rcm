#!/usr/bin/env python3
"""
Convierte un xlsx a CSV de manera eficiente usando openpyxl en modo read-only.
Auto-detecta la fila del header escaneando las filas hasta encontrar todas las columnas
requeridas (case-insensitive, post unaccent + Y→I para tolerar variaciones).

Mucho más rápido que parsear el xlsx con PHP/openspout para volúmenes grandes.

Uso:
    xlsx_to_csv.py <input.xlsx> <output.csv> --required COL1,COL2,COL3 [--sheet NAME] [--max-scan-rows 50]

Salida (stdout, una línea):
    OK rows=N header_row=N elapsed=S.SSs

Códigos de salida:
    0  OK
    1  archivo no existe / no es xlsx válido
    2  no se encontró el header
"""
import argparse
import csv
import sys
import time
import unicodedata
from pathlib import Path

try:
    from openpyxl import load_workbook
except ImportError:
    print("ERROR: openpyxl no instalado. apt install python3-openpyxl  o  pip install openpyxl", file=sys.stderr)
    sys.exit(1)


def normalize_token(s: str) -> str:
    """unaccent + upper + trim + Y→I (igual que en SQL)."""
    if s is None:
        return ""
    s = str(s).strip().upper()
    # remove accents
    nfd = unicodedata.normalize("NFD", s)
    s = "".join(c for c in nfd if unicodedata.category(c) != "Mn")
    return s.replace("Y", "I")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("input", help="ruta al xlsx")
    parser.add_argument("output", help="ruta al csv de salida (UTF-8)")
    parser.add_argument(
        "--required",
        required=True,
        help="cabeceras obligatorias separadas por coma (ej: 'RUN,DV,NOMBRES')",
    )
    parser.add_argument("--sheet", default=None, help="nombre del sheet (default: primero)")
    parser.add_argument("--max-scan-rows", type=int, default=50, help="cuántas filas escanear buscando header (default: 50)")
    args = parser.parse_args()

    in_path = Path(args.input)
    if not in_path.is_file():
        print(f"ERROR: archivo no existe: {in_path}", file=sys.stderr)
        return 1

    required_norm = [normalize_token(r) for r in args.required.split(",") if r.strip()]

    started = time.time()

    try:
        wb = load_workbook(filename=str(in_path), read_only=True, data_only=True)
    except Exception as e:
        print(f"ERROR: no se pudo abrir el xlsx: {e}", file=sys.stderr)
        return 1

    try:
        ws = wb[args.sheet] if args.sheet else wb.active

        headers = None
        header_row = None
        rows_written = 0

        with open(args.output, "w", newline="", encoding="utf-8") as f:
            writer = csv.writer(f, quoting=csv.QUOTE_MINIMAL)

            for i, row in enumerate(ws.iter_rows(values_only=True), start=1):
                # Convert all to strings, preserve None as ''
                cells = []
                for c in row:
                    if c is None:
                        cells.append("")
                    elif isinstance(c, float) and c.is_integer():
                        # avoid "1909.0" for ints
                        cells.append(str(int(c)))
                    else:
                        cells.append(str(c))

                if headers is None:
                    if i > args.max_scan_rows:
                        print(f"ERROR: header no encontrado en las primeras {args.max_scan_rows} filas. Requeridas: {args.required}", file=sys.stderr)
                        return 2
                    upper_set = {normalize_token(c) for c in cells if c.strip()}
                    if all(req in upper_set for req in required_norm):
                        headers = [c.strip() for c in cells]
                        header_row = i
                        # densify trailing empties out
                        while headers and headers[-1] == "":
                            headers.pop()
                        writer.writerow(headers)
                    continue

                # Skip empty rows
                if not any(c.strip() for c in cells):
                    continue

                # Pad / truncate to header length
                if len(cells) < len(headers):
                    cells = cells + [""] * (len(headers) - len(cells))
                elif len(cells) > len(headers):
                    cells = cells[: len(headers)]

                # Strip each cell
                cells = [c.strip() if isinstance(c, str) else c for c in cells]
                writer.writerow(cells)
                rows_written += 1
    finally:
        wb.close()

    if headers is None:
        return 2

    elapsed = time.time() - started
    print(f"OK rows={rows_written} header_row={header_row} cols={len(headers)} elapsed={elapsed:.2f}s")
    return 0


if __name__ == "__main__":
    sys.exit(main())
