<?php
require __DIR__.'/../global/bootstrap.php';

// No page heading - see the same note in start.php.
echo '<div class="row">';

echo '<div class="col-md-6">';
echo '<div class="card">';
echo '<div class="card-header">'.$addon_lang['title_recipients'].'</div>';
echo '<div class="card-body" hx-get="/admin-xhr/addons/plugin/former/read/?show=recipients" hx-trigger="load, update_former_recipients from:body">LOADING ...</div>';
echo '</div>';
echo '</div>';

echo '<div class="col-md-6">';
echo '<div class="card">';
echo '<div class="card-header">'.$addon_lang['title_captcha'].' &amp; '.$addon_lang['title_upload_settings'].'</div>';
echo '<div class="card-body" hx-get="/admin-xhr/addons/plugin/former/read/?show=settings_form" hx-trigger="load, update_former_settings from:body">LOADING ...</div>';
echo '</div>';
echo '</div>';

echo '</div>';
