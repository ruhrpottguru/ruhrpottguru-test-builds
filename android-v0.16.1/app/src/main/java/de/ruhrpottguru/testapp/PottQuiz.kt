package de.ruhrpottguru.testapp

// Questions, answers and ranks copied unchanged from the existing Web QuizView.
internal data class PottQuestion(val id:Int,val category:String,val question:String,val options:List<String>,val correctIndex:Int,val explanation:String)
internal data class PottRank(val min:Int,val title:String,val badge:String,val description:String)
internal object PottQuiz {
    val questions=listOf(
        PottQuestion(1,"Bergbau & Tradition","Was bedeutet der traditionelle Bergmannsgruß „Glückauf!“ im Ruhrgebiet?",listOf("Wunsch für ein fröhliches Feierabendbier","Wunsch zum Öffnen neuer Erzgänge & gesundes Ausfahren aus dem Schacht","Hoffnung auf einen Sieg des Lieblings-Fußballvereins","Segen beim Betreten der Zeche am Morgen"),1,"„Glückauf!“ drückt den Wunsch der Bergleute aus, dass sich neue Erzgänge auftun („Glück auf!“) und sie nach der Schicht wieder gesund aus der Grube ausfahren."),
        PottQuestion(2,"Industriekultur","Welche Zeche in Essen gilt als „die schönste Zeche der Welt“ und ist UNESCO-Welterbe?",listOf("Zeche Zollverein","Zeche Ewald","Zeche Nachtigall","Zeche Consolidation"),0,"Zeche Zollverein im Essener Norden ist ein herausragendes Denkmal der Industriekultur und seit 2001 UNESCO-Welterbe."),
        PottQuestion(3,"Kult & Kiosk","Was versteht man im Ruhrpott liebevoll unter einer „Trinkhalle“ oder einem „Büdchen“?",listOf("Eine Halle zur Mineralwasser-Abfüllung","Das Nachbarschafts-Wohnzimmer für Gemischte Tüten, Kaltgetränke & Schnack","Eine überdachte Haltestelle der Straßenbahn","Eine kleine Brauerei im Hinterhof"),1,"Das Büdchen ist gelebte Revierkultur: Treffpunkt für Nachbarn, Gemischte Tüte, Kaltgetränk und den schnellen Schnack."),
        PottQuestion(4,"Kult-Kulinaria","Welche Kombination bezeichnet man im Ruhrpott als legendäre „Manta-Platte“?",listOf("Fischbrötchen mit Zwiebeln & Essiggurke","Currywurst mit Pommes rot-weiß („Schranke“)","Schnitzel mit Bratkartoffeln","Döner Kebab mit Schafskäse"),1,"Die Manta-Platte besteht klassisch aus Currywurst und Pommes mit Ketchup und Mayonnaise – rot-weiß, also „Schranke“."),
        PottQuestion(5,"Pott-Sprache","Was meint jemand im Ruhrgebiet, wenn er sagt: „Mach nich so’n Eiertanz!“?",listOf("Du sollst beim Tanzen nicht stolpern","Drucks nicht herum und komm direkt auf den Punkt!","Zerbrich keine frischen Ostereier","Lauf vorsichtiger über die Straße"),1,"Ein Musterbeispiel für Ruhrpott-Direktheit: nicht lange herumreden, sondern auf den Punkt kommen."),
        PottQuestion(6,"Halden & Panoramen","Was sind die begrünten Bergehalden des Ruhrgebiets heute größtenteils?",listOf("Abgesperrte Mülldeponien ohne Zutritt","Beliebte Freizeit- & Panoramaziele mit Haldenkunst","Unterirdische U-Bahn-Depots","Private Firmengelände"),1,"Viele ehemalige Bergehalden des Bergbaus wurden zu Freizeit- und Landschaftsräumen mit Aussichtspunkten und Landmarken wie Tetraeder oder Tiger & Turtle."),
        PottQuestion(7,"Pott-Sprache","Woher stammt das typische Ruhrpott-Wort „Malochen“ für harte Arbeit?",listOf("Aus dem Französischen","Aus dem Jiddischen beziehungsweise Rotwelschen","Aus dem Altgriechischen","Aus dem Englischen"),1,"„Malochen“ geht auf jiddische beziehungsweise rotwelsche Sprachwurzeln zurück und wurde im Deutschen zum Ausdruck für schwere Arbeit."),
        PottQuestion(8,"Highlights & Kultur","Welcher Gasometer im Ruhrgebiet ist 117 Meter hoch und heute für große Ausstellungen bekannt?",listOf("Gasometer Oberhausen","Gasometer Dortmund","Gasometer Bochum","Gasometer Duisburg"),0,"Der Gasometer Oberhausen ist 117 Meter hoch und zählt zu den bekanntesten Ausstellungshallen und Industriedenkmälern des Ruhrgebiets."),
        PottQuestion(9,"Bier & Brauerei","Welche Bierart ist besonders eng mit traditionsreichen Ruhrgebietsmarken wie Stauder, Fiege oder Brinkhoff’s verbunden?",listOf("Pils","Kölsch","Altbier","Weißbier"),0,"Pils prägt viele klassische Ruhrgebietsmarken und gehört seit Jahrzehnten zur Kneipen-, Büdchen- und Feierabendkultur des Reviers."),
        PottQuestion(10,"Routen & Freizeit","Wie lang ist die Route der Industriekultur ungefähr, die die großen Standorte im Ruhrgebiet verbindet?",listOf("Ca. 50 km","Ca. 400 km","Ca. 1.500 km","Exakt 100 km"),1,"Die Route der Industriekultur erschließt auf rund 400 Kilometern Straßenverbindung die wichtigsten Zeugnisse der Industriegeschichte im Ruhrgebiet.")
    )
    val ranks=listOf(
        PottRank(80,"Echter Ruhrpott Guru","👑","Stark! Du kennst Zechen, Halden, Pott-Sprache und Revierkultur richtig gut."),
        PottRank(60,"Erfahrener Steiger","🕯️","Starke Leistung. Im Revier macht dir so schnell keiner etwas vor."),
        PottRank(35,"Wegbier-Genießer","🍺","Du bist schon ziemlich gut auf Büdchen und Pott-Kultur eingeschworen."),
        PottRank(0,"Gruben-Lehrling","⛏️","Da geht noch was – aber jeder echte Guru hat mal am Schacht angefangen.")
    )
    fun percentage(score:Int)=kotlin.math.round(score*100.0/questions.size).toInt()
    fun rank(percentage:Int)=ranks.first {percentage>=it.min}
}
