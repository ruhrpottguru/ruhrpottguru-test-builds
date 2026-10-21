package de.ruhrpottguru.testapp

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.provider.CalendarContract
import android.widget.Toast
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.compose.ui.text.font.FontWeight
import org.json.JSONObject
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneOffset
import java.time.format.DateTimeFormatter

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EventDateFilters(c:FoodController) {
    var calendar by remember {mutableStateOf(false)}
    Row(Modifier.horizontalScroll(rememberScrollState()),horizontalArrangement=Arrangement.spacedBy(8.dp)) {
        listOf("today" to "Heute","" to "Alle Termine","tomorrow" to "Morgen","weekend" to "Wochenende").forEach {(mode,label)->
            FilterChip(selected=c.dateMode==mode,onClick={c.setDatePreset(mode)},label={Text(label)})
        }
        FilterChip(selected=c.dateMode=="range",onClick={calendar=true},label={Text(if(c.dateMode=="range")c.dateLabel else "Zeitraum")})
    }
    if(calendar) {
        fun millis(value:String)=runCatching {LocalDate.parse(value).atStartOfDay().toInstant(ZoneOffset.UTC).toEpochMilli()}.getOrNull()
        val state=rememberDateRangePickerState(initialSelectedStartDateMillis=millis(c.dateFrom),initialSelectedEndDateMillis=millis(c.dateTo))
        DatePickerDialog(onDismissRequest={calendar=false},confirmButton={
            TextButton(enabled=state.selectedStartDateMillis!=null,onClick={
                fun iso(value:Long)=Instant.ofEpochMilli(value).atOffset(ZoneOffset.UTC).toLocalDate().toString()
                val from=state.selectedStartDateMillis ?: return@TextButton
                c.setDateRange(iso(from),iso(state.selectedEndDateMillis ?: from));calendar=false
            }){Text("Übernehmen")}
        },dismissButton={TextButton(onClick={calendar=false}){Text("Abbrechen")}}) {
            DateRangePicker(state,modifier=Modifier.heightIn(max=500.dp),title={Text("Zeitraum wählen",Modifier.padding(16.dp))})
        }
    }
}

@Composable
fun EventNotice(item:JSONObject,external:(String)->Unit) {
    val event=item.optJSONObject("event") ?: return
    DetailBlock("Veranstaltungshinweis") {
        Text("Termin",fontWeight=FontWeight.Bold);Text(event.text("date").ifBlank {"Infos laut Anbieter"})
        if(event.text("dayHours").isNotBlank()&&event.text("date").isBlank())Text(event.text("dayHours"))
        if(event.text("visitorHours").isNotBlank()&&event.text("dayHours").isBlank())Text(event.text("visitorHours"))
        val rows=event.optJSONArray("priceRows")?.objects().orEmpty()
        if(rows.isNotEmpty()||event.text("priceText").isNotBlank()) {Text("Preis",fontWeight=FontWeight.Bold);if(rows.isNotEmpty())rows.forEach {r->Text(listOf(r.text("label"),r.text("value")).filter {it.isNotBlank()}.joinToString(": "))} else Text(event.text("priceText"))}
        Action(event.text("ticketUrl"),"Ticketlink öffnen",external)
        event.optJSONArray("participants")?.objects()?.takeIf {it.isNotEmpty()}?.let {rows->Text("Mitwirkende",fontWeight=FontWeight.Bold);rows.forEach {row->if(row.text("url").isNotBlank())Action(row.text("url"),row.text("name"),external) else Text(row.text("name"));if(row.text("role").isNotBlank())Text(row.text("role"))}}
    }
}
@Composable fun EventDownloads(item:JSONObject,external:(String)->Unit) {val docs=item.optJSONObject("event")?.optJSONArray("documents")?.objects().orEmpty();if(docs.isNotEmpty())DetailBlock("Programm & Downloads") {docs.forEach {Action(it.text("url"),it.text("label").ifBlank {"Weitere Informationen"},external)}}}
@Composable fun EventCalendar(item:JSONObject) {val data=item.optJSONObject("event")?.optJSONObject("calendar") ?: return;val ctx=LocalContext.current;DetailBlock("Event in den Kalender übernehmen") {Text("Datum, Uhrzeit (falls vorhanden), Ort und Beschreibung werden übernommen.");Button(onClick={openEventCalendar(ctx,data)}){Text("Android / Kalender")}}}
@Composable fun EventOrganizers(item:JSONObject,external:(String)->Unit) {
    val rows=item.optJSONObject("event")?.optJSONArray("organizers")?.objects().orEmpty()
    if(rows.isNotEmpty())DetailBlock("Veranstalter & Kontakt") {rows.forEach {r->Text(r.text("name").ifBlank {r.text("company").ifBlank {"Veranstalter"}},fontWeight=FontWeight.Bold);val address=listOf(listOf(r.text("street"),r.text("houseNumber")).filter {it.isNotBlank()}.joinToString(" "),listOf(r.text("zip"),r.text("city")).filter {it.isNotBlank()}.joinToString(" ")).filter {it.isNotBlank()}.joinToString(", ");if(address.isNotBlank())Text(address);if(r.text("phone").isNotBlank())Action("tel:"+r.text("phone"),r.text("phone"),external);if(r.text("email").isNotBlank())Action("mailto:"+r.text("email"),r.text("email"),external);Action(r.text("web"),"Website →",external)}}
}
@Composable fun EventRelated(c:FoodController,external:(String)->Unit) {
    var expanded by remember {mutableStateOf(false)}
    if(c.relatedLoading)LinearProgressIndicator(Modifier.fillMaxWidth())
    if(c.relatedItems.isNotEmpty())DetailBlock(if(c.relatedItems.all {it.text("relation")=="same-event-occurrence"})"Weitere Termine" else "Weitere Events / Termine dieser Reihe") {
        if(c.relatedLabel.isNotBlank())Text(c.relatedLabel,color=Color(0xFFF59E0B));if(c.relatedCount>1)Text("${c.relatedCount} weitere")
        (if(expanded)c.relatedItems else c.relatedItems.take(6)).forEach {row->OutlinedButton(onClick={if(row.optLong("eventId")>0)c.openRelated(row.optLong("eventId")) else external(row.text("canonicalUrl"))},enabled=!c.relatedLoading,modifier=Modifier.fillMaxWidth()){Column(Modifier.fillMaxWidth()){Text(row.text("title"));Text(row.text("date"),color=Color(0xFFF59E0B));val venue=row.text("venueName").ifBlank {row.text("city")};if(venue.isNotBlank()&&venue!=c.detail?.text("subtitle"))Text(venue)}}}
        if(c.relatedItems.size>6)TextButton(onClick={expanded=!expanded}){Text(if(expanded)"Weniger anzeigen" else "Alle Events dieser Reihe anzeigen")}
    }
}
@Composable fun EventSource(item:JSONObject,external:(String)->Unit) {if(item.text("sourceAttribution").isNotBlank()||item.text("sourceName").isNotBlank())DetailBlock("Datenquelle & Lizenz") {Text(item.text("sourceAttribution").ifBlank {"Quelle: "+item.text("sourceName")});if(item.text("sourceLicense").isNotBlank())Text(item.text("sourceLicense"));if(item.text("sourcePageUrl").isNotBlank())Action(item.text("sourcePageUrl"),"Originalquelle öffnen →",external) else Action(item.text("sourceTermsUrl"),"Quellenbedingungen öffnen →",external)}}

private fun openEventCalendar(context:Context,data:JSONObject) {
    val allDay=data.optBoolean("allDay")
    fun utcDay(key:String)=LocalDate.parse(data.text(key),DateTimeFormatter.BASIC_ISO_DATE).atStartOfDay().toInstant(ZoneOffset.UTC).toEpochMilli()
    val start=if(allDay)runCatching {utcDay("startDay")}.getOrNull() ?: return else data.optLong("start")
    if(start<=0)return
    val end=if(allDay)runCatching {utcDay("endDay")}.getOrNull() else data.optLong("end").takeIf {it>start}
    val intent=Intent(Intent.ACTION_INSERT,CalendarContract.Events.CONTENT_URI).apply {
        putExtra(CalendarContract.Events.TITLE,data.text("title"))
        putExtra(CalendarContract.Events.EVENT_LOCATION,data.text("location"))
        putExtra(CalendarContract.Events.DESCRIPTION,data.text("description"))
        putExtra(CalendarContract.EXTRA_EVENT_BEGIN_TIME,start)
        end?.let {putExtra(CalendarContract.EXTRA_EVENT_END_TIME,it)}
        putExtra(CalendarContract.EXTRA_EVENT_ALL_DAY,allDay)
    }
    try {context.startActivity(intent)} catch(_:ActivityNotFoundException) {Toast.makeText(context,"Keine Kalender-App installiert.",Toast.LENGTH_SHORT).show()}
}
