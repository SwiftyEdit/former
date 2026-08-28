<?php
require __DIR__.'/../global/bootstrap.php';

// No page heading here - the "Formulare" tab itself already says where we
// are, an <h1> repeating that would be pure noise. Only the drilled-down
// sub-pages (form-editor.php, submissions.php) that aren't their own tab
// get a small breadcrumb-style heading, since those need to say *which*
// form you're on.
echo '<div class="row">';
echo '<div class="col-md-12">';
echo '<div hx-get="/admin-xhr/addons/plugin/former/read/?show=forms_list" hx-trigger="load, update_former_forms from:body">';
echo 'LOADING ...';
echo '</div>';
echo '</div>';
echo '</div>';
