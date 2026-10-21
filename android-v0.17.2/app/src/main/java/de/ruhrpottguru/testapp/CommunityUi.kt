package de.ruhrpottguru.testapp

import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import org.json.JSONObject
import org.json.JSONArray

private fun plain(value:String)=android.text.Html.fromHtml(value,android.text.Html.FROM_HTML_MODE_LEGACY).toString().trim()
@Composable
fun CommunitySection(item:JSONObject,c:FoodController,external:(String)->Unit,onWrite:()->Unit) {
    var selected by remember(item.text("id")) {mutableStateOf<String?>(null)}
    Text("Aus der Community",fontWeight=FontWeight.Bold)
    OutlinedButton(onClick=onWrite){Text("✍ Beitrag erstellen")}
    when {
        c.storiesLoading->LinearProgressIndicator(Modifier.fillMaxWidth())
        c.storiesError.isNotBlank()->{Text(c.storiesError);TextButton(onClick=c::loadStories){Text("Erneut versuchen")}}
        c.stories.isEmpty()->Text("Noch keine freigegebenen Beiträge. Kennst du diesen Ort?")
        else->c.stories.forEach {story->
            val id=story.text("id");Card(onClick={selected=if(selected==id)null else id},modifier=Modifier.fillMaxWidth()) {Column(Modifier.padding(14.dp),verticalArrangement=Arrangement.spacedBy(8.dp)) {
                Text(story.text("title"),fontWeight=FontWeight.Bold);Text("von "+story.text("nickname").ifBlank {"RuhrpottGuru-Community"})
                if(selected!=id)Text(plain(story.text("content")).take(118)+" …") else {
                    CommunityGallery(story,c)
                    Text(plain(story.text("content")))
                    var insta by remember(story.text("instagram")) {mutableStateOf<JSONObject?>(null)}
                    LaunchedEffect(story.text("instagram")){c.engine.call("rpgCommunityInstagram(${JSONObject.quote(story.text("instagram"))})"){insta=it.getOrNull() as? JSONObject}}
                    insta?.let {if(it.text("url").isNotBlank())Action(it.text("url"),it.text("label"),external) else Text(it.text("label"))}
                }
                Text(if(selected==id)"Beitrag einklappen ↑" else "Beitrag lesen →")
            }}
        }
    }
}
@Composable private fun CommunityGallery(story:JSONObject,c:FoodController) {
    fun initial():List<String> = buildList {
        story.optJSONArray("images")?.let {rows->for(i in 0 until rows.length()){val value=rows.opt(i);val url=if(value is JSONObject)value.text("url") else value as? String ?: "";if(url.isNotBlank())add(url)}}
        story.optJSONArray("imageUrls")?.let {rows->for(i in 0 until rows.length())rows.optString(i).takeIf {it.isNotBlank()}?.let {add(it)}}
        story.text("imageUrl").takeIf {it.isNotBlank()}?.let {add(it)}
    }.distinct()
    var urls by remember(story.text("id")) {mutableStateOf(initial())}
    LaunchedEffect(story.text("id")) {if(story.optLong("id")>0)c.detailWork({CommunityTransport.readMedia(story.optLong("id"))}) {result->
        val rows=(result.getOrNull() as? JSONArray)?.objects().orEmpty();val remote=rows.map {m->val sizes=m.optJSONObject("media_details")?.optJSONObject("sizes");sizes?.optJSONObject("large")?.text("source_url").orEmpty().ifBlank {sizes?.optJSONObject("medium_large")?.text("source_url").orEmpty()}.ifBlank {m.text("source_url")}}.filter {it.isNotBlank()};if(remote.isNotEmpty())urls=(remote+initial()).distinct().take(5)
    }}
    if(urls.isNotEmpty()) {
        val gallery=JSONArray(urls.map {JSONObject().put("url",it).put("credit","Community-Foto · "+story.text("nickname").ifBlank {"RuhrpottGuru-Community"})})
        PlacePhoto(JSONObject().put("id","story:"+story.text("id")).put("title",story.text("title")).put("gallery",gallery).put("media",gallery.getJSONObject(0)),detail=true)
    }
}

@Composable
fun DetailContributionDialog(item:JSONObject,c:FoodController,mode:String,onClose:()->Unit) {
    val story=mode=="story";val context=LocalContext.current
    var showPrivacy by remember {mutableStateOf(false)}
    var fields by remember {mutableStateOf(mapOf<String,String>())}
    var title by remember {mutableStateOf("")};var content by remember {mutableStateOf("")};var name by remember {mutableStateOf("")};var email by remember {mutableStateOf("")};var instagram by remember {mutableStateOf("")};var sender by remember {mutableStateOf("community")}
    var photos by remember {mutableStateOf(emptyList<UploadPhoto>())};var rights by remember {mutableStateOf(false)};var terms by remember {mutableStateOf(false)};var privacy by remember {mutableStateOf(false)}
    var processing by remember {mutableStateOf(false)};var sending by remember {mutableStateOf(false)};var done by remember {mutableStateOf(false)};var status by remember {mutableStateOf("")};var alive by remember {mutableStateOf(true)}
    DisposableEffect(Unit){onDispose {alive=false}}
    val picker=rememberLauncherForActivityResult(ActivityResultContracts.GetMultipleContents()) {uris->
        if(uris.isNotEmpty()) {
            val picked=if(story)uris.filter {uri->photos.none {it.sourceUri==uri.toString()}}.take(5-photos.size) else uris.take(5)
            processing=true;rights=false;status="Fotos werden optimiert …"
            c.detailWork({val output=mutableListOf<UploadPhoto>();var message="";for(uri in picked)runCatching {CommunityTransport.optimize(context,uri)}.onSuccess {output.add(it)}.onFailure {message=it.message.orEmpty()};Pair(output,message)}) {result->
                if(alive){processing=false;result.onSuccess {value->@Suppress("UNCHECKED_CAST") val next=value as Pair<List<UploadPhoto>,String>;photos=if(story)(photos+next.first).take(5) else next.first;status=next.second}.onFailure {status=it.message.orEmpty()}}
            }
        }
    }
    Dialog(onDismissRequest=onClose,properties=DialogProperties(usePlatformDefaultWidth=false)) {
        Surface(Modifier.fillMaxSize().windowInsetsPadding(WindowInsets.safeDrawing)) {
            Column(Modifier.padding(16.dp)) {
                Row(Modifier.fillMaxWidth(),verticalAlignment=Alignment.CenterVertically) {Text(if(story)"Beitrag erstellen" else "Daten ergänzen oder Bild hochladen",Modifier.weight(1f),fontWeight=FontWeight.Bold);TextButton(onClick=onClose){Text("× Schließen")}}
                Text(item.text("title"))
                Column(Modifier.weight(1f).verticalScroll(rememberScrollState()),verticalArrangement=Arrangement.spacedBy(12.dp)) {
                    if(done) {Text(status);Text("Die Redaktion prüft die Einsendung vor der Veröffentlichung.");Button(onClick=onClose){Text("Schließen")}}
                    else {
                        Text(if(story)"Kein Konto nötig. Dein Beitrag erscheint erst nach redaktioneller Freigabe." else "Alle Einsendungen werden vor Veröffentlichung geprüft.")
                        if(story) {FormText("Überschrift *",title,{title=it.take(120)});FormText("Dein Tipp *",content,{content=it.take(6000)},multi=true)}
                        else {
                            val config=item.optJSONArray("contributionFields") ?: JSONArray()
                            for(i in 0 until config.length()) {
                                val row=config.getJSONArray(i);val key=row.getString(0);val label=row.getString(1);val type=row.optString(2)
                                if(type=="status") {Text(label);listOf("" to "Bitte auswählen","active" to "Geöffnet / aktiv","temporarily_closed" to "Vorübergehend geschlossen","permanently_closed" to "Dauerhaft geschlossen").forEach {(v,text)->Row {RadioButton(selected=fields[key].orEmpty()==v,onClick={fields=fields+(key to v)});TextButton(onClick={fields=fields+(key to v)}){Text(text)}}}}
                                else FormText(label,fields[key].orEmpty(),{fields=fields+(key to it)},multi=type=="textarea",keyboard=if(type=="url")KeyboardType.Uri else if(type=="tel")KeyboardType.Phone else KeyboardType.Text)
                            }
                        }
                        Text(if(story)"Fotos * (1–5)" else "Bilder (bis zu 5)",fontWeight=FontWeight.Bold)
                        Text("Automatisch optimiert: maximal 2400 px und 2 MB je Foto, Seitenverhältnis bleibt erhalten. JPG, PNG, WebP, unterstützte HEIC/HEIF.")
                        OutlinedButton(onClick={picker.launch("image/*")},enabled=!processing&&!sending&&(!story||photos.size<5)){Text(if(processing)"Fotos werden optimiert …" else "Dateien auswählen")}
                        photos.forEachIndexed {i,photo->
                            Text(photo.name+" · "+(photo.bytes.size/1024)+" KB")
                            if(!story){FormText("Copyright / Urheber bzw. Rechteinhaber *",photo.copyright,{v->photos=photos.mapIndexed {j,p->if(i==j)p.copy(copyright=v) else p}});FormText("Quelle / Website (optional)",photo.sourceUrl,{v->photos=photos.mapIndexed {j,p->if(i==j)p.copy(sourceUrl=v) else p}},keyboard=KeyboardType.Uri)}
                            TextButton(onClick={photos=photos.filterIndexed {j,_->j!=i};rights=false},enabled=!sending&&!processing){Text("Foto entfernen")}
                        }
                        if(story||photos.isNotEmpty()) {
                            if(!story)Text("Für Bild-Uploads benötigen wir Namen und E-Mail. Bitte bestätige anschließend den Link in der E-Mail; die Angaben und Rechteerklärung werden als Einsendungsnachweis gespeichert.")
                            FormText(if(story)"Name / Nickname *" else "Name / Firma *",name,{name=it});FormText("E-Mail-Adresse *",email,{email=it},keyboard=KeyboardType.Email)
                            if(story)FormText("Instagram (optional)",instagram,{instagram=it})
                            CheckLine("Ich bestätige, dass ich die Bildrechte habe und RuhrpottGuru die Bilder veröffentlichen darf. *",rights){rights=it}
                        }
                        if(story) {CheckLine("Ich stimme den Einreichungsbedingungen zu. *",terms){terms=it};CheckLine("Ich stimme der Speicherung meiner Angaben zur Bearbeitung dieses Beitrags gemäß Datenschutzerklärung zu. *",privacy){privacy=it}}
                        else {Text("Du meldest als");Row {FilterChip(selected=sender=="community",onClick={sender="community"},label={Text("Besucher/in")});FilterChip(selected=sender=="operator",onClick={sender="operator"},label={Text("Betreiber/in")})}}
                        TextButton(onClick={showPrivacy=true}){Text("Datenschutzhinweise der App lesen")}
                        Button(onClick={
                            val input=JSONObject().put("fields",JSONObject(fields)).put("images",JSONArray(photos.map {JSONObject().put("name",it.name).put("copyright",it.copyright).put("sourceUrl",it.sourceUrl)})).put("rights",rights).put("terms",terms).put("privacy",privacy).put("name",name).put("email",email).put("title",title).put("content",content).put("instagram",instagram).put("sender",sender)
                            sending=true;status=""
                            c.submitDetailForm(item,mode,input,photos) {result->if(alive){sending=false;result.onSuccess {data->done=true;status=data.text("message").ifBlank {if(!story&&photos.isNotEmpty())"Danke! Bitte bestätige den Link in der E-Mail. Erst danach erscheint die Einsendung in der Redaktion." else "Danke! Deine Einsendung wartet auf Freigabe."}}.onFailure {status=it.message.orEmpty()}}}
                        },enabled=!sending&&!processing,modifier=Modifier.fillMaxWidth()){Text(if(sending)"Wird gesendet …" else if(story)"Beitrag einreichen" else "Zur Prüfung senden")}
                        if(status.isNotBlank())Text(status)
                    }
                }
            }
        }
    }
    if(showPrivacy)AppPrivacyDialog{showPrivacy=false}
}
@Composable private fun FormText(label:String,value:String,change:(String)->Unit,multi:Boolean=false,keyboard:KeyboardType=KeyboardType.Text) {OutlinedTextField(value,change,label={Text(label)},singleLine=!multi,minLines=if(multi)3 else 1,keyboardOptions=KeyboardOptions(keyboardType=keyboard),modifier=Modifier.fillMaxWidth())}
@Composable private fun CheckLine(label:String,checked:Boolean,change:(Boolean)->Unit) {Row(verticalAlignment=Alignment.CenterVertically){Checkbox(checked,change);Text(label,Modifier.clickable {change(!checked)})}}

@Composable fun DetailFeedbackDialog(item:JSONObject,c:FoodController,onClose:()->Unit) {
    var showPrivacy by remember {mutableStateOf(false)}
    var token by remember {mutableStateOf("")};var text by remember {mutableStateOf("Problem bei „${item.text("title")}“: ")};var status by remember {mutableStateOf("")};var sending by remember {mutableStateOf(false)};var sent by remember {mutableStateOf(false)}
    LaunchedEffect(Unit) {c.detailApi("feedback-token") {it.onSuccess {r->token=r.text("token");if(token.isBlank())status="Feedback ist gerade nicht verfügbar."}.onFailure {status="Feedback ist gerade nicht verfügbar."}}}
    AlertDialog(onDismissRequest=onClose,title={Text("Feedback")},text={Column {if(sent)Text("Danke – dein Hinweis ist angekommen.") else {Text("Was war falsch, hat gefehlt oder war unklar?");OutlinedTextField(text,{text=it.take(1200)},minLines=4);TextButton(onClick={showPrivacy=true}){Text("Datenschutzhinweise der App lesen")};if(status.isNotBlank())Text(status)}}},dismissButton={TextButton(onClick=onClose){Text("Schließen")}},confirmButton={if(!sent)TextButton(enabled=token.isNotBlank()&&!sending&&text.trim().isNotBlank(),onClick={
        sending=true
        val path=runCatching {java.net.URI(item.text("share")).path}.getOrDefault("")
        c.detailApi("beta-feedback",JSONObject().put("message",text.trim()).put("page",path).put("category",item.text("category")).put("feedback_token",token).put("company","")) {r->sending=false;r.onSuccess {sent=true;token=""}.onFailure {status=it.message.orEmpty()}}
    }){Text(if(sending)"Wird gesendet …" else "Feedback senden")}})
    if(showPrivacy)AppPrivacyDialog{showPrivacy=false}
}
