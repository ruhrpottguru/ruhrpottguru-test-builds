Theme 353.0 – Martin- und Weihnachtsseite auch im Seitencode (ohne JavaScript)

Neu gegenüber 352.0:
- Einleitung, Terminliste und FAQ stehen im HTML der Seite (nicht nur per JavaScript). Die App ersetzt den Block, sobald sie startet.
- Eine Quelle für Texte: inc/special-ssr.php (PHP) -> Einstellung specialTexts -> Anzeige. Keine zweite Textfassung im JavaScript.
- Termine kommen aus derselben Eventquelle (ruhrpott_guru_event_rest). Berechnung im Hintergrund (WP-Cron), 30 Minuten Zwischenspeicher; ein Seitenaufruf wartet nie auf die Eventquelle. Solange noch nichts berechnet ist, zeigt der Seitencode nur Texte und FAQ.
- Weihnachten: im Seitencode nur die Weihnachtsmärkte (nicht alle sechs Kategorien).
- Auswahlregeln PHP = JavaScript, abgesichert durch tests/v353-special-ssr-parity.cjs.
- noscript-Hinweis ohne zweite H1.
Nicht im Browser/WordPress getestet. Rückfall: Theme 352.0 oder 351.0.
