<div id="fmr-form-{form_id}">
<div class="card p-3">
{banner_html}
<form hx-post="/xhr/plugins/former/" hx-target="#fmr-form-{form_id}" hx-swap="outerHTML" {enctype}>
<input type="hidden" name="form_id" value="{form_id}">
{fields_html}
{captcha_html}
<div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
<input type="text" name="fmr_hp" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="sendtime" value="{sendtime}">
{hidden_csrf_token}
<button type="submit" class="btn btn-primary">{submit_label}</button>
</form>
</div>
</div>
