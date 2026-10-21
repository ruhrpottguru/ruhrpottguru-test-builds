  function rpgpqRound(pool){
    const randomize=items=>{const a=items.slice();for(let i=a.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[a[i],a[j]]=[a[j],a[i]];}return a;};
    const groups={};pool.forEach(q=>(groups[q.category]||(groups[q.category]=[])).push(q));
    Object.keys(groups).forEach(k=>groups[k]=randomize(groups[k]));
    const picked=[];
    while(picked.length<10){
      const keys=randomize(Object.keys(groups).filter(k=>groups[k].length));
      if(!keys.length)break;
      keys.forEach(k=>{if(picked.length<10&&groups[k].length)picked.push(groups[k].pop());});
    }
    return picked.map(q=>{
      const order=randomize([0,1,2,3]);
      return {...q, options:order.map(i=>q.options[i]), correctIndex:order.indexOf(q.correctIndex)};
    });
  }
