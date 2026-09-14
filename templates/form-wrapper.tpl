<div id="fmr-form-{form_id}">
<div class="card p-3">
{banner_html}
<form hx-post="/xhr/plugins/former/" hx-target="#fmr-form-{form_id}" hx-swap="outerHTML" {enctype}>
<input type="hidden" name="form_id" value="{form_id}">
{fields_html}
{captcha_html}
<div style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;" aria-hidden="true">
<input type="text" name="fmr_hp" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="sendtime" value="{sendtime}">
<input type="hidden" name="fmr_page_slug" value="{page_slug}">
{hidden_csrf_token}
<button type="submit" class="btn btn-primary">{submit_label}</button>
</form>
</div>
</div>
