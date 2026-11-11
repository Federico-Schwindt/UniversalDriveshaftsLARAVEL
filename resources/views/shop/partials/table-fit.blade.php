{{-- Fits the .ptfit box right before this include to the screen before it paints: denser steps first, cards when
     nothing fits ("TABLE FIT" in uds-2026.css). Include it straight after each box. --}}
<script>
@once
window.udsFit=(function(){
  var steps=['is-d1','is-d2','is-d3','is-d4','is-d5','is-cards'],phone=window.matchMedia('(max-width:760px)'),boxes=[],raf=0;
  function fit(box){
    var w,i=0;
    box.classList.remove.apply(box.classList,steps);
    if(phone.matches||!(w=box.querySelector('.ptbl-wrap')))return;
    while(i<steps.length&&w.scrollWidth>w.clientWidth){if(i)box.classList.remove(steps[i-1]);box.classList.add(steps[i++]);}
  }
  function all(){raf=0;boxes.forEach(fit);}
  function later(){if(!raf)raf=requestAnimationFrame(all);}
  window.addEventListener('resize',later); window.addEventListener('load',later);
  if(document.fonts&&document.fonts.ready)document.fonts.ready.then(later);
  return function(box){
    boxes.push(box); fit(box);
    /* filter / sort / page reloads swap the rows in place */
    if(window.MutationObserver)new MutationObserver(function(){fit(box);}).observe(box,{childList:true});
  };
})();
@endonce
udsFit(document.currentScript.previousElementSibling);
</script>
