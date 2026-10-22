"""Lit des tables d'un dump phpMyAdmin sans MySQL. Usage : sqlpeek.py dump.sql table [table...]
Imprime colonnes + lignes en JSON (une ligne par enregistrement)."""
import json, re, sys

def parse_values(s, i):
    rows, row, n = [], [], len(s)
    while i < n:
        c = s[i]
        if c == '(':
            row = []; i += 1
            while True:
                while s[i] in ' \n\r\t': i += 1
                if s[i] == "'":
                    i += 1; buf = []
                    while True:
                        ch = s[i]
                        if ch == '\\':
                            nx = s[i+1]; buf.append({'n':'\n','r':'\r','t':'\t','0':'\0'}.get(nx, nx)); i += 2
                        elif ch == "'":
                            if s[i+1] == "'": buf.append("'"); i += 2
                            else: i += 1; break
                        else: buf.append(ch); i += 1
                    row.append(''.join(buf))
                else:
                    j = i
                    while s[j] not in ',)': j += 1
                    tok = s[i:j].strip(); row.append(None if tok == 'NULL' else tok); i = j
                while s[i] in ' \n\r\t': i += 1
                if s[i] == ',': i += 1; continue
                if s[i] == ')': i += 1; break
            rows.append(row)
        elif c == ';':
            return rows, i + 1
        else:
            i += 1
    return rows, i

def main():
    path, tables = sys.argv[1], set(sys.argv[2:])
    data = open(path, encoding='utf-8', errors='replace').read()
    for m in re.finditer(r"INSERT INTO `([^`]+)` \(([^)]*)\) VALUES", data):
        t = m.group(1)
        if t not in tables: continue
        cols = [c.strip(' `') for c in m.group(2).split(',')]
        rows, _ = parse_values(data, m.end())
        for r in rows:
            print(json.dumps({'_t': t, **dict(zip(cols, r))}, ensure_ascii=False))

sys.stdout.reconfigure(encoding='utf-8')
main()
