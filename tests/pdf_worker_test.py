import hashlib
import importlib.util
import tempfile
import sys
import unittest
from pathlib import Path

import pymupdf as fitz

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'resources/pdf'))
spec = importlib.util.spec_from_file_location('pdf_worker', ROOT / 'resources/pdf/worker.py')
worker = importlib.util.module_from_spec(spec)
spec.loader.exec_module(worker)


class PdfWorkerTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.output = Path(self.directory.name) / 'prepared.pdf'
        self.source = ROOT / 'tests/Fixtures/two-signers.pdf'

    def steps(self, source=None):
        data = worker.run({'action': 'scan', 'input': str(source or self.source)})
        return [{**data['placeholders']['${tte:signer:'+str(i)+'}'][0],
                 'sequence': i, 'placeholder': '${tte:signer:'+str(i)+'}',
                 'name_snapshot': 'Signer '+str(i)} for i in [1, 2]]

    def prepare(self, source=None, steps=None):
        return worker.run({'action': 'prepare', 'input': str(source or self.source),
                           'output': str(self.output), 'steps': steps or self.steps(source),
                           'verification_url': 'https://signwork.example/verify/12345678-1234-4123-8123-123456789012'})

    def test_prepared_pdf_has_no_placeholders_or_unsigned_qr(self):
        original = self.source.read_bytes()
        self.prepare()
        with fitz.open(self.output) as doc:
            self.assertNotIn('${tte:', doc[0].get_text())
            self.assertEqual(len(doc[0].get_image_info()), 0)
            self.assertEqual(len(doc[0].get_links()), 0)
        self.assertEqual(original, self.source.read_bytes())

    def test_two_mock_signatures_append_to_previous_pdf_bytes(self):
        self.prepare()
        previous = self.output
        for i in [1, 2]:
            output = Path(self.directory.name) / f'signed-{i}.pdf'
            worker.run({'action': 'mock_sign', 'input': str(previous), 'output': str(output), 'receipt': f'MOCK actor #{i}', 'step': self.steps()[i-1], 'verification_url': 'https://signwork.example/verify/demo'})
            self.assertTrue(output.read_bytes().startswith(previous.read_bytes()))
            self.assertNotEqual(hashlib.sha256(output.read_bytes()).hexdigest(), hashlib.sha256(previous.read_bytes()).hexdigest())
            with fitz.open(output) as doc:
                self.assertEqual(len(list(doc[0].annots())), i)
                self.assertEqual(len(doc[0].get_image_info()), i)
                self.assertEqual(len(doc[0].get_links()), i)
            previous = output

    def test_rotated_page_scan_and_prepare_use_same_coordinate_space(self):
        source = ROOT / 'tests/Fixtures/rotated.pdf'
        steps = self.steps(source)
        # Rotated placeholders are detected; manual fallback can move the blocks into free space.
        for i, step in enumerate(steps):
            step.update(x=40 + i * 200, y=350)
        self.prepare(source, steps)
        with fitz.open(self.output) as doc:
            self.assertEqual(doc[0].rotation, 0)
            self.assertAlmostEqual(doc[0].rect.width, 842)
            self.assertAlmostEqual(doc[0].rect.height, 595)
            self.assertNotIn('${tte:', doc[0].get_text())

    def test_scanned_pdf_can_use_manual_placement(self):
        source = ROOT / 'tests/Fixtures/scanned.pdf'
        data = worker.run({'action': 'scan', 'input': str(source)})
        self.assertEqual(data['placeholders'], {})
        steps = self.steps()
        self.prepare(source, steps)
        self.assertTrue(self.output.exists())

    def test_overlap_and_out_of_bounds_fail(self):
        for change in [{'x': 50}, {'x': 580}]:
            steps = self.steps()
            steps[1].update(change)
            with self.assertRaises(worker.InvalidPdf):
                self.prepare(steps=steps)

    def test_password_protected_and_form_pdfs_are_rejected(self):
        path = Path(self.directory.name) / 'protected.pdf'
        doc = fitz.open(self.source)
        doc.save(path, encryption=fitz.PDF_ENCRYPT_AES_256, user_pw='secret', owner_pw='owner')
        doc.close()
        with self.assertRaises(worker.InvalidPdf):
            worker.run({'action': 'scan', 'input': str(path)})
        path = Path(self.directory.name) / 'form.pdf'
        doc = fitz.open(self.source)
        widget = fitz.Widget()
        widget.field_type = fitz.PDF_WIDGET_TYPE_TEXT
        widget.field_name = 'field'
        widget.rect = fitz.Rect(20, 20, 100, 40)
        doc[0].add_widget(widget)
        doc.save(path)
        doc.close()
        with self.assertRaises(worker.InvalidPdf):
            worker.run({'action': 'scan', 'input': str(path)})

    def test_duplicate_placeholder_is_rejected_before_preparing(self):
        with self.assertRaises(worker.InvalidPdf):
            self.prepare(ROOT / 'tests/Fixtures/duplicate.pdf', self.steps())

    def test_repeated_placeholder_on_different_pages_is_supported(self):
        source = Path(self.directory.name) / 'placeholder-pages.pdf'
        doc = fitz.open()
        for page_number in [1, 2]:
            page = doc.new_page(width=595, height=842)
            page.insert_text((72, 120), '${tte:signer:1}', fontsize=12)
        doc.save(source)
        doc.close()

        data = worker.run({'action': 'scan', 'input': str(source)})
        matches = data['placeholders']['${tte:signer:1}']
        positions = {
            str(match['page']): {
                'x': match['x'], 'y': match['y'],
                'width': 56.693, 'height': 56.693,
            }
            for match in matches
        }
        steps = [{
            **matches[0], 'sequence': 1, 'placeholder': '${tte:signer:1}',
            'name_snapshot': 'Signer 1', 'specimen_format': 'qr_2cm',
            'specimen_scope': 'selected_pages',
            'specimen_pages': [1, 2], 'specimen_positions': positions,
        }]
        worker.run({'action': 'prepare', 'input': str(source), 'output': str(self.output),
                    'steps': steps,
                    'verification_url': 'https://signwork.example/verify/repeated'})
        with fitz.open(self.output) as prepared:
            self.assertEqual(len(prepared), 2)
            for page in prepared:
                self.assertNotIn('${tte:', page.get_text())
                self.assertEqual(len(page.get_links()), 1)

    def test_multiple_signers_are_detected_on_independent_pages(self):
        source = Path(self.directory.name) / 'multi-signer-pages.pdf'
        doc = fitz.open()
        for page_number in range(1, 5):
            page = doc.new_page(width=595, height=842)
            if page_number in [1, 3]:
                page.insert_text((72, 120), '${tte:signer:1}', fontsize=12)
            if page_number in [2, 4]:
                page.insert_text((300, 120), '${tte:signer:2}', fontsize=12)
        doc.save(source)
        doc.close()

        data = worker.run({'action': 'scan', 'input': str(source)})
        self.assertEqual(
            [match['page'] for match in data['placeholders']['${tte:signer:1}']],
            [1, 3],
        )
        self.assertEqual(
            [match['page'] for match in data['placeholders']['${tte:signer:2}']],
            [2, 4],
        )

        steps = []
        for sequence, token in [(1, '${tte:signer:1}'), (2, '${tte:signer:2}')]:
            matches = data['placeholders'][token]
            positions = {
                str(match['page']): {
                    'x': match['x'], 'y': match['y'],
                    'width': 56.693, 'height': 56.693,
                }
                for match in matches
            }
            steps.append({
                **matches[0], 'sequence': sequence, 'placeholder': token,
                'name_snapshot': f'Signer {sequence}', 'specimen_format': 'qr_2cm',
                'specimen_scope': 'selected_pages',
                'specimen_pages': [match['page'] for match in matches],
                'specimen_positions': positions,
            })

        worker.run({
            'action': 'prepare', 'input': str(source), 'output': str(self.output),
            'steps': steps,
            'verification_url': 'https://signwork.example/verify/multi-signer',
        })
        with fitz.open(self.output) as prepared:
            self.assertEqual(len(prepared), 4)
            for page in prepared:
                self.assertNotIn('${tte:', page.get_text())

    def test_cropped_page_stays_consistent(self):
        path = Path(self.directory.name) / 'cropped.pdf'
        doc = fitz.open(self.source)
        doc[0].set_cropbox(fitz.Rect(20, 20, 580, 800))
        doc.save(path)
        doc.close()
        self.prepare(path)
        with fitz.open(self.output) as doc:
            self.assertAlmostEqual(doc[0].rect.width, 560)
            self.assertNotIn('${tte:', doc[0].get_text())

    def test_compact_three_by_two_cm_signing_and_size_limits(self):
        steps = self.steps()
        steps[0].update(width=85.04, height=56.69)
        self.prepare(steps=steps)
        output = Path(self.directory.name) / 'compact.pdf'
        worker.run({'action': 'mock_sign', 'input': str(self.output), 'output': str(output),
                    'receipt': 'compact signer', 'step': steps[0],
                    'verification_url': 'https://signwork.example/verify/12345678-1234-4123-8123-123456789012'})
        with fitz.open(output) as doc:
            self.assertEqual(next(doc[0].annots()).info['title'], 'MOCK - BUKAN TTE SAH')
            self.assertEqual(len(doc[0].get_image_info()), 1)
            self.assertEqual(len(doc[0].get_links()), 1)
        for width, height in [(71, 54), (72, 53), (401, 100), (100, 301)]:
            steps[0].update(width=width, height=height)
            with fitz.open(self.source) as doc:
                with self.assertRaises(worker.InvalidPdf):
                    worker.validate_positions(doc, steps)

    def test_more_than_fifty_pages_is_supported(self):
        path = Path(self.directory.name) / 'long.pdf'
        doc = fitz.open()
        for _ in range(55):
            doc.new_page()
        doc.save(path)
        doc.close()
        self.assertEqual(len(worker.run({'action': 'scan', 'input': str(path)})['pages']), 55)

    def test_footer_preserves_visible_content_on_normal_cropped_rotated_and_scanned_pages(self):
        for source in [self.source, ROOT / 'tests/Fixtures/rotated.pdf', ROOT / 'tests/Fixtures/scanned.pdf']:
            for cropped in [False, True]:
                with worker.open_pdf(source) as doc:
                    page = doc[0]
                    if cropped:
                        page.set_cropbox(fitz.Rect(20, 20, page.rect.width - 20, page.rect.height - 20))
                    bounds = fitz.Rect(page.rect)
                    before = page.get_pixmap(alpha=False).samples
                    worker.specimens.footer(doc)
                    page = doc[0]
                    self.assertAlmostEqual(page.rect.height, bounds.height + worker.specimens.FOOTER_HEIGHT, places=3)
                    after = page.get_pixmap(clip=bounds, alpha=False).samples
                    self.assertEqual(before, after)
                    for n, page in enumerate(doc):
                        self.assertIn(f'Halaman {n + 1}/{len(doc)}', page.get_text())

    def test_pending_specimens_are_red_and_signed_specimen_becomes_black(self):
        steps = self.steps()
        profile = {'name': 'AGUNG NAWAWI, S.Kom', 'jabatan': 'Pranata Komputer Ahli Pertama',
                   'unit_kerja': 'DINAS KOMUNIKASI, INFORMATIKA, PERSANDIAN DAN STATISTIK',
                   'pangkat': 'Penata Muda Tk. I', 'golongan': 'III/b'}
        steps[0].update(specimen_format='framed', profile_snapshot=profile, x=30, y=150)
        steps[1].update(specimen_format='qr_only', profile_snapshot=profile, x=400, y=500)
        for step in steps:
            geometry = worker.specimens.layout(step)
            step.update(width=geometry['width'], height=geometry['height'])
        worker.run({'action': 'prepare', 'input': str(self.source), 'output': str(self.output),
                    'steps': steps, 'specimen_version': 1,
                    'verification_url': 'https://example.test/verify/demo'})
        with fitz.open(self.output) as doc:
            self.assertIn('AGUNG NAWAWI', doc[0].get_text())
            self.assertEqual(len(doc[0].get_image_info()), 2)
            pixmap = doc[0].get_pixmap(clip=fitz.Rect(30, 150, 340, 300), alpha=False)
            self.assertTrue(any(r > 150 and g < 100 and b < 100 for r, g, b in zip(pixmap.samples[0::3], pixmap.samples[1::3], pixmap.samples[2::3])))
        previous = self.output
        for index, step in enumerate(steps):
            output = Path(self.directory.name) / f'specimen-{index}.pdf'
            worker.run({'action': 'mock_sign', 'input': str(previous), 'output': str(output),
                        'step': step, 'receipt': 'test', 'verification_url': 'https://example.test/verify/demo'})
            self.assertTrue(output.read_bytes().startswith(previous.read_bytes()))
            with fitz.open(output) as doc:
                self.assertNotIn('KABUPATEN SUBANG', doc[0].get_text())
                self.assertEqual(doc[0].get_text().count('SIMULASI TTE - BUKAN TTE SAH'), 1)
                self.assertEqual(len(doc[0].get_image_info()), [2, 3][index])
                rects = [fitz.Rect(image['bbox']) for image in doc[0].get_image_info()]
                self.assertTrue(any(abs(rect.width - 2 * worker.specimens.CM) < .02 for rect in rects))
                if index == 1:
                    self.assertTrue(any(abs(rect.width - 2.5 * worker.specimens.CM) < .02 for rect in rects))
                if index == 0:
                    pixmap = doc[0].get_pixmap(clip=fitz.Rect(30, 150, 340, 300), alpha=False)
                    self.assertFalse(any(r > 150 and g < 100 and b < 100 for r, g, b in zip(pixmap.samples[0::3], pixmap.samples[1::3], pixmap.samples[2::3])))
            previous = output

    def test_new_qr_sizes_render_at_exact_dimensions_and_legacy_size_is_retained(self):
        choices = worker.specimens.options([{}], 'https://example.test/verify/demo')[0]
        self.assertEqual(set(choices), {'framed', 'qr_2cm', 'qr_3cm'})
        for fmt, cm in [('qr_2cm', 2), ('qr_3cm', 3), ('qr_only', 2.5)]:
            with fitz.open() as doc:
                page = doc.new_page()
                spec = worker.specimens.draw(page, {'specimen_format': fmt}, 'https://example.test/verify/demo', 30, 50)
                image = page.get_image_info()[0]
                bounds = fitz.Rect(image['bbox'])
                self.assertAlmostEqual(bounds.width, cm * 72 / 2.54, places=3)
                self.assertAlmostEqual(bounds.height, cm * 72 / 2.54, places=3)
                self.assertEqual(page.get_drawings(), [])
                self.assertAlmostEqual(spec['width'], cm * 72 / 2.54, places=3)

    def test_specimens_are_rendered_on_all_pages_for_all_formats_and_selected_page_is_preserved(self):
        source = Path(self.directory.name) / 'two-pages.pdf'
        with fitz.open() as doc:
            doc.new_page(width=595, height=842)
            doc.new_page(width=595, height=842)
            doc.save(source)
        profile = {'name': 'Agung Nawawi, S.Kom', 'jabatan': 'Pranata Komputer Ahli Pertama',
                   'unit_kerja': 'DINAS KOMUNIKASI DAN INFORMATIKA', 'pangkat': 'Penata Muda', 'golongan': 'III/a'}
        steps = [
            {'sequence': 1, 'placeholder': '${tte:signer:1}', 'name_snapshot': 'Signer 1', 'page': 1,
             'x': 30, 'y': 100, 'specimen_format': 'framed', 'specimen_scope': 'all_pages',
             'profile_snapshot': profile},
            {'sequence': 2, 'placeholder': '${tte:signer:2}', 'name_snapshot': 'Signer 2', 'page': 1,
             'x': 330, 'y': 100, 'specimen_format': 'qr_2cm', 'specimen_scope': 'all_pages'},
            {'sequence': 3, 'placeholder': '${tte:signer:3}', 'name_snapshot': 'Signer 3', 'page': 2,
             'x': 430, 'y': 100, 'specimen_format': 'qr_3cm', 'specimen_scope': 'selected_page'},
        ]
        for step in steps:
            geometry = worker.specimens.layout(step)
            step.update(width=geometry['width'], height=geometry['height'])
        worker.run({'action': 'prepare', 'input': str(source), 'output': str(self.output), 'steps': steps,
                    'specimen_version': 1, 'verification_url': 'https://example.test/verify/demo'})
        previous = self.output
        for index, step in enumerate(steps, 1):
            output = Path(self.directory.name) / f'all-pages-{index}.pdf'
            worker.run({'action': 'mock_sign', 'input': str(previous), 'output': str(output), 'step': step,
                        'receipt': f'signer {index}', 'verification_url': 'https://example.test/verify/demo'})
            with fitz.open(output) as doc:
                self.assertEqual(len(doc[0].get_image_info()), [2, 3, 3][index - 1])
                self.assertEqual(len(doc[1].get_image_info()), 2 + index)
            previous = output

    def test_specimen_can_target_multiple_selected_pages(self):
        source = Path(self.directory.name) / 'three-pages.pdf'
        with fitz.open() as doc:
            for _ in range(3):
                doc.new_page(width=595, height=842)
            doc.save(source)
        step = {'sequence': 1, 'placeholder': '${tte:signer:1}', 'name_snapshot': 'Signer 1',
                'page': 1, 'specimen_pages': [1, 3], 'x': 30, 'y': 100,
                'specimen_format': 'qr_2cm', 'specimen_scope': 'selected_pages',
                'specimen_positions': {
                    '1': {'x': 30, 'y': 100},
                    '3': {'x': 30, 'y': 100},
                }}
        geometry = worker.specimens.layout(step)
        step.update(width=geometry['width'], height=geometry['height'])
        for position in step['specimen_positions'].values():
            position.update(width=geometry['width'], height=geometry['height'])
        worker.run({'action': 'prepare', 'input': str(source), 'output': str(self.output), 'steps': [step],
                    'specimen_version': 1, 'verification_url': 'https://example.test/verify/demo'})
        signed = Path(self.directory.name) / 'selected-pages.pdf'
        worker.run({'action': 'mock_sign', 'input': str(self.output), 'output': str(signed),
                    'receipt': 'selected pages', 'step': step,
                    'verification_url': 'https://example.test/verify/demo'})
        with fitz.open(signed) as doc:
            self.assertEqual(len(doc[0].get_image_info()), 2)
            self.assertEqual(len(doc[1].get_image_info()), 0)
            self.assertEqual(len(doc[2].get_image_info()), 2)

    def test_selected_page_specimens_use_a_different_position_per_page(self):
        source = Path(self.directory.name) / 'three-pages-dynamic.pdf'
        with fitz.open() as doc:
            for _ in range(3):
                doc.new_page(width=595, height=842)
            doc.save(source)
        step = {'sequence': 1, 'placeholder': '${tte:signer:1}', 'name_snapshot': 'Signer 1',
                'page': 1, 'specimen_pages': [1, 3], 'x': 30, 'y': 100,
                'specimen_format': 'qr_2cm', 'specimen_scope': 'selected_pages',
                'specimen_positions': {
                    '1': {'x': 30, 'y': 100, 'width': 56.693, 'height': 56.693},
                    '3': {'x': 300, 'y': 500, 'width': 56.693, 'height': 56.693},
                }}
        worker.run({'action': 'prepare', 'input': str(source), 'output': str(self.output), 'steps': [step],
                    'specimen_version': 1, 'verification_url': 'https://example.test/verify/demo'})
        signed = Path(self.directory.name) / 'selected-pages-dynamic.pdf'
        worker.run({'action': 'mock_sign', 'input': str(self.output), 'output': str(signed),
                    'receipt': 'selected pages dynamic', 'step': step,
                    'verification_url': 'https://example.test/verify/demo'})
        with fitz.open(signed) as doc:
            first = fitz.Rect(doc[0].get_image_info()[0]['bbox'])
            third = fitz.Rect(doc[2].get_image_info()[0]['bbox'])
            self.assertAlmostEqual(first.x0, 30, places=2)
            self.assertAlmostEqual(first.y0, 100, places=2)
            self.assertAlmostEqual(third.x0, 300, places=2)
            self.assertAlmostEqual(third.y0, 500, places=2)

    def test_selected_pages_must_be_nonempty_and_in_range(self):
        step = self.steps()[0]
        step.update(specimen_scope='selected_pages', specimen_pages=[])
        with fitz.open(self.source) as doc:
            with self.assertRaises(worker.InvalidPdf):
                worker.validate_positions(doc, [step])
        step.update(specimen_pages=[999])
        with fitz.open(self.source) as doc:
            with self.assertRaises(worker.InvalidPdf):
                worker.validate_positions(doc, [step])

    def test_selected_page_positions_can_inherit_size_from_legacy_step_data(self):
        source = Path(self.directory.name) / 'three-pages-legacy-position-map.pdf'
        with fitz.open() as doc:
            for _ in range(3):
                doc.new_page(width=595, height=842)
            doc.save(source)
        step = {'sequence': 1, 'placeholder': '${tte:signer:1}', 'name_snapshot': 'Signer 1',
                'page': 1, 'x': 30, 'y': 100, 'width': 56.693, 'height': 56.693,
                'specimen_pages': [1, 3], 'specimen_format': 'qr_2cm', 'specimen_scope': 'selected_pages',
                'specimen_positions': {
                    '1': {'x': 30, 'y': 100},
                    '3': {'x': 300, 'y': 500},
                }}
        worker.run({'action': 'prepare', 'input': str(source), 'output': str(self.output), 'steps': [step],
                    'specimen_version': 1, 'verification_url': 'https://example.test/verify/demo'})
        with fitz.open(self.output) as doc:
            self.assertEqual(len(doc[0].get_links()), 1)
            self.assertEqual(len(doc[1].get_links()), 0)
            self.assertEqual(len(doc[2].get_links()), 1)

    def test_missing_profile_explains_blocked_frame_but_qr_only_still_has_preview(self):
        step = {'profile_snapshot': {'name': 'Rizky Hidayat', 'jabatan': 'Analis',
                                    'unit_kerja': '  ', 'pangkat': None, 'golongan': 'III/a'}}
        choices = worker.specimens.options([step], 'https://example.test/verify/demo')[0]
        self.assertIn('Profil signer belum lengkap: unit kerja, pangkat.', choices['framed']['error'])
        self.assertNotIn('preview', choices['framed'])
        self.assertTrue(choices['qr_2cm']['preview'].startswith('data:image/png;base64,'))
        step['profile_snapshot'].update(unit_kerja='Diskominfo', pangkat='Penata Muda')
        fixed = worker.specimens.options([step], 'https://example.test/verify/demo')[0]
        self.assertNotIn('error', fixed['framed'])
        self.assertTrue(fixed['framed']['preview'].startswith('data:image/png;base64,'))

    def test_long_identity_wraps_without_truncation_and_oversize_is_rejected(self):
        profile = {'name': 'Nama Pengguna, S.Kom', 'jabatan': 'Pranata Komputer Ahli Pertama',
                   'unit_kerja': 'DINAS KOMUNIKASI, INFORMATIKA, PERSANDIAN DAN STATISTIK',
                   'pangkat': 'Penata Muda Tk. I', 'golongan': 'III/b'}
        spec = worker.specimens.layout({'specimen_format': 'framed', 'profile_snapshot': profile})
        joined = ' '.join(line['text'] for line in spec['lines'])
        self.assertIn(profile['unit_kerja'], joined)
        for line in spec['lines']:
            self.assertLessEqual(line['x'] + fitz.Font(line['font']).text_length(line['text'], fontsize=line['size']), spec['width'])
        profile['unit_kerja'] = 'Unit ' * 100
        with self.assertRaises(ValueError):
            worker.specimens.layout({'specimen_format': 'framed', 'profile_snapshot': profile})

    def test_framed_specimen_places_signer_name_above_rank_after_spacing(self):
        profile = {'name': 'Agung Nawawi', 'jabatan': 'Analis', 'unit_kerja': 'Diskominfo',
                   'pangkat': 'Penata', 'golongan': 'III/c'}
        spec = worker.specimens.layout({'specimen_format': 'framed', 'profile_snapshot': profile})
        by_text = {line['text']: line for line in spec['lines']}
        leading = spec['lines'][1]['size'] * 1.2

        self.assertAlmostEqual(
            by_text[profile['name']]['y'] - by_text[profile['unit_kerja'].upper()]['y'],
            4 * leading,
            places=5,
        )
        self.assertAlmostEqual(
            by_text[f"{profile['pangkat']} ({profile['golongan']})"]['y'] - by_text[profile['name']]['y'],
            leading,
            places=5,
        )



if __name__ == '__main__':
    unittest.main()
