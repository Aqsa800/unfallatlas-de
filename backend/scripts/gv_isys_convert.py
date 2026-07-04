#!/usr/bin/env python3
"""
Convert GV-ISys XLSX to a flat CSV suitable for the Laravel importer.
Usage: python3 gv_isys_convert.py input.xlsx output.csv
"""
import sys, csv, openpyxl

xlsx_path = sys.argv[1]
csv_path  = sys.argv[2]

wb = openpyxl.load_workbook(xlsx_path, read_only=True, data_only=True)

# Find the data sheet (name contains 'Gemeinden')
sheet_name = next((s for s in wb.sheetnames if 'Gemeinden' in s or 'gemeinden' in s), wb.sheetnames[-1])
ws = wb[sheet_name]

written = 0
with open(csv_path, 'w', newline='', encoding='utf-8') as f:
    w = csv.writer(f)
    w.writerow(['satzart','ags','name','population','area_km2','lon','lat'])

    for i, row in enumerate(ws.iter_rows(values_only=True)):
        if i < 6:
            continue

        satzart = str(row[0]).strip() if row[0] is not None else ''
        if satzart not in ('10','40','41','60','61'):
            continue

        def col(idx, pad=None):
            v = row[idx] if idx < len(row) else None
            s = str(v).strip() if v is not None else ''
            return s.zfill(pad) if (pad and s) else s

        land  = col(2, 2)
        rb    = col(3, 1) or '0'
        kreis = col(4, 2)
        gem   = col(6, 3)
        name  = str(row[7]).strip() if row[7] is not None else ''

        if not name or name == 'None':
            continue

        if satzart == '10':
            ags = land
        elif satzart in ('40','41'):
            ags = land + rb + kreis
        else:
            ags = land + rb + kreis + gem

        pop  = row[9]  if (len(row) > 9  and row[9]  is not None) else ''
        area = row[8]  if (len(row) > 8  and row[8]  is not None) else ''
        lon  = str(row[14]).replace(',','.') if (len(row) > 14 and row[14] is not None) else ''
        lat  = str(row[15]).replace(',','.') if (len(row) > 15 and row[15] is not None) else ''

        w.writerow([satzart, ags, name, pop, area, lon, lat])
        written += 1

wb.close()
print(f"{written} rows written")
