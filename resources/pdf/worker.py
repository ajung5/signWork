"""Private JSON/stdio PDF worker. Coordinates are points, top-left, after rotation normalization."""
import io
import json
import math
import re
import shutil
import sys
from pathlib import Path

import pymupdf as fitz
import qrcode

import specimens


class InvalidPdf(ValueError):
    pass


def open_pdf(path, normalize=True):
    doc = fitz.open(path)
    if not doc.is_pdf or doc.needs_pass or len(doc) < 1:
        raise InvalidPdf('PDF harus tidak terenkripsi dan memiliki minimal satu halaman.')
    if doc.embfile_count():
        raise InvalidPdf('PDF dengan lampiran tertanam tidak didukung.')
    for xref in range(1, doc.xref_length()):
        obj = doc.xref_object(xref)
        if re.search(r'/(JavaScript|JS|Launch|RichMedia|XFA|ByteRange)\b', obj):
            raise InvalidPdf('PDF dengan konten aktif tidak didukung.')
    for page in doc:
        if any(a.type[0] == fitz.PDF_ANNOT_REDACT for a in page.annots() or []):
            raise InvalidPdf('PDF dengan anotasi redaksi belum diterapkan tidak didukung.')
        if list(page.widgets()):
            raise InvalidPdf('PDF dengan form atau tanda tangan digital lama tidak didukung. Gunakan PDF sumber tanpa tanda tangan.')
        if page.rect.width > 2500 or page.rect.height > 2500:
            raise InvalidPdf('Ukuran halaman terlalu besar.')
        if normalize:
            page.remove_rotation()
    return doc


def scan(doc):
    pages, placeholders = [], {}
    for index, page in enumerate(doc):
        pages.append({'page': index + 1, 'width': page.rect.width, 'height': page.rect.height})
        text = page.get_text()
        for token in set(re.findall(r'\$\{tte:signer:[1-9][0-9]*\}|\$\{tandatangan_naskah\}', text)):
            for rect in page.search_for(token):
                placeholders.setdefault(token, []).append({
                    'page': index + 1, 'x': rect.x0, 'y': rect.y0,
                    'text_rect': list(rect), 'width': 160, 'height': 110,
                })
    return {'pages': pages, 'placeholders': placeholders}


def specimen_pages(step, total_pages):
    scope = step.get('specimen_scope') or 'selected_page'
    if scope == 'all_pages':
        return list(range(1, total_pages + 1))
    if scope == 'selected_pages':
        try:
            return sorted({int(value) for value in (step.get('specimen_pages') or [])})
        except (TypeError, ValueError) as error:
            raise InvalidPdf('Daftar halaman spesimen tidak valid.') from error
    return [int(step.get('page') or 0)]


def specimen_position(step, page_number):
    """Return the page-specific position, with legacy fallback only for global scope."""
    positions = step.get('specimen_positions') or {}
    position = positions.get(str(page_number), positions.get(page_number))
    if position is None and step.get('specimen_scope') != 'selected_pages':
        position = step
    try:
        return {key: float(position[key]) for key in ['x', 'y', 'width', 'height']}
    except (KeyError, TypeError, ValueError) as error:
        raise InvalidPdf(f'Posisi spesimen halaman {page_number} belum lengkap.') from error


def draw_specimens(doc, steps, url, color='red'):
    """Draw all configured specimens in their persisted page-specific positions."""
    for step in steps:
        if not step.get('specimen_format'):
            continue
        for page_number in specimen_pages(step, len(doc)):
            position = specimen_position(step, page_number)
            specimens.draw(doc[page_number - 1], step, url, position['x'], position['y'], color=color)


def clear_specimen(page, rect):
    """Remove a pending specimen before replacing it with the signed version."""
    for link in page.get_links():
        if fitz.Rect(link['from']).intersects(rect):
            page.delete_link(link)
    page.add_redact_annot(rect, fill=(1, 1, 1))
    page.apply_redactions(images=2, graphics=2, text=0)


def validate_positions(doc, steps):
    if not 1 <= len(steps) <= 10:
        raise InvalidPdf('Jumlah signer harus 1–10.')
    rectangles = []
    for step in steps:
        scope = step.get('specimen_scope') or 'selected_page'
        if scope not in {'all_pages', 'selected_pages', 'selected_page'}:
            raise InvalidPdf('Cakupan spesimen tidak valid.')
        page_numbers = specimen_pages(step, len(doc))
        if scope == 'selected_pages':
            if not page_numbers:
                raise InvalidPdf('Pilih minimal satu halaman untuk spesimen.')
        if any(n < 1 or n > len(doc) for n in page_numbers):
            raise InvalidPdf('Halaman posisi tidak tersedia.')
        if step.get('specimen_format'):
            try:
                spec = specimens.layout(step)
            except ValueError as error:
                raise InvalidPdf(str(error)) from error
        for n in page_numbers:
            position = specimen_position(step, n)
            x, y, width, height = [position[key] for key in ['x', 'y', 'width', 'height']]
            if not all(math.isfinite(v) for v in [x, y, width, height]):
                raise InvalidPdf('Koordinat tidak valid.')
            if step.get('specimen_format'):
                if abs(width - spec['width']) > .01 or abs(height - spec['height']) > .01:
                    raise InvalidPdf('Ukuran spesimen berubah. Muat ulang preview dan konfirmasi kembali.')
            elif width < 72 or height < 54 or width > 400 or height > 300:
                raise InvalidPdf('Ukuran blok minimal 72×54 dan maksimal 400×300 point.')
            rect = fitz.Rect(x, y, x + width, y + height)
            if not doc[n - 1].rect.contains(rect):
                raise InvalidPdf(f'Blok QR keluar batas halaman {n}. Pindahkan melalui preview.')
            if any(p == n and rect.intersects(r) for p, r in rectangles):
                raise InvalidPdf(f'Blok tanda tangan bertumpuk pada halaman {n}.')
            rectangles.append((n, rect))
    return rectangles


def prepare(doc, payload):
    steps = payload['steps']
    rectangles = validate_positions(doc, steps)
    metadata = scan(doc)
    for step in steps:
        token = step['placeholder']
        matches = metadata['placeholders'].get(token, [])
        if token == '${tte:signer:1}' and not matches and len(steps) == 1:
            matches = metadata['placeholders'].get('${tandatangan_naskah}', [])
        if len(matches) > 1:
            raise InvalidPdf('Placeholder duplikat. Gunakan satu placeholder per signer.')
        if matches:
            match = matches[0]
            doc[match['page'] - 1].add_redact_annot(fitz.Rect(match['text_rect']), fill=(1, 1, 1))
    for page in doc:
        page.apply_redactions(images=0, graphics=0)
    if payload.get('specimen_version') == 1:
        specimens.footer(doc)
    draw_specimens(doc, steps, payload['verification_url'], color='red')
    doc.set_metadata({**doc.metadata, 'subject': 'SignWork MOCK - bukan tanda tangan elektronik kriptografis'})
    doc.save(payload['output'], garbage=4, deflate=True)
    return {'ok': True}


def draw_signed_qr(doc, payload):
    steps = [payload['step']]
    validate_positions(doc, steps)
    url = payload['verification_url']
    if not url.startswith(('https://', 'http://')) or len(url) > 350:
        raise InvalidPdf('URL verifikasi tidak valid atau terlalu panjang.')
    step = steps[0]
    page_numbers = specimen_pages(step, len(doc))
    for n in page_numbers:
        position = specimen_position(step, n)
        rect = fitz.Rect(position['x'], position['y'], position['x'] + position['width'], position['y'] + position['height'])
        page = doc[n - 1]
        clear_specimen(page, rect)
        if step.get('specimen_format'):
            specimens.draw(page, step, url, position['x'], position['y'], color='black')
            continue
        qr = qrcode.QRCode(error_correction=qrcode.constants.ERROR_CORRECT_M, box_size=8, border=4)
        qr.add_data(url)
        qr.make(fit=True)
        buf = io.BytesIO()
        qr.make_image(fill_color='black', back_color='white').save(buf, format='PNG')
        side = min(rect.width, rect.height)
        left = rect.x0 + (rect.width - side) / 2
        top = rect.y0 + (rect.height - side) / 2
        qr_rect = fitz.Rect(left, top, left + side, top + side)
        page.insert_image(qr_rect, stream=buf.getvalue())
        page.insert_link({'kind': fitz.LINK_URI, 'from': qr_rect, 'uri': url})

def run(payload):
    action = payload['action']
    if action == 'specimens':
        return {'layouts': specimens.options(payload['steps'], payload['verification_url'], payload.get('previews', True))}
    if action == 'mock_sign':
        shutil.copyfile(payload['input'], payload['output'])
        with open_pdf(payload['output'], normalize=False) as doc:
            if payload.get('step'):
                draw_signed_qr(doc, payload)
            annotation = doc[0].add_text_annot((12, 12), 'SIMULASI SIGNWORK\n' + payload['receipt'])
            annotation.set_info(title='MOCK - BUKAN TTE SAH')
            doc.saveIncr()
        return {'ok': True}
    with open_pdf(payload['input']) as doc:
        if action == 'scan':
            return scan(doc)
        if action == 'validate':
            validate_positions(doc, payload['steps'])
            return {'ok': True}
        if action == 'render':
            if payload.get('specimen_version') == 1:
                specimens.footer(doc)
            page = int(payload['page'])
            if not 1 <= page <= len(doc):
                raise InvalidPdf('Halaman tidak tersedia.')
            doc[page - 1].get_pixmap(matrix=fitz.Matrix(1.4, 1.4), alpha=False).save(payload['output'])
            return {'ok': True}
        if action == 'prepare':
            return prepare(doc, payload)
        raise InvalidPdf('Operasi PDF tidak dikenali.')


if __name__ == '__main__':
    try:
        print(json.dumps(run(json.load(sys.stdin))))
    except (InvalidPdf, ValueError) as error:
        print(json.dumps({'error': str(error)}))
        sys.exit(2)
    except Exception:
        print(json.dumps({'error': 'PDF tidak dapat diproses. Periksa file dan instalasi PDF worker.'}))
        sys.exit(1)
