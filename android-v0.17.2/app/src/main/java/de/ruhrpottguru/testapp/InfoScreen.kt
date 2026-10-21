package de.ruhrpottguru.testapp

import android.text.Html
import android.text.method.LinkMovementMethod
import android.widget.TextView
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import org.json.JSONObject

@Composable internal fun AppPrivacyDialog(onClose:()->Unit) {
    val context=LocalContext.current
    Dialog(onDismissRequest=onClose,properties=DialogProperties(usePlatformDefaultWidth=false)) {
        Surface(Modifier.fillMaxSize().windowInsetsPadding(WindowInsets.safeDrawing)) {
            Column {
                TextButton(onClick=onClose){Text("← Zurück zum Formular")}
                Box(Modifier.weight(1f)){InfoScreen("Datenschutz"){openExternal(context,it)}}
            }
        }
    }
}

@Composable internal fun InfoScreen(title:String,openExternal:(String)->Unit) {
    val ctx=LocalContext.current
    val document=remember(title) {
        if(title=="Impressum"||title=="Datenschutz")ctx.assets.open(if(title=="Impressum")"impressum.json" else "datenschutz.json").bufferedReader().use {JSONObject(it.readText())} else null
    }
    key(title) {Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp),verticalArrangement=Arrangement.spacedBy(16.dp)) {
        Text(title,style=MaterialTheme.typography.headlineSmall)
        when(title) {
            "App-Info"->Text("RuhrpottGuru · Testversion ${BuildConfig.VERSION_NAME}\n\nEvents, Food, Highlights, Kultur und Aktivitäten mit Standort, Filtern, Bildern und Details. Dazu: Route der Industriekultur, Halloween, Weihnachten, Trinkhallen-Radar, Imbiss, Zeitreise von oben und deine lokale Merkliste.\n\nAls „In Vorbereitung“ markierte Menüpunkte sind noch nicht verfügbar. Diese Version dient internen Gerätetests.")
            "Bildnachweise"->{
                Text("Peter-Behrens-Bau: Foto Tuxyso, 17.05.2025, Wikimedia Commons. Original unverändert, Anzeige eingepasst. CC BY-SA 4.0.")
                TextButton(onClick={openExternal("https://commons.wikimedia.org/wiki/File:Peter-Behrens-Bau-Oberhausen-2025.jpg")}){Text("Peter-Behrens-Bau – Bildquelle")}
                TextButton(onClick={openExternal("https://creativecommons.org/licenses/by-sa/4.0/")}){Text("Fotolizenz CC BY-SA 4.0")}

                Text("Zeitreise-Kachel: echtes historisches Luftbild Essen/Zollverein, RVR1952. Aufnahmezeitraum 1951–1980, überwiegend 1952. © Regionalverband Ruhr.")
                TextButton(onClick={openExternal("https://www.govdata.de/dl-de/by-2-0")}){Text("Datenlizenz Deutschland – Namensnennung 2.0")}
                Text("Weitere Fotokacheln: KI-generierte Symbolmotive; keine Darstellung bestimmter Betriebe oder Veranstaltungen.\n\nListen, Details und Galerien nutzen Originalbilder aus dem gemeinsamen Places-/Eventbestand. Urheber, Quellen und vorhandene Lizenzangaben stehen am jeweiligen Bild.\n\nKarten: © OpenStreetMap-Mitwirkende. Historische Luftbilder: jeweilige RVR-/NRW-Nachweise im Quellenmenü der Zeitreise.\n\nLogo: vorhandenes RuhrpottGuru-Projektasset.")
            }
            else->{
                Text(document!!.optString("edition","Webseiten-Fassung · abgerufen am 20.10.2026"),style=MaterialTheme.typography.labelMedium)
                val color=MaterialTheme.colorScheme.onSurface
                AndroidView(factory={context->TextView(context).apply {textSize=16f;setTextColor(android.graphics.Color.rgb((color.red*255).toInt(),(color.green*255).toInt(),(color.blue*255).toInt()));setLinkTextColor(0xFFF59E0B.toInt());setTextIsSelectable(true);movementMethod=LinkMovementMethod.getInstance()}},update={view->
                    val spanned=android.text.SpannableString(Html.fromHtml(document!!.getString("html"),Html.FROM_HTML_MODE_LEGACY))
                    spanned.getSpans(0,spanned.length,android.text.style.URLSpan::class.java).forEach {old->val start=spanned.getSpanStart(old);val end=spanned.getSpanEnd(old);spanned.removeSpan(old);spanned.setSpan(object:android.text.style.ClickableSpan(){override fun onClick(widget:android.view.View){openExternal(old.url)}},start,end,0)}
                    view.text=spanned
                },modifier=Modifier.fillMaxWidth())
                TextButton(onClick={openExternal(document!!.getString("source"))}){Text(document!!.optString("sourceLabel","Originalfassung im Browser öffnen"))}
            }
        }
    }}
}
