import sys
from pathlib import Path
import unittest
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))
from pottfuchs_public_audit import SEO, parse_seo, clean

class AuditTests(unittest.TestCase):
    def test_head_and_jsonld(self):
        page='''<html><head><title>Zum Schwarzen Essen | PottFuchs</title>
        <link rel="canonical" href="https://www.pottfuchs.de/ort/16936/essen/zum-schwarzen/"/>
        <link rel="icon" href="/favicon.svg"/>
        <meta name="description" content="Restaurant"/>
        <script type="application/ld+json">{"@graph":[{"@type":"WebSite","name":"PottFuchs"},{"@type":"Organization","name":"PottFuchs"}]}</script>
        </head><body><h1>Restaurant</h1></body></html>'''
        seo=parse_seo(page)
        self.assertEqual(seo["title"], "Zum Schwarzen Essen | PottFuchs")
        self.assertEqual(seo["h1"],1)
        self.assertEqual(len(seo["canonical"]),1)
        self.assertEqual(len(seo["descriptions"]),1)
        self.assertEqual(len(seo["brand_schema"]),2)
        self.assertEqual(seo["icons"],["/favicon.svg"])
    def test_duplicate_detection_source(self):
        seo=parse_seo('<h1>A</h1><h1>B</h1><meta name="description" content="A"><meta name="description" content="B">')
        self.assertEqual(seo["h1"],2)
        self.assertEqual(len(seo["descriptions"]),2)
    def test_discard_response_body(self):
        self.assertEqual(clean({"body":"secret raw HTML","status":200,"url":"/"}), {"status":200,"url":"/"})

if __name__=="__main__": unittest.main()
