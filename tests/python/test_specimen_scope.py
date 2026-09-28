import sys
import unittest
from pathlib import Path

import pymupdf as fitz

PDF_DIR = Path(__file__).resolve().parents[2] / 'resources' / 'pdf'
sys.path.insert(0, str(PDF_DIR))

import specimens  # noqa: E402
import worker  # noqa: E402


class SpecimenScopeTest(unittest.TestCase):
    def document(self, second_width=500, second_height=700):
        doc = fitz.open()
        doc.new_page(width=595, height=842)
        doc.new_page(width=second_width, height=second_height)
        return doc

    def step(self, fmt, scope='all_pages', page=1, x=20, y=20):
        profile = {
            'name': 'Rizky Hidayat',
            'jabatan': 'Analis Sistem Informasi',
            'unit_kerja': 'Badan Perencanaan Pembangunan Daerah',
            'pangkat': 'Penata',
            'golongan': 'III/c',
        }
        candidate = {'specimen_format': fmt, 'profile_snapshot': profile}
        layout = specimens.layout(candidate)
        return {
            **candidate,
            'specimen_scope': scope,
            'page': page,
            'x': x,
            'y': y,
            'width': layout['width'],
            'height': layout['height'],
        }

    def test_all_three_formats_are_drawn_on_every_page(self):
        for fmt in ('framed', 'qr_2x2', 'qr_3x3'):
            with self.subTest(fmt=fmt):
                with self.document() as doc:
                    step = self.step(fmt)
                    worker.draw_signed_qr(doc, {'step': step, 'verification_url': 'https://example.test/verify/123'})
                    self.assertEqual(len(doc[0].get_links()), 1)
                    self.assertEqual(len(doc[1].get_links()), 1)

    def test_selected_page_draws_only_on_that_page(self):
        with self.document() as doc:
            step = self.step('qr_2x2', scope='selected_page', page=2)
            worker.draw_signed_qr(doc, {'step': step, 'verification_url': 'https://example.test/verify/123'})
            self.assertEqual(len(doc[0].get_links()), 0)
            self.assertEqual(len(doc[1].get_links()), 1)

    def test_all_pages_validation_checks_the_smallest_page(self):
        with self.document(second_width=60, second_height=60) as doc:
            step = self.step('qr_3x3')
            with self.assertRaisesRegex(worker.InvalidPdf, 'halaman 2'):
                worker.validate_positions(doc, [step])


if __name__ == '__main__':
    unittest.main()
