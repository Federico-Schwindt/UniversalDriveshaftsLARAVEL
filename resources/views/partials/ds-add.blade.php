{{-- Add-to-cart for finder driveshafts ([data-ds-add]); price is re-read server side. --}}
<script>
document.addEventListener('click',function(e){var a=e.target.closest('[data-ds-add]');if(!a)return;e.preventDefault();a.disabled=true;
var csrf=(document.querySelector('meta[name="csrf-token"]')||{}).content||'';
fetch('/catalog/driveshafts/add',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({part_number:a.getAttribute('data-ds-add'),qty:1})})
.then(function(r){return r.json();}).then(function(d){a.disabled=false;if(d.success){a.classList.add('is-done');document.querySelectorAll('[data-cart-count]').forEach(function(b){b.textContent=d.count;b.hidden=false;});setTimeout(function(){a.classList.remove('is-done');},1400);}else{alert(d.message||'Could not add this part.');}}).catch(function(){a.disabled=false;});});
</script>
