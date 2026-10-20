package de.ruhrpottguru.testapp

internal data class AppMenuEntry(val label:String,val available:Boolean=true)
internal data class AppMenuGroup(val label:String,val entries:List<AppMenuEntry>)
internal object AppNavigation {
    val groups=listOf(
        AppMenuGroup("Entdecken",listOf("Start","Events","Food","Highlights","Kultur","Aktivitäten").map {AppMenuEntry(it)}+AppMenuEntry("Suche",false)),
        AppMenuGroup("Specials & Mehr",listOf(
            AppMenuEntry("Trinkhallen-Radar"),AppMenuEntry("Route der Industriekultur"),
            AppMenuEntry("Zeitreise von oben"),AppMenuEntry("Hier geht’s um die Wurst"),
            AppMenuEntry("Halloween im Pott"),AppMenuEntry("Weihnachten im Pott"),
            AppMenuEntry("Pott-Quiz"),AppMenuEntry("Ruhrpott-Lexikon",false))),
        AppMenuGroup("Mein Pott",listOf("Standort & Radius","Merkliste").map {AppMenuEntry(it)}),
        AppMenuGroup("Informationen",listOf("App-Info","Bildnachweise","Impressum","Datenschutz").map {AppMenuEntry(it)})
    )
    val infoPages=setOf("App-Info","Bildnachweise","Impressum","Datenschutz")
    fun enabled(label:String)=groups.flatMap {it.entries}.any {it.label==label&&it.available}
}
