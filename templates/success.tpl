<div id="fmr-form-{form_id}">
<div class="card p-3">
<div class="alert alert-success mb-0"><p class="mb-0">{message}</p></div>
</div>
<script>
document.dispatchEvent(new CustomEvent('former:submitted', { bubbles: true, detail: {tracking_event_json} }));
</script>
</div>
