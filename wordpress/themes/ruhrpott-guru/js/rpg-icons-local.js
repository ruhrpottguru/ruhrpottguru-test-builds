(function(){
'use strict';
const N='http://www.w3.org/2000/svg';
const paths={
'menu':['M4 7h16','M4 12h16','M4 17h16'],
'x':['M6 6l12 12','M18 6L6 18'],
'chevron-right':['M9 18l6-6-6-6'],
'arrow-right':['M5 12h14','M13 6l6 6-6 6'],
'arrow-left':['M19 12H5','M11 18l-6-6 6-6'],
'map-pin':['M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0','M12 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6'],
'calendar':['M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2','M8 2v4','M16 2v4','M3 9h18'],
'heart':['M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8'],
'compass':['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20','M16 8l-2 6-6 2 2-6 6-2'],
'sparkles':['M12 3l1.2 3.8L17 8l-3.8 1.2L12 13l-1.2-3.8L7 8l3.8-1.2L12 3','M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15','M5 14l.8 1.7L8 16l-2.2.3L5 18l-.8-1.7L2 16l2.2-.3L5 14'],
'image':['M3 5h18v14H3z','M7 15l4-4 3 3 2-2 4 4','M8 9a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3'],
'search-x':['M11 19a8 8 0 1 1 5.7-2.3L22 22','M8 8l6 6','M14 8l-6 6'],
'loader-2':['M21 12a9 9 0 1 1-6.2-8.6'],
'user':['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8','M4 21a8 8 0 0 1 16 0'],
'book-open':['M3 5a7 7 0 0 1 8 1v14a7 7 0 0 0-8-1z','M21 5a7 7 0 0 0-8 1v14a7 7 0 0 1 8-1z'],
'shield-check':['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10','M8 12l2.5 2.5L16 9'],
'file-text':['M6 2h8l4 4v16H6z','M14 2v5h5','M9 13h6','M9 17h6'],
'cookie':['M20 12a8 8 0 1 1-8-8c0 2 2 4 4 4 0 2 2 4 4 4','M8 9h.01','M11 15h.01','M7 17h.01','M14 11h.01'],
'utensils':['M6 3v7','M3 3v5c0 2 6 2 6 0V3','M6 10v11','M16 3v18','M16 3c4 2 4 7 0 9'],
'activity':['M3 12h4l2-6 4 12 2-6h6'],
'landmark':['M3 9l9-6 9 6','M5 10h14','M6 10v8','M10 10v8','M14 10v8','M18 10v8','M3 21h18'],
'ticket':['M3 7h18v4a2 2 0 0 0 0 4v4H3v-4a2 2 0 0 0 0-4z','M13 7v2','M13 13v2','M13 17v2'],
'trophy':['M8 4h8v5a4 4 0 0 1-8 0z','M8 6H4v2a4 4 0 0 0 4 4','M16 6h4v2a4 4 0 0 1-4 4','M12 13v5','M8 21h8']
};
function make(el){
  const name=el.getAttribute('data-lucide');
  const d=paths[name]||paths.compass;
  const svg=document.createElementNS(N,'svg');
  for(const a of el.attributes) if(a.name!=='data-lucide') svg.setAttribute(a.name,a.value);
  svg.setAttribute('viewBox','0 0 24 24'); svg.setAttribute('fill','none');
  svg.setAttribute('stroke','currentColor'); svg.setAttribute('stroke-width','2');
  svg.setAttribute('stroke-linecap','round'); svg.setAttribute('stroke-linejoin','round');
  svg.setAttribute('aria-hidden','true');
  d.forEach(x=>{const p=document.createElementNS(N,'path');p.setAttribute('d',x);svg.appendChild(p);});
  el.replaceWith(svg);
}
window.lucide={createIcons:function(){document.querySelectorAll('[data-lucide]').forEach(make);}};
})();