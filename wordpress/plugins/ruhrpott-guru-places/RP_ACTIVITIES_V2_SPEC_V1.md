# RuhrpottGuru Activities Intelligence V2 – Entwickler-Spezifikation V1.0

Status: Dark-Launch-Fach-/Entwicklerspezifikation, 17.09.2026

## 1. Produktdefinition
Aktivitäten sind Orte, an denen ein normaler Besucher selbst eine konkrete Freizeitaktivität ausüben kann – spontan oder geplant, allein oder gemeinsam. Reine Infrastruktur, private Anlagen, reine Vereins-/Mitgliedsnutzung, Handel und nicht nutzbare POIs sind keine Aktivitäten.

## 2. Verbindliche Verarbeitungskette
Rohdaten -> Experience-Erkennung -> Hard Blocker -> Admission -> Experience Role -> statische Scores -> Capability Tags -> dynamischer Kontext -> Local Relevance -> Ranking.

## 3. Admission
- REIN: klare, für Besucher nutzbare Freizeitaktivität.
- BEDINGT: nur bei zusätzlicher Evidenz zu Zugang, Gastangebot oder Buchbarkeit.
- RAUS: privat, access=no, nur Mitglieder, reine Infrastruktur, Handel, geschlossen oder keine echte Experience.

Scarcity/Local Relevance darf RAUS niemals zu REIN befördern.

## 4. Experience Role
- CORE: definiert den Grundcharakter des Places.
- ADD_ON: echte Experience und suchbar, darf aber die primäre Hauptkategorisierung des Places nicht überschreiben.

Beispiel Freibad: swimming=CORE, beach_volleyball=ADD_ON.

## 5. Statische Scores (0-100)
- Access Score: reale Nutzbarkeit für normale Besucher.
- Experience Score: Stärke und Eindeutigkeit der Aktivität.
- Destination Score: Aufenthalts-/Ausflugswert.
- Spontaneity Score: ohne Vorbereitung/Buchung direkt nutzbar.
- Local Relevance Score: relative Wichtigkeit im erreichbaren lokalen Angebot.

Local Relevance besteht später aus type_scarcity + overall_scarcity und wird radial, nicht anhand starrer Stadtgrenzen berechnet.

## 6. Capability Tags
Capabilities sind positive Eignungen und keine Gegensätze:
solo_friendly, friends_friendly, pair_friendly, date_friendly, group_friendly, spontaneous, booking_required, indoor, outdoor, weatherproof, good_weather, free, low_budget, action, active, relaxed, evening, summer, water, urban.

Hard Blocker sind separat: private_access, access_no, members_only, permanently_closed, experience_missing.

## 7. Evidenz-Hierarchie
### Starke positive Signale
- access=yes/permissive/customers
- explizites Gastangebot/Buchbarkeit/Tageskarte/Verleih
- fee=yes + Öffnungszeiten/Website/Booking-Kontext
- klare Freizeitobjekte wie skate_park, escape_game, trampoline_park, public_bath

### Booster, aber kein alleiniger Admission-Trigger
- name
- website
- phone/email/operator
- lit, benches, Aufenthaltsinfrastruktur
- Wikidata/Wikipedia

### Harte negative Signale
- access=private/no
- members only / reine Vereinsnutzung ohne Gastangebot
- shop=* ohne tatsächliche Freizeit-Experience
- disused/abandoned/removed/closed

## 8. Zentrale Typregeln
- Skatepark/Skateplatz/Pumptrack/BMX/Bikepark: REIN.
- Basketball/Streetball öffentlich: REIN.
- Fußball-Vereinsplatz: i.d.R. RAUS; Bolzplatz/Streetcourt mit öffentlichen Signalen: REIN/BEDINGT.
- Tischtennis: BEDINGT, durch Cluster/öffentlichen Freizeitkontext aufwertbar.
- Hallenbad/Freibad/öffentliches Schwimmbad: REIN; Schulbad nur bei öffentlichem Betrieb.
- Reiterhof: BEDINGT; Gastangebot/Ausritte/Reitstunden -> REIN; privater Stall -> RAUS.
- Tennis/Padel: BEDINGT; Kunden-/Buchungszugang -> REIN; Mitgliederanlage -> RAUS.
- Escape/Bowling/Kart/Trampolin/Klettern/Bouldern/Wakeboard/Minigolf: REIN bei aktivem Besucherbetrieb.
- normales Fitnessstudio: nicht als Standard-Activity; Outdoor-Fitness/Calisthenics: REIN.
- Skateshop: RAUS als Skatepark, auch bei sport=skateboard.

## 9. Hybrid-Places
Ein Place darf mehrere Experiences tragen. Die schwächste Teilaktivität entscheidet nicht über den gesamten Place. CORE bestimmt die Identität, ADD_ON darf spezialisierte Suchanfragen triggern. Deduplizierung erfolgt auf Place-Ebene.

## 10. Situationsranking
Final Rank wird später aus Experience Quality + Intent Match + Dynamic Context + Local Relevance - Friction gebildet. Buchung ist ein Friction-Malus, normalerweise kein Hard Blocker. Wetter ändert nicht die Wahrheit über einen Place, sondern nur den aktuellen Context Score.

## 11. Schutzregeln für kleine Orte
Ein normales, aber nutzbares Hallenbad oder ein buchbarer Reiterhof kann in dünn versorgten Bereichen hoch ranken. Qualität/Zugang werden nicht künstlich verändert; nur Local Relevance steigt. Keine Stadtgrößenpauschale, sondern radiale Angebotsdichte.

## 12. Regressionstestfälle
1. Skateplatz Gladbeck: Skate-Synonym -> Activity REIN.
2. Club Mumbai Palais Skateshop: shop=sports + sport=skateboard -> kein Skatepark-Treffer.
3. Basketballcourt: pitch + sport=basketball + öffentlich/unklar ohne Privatblocker -> REIN.
4. Vereinsfußballplatz: pitch + soccer + club/membership/private -> RAUS.
5. Bolzplatz: soccer + informal/asphalt/public context -> REIN/BEDINGT.
6. Hallenbad Hünxe: öffentliches Bad -> REIN; spätere hohe Local Relevance bei geringer Dichte.
7. Reiterhof mit Gastangebot -> REIN; privater Stall -> RAUS.
8. Freibad + Beachvolleyball: swimming CORE + beach_volleyball ADD_ON; Suchtreffer über beide, Hauptidentität Freibad.
9. Boulderhalle + Café: bouldering CORE; Café nur Ausstattung.
10. Escape Room mit booking_required: REIN, aber Spontaneity niedriger.

## 13. Rollout
Phase 2A Dark Launch: V2 parallel berechnen und auditieren, keine Besucher-Ausgabe ändern.
Phase 2B Real-Daten-Abnahme: Stichproben/Verteilung über 53 Kommunen, Edge-Cases und False Positives.
Phase 2C kontrollierte Aktivierung: Admission/Ranking auf Activities-Feed schalten, Theme/andere Kategorien unverändert.

## V1.2 – Source Coverage / Experience Matrix (Backend 1.10.88)

- `sport=soccer` is no longer sufficient to identify every soccer-related venue as a generic pitch in V2. Verified Five-a-side/futsal venues can be `soccer_five`.
- `sport=model_aerodrome`, `sport=shooting`, and `sport=athletics` are first-class Experience signals. Admission remains separate from recognition.
- Multi-sport centres may expose several Experience Units; a weak or closed sub-activity does not invalidate the whole Place.
- Basketball/beach-volleyball in a generic sports centre/hall require public, dedicated-court or visitor evidence for `REIN`.
- High-specificity nameless public-use OSM facilities may receive neutral derived display names. Generic nameless sports infrastructure remains excluded.
- Source coverage is completed before Local Relevance/Scarcity is calibrated, so rarity is not calculated from an artificially incomplete inventory.
