package de.ruhrpottguru.testapp

/** Pure quiz transitions, shared by the UI and unit tests. */
internal data class QuizSession(
    val index:Int=0,val selected:Int=-1,val checked:Boolean=false,
    val score:Int=0,val finished:Boolean=false
) {
    fun select(answer:Int):QuizSession =
        if(checked || finished || answer !in PottQuiz.questions[index].options.indices)this
        else copy(selected=answer)
    fun check():QuizSession =
        if(checked || finished || selected<0)this
        else copy(checked=true,score=score+if(selected==PottQuiz.questions[index].correctIndex)1 else 0)
    fun next():QuizSession =
        if(!checked || finished)this
        else if(index+1==PottQuiz.questions.size)copy(finished=true)
        else copy(index=index+1,selected=-1,checked=false)
}
