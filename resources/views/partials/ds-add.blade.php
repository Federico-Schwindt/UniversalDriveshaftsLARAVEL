{{-- Add-to-cart for finder driveshafts ([data-ds-add]); price is re-read server side. Same response as every Add button (uds.js udsAdd). --}}
<script>
document.addEventListener('click',function(e){var a=e.target.closest('[data-ds-add]');if(!a)return;e.preventDefault();
window.udsAdd(a,'/catalog/driveshafts/add',{part_number:a.getAttribute('data-ds-add'),qty:1});});
</script>
