package de.ruhrpottguru.testapp

import android.content.Intent
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import org.json.JSONObject
import org.json.JSONArray

private val Accent=Color(0xFFF59E0B)
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun NativeDetailScreen(item:JSONObject,c:FoodController,onPhotoFailure:(String)->Unit,onTag:(String)->Unit,external:(String)->Unit) {
    if(item.text("category")=="buedchen"){BuedchenDetailScreen(item,c,onPhotoFailure,external);return}
    val facts=item.optJSONObject("details") ?: JSONObject();val isEvent=item.text("category")=="events";val event=item.optJSONObject("event") ?: JSONObject()
    var sharing by remember(item.text("id")) {mutableStateOf<String?>(null)}
    var form by remember(item.text("id")) {mutableStateOf<String?>(null)}
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(18.dp),verticalArrangement=Arrangement.spacedBy(16.dp)) {
        PlacePhoto(item,detail=true,onFailure=onPhotoFailure,external=external)
        Text(item.text("title"),fontWeight=FontWeight.Bold,fontSize=29.sp)
        if(item.text("subtitle").isNotBlank())Text(item.text("subtitle"),color=Accent)
        FlowRow(horizontalArrangement=Arrangement.spacedBy(8.dp)) {
            Button(onClick={c.toggleSaved(item)}){Text(if(c.isSaved(item))"♥ Auf Merkzettel" else "♡ Merken")}
            Action(item.text("directions"),"Route planen",external)
            OutlinedButton(onClick={sharing="share"}){Text("Teilen")}
            OutlinedButton(onClick={sharing="invite"}){Text("🍻 Komm rum, ich bin hier!")}
            Action(event.text("ticketUrl"),"Tickets / Buchung",external)
            Action(facts.text("website").ifBlank {item.text("website")},if(isEvent)"Event-Webseite" else "Website",external)
            if(facts.optBoolean("routeIndustrial"))Action(facts.text("routeOfficialUrl"),"Zur offiziellen Seite ↗",external)
        }
        if(isEvent)EventReminderControl(item,c)
        if(facts.optBoolean("routeIndustrial"))DetailBlock("Route der Industriekultur") {Text(facts.text("routeType").ifBlank {"Primärstandort"},color=Accent)}
        DetailBlock("Adresse & Ort") {
            Text(item.text("address").ifBlank {item.text("city").ifBlank {"Ruhrgebiet"}})
            if(item.text("city").isNotBlank()&&!item.text("address").contains(item.text("city"),true))Text(item.text("city"))
            if(item.text("distance").isNotBlank())Text(item.text("distance")+" entfernt")
            if(item.text("category")=="food")FlowRow(horizontalArrangement=Arrangement.spacedBy(8.dp)) {Action(facts.text("menu").ifBlank {item.text("menu")},"Speisekarte",external);Action(facts.text("reservation").ifBlank {item.text("reservation")},"Reservieren",external)}
            Action(item.text("phone").takeIf {it.isNotBlank()}?.let {"tel:$it"}.orEmpty(),"Anrufen",external)
        }
        if(isEvent)EventNotice(item,external) else {
            val status=facts.optJSONObject("openingStatus")
            DetailBlock(status?.text("heading").orEmpty().ifBlank {item.text("hoursHeading").ifBlank {"Öffnungszeiten"}}) {
                Text(status?.text("shortLabel").orEmpty().ifBlank {status?.text("label").orEmpty().ifBlank {item.text("hours").ifBlank {"Öffnungszeiten nicht bekannt"}}})
                if(facts.text("opening").isNotBlank())Text(facts.text("opening"))
                val price=facts.text("price").ifBlank {item.text("price")}
                if(price.isNotBlank())Text((if(item.text("category")=="food")"Preisniveau: " else "Preisinfo: ")+price)
            }
        }
        val description=facts.text("description").ifBlank {item.text("description")}
        if(description.isNotBlank()) {Text("Darum geht’s",fontWeight=FontWeight.Bold,fontSize=20.sp);Text(description)}
        if(isEvent) {
            EventDownloads(item,external)
            EventCalendar(item)
            EventOrganizers(item,external)
            EventRelated(c,external)
            val glance=facts.optJSONArray("glance")?.objects().orEmpty()
            if(glance.isNotEmpty())DetailBlock("Auf einen Blick") {FlowRow(horizontalArrangement=Arrangement.spacedBy(8.dp),verticalArrangement=Arrangement.spacedBy(8.dp)){glance.forEach {row->Surface(shape=MaterialTheme.shapes.small,color=MaterialTheme.colorScheme.surfaceVariant){Text(row.text("label"),Modifier.padding(8.dp))}}}}
            EventSource(item,external)
        } else {
            PlaceEventsSection(c,external)
            CommunitySection(item,c,external){form="story"}
            Text("Gemeinsam fürs Revier",fontWeight=FontWeight.Bold,fontSize=20.sp)
            OutlinedButton(onClick={form="contribution"},modifier=Modifier.fillMaxWidth()){Text("Daten ergänzen oder Bild hochladen")}
        }
        if(isEvent)TextButton(onClick={form="feedback"}){Text("Stimmt hier etwas nicht?")}
        val labels=item.optJSONArray("labels")?.objects().orEmpty()
        if(labels.isNotEmpty()) {
            Text("Mehr davon entdecken",fontWeight=FontWeight.Bold)
            FlowRow(horizontalArrangement=Arrangement.spacedBy(8.dp)){labels.forEach {tag->AssistChip(onClick={onTag(tag.text("slug"))},label={Text(tag.text("label")+" →")})}}
        }
        if(c.detailError.isNotBlank())Text(c.detailError,color=MaterialTheme.colorScheme.error)
    }
    sharing?.let {mode->DetailShareDialog(item,c,mode){sharing=null}}
    form?.let {mode->if(mode=="feedback")DetailFeedbackDialog(item,c){form=null} else DetailContributionDialog(item,c,mode){form=null}}
}
@Composable internal fun DetailBlock(title:String,content:@Composable ColumnScope.()->Unit) {Card(Modifier.fillMaxWidth()){Column(Modifier.padding(16.dp),verticalArrangement=Arrangement.spacedBy(10.dp)){Text(title,fontWeight=FontWeight.Bold);content()}}}
@Composable internal fun Action(url:String,label:String,external:(String)->Unit) {if(url.isNotBlank())OutlinedButton(onClick={external(url)}){Text(label)}}
@Composable internal fun DetailShareDialog(item:JSONObject,c:FoodController,initial:String,onClose:()->Unit) {
    var mode by remember {mutableStateOf(initial)};var message by remember {mutableStateOf("Ich bin gerade hier. Wer kommt rum? 🍻")};var duration by remember {mutableStateOf("60")};val context=LocalContext.current
    AlertDialog(onDismissRequest=onClose,title={Text(if(mode=="invite")"Komm rum, ich bin hier!" else "Location teilen")},text={Column {
        listOf("share" to "Location teilen","invite" to "Komm rum, ich bin hier!").forEach {(v,label)->Row {RadioButton(selected=mode==v,onClick={mode=v});TextButton(onClick={mode=v}){Text(label)}}}
        if(mode=="invite") {OutlinedTextField(message,{message=it.take(500)},label={Text("Deine Nachricht")});listOf("30" to "30 Minuten","60" to "1 Stunde","120" to "2 Stunden","none" to "Keine Angabe").forEach {(v,label)->Row {RadioButton(selected=duration==v,onClick={duration=v});TextButton(onClick={duration=v}){Text(label)}}}}
    }},dismissButton={TextButton(onClick=onClose){Text("Abbrechen")}},confirmButton={TextButton(onClick={c.shareDetail(item,mode,message,duration){payload->context.startActivity(Intent.createChooser(Intent(Intent.ACTION_SEND).apply {type="text/plain";putExtra(Intent.EXTRA_SUBJECT,payload.text("title"));putExtra(Intent.EXTRA_TEXT,listOf(payload.text("text"),payload.text("url")).filter {it.isNotBlank()}.joinToString("\n"))},"RuhrpottGuru teilen"));onClose()}}){Text(if(mode=="invite")"Einladung teilen" else "Jetzt teilen")}})
}
@Composable private fun PlaceEventsSection(c:FoodController,external:(String)->Unit) {
    var expanded by remember {mutableStateOf(false)}
    if(c.placeEventsError.isNotBlank())Text(c.placeEventsError)
    if(c.placeEvents.isNotEmpty())DetailBlock("Events an diesem Ort") {
        (if(expanded)c.placeEvents else c.placeEvents.take(4)).forEach {row->OutlinedButton(onClick={if(row.optLong("eventId")>0)c.openRelated(row.optLong("eventId")) else c.openDetail(row.text("id"))},modifier=Modifier.fillMaxWidth(),enabled=!c.relatedLoading){Column {Text(row.text("title"));Text(row.text("date"),color=Accent)}}}
        if(c.placeEvents.size>4)TextButton(onClick={expanded=!expanded}){Text(if(expanded)"Weniger anzeigen" else "Alle ${c.placeEvents.size} Events anzeigen")}
    }
}
@Composable fun SavedScreen(c:FoodController) {
    var filter by androidx.compose.runtime.saveable.rememberSaveable {mutableStateOf("Alle")}
    LaunchedEffect(Unit){c.refreshReminders()}
    val rows=c.savedItems.filter {filter=="Alle" || (it.text("category")=="events")== (filter=="Events")}
    LazyColumn(Modifier.fillMaxSize(),contentPadding=PaddingValues(16.dp),verticalArrangement=Arrangement.spacedBy(12.dp)) {
        item {
            Text("Mein Pott · Merkliste",fontSize=26.sp,fontWeight=FontWeight.Bold)
            Text("Auf diesem Gerät gespeichert, ohne Login. Bleibt bei App-Updates erhalten; Löschen der App-Daten oder Deinstallation entfernt die Merkliste.")
            Row(horizontalArrangement=Arrangement.spacedBy(8.dp)) {
                listOf("Alle","Events","Orte").forEach {name->FilterChip(selected=filter==name,onClick={filter=name},label={Text(name)})}
            }
            TextButton(onClick={c.refreshReminders()}){Text("Erinnerungstermine aktualisieren")}
        }
        if(c.error.isNotBlank())item {Text(c.error)}
        if(rows.isEmpty())item {Text("Noch keine passenden Orte oder Events gemerkt. Tippe auf das Herz einer Karte oder Detailansicht.")}
        items(rows,key={it.text("id")}) {row->
            Card(onClick={c.openSaved(row)},modifier=Modifier.fillMaxWidth()) {
                PlacePhoto(row)
                Column(Modifier.padding(16.dp),verticalArrangement=Arrangement.spacedBy(6.dp)) {
                    Text(row.text("title"),fontWeight=FontWeight.Bold);Text(row.text("city"))
                    if(row.text("category")=="events") {
                        Text(reminderLabel(row))
                        TextButton(onClick={c.openSaved(row)}){Text("Termin öffnen / Erinnerung ändern")}
                        if(row.has("reminder"))TextButton(onClick={c.clearReminder(row.text("id"))}){Text("Erinnerung ausschalten")}
                    }
                    TextButton(onClick={c.removeSaved(row.text("id"))}){Text("♥ Nicht mehr merken")}
                }
            }
        }
    }
}
