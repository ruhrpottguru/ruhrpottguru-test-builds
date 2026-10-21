package de.ruhrpottguru.testapp

import android.Manifest
import android.content.Intent
import android.os.Build
import android.provider.Settings
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.Alignment
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.unit.dp
import org.json.JSONObject

internal fun reminderLabel(row:JSONObject):String {
    val r=row.optJSONObject("reminder") ?: return "Keine Erinnerung"
    return when(r.text("state")) {
        "cancelled"->"Quelle meldet: abgesagt · Erinnerung gestoppt"
        "postponed"->"Verschoben · Erinnerung pausiert"
        "unavailable"->"Termin nicht abrufbar · Erinnerung pausiert"
        "unknown"->"Startzeit unklar · Erinnerung pausiert"
        "past"->"Termin bereits begonnen"
        else->if(r.optLong("sentStart")==r.optLong("start"))"Erinnerung ausgelöst" else if(r.optBoolean("allDay"))"Erinnerung: am Vortag um 18 Uhr" else "Erinnerung: ${if(r.optInt("lead")==120)"2 Stunden" else "1 Tag"} vorher"
    }
}

@Composable internal fun EventReminderControl(item:JSONObject,c:FoodController) {
    if(item.text("category")!="events")return
    val context=LocalContext.current
    var expanded by remember(item.text("id")){mutableStateOf(false)}
    var choosing by remember(item.text("id")){mutableStateOf(false)}
    var lead by remember {mutableIntStateOf(120)}
    var denied by remember {mutableStateOf(false)}
    val launcher=rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) {granted->
        if(granted)c.configureReminder(item,lead) else denied=true
    }
    val saved=c.savedItems.firstOrNull {it.text("id")==item.text("canonicalId")}
    val allDay=item.optJSONObject("event")?.optJSONObject("calendar")?.optBoolean("allDay")==true
    Card(Modifier.fillMaxWidth()) {
        Column {
            Row(
                modifier=Modifier.fillMaxWidth().heightIn(min=56.dp)
                    .semantics {stateDescription=if(expanded)"Aufgeklappt" else "Eingeklappt"}
                    .clickable(role=Role.Button,onClickLabel=if(expanded)"Erinnerungsdetails schließen" else "Erinnerungsdetails öffnen"){expanded=!expanded}
                    .padding(horizontal=16.dp,vertical=12.dp),
                verticalAlignment=Alignment.CenterVertically,
                horizontalArrangement=Arrangement.spacedBy(12.dp)
            ) {
                Text("🔔")
                Text(saved?.let(::reminderLabel) ?: "Erinnerung einrichten",modifier=Modifier.weight(1f))
                Text(if(expanded)"⌃" else "⌄")
            }
            if(expanded)Column(Modifier.padding(start=16.dp,end=16.dp,bottom=16.dp),verticalArrangement=Arrangement.spacedBy(10.dp)) {
                if(saved==null)Text("Der Termin wird beim Einrichten automatisch gemerkt.")
                Button(onClick={choosing=true}){Text(if(saved?.has("reminder")==true)"Erinnerung ändern" else "Erinnerung einrichten")}
                if(saved?.has("reminder")==true)TextButton(onClick={c.clearReminder(saved.text("id"))}){Text("Erinnerung ausschalten")}
                if(c.error.isNotBlank())Text(c.error)
                Text("Android kann Benachrichtigungen verzögern. Terminänderungen werden bei erlaubter Hintergrundausführung regelmäßig und vor der Erinnerung geprüft. Ohne Netz ist die Aktualität nicht gesichert.")
                if(!EventReminders.allowed(context)||denied) {
                    Text("Benachrichtigungen müssen in Android erlaubt sein.")
                    TextButton(onClick={context.startActivity(Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS).putExtra(Settings.EXTRA_APP_PACKAGE,context.packageName))}){Text("Android-Einstellungen öffnen")}
                }
            }
        }
    }
    if(choosing)AlertDialog(onDismissRequest={choosing=false},title={Text("Wann erinnern?")},text={
        Column(verticalArrangement=Arrangement.spacedBy(8.dp)) {
            (if(allDay)listOf(1440) else listOf(1440,120)).forEach {minutes->
                OutlinedButton(onClick={
                    choosing=false;lead=minutes
                    if(Build.VERSION.SDK_INT>=33&&context.checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS)!=android.content.pm.PackageManager.PERMISSION_GRANTED)launcher.launch(Manifest.permission.POST_NOTIFICATIONS)
                    else c.configureReminder(item,minutes)
                },modifier=Modifier.fillMaxWidth()){Text(if(allDay)"Am Vortag um 18 Uhr" else if(minutes==120)"2 Stunden vorher" else "1 Tag vorher")}
            }
        }
    },confirmButton={TextButton(onClick={choosing=false}){Text("Abbrechen")}})
}
