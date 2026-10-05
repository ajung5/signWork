"""Private JSON/stdio PDF worker. Coordinates are points, top-left, after rotation normalization."""
import io
import json
import math
import re
import shutil
import subprocess
import sys
from pathlib import Path

import pymupdf as fitz
import qrcode

import specimens


class InvalidPdf(ValueError):
    pass


PLACEHOLDER_PATTERN = re.compile(r'\$\{tte:signer:[1-9][0-9]*\}|\$\{tandatangan_naskah\}')


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


def _word_lines(page):
    lines = {}
    for word in page.get_text('words'):
        x0, y0, x1, y1, text, block_number, line_number, word_number = word
        lines.setdefault((block_number, line_number), []).append(
            (int(word_number), fitz.Rect(x0, y0, x1, y1), text)
        )
    return [sorted(words, key=lambda word: word[0]) for words in lines.values()]


def _fallback_placeholder_rects(page, token):
    rects = []
    for words in _word_lines(page):
        joined = ''
        owners = []
        for _, rect, text in words:
            compact = re.sub(r'\s+', '', text)
            joined += compact
            owners.extend([rect] * len(compact))
        offset = 0
        while True:
            start = joined.find(token, offset)
            if start < 0:
                break
            end = start + len(token)
            owned = owners[start:end]
            if owned:
                rect = fitz.Rect(owned[0])
                for word_rect in owned[1:]:
                    rect |= word_rect
                rects.append(rect)
            offset = end
    return rects


def _placeholder_tokens(page):
    page_text = page.get_text()
    tokens = set(PLACEHOLDER_PATTERN.findall(page_text))

    # Most PDFs do not contain placeholders. Avoid the second, more
    # expensive word-level pass for those documents. The fallback is still
    # needed when a placeholder is split across PDF text spans.
    if '${' not in page_text:
        return tokens

    for words in _word_lines(page):
        joined = ''.join(re.sub(r'\s+', '', text) for _, _, text in words)
        tokens.update(PLACEHOLDER_PATTERN.findall(joined))
    return tokens


def _placeholder_rects(page, token):
    rects = list(page.search_for(token))
    return rects or _fallback_placeholder_rects(page, token)


def scan(doc):
    pages, placeholders = [], {}
    for index, page in enumerate(doc):
        pages.append({'page': index + 1, 'width': page.rect.width, 'height': page.rect.height})
        for token in _placeholder_tokens(page):
            for rect in _placeholder_rects(page, token):
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
    """Return a complete page position while preserving page-specific x/y values."""
    positions = step.get('specimen_positions') or {}
    position = positions.get(str(page_number), positions.get(page_number))
    if position is None and step.get('specimen_scope') != 'selected_pages':
        position = step
    if not isinstance(position, dict):
        raise InvalidPdf(f'Posisi spesimen halaman {page_number} belum lengkap (data posisi tidak valid).')

    # Older confirmed workflows stored only x/y in the per-page map while
    # width/height remained on the signer step. Keep those documents usable
    # without ever falling back to another page's x/y coordinates.
    position = dict(position)
    for key in ['width', 'height']:
        if position.get(key) is None:
            position[key] = step.get(key)

    missing = [key for key in ['x', 'y', 'width', 'height'] if position.get(key) is None]
    if missing:
        raise InvalidPdf(
            f"Posisi spesimen halaman {page_number} belum lengkap (kurang: {', '.join(missing)})."
        )
    try:
        return {key: float(position[key]) for key in ['x', 'y', 'width', 'height']}
    except (KeyError, TypeError, ValueError) as error:
        raise InvalidPdf(f'Posisi spesimen halaman {page_number} belum lengkap (koordinat bukan angka).') from error


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


def redact_placeholder_matches(doc, matches):
    for match in matches:
        text_rect = match.get('text_rect')
        if isinstance(text_rect, (list, tuple)) and len(text_rect) >= 4:
            rect = fitz.Rect(text_rect[:4])
        else:
            rect = fitz.Rect(
                match['x'], match['y'],
                match['x'] + float(match.get('width', 0)),
                match['y'] + float(match.get('height', 0)),
            )
        doc[match['page'] - 1].add_redact_annot(rect, fill=(1, 1, 1))
    for page in doc:
        page.apply_redactions(images=0, graphics=0)


def redact_all_placeholders(doc):
    """Redact detected placeholder text for a source-page preview."""
    metadata = scan(doc)
    redact_placeholder_matches(doc, [match for matches in metadata['placeholders'].values() for match in matches])


def replace_placeholders(doc, steps):
    """Remove every configured placeholder before drawing its specimen replacement."""
    metadata = scan(doc)
    matches_to_redact = []
    for step in steps:
        token = step.get('placeholder')
        if not token:
            continue
        matches = metadata['placeholders'].get(token, [])
        if token == '${tte:signer:1}' and not matches and len(steps) == 1:
            matches = metadata['placeholders'].get('${tandatangan_naskah}', [])
        if step.get('placement_source') == 'placeholder' and not matches:
            raise InvalidPdf(f'Placeholder {token} tidak ditemukan pada PDF sumber.')
        if len({match['page'] for match in matches}) != len(matches):
            raise InvalidPdf('Placeholder duplikat. Gunakan maksimal satu placeholder per signer pada setiap halaman.')
        matches_to_redact.extend(matches)
    redact_placeholder_matches(doc, matches_to_redact)


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
    validate_positions(doc, steps)
    replace_placeholders(doc, steps)
    if payload.get('specimen_version') == 1 and payload.get('provider', 'mock') == 'mock':
        specimens.footer(doc)
    draw_specimens(doc, steps, payload['verification_url'], color='red')
    subject = 'SignWork MOCK - bukan tanda tangan elektronik kriptografis' if payload.get('provider', 'mock') == 'mock' else 'SignWork - dokumen ditandatangani melalui BSrE'
    doc.set_metadata({**doc.metadata, 'subject': subject})
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


def prepare_signed_specimen(doc, payload):
    draw_signed_qr(doc, payload)
    doc.save(payload['output'], garbage=4, deflate=True)
    return {'ok': True}


def _openssl(args, data):
    try:
        result = subprocess.run(
            ['openssl', *args],
            input=data,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            check=True,
            timeout=15,
        )
    except (FileNotFoundError, subprocess.CalledProcessError, subprocess.TimeoutExpired):
        return None
    return result.stdout


def _certificate_details(pem):
    output = _openssl([
        'x509', '-noout', '-subject', '-issuer', '-startdate', '-enddate',
        '-fingerprint', '-sha1', '-serial', '-text', '-nameopt', 'RFC2253',
    ], pem)
    if not output:
        return None

    text = output.decode('utf-8', errors='replace')

    def value(pattern):
        match = re.search(pattern, text, re.IGNORECASE | re.MULTILINE)
        return match.group(1).strip() if match else None

    fingerprint = value(r'^sha1 fingerprint\s*=\s*(.+)$')
    return {
        'fingerprint_sha1': fingerprint.upper() if fingerprint else None,
        'issuer_dn': value(r'^issuer\s*=\s*(.+)$'),
        'subject_dn': value(r'^subject\s*=\s*(.+)$'),
        'not_before': value(r'^notbefore\s*=\s*(.+)$'),
        'not_after': value(r'^notafter\s*=\s*(.+)$'),
        'serial': value(r'^serial\s*=\s*(.+)$'),
        'is_ca': bool(re.search(r'\bCA:TRUE\b', text, re.IGNORECASE)),
    }


def certificate_info(doc):
    """Detect embedded PDF signatures and extract certificate metadata when available."""
    signatures = []

    for xref in range(1, doc.xref_length()):
        obj = doc.xref_object(xref, compressed=False)

        if '/ByteRange' not in obj or '/SubFilter' not in obj:
            continue

        signature = {
            'fingerprint_sha1': None,
            'issuer_dn': None,
            'subject_dn': None,
            'not_before': None,
            'not_after': None,
            'serial': None,
            'certificate_available': False,
            'xref': xref,
        }

        contents = re.search(
            r'/Contents\s*<([0-9A-Fa-f\s]+)>',
            obj,
            re.DOTALL
        )

        # Signature tetap dianggap terdeteksi meskipun format
        # Contents tidak dapat dibaca.
        if not contents:
            signatures.append(signature)
            continue

        try:
            cms = bytes.fromhex(
                re.sub(r'\s+', '', contents.group(1))
            ).rstrip(b'\x00')
        except ValueError:
            signatures.append(signature)
            continue

        pem_bundle = _openssl([
            'pkcs7',
            '-inform',
            'DER',
            '-print_certs',
            '-outform',
            'PEM',
        ], cms)

        # TTE terdeteksi, tetapi metadata sertifikat belum dapat dibaca.
        if not pem_bundle:
            signatures.append(signature)
            continue

        certificates = re.findall(
            rb'-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----',
            pem_bundle,
            re.DOTALL,
        )

        parsed = [
            detail
            for pem in certificates
            if (detail := _certificate_details(pem))
        ]

        if not parsed:
            signatures.append(signature)
            continue

        leaf = next(
            (
                detail
                for detail in reversed(parsed)
                if not detail['is_ca']
            ),
            parsed[-1],
        )

        leaf.pop('is_ca', None)
        leaf['certificate_available'] = True
        leaf['xref'] = xref

        signatures.append(leaf)

    return {
        'signatures': signatures,
    }
    """Extract leaf certificate metadata from embedded PDF CMS signatures."""
    signatures = []
    for xref in range(1, doc.xref_length()):
        obj = doc.xref_object(xref, compressed=False)
        if '/ByteRange' not in obj or '/SubFilter' not in obj:
            continue
        contents = re.search(r'/Contents\s*<([0-9A-Fa-f\s]+)>', obj, re.DOTALL)
        if not contents:
            continue
        try:
            cms = bytes.fromhex(re.sub(r'\s+', '', contents.group(1))).rstrip(b'\x00')
        except ValueError:
            continue
        pem_bundle = _openssl(['pkcs7', '-inform', 'DER', '-print_certs', '-outform', 'PEM'], cms)
        if not pem_bundle:
            continue
        certificates = re.findall(
            rb'-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----',
            pem_bundle,
            re.DOTALL,
        )
        parsed = [detail for pem in certificates if (detail := _certificate_details(pem))]
        if not parsed:
            continue
        leaf = next((detail for detail in reversed(parsed) if not detail['is_ca']), parsed[-1])
        leaf.pop('is_ca', None)
        leaf['xref'] = xref
        signatures.append(leaf)
    return {'signatures': signatures}

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
    if action == 'certificate_info':
        doc = fitz.open(payload['input'])
        if not doc.is_pdf or doc.needs_pass:
            raise InvalidPdf('PDF harus tidak terenkripsi.')
        try:
            return certificate_info(doc)
        finally:
            doc.close()
    with open_pdf(payload['input']) as doc:
        if action == 'scan':
            return scan(doc)
        if action == 'validate':
            validate_positions(doc, payload['steps'])
            return {'ok': True}
        if action == 'render':
            if payload.get('redact_placeholders'):
                redact_all_placeholders(doc)
            if payload.get('specimen_version') == 1:
                specimens.footer(doc)
            page = int(payload['page'])
            if not 1 <= page <= len(doc):
                raise InvalidPdf('Halaman tidak tersedia.')
            doc[page - 1].get_pixmap(matrix=fitz.Matrix(1.4, 1.4), alpha=False).save(payload['output'])
            return {'ok': True}
        if action == 'prepare':
            return prepare(doc, payload)
        if action == 'sign_specimen':
            return prepare_signed_specimen(doc, payload)
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
