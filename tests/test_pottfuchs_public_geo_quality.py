import unittest,sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/"tools"))
from pottfuchs_public_geo_quality import coords_place,coords_event,norm_event,analyze_items
class PublicGeoAuditTests(unittest.TestCase):
    def test_tagged_point(self):
        self.assertEqual(coords_place({"lat":51.4344,"lon":6.7623}),(51.4344,6.7623))
    def test_geo_nested_main(self):
        self.assertEqual(coords_event({"geo":{"main":{"latitude":51.4,"longitude":6.7}}}),(51.4,6.7))
    def test_city_only_never_assigned_radius(self):
        x={"rpg_geo_scope":"city","geo":{"main":{"latitude":51.4344,"longitude":6.7623}}}
        result=analyze_items([x],(51.4344,6.7623),event=True)
        self.assertEqual(result["cityscope_flag"],1)
        self.assertEqual(result["coord_within_5km"],0)
        self.assertEqual(result["no_reliable_coords_in_payload"],1)
    def test_missing_image_does_not_discard_row_in_evidence(self):
        x={"lat":51.4344,"lon":6.7623,"tags":{"name":"Test","addr:city":"Duisburg"}}
        r=analyze_items([x],(51.4344,6.7623))
        self.assertEqual(r["received"],1)
        self.assertEqual(r["no_image_tag_evidence"],1)
        self.assertEqual(r["coord_within_5km"],1)
if __name__=="__main__":unittest.main()
