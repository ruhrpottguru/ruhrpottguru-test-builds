package de.ruhrpottguru.testapp

import org.json.JSONObject
import kotlin.random.Random

internal data class PottQuestion(val id:String,val category:String,val question:String,val options:List<String>,val correctIndex:Int,val explanation:String)
internal data class PottRank(val min:Int,val title:String,val badge:String,val description:String)
internal data class QuizPool(val version:String,val questions:List<PottQuestion>)

internal object PottQuiz {
    const val ROUND_SIZE=10
    fun parsePool(raw:String):QuizPool {
        val data=JSONObject(raw)
        require(data.getInt("schemaVersion")==1 && data.getString("language")=="de")
        val rows=data.getJSONArray("questions")
        val questions=(0 until rows.length()).map {i->
            val q=rows.getJSONObject(i);val answers=q.getJSONArray("answers")
            PottQuestion(q.getString("id"),q.getString("category"),q.getString("question"),
                (0 until answers.length()).map {answers.getString(it)},q.getInt("correctIndex"),q.getString("explanation"))
        }
        require(questions.size==200 && questions.map {it.id}.toSet().size==questions.size)
        require(questions.all {it.id.isNotBlank() && it.category.isNotBlank() && it.question.isNotBlank() && it.explanation.isNotBlank() && it.options.size==4 && it.options.toSet().size==4 && it.options.all(String::isNotBlank) && it.correctIndex in 0..3})
        return QuizPool(data.getString("poolVersion").also {require(it.isNotBlank())},questions)
    }
    private fun <T> randomize(items:List<T>,nextInt:(Int)->Int):MutableList<T> {
        val a=items.toMutableList()
        for(i in a.lastIndex downTo 1){val j=nextInt(i+1);require(j in 0..i);val temp=a[i];a[i]=a[j];a[j]=temp}
        return a
    }
    /** Mechanical port of Web v2.3 rpgpqRound: balanced category passes, then answer permutation. */
    fun newRound(pool:List<PottQuestion>,nextInt:(Int)->Int={Random.nextInt(it)}):List<PottQuestion> {
        require(pool.size>=ROUND_SIZE)
        val groups=pool.groupBy {it.category}.mapValues {randomize(it.value,nextInt)}
        val picked=mutableListOf<PottQuestion>()
        while(picked.size<ROUND_SIZE) {
            val keys=randomize(groups.keys.filter {groups.getValue(it).isNotEmpty()},nextInt)
            check(keys.isNotEmpty())
            keys.forEach {key->if(picked.size<ROUND_SIZE){val rows=groups.getValue(key);picked.add(rows.removeAt(rows.lastIndex))}}
        }
        return picked.map {q->val order=randomize(listOf(0,1,2,3),nextInt);q.copy(options=order.map {q.options[it]},correctIndex=order.indexOf(q.correctIndex))}
    }
    val ranks=listOf(
        PottRank(80,"Echter Ruhrpott Guru","👑","Stark! Du kennst Zechen, Halden, Pott-Sprache und Revierkultur richtig gut."),
        PottRank(60,"Erfahrener Steiger","🕯️","Starke Leistung. Im Revier macht dir so schnell keiner etwas vor."),
        PottRank(35,"Wegbier-Genießer","🍺","Du bist schon ziemlich gut auf Büdchen und Pott-Kultur eingeschworen."),
        PottRank(0,"Gruben-Lehrling","⛏️","Da geht noch was – aber jeder echte Guru hat mal am Schacht angefangen.")
    )
    fun percentage(score:Int,total:Int=ROUND_SIZE)=kotlin.math.round(score*100.0/total).toInt()
    fun rank(percentage:Int)=ranks.first {percentage>=it.min}
}
