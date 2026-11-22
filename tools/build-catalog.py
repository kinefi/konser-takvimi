#!/usr/bin/env python3
"""Builds languages/konser-takvimi-en_US.po and .mo from languages/konser-takvimi.pot and the English dictionary.

Usage: python3 tools/build-catalog.py                 (regenerate the en_US .po/.mo from the .pot and the dictionary)
       python3 tools/build-catalog.py --mo-only FILE  (compile any .po file, e.g. one edited in Poedit)
"""
import os, re, struct, sys, importlib.util

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LANG = os.path.join(ROOT, 'languages')

def unq(s): return s.replace('\\n', '\n').replace('\\t', '\t').replace('\\"', '"').replace('\\\\', '\\')
def q(s): return '"' + s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n').replace('\t', '\\t') + '"'

def parse_po(text):
    """Returns list of dict(msgid, plural, msgstr[list], meta lines) for non-header entries + header msgstr."""
    entries, header = [], ''
    for block in re.split(r'\n\s*\n', text.strip() + '\n'):
        if not block.strip(): continue
        e = {'comments': [], 'msgid': None, 'plural': None, 'str': {}}
        cur = None
        for line in block.split('\n'):
            if line.startswith('#'): e['comments'].append(line); continue
            m = re.match(r'(msgid_plural|msgid|msgstr(?:\[(\d+)\])?)\s+"(.*)"$', line)
            if m:
                key = m.group(1)
                if key == 'msgid': cur = ('id',); e['msgid'] = unq(m.group(3))
                elif key == 'msgid_plural': cur = ('pl',); e['plural'] = unq(m.group(3))
                else:
                    idx = int(m.group(2)) if m.group(2) else 0
                    cur = ('str', idx); e['str'][idx] = unq(m.group(3))
                continue
            m = re.match(r'"(.*)"$', line)
            if m and cur:
                v = unq(m.group(1))
                if cur[0] == 'id': e['msgid'] += v
                elif cur[0] == 'pl': e['plural'] += v
                else: e['str'][cur[1]] += v
        if e['msgid'] == '': header = e['str'].get(0, '')
        elif e['msgid'] is not None: entries.append(e)
    return entries, header

def write_mo(entries, header, path):
    msgs = {'': header}
    for e in entries:
        if e['plural'] is not None:
            forms = [e['str'].get(i, '') for i in range(2)]
            if all(forms): msgs[e['msgid'] + '\0' + e['plural']] = '\0'.join(forms)
        elif e['str'].get(0): msgs[e['msgid']] = e['str'][0]
    keys = sorted(msgs.keys())
    ids = b''; strs = b''; offs = []
    for k in keys:
        kb, vb = k.encode('utf-8'), msgs[k].encode('utf-8')
        offs.append((len(ids), len(kb), len(strs), len(vb)))
        ids += kb + b'\0'; strs += vb + b'\0'
    n = len(keys); keystart = 7 * 4 + 16 * n; valstart = keystart + len(ids)
    ko, vo = [], []
    for o1, l1, o2, l2 in offs:
        ko += [l1, o1 + keystart]; vo += [l2, o2 + valstart]
    out = struct.pack('Iiiiiii', 0x950412de, 0, n, 7 * 4, 7 * 4 + n * 8, 0, 0)
    out += struct.pack('%di' % len(ko), *ko) + struct.pack('%di' % len(vo), *vo) + ids + strs
    open(path, 'wb').write(out)
    return n - 1

def main():
    po_path = os.path.join(LANG, 'konser-takvimi-en_US.po')
    if '--mo-only' in sys.argv and len(sys.argv) > sys.argv.index('--mo-only') + 1:
        po_path = os.path.abspath(sys.argv[sys.argv.index('--mo-only') + 1])  # compile any translator-edited .po
    mo_path = po_path[:-3] + '.mo'
    if '--mo-only' not in sys.argv:
        pot_entries, _ = parse_po(open(os.path.join(LANG, 'konser-takvimi.pot'), encoding='utf-8').read())
        T = {}
        for part in ('en_US_part1', 'en_US_part2'):
            spec = importlib.util.spec_from_file_location(part, os.path.join(ROOT, 'tools', part + '.py'))
            m = importlib.util.module_from_spec(spec); spec.loader.exec_module(m); T.update(m.T)
        ids = {e['msgid'] for e in pot_entries}
        missing = [e['msgid'] for e in pot_entries if e['msgid'] not in T and e['plural'] is None]
        unused = [k for k in T if k not in ids]
        if missing: print('MISSING translations:', len(missing)); [print('  ', repr(x)) for x in missing]
        if unused: print('Unused dictionary entries:', len(unused)); [print('  ', repr(x)) for x in unused]
        out = ['# Konser Takvimi — English (United States) translation. Source language: Turkish.',
               'msgid ""', 'msgstr ""', '"Project-Id-Version: Konser Takvimi\\n"', '"Language: en_US\\n"', '"MIME-Version: 1.0\\n"',
               '"Content-Type: text/plain; charset=UTF-8\\n"', '"Content-Transfer-Encoding: 8bit\\n"', '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"', '']
        for e in pot_entries:
            out += e['comments']
            out.append('msgid ' + q(e['msgid']))
            if e['plural'] is not None:
                out.append('msgid_plural ' + q(e['plural']))
                # Source is Turkish (no plural forms), English needs singular/plural
                out.append('msgstr[0] ' + q(T.get(e['msgid'] + '|0', '%d day'))); out.append('msgstr[1] ' + q(T.get(e['msgid'] + '|1', '%d days')))
            else:
                out.append('msgstr ' + q(T.get(e['msgid'], '')))
            out.append('')
        open(po_path, 'w', encoding='utf-8').write('\n'.join(out))
        if missing: sys.exit(1)
    entries, header = parse_po(open(po_path, encoding='utf-8').read())
    print('Wrote', write_mo(entries, header, mo_path), 'messages to', os.path.relpath(mo_path, ROOT))

if __name__ == '__main__': main()
