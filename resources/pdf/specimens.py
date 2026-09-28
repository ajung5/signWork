"""Version 1 specimen geometry in PDF points; used for preview and final rendering."""
import base64
import io
import math

import pymupdf as fitz
import qrcode

CM = 72 / 2.54
FOOTER_HEIGHT = 34.016


def wrap(text, width, font, size):
    lines = []
    line = ''
    for word in str(text).split():
        if font.text_length(word, fontsize=size) > width:
            raise ValueError('Ada kata terlalu panjang pada identitas. Periksa data pengguna.')
        candidate = f'{line} {word}'.strip()
        if line and font.text_length(candidate, fontsize=size) > width:
            lines.append(line)
            line = word
        else:
            line = candidate
    if line:
        lines.append(line)
    return lines


def layout(step):
    fmt = step.get('specimen_format')
    # Retain qr_only geometry for previously confirmed/signed documents.
    sizes = {'qr_only': 2.5, 'qr_2cm': 2, 'qr_3cm': 3, 'qr_2x2': 2, 'qr_3x3': 3}
    if fmt in sizes:
        size = sizes[fmt] * CM
        return {'width': round(size, 3), 'height': round(size, 3), 'lines': [],
                'qr': [0, 0, size, size]}
    if fmt != 'framed':
        raise ValueError('Pilih salah satu dari tiga format spesimen TTE.')
    profile = step.get('profile_snapshot') or {}
    labels = {'name': 'nama', 'jabatan': 'jabatan', 'unit_kerja': 'unit kerja',
              'pangkat': 'pangkat', 'golongan': 'golongan'}
    missing = [label for key, label in labels.items() if not str(profile.get(key) or '').strip()]
    if missing:
        raise ValueError('Profil signer belum lengkap: ' + ', '.join(missing) +
                         '. Minta admin melengkapinya melalui Manajemen User, lalu muat ulang halaman ini. '
                         'Atau pilih format QR saja.')
    width, divider, padding = 10.5 * CM, 2.6 * CM, 6
    text_width = width - divider - 2 * padding
    normal, bold = fitz.Font('helv'), fitz.Font('hebo')
    fields = [
        ('Ditandatangani secara elektronik oleh:', False),
        (profile['jabatan'], False),
        (profile['unit_kerja'].upper(), False),
        (profile['name'], True),
        (f"{profile['pangkat']} ({profile['golongan']})", False),
    ]
    for size in [10, 9.5, 9, 8.5, 8]:
        try:
            groups = [wrap(text, text_width, bold if heavy else normal, size) for text, heavy in fields]
        except ValueError:
            continue
        if all(len(group) <= 2 for group in groups):
            break
    else:
        raise ValueError('Identitas terlalu panjang untuk bingkai. Gunakan nama singkat resmi atau format QR saja; teks tidak dipotong.')
    leading = size * 1.2
    y, lines = padding + size, []
    for index, group in enumerate(groups):
        if index == 4:
            y += 3 * leading
        for text in group:
            lines.append({'text': text, 'x': divider + padding, 'y': y,
                          'font': 'hebo' if fields[index][1] else 'helv', 'size': size})
            y += leading
    height = max(y - leading + padding + size * .3, 2 * CM + 2 * padding)
    left = (divider - 2 * CM) / 2
    top = (height - 2 * CM) / 2
    return {'width': round(width, 3), 'height': round(height, 3), 'divider': divider,
            'lines': lines, 'qr': [left, top, left + 2 * CM, top + 2 * CM]}


def qr_bytes(url, color='black'):
    if not url.startswith(('https://', 'http://')) or len(url) > 350:
        raise ValueError('URL verifikasi tidak valid atau terlalu panjang.')
    qr = qrcode.QRCode(error_correction=qrcode.constants.ERROR_CORRECT_M, box_size=10, border=4)
    qr.add_data(url)
    qr.make(fit=True)
    buf = io.BytesIO()
    qr.make_image(fill_color=color, back_color='white').save(buf, format='PNG')
    return buf.getvalue()


def draw(page, step, url, x=0, y=0, color='black'):
    spec = layout(step)
    ink = {'black': (0, 0, 0), 'red': (.85, 0, 0)}.get(color, color)
    rect = fitz.Rect(x, y, x + spec['width'], y + spec['height'])
    if step['specimen_format'] == 'framed':
        # Inset stroke so ink remains inside the validated rectangle.
        page.draw_rect(fitz.Rect(rect.x0 + .35, rect.y0 + .35, rect.x1 - .35, rect.y1 - .35),
                       color=ink, fill=(1, 1, 1), width=.7)
        page.draw_line((x + spec['divider'], y), (x + spec['divider'], rect.y1), color=ink, width=.7)
        for line in spec['lines']:
            page.insert_text((x + line['x'], y + line['y']), line['text'],
                             fontname=line['font'], fontsize=line['size'], color=ink)
    q = spec['qr']
    qr_rect = fitz.Rect(x + q[0], y + q[1], x + q[2], y + q[3])
    page.insert_image(qr_rect, stream=qr_bytes(url, color))
    page.insert_link({'kind': fitz.LINK_URI, 'from': qr_rect, 'uri': url})
    return spec


def options(steps, url, previews=True):
    results = []
    for step in steps:
        choices = {}
        for fmt in ['framed', 'qr_2cm', 'qr_3cm']:
            candidate = {**step, 'specimen_format': fmt}
            try:
                spec = layout(candidate)
                if previews:
                    with fitz.open() as preview:
                        page = preview.new_page(width=spec['width'], height=spec['height'])
                        draw(page, candidate, url, color='red')
                        spec['preview'] = 'data:image/png;base64,' + base64.b64encode(
                            page.get_pixmap(matrix=fitz.Matrix(2, 2), alpha=False).tobytes('png')).decode('ascii')
                choices[fmt] = spec
            except ValueError as error:
                choices[fmt] = {'error': str(error)}
        results.append(choices)
    return results


def footer(doc):
    """Extend the crop/media boxes downward, leaving original content and coordinates intact."""
    total = len(doc)
    for index, page in enumerate(doc):
        height = page.rect.height
        crop, media = page.cropbox, page.mediabox
        # Account for media-box origin in PyMuPDF's top-left crop coordinates.
        extra = math.ceil(max(0, crop.y1 + FOOTER_HEIGHT - media.height))
        page.set_mediabox(fitz.Rect(media.x0, media.y0 - extra, media.x1, media.y1))
        page.set_cropbox(fitz.Rect(crop.x0, crop.y0, crop.x1, crop.y1 + FOOTER_HEIGHT))
        w = page.rect.width
        page.draw_rect(fitz.Rect(0, height, w, page.rect.height), color=None, fill=(1, 1, 1), overlay=True)
        if w < 140:
            raise ValueError('Halaman terlalu sempit untuk footer (minimal 140 point).')
        page.draw_line((8, height + 5), (w - 8, height + 5), color=(.5, .5, .5), width=.4)
        text = f'SIMULASI TTE - BUKAN TTE SAH | SignWork | Halaman {index + 1}/{total}'
        size = min(7, (w - 16) / fitz.Font('helv').text_length(text, fontsize=1))
        page.insert_text((8, height + 15), text, fontname='helv', fontsize=size, color=(.25, .25, .25))
        line = 'Verifikasi dokumen melalui QR setelah signer menandatangani.'
        size = min(7, (w - 16) / fitz.Font('helv').text_length(line, fontsize=1))
        page.insert_text((8, height + 25), line, fontname='helv', fontsize=size, color=(.25, .25, .25))
