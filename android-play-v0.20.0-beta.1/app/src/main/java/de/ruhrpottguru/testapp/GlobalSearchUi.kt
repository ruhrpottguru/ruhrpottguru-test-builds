package de.ruhrpottguru.testapp

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListState
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import org.json.JSONObject

@Composable internal fun GlobalSearchBar(c:FoodController,onSearch:()->Unit) {
    val focus=LocalFocusManager.current
    fun submit(){focus.clearFocus();onSearch()}
    OutlinedTextField(value=c.globalQuery,onValueChange=c::editGlobalQuery,singleLine=true,
        label={Text("Ruhrpott durchsuchen")},placeholder={Text("Events, Orte, Künstler …")},
        modifier=Modifier.fillMaxWidth().padding(horizontal=16.dp,vertical=4.dp),
        keyboardOptions=KeyboardOptions(imeAction=ImeAction.Search),keyboardActions=KeyboardActions(onSearch={submit()}),
        trailingIcon={Row {
            if(c.globalQuery.isNotEmpty())IconButton(onClick=c::clearGlobalSearch,modifier=Modifier.semantics {contentDescription="Allgemeine Suche leeren"}){Text("×",fontSize=22.sp)}
            IconButton(onClick={submit()},modifier=Modifier.semantics {contentDescription="Alle Kategorien durchsuchen"}){Text("⌕",fontSize=26.sp)}
        }})
}

@Composable internal fun GlobalSearchScreen(c:FoodController,state:LazyListState,open:(String)->Unit,external:(String)->Unit) {
    fun choose(row:JSONObject) {
        when(val destination=GlobalSearchPolicy.destination(row)) {
            is SearchDestination.Detail->c.openSearchResult(destination)
            is SearchDestination.Page->open(destination.name)
            is SearchDestination.External->external(destination.url)
            null->Unit
        }
    }
    LazyColumn(Modifier.fillMaxSize(),state=state,contentPadding=PaddingValues(16.dp),verticalArrangement=Arrangement.spacedBy(12.dp)) {
        item {Text("Suche",fontSize=28.sp,fontWeight=FontWeight.Bold)}
        item {Text("Durchsuche Events, Orte, Specials und Beiträge im gesamten Ruhrgebiet – unabhängig vom gewählten Umkreis.")}
        when {
            c.globalLoading->item {Row(horizontalArrangement=Arrangement.spacedBy(12.dp)){CircularProgressIndicator(Modifier.size(24.dp));Text("Das Revier wird durchsucht …")}}
            c.globalError.isNotBlank()->item {Text(c.globalError,color=MaterialTheme.colorScheme.error)}
            c.globalExecutedQuery.isBlank()->item {Text("Gib oben einen Namen, Ort, Künstler oder ein Stichwort ein.")}
            else->{
                item {Text("${c.globalTotal} Treffer für „${c.globalExecutedQuery}“",fontWeight=FontWeight.SemiBold)}
                if(c.globalTotal==0)item {Text("Keine Treffer gefunden. Versuche einen anderen Namen oder ein anderes Stichwort.")}
                if(c.searchDetailOpening)item {LinearProgressIndicator(Modifier.fillMaxWidth())}
                if(c.searchDetailError.isNotBlank())item {Text(c.searchDetailError,color=MaterialTheme.colorScheme.error)}
                c.globalSections.forEach {section->
                    item(key="group:"+section.key){Text("${section.label} · ${section.rows.size}",fontSize=21.sp,fontWeight=FontWeight.Bold)}
                    itemsIndexed(section.rows,key={index,row->section.key+":"+row.optString("id")+":"+index}) {_,row->
                        val destination=GlobalSearchPolicy.destination(row)
                        Card(onClick={choose(row)},enabled=destination!=null&&!c.searchDetailOpening,modifier=Modifier.fillMaxWidth()) {
                            Column(Modifier.padding(16.dp),verticalArrangement=Arrangement.spacedBy(6.dp)) {
                                Text(row.text("title"),fontSize=18.sp,fontWeight=FontWeight.SemiBold)
                                val description=listOf(row.text("subtitle"),row.text("city")).filter {it.isNotBlank()}.joinToString(" · ")
                                if(description.isNotBlank())Text(description)
                                Text(when(destination){is SearchDestination.External->"Im Browser öffnen ↗";is SearchDestination.Detail->"Details öffnen →";is SearchDestination.Page->"In der App öffnen →";null->"Details derzeit nicht verfügbar"},color=MaterialTheme.colorScheme.primary)
                            }
                        }
                    }
                }
            }
        }
    }
}
