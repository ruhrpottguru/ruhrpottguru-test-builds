import sys
from pathlib import Path
import unittest
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/"tools"))
from pottfuchs_rest_sitemap_probe import search_summary,sitemap_summary

class ProbeTests(unittest.TestCase):
    def test_search_groups(self):
        got=search_summary('{"total":1,"groups":{"food":[{"id":"5","title":"Topgolf","city":"Oberhausen"}]}}')
        self.assertEqual(got["total"],1)
        self.assertEqual(got["groups"]["food"]["count"],1)
        self.assertEqual(got["groups"]["food"]["sample"][0]["city"],"Oberhausen")
    def test_api_error(self):
        got=search_summary('{"code":"rest_no_route","message":"No route was found"}')
        self.assertEqual(got["api_error_code"],"rest_no_route")
    def test_sitemap_index(self):
        xml='<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>https://www.pottfuchs.de/a.xml</loc></sitemap></sitemapindex>'
        got=sitemap_summary(xml,'https://www.pottfuchs.de/wp-sitemap.xml')
        self.assertEqual(got["type"],"index")
        self.assertEqual(got["hosts"],{"www.pottfuchs.de":1})
    def test_old_domain_loc(self):
        xml='<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://www.ruhrpottguru.de/ort/a</loc></url></urlset>'
        got=sitemap_summary(xml,'https://www.pottfuchs.de/rpg-sitemap.xml')
        self.assertEqual(len(got["non_pottfuchs_loc_samples"]),1)
if __name__=="__main__":unittest.main()
