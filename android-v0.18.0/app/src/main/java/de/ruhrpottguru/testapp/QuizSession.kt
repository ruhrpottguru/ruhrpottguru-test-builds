package de.ruhrpottguru.testapp

import org.json.JSONArray
import org.json.JSONObject

/** Existing transitions now operate on an immutable round; saved order survives recreation. */
internal data class QuizSession(
    val round:List<PottQuestion>,val index:Int=0,val selected:Int=-1,val checked:Boolean=false,
    val score:Int=0,val finished:Boolean=false
) {
    fun select(answer:Int):QuizSession =
        if(checked || finished || answer !in round[index].options.indices)this
        else copy(selected=answer)
    fun check():QuizSession =
        if(checked || finished || selected<0)this
        else copy(checked=true,score=score+if(selected==round[index].correctIndex)1 else 0)
    fun next():QuizSession =
        if(!checked || finished)this
        else if(index+1==round.size)copy(finished=true)
        else copy(index=index+1,selected=-1,checked=false)
    fun snapshot(pool:QuizPool):String = JSONObject().put("poolVersion",pool.version)
        .put("round",JSONArray(round.map {q->JSONObject().put("id",q.id).put("answers",JSONArray(q.options))}))
        .put("index",index).put("selected",selected).put("checked",checked).put("score",score).put("finished",finished).toString()
    companion object {
        fun restore(raw:Any?,pool:QuizPool):QuizSession? = runCatching {
            require(raw is String)
            val d=JSONObject(raw);require(d.getString("poolVersion")==pool.version)
            val rows=d.getJSONArray("round");require(rows.length()==PottQuiz.ROUND_SIZE)
            val round=(0 until rows.length()).map {i->
                val row=rows.getJSONObject(i);val original=pool.questions.first {it.id==row.getString("id")}
                val a=row.getJSONArray("answers");val options=(0 until a.length()).map {a.getString(it)}
                require(options.size==4 && options.toSet()==original.options.toSet())
                original.copy(options=options,correctIndex=options.indexOf(original.options[original.correctIndex]))
            }
            require(round.map {it.id}.toSet().size==round.size)
            val s=QuizSession(round,d.getInt("index"),d.getInt("selected"),d.getBoolean("checked"),d.getInt("score"),d.getBoolean("finished"))
            require(s.index in round.indices && s.selected in -1..3 && (!s.checked || s.selected>=0))
            require(s.score in 0..(s.index+if(s.checked)1 else 0))
            require(!s.finished || (s.index==round.lastIndex && s.checked))
            s
        }.getOrNull()
    }
}
