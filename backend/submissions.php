<?php
require __DIR__.'/../global/bootstrap.php';

// This is both the "Einsendungen" tab itself (no form_id - all forms) and
// the target of the "Einsendungen" button on a form row in the Formulare
// list (form_id set - pre-filtered). Same page either way, just a
// different initial value for the filter below - no separate per-form
// page anymore, and no page heading (this being its own tab now makes one
// redundant, same as start.php/settings.php).
$form_id = (int) ($_GET['form_id'] ?? 0);
$forms = $former_db->select('forms', ['id', 'name'], ['ORDER' => ['name' => 'ASC']]);

// A stale/unknown form_id (deleted form, tampered URL) falls back to "all"
// rather than showing an empty/broken filtered view.
if ($form_id > 0 && !in_array($form_id, array_column($forms, 'id'))) {
    $form_id = 0;
}

echo '<div class="row">';

echo '<div class="col-md-9">';
echo '<div id="formSubmissionsList" hx-get="/admin-xhr/addons/plugin/former/read/?show=submissions&form_id='.$form_id.'&page=1" hx-trigger="load">LOADING ...</div>';
echo '</div>';

echo '<div class="col-md-3">';
echo '<div class="card">';
echo '<div class="card-header">'.$addon_lang['label_submissions_filter'].'</div>';
echo '<div class="card-body">';
echo '<select class="form-select" onchange="location.href = \'/admin/addons/plugin/former/submissions/\' + (this.value ? (\'?form_id=\' + this.value) : \'\')">';
echo '<option value=""'.($form_id === 0 ? ' selected' : '').'>'.$addon_lang['option_submissions_all'].'</option>';
foreach ($forms as $f) {
    $selected = ((int) $f['id'] === $form_id) ? ' selected' : '';
    echo '<option value="'.$f['id'].'"'.$selected.'>'.htmlspecialchars($f['name']).'</option>';
}
echo '</select>';
echo '</div>';
echo '</div>';

// Small stats card under the filter, scoped to the same $form_id filter as
// the dropdown above it (page reloads on select, so a plain PHP render here
// stays in sync without its own htmx round-trip). Counts are cheap - a
// handful of COUNT()/GROUP BY queries against submissions.id, which is the
// table's primary key.
$stats_conditions = $form_id > 0 ? ['form_id' => $form_id] : [];
$total_count = $former_db->count('submissions', $stats_conditions);
$this_month_count = $former_db->count('submissions', $stats_conditions + [
    'created_at[>=]' => date('Y-m-01 00:00:00'),
]);

// Last 6 months (this one included), oldest first. Grouped in one query via
// strftime rather than 6 separate COUNT() calls, then zero-filled so a month
// without any submissions still shows up as "0" instead of being skipped.
$six_months_ago = date('Y-m-01 00:00:00', strtotime('-5 months'));
$monthly_params = [':from' => $six_months_ago];
$monthly_sql = "SELECT strftime('%Y-%m', created_at) AS ym, COUNT(*) AS cnt FROM submissions WHERE created_at >= :from";
if ($form_id > 0) {
    $monthly_sql .= ' AND form_id = :form_id';
    $monthly_params[':form_id'] = $form_id;
}
$monthly_sql .= ' GROUP BY ym';
$monthly_rows = $former_db->query($monthly_sql, $monthly_params)->fetchAll();
$counts_by_month = array_column($monthly_rows, 'cnt', 'ym');

$months = [];
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i months"));
    $months[$ym] = (int) ($counts_by_month[$ym] ?? 0);
}

echo '<div class="card mt-3">';
echo '<div class="card-header">'.$addon_lang['title_submissions_stats'].'</div>';
// list-group-flush wants to be a direct child of .card (not .card-body) -
// that's what gives it the edge-to-edge look and lets the card draw the
// separator lines between it and its siblings.
echo '<ul class="list-group list-group-flush">';
echo '<li class="list-group-item d-flex justify-content-between align-items-center">'.$addon_lang['label_stats_total'].' <strong>'.$total_count.'</strong></li>';
echo '<li class="list-group-item d-flex justify-content-between align-items-center">'.$addon_lang['label_stats_this_month'].' <strong>'.$this_month_count.'</strong></li>';
echo '</ul>';
echo '<div class="card-body py-2">';
echo '<div class="small text-muted">'.$addon_lang['label_stats_last_6_months'].'</div>';
echo '</div>';
echo '<ul class="list-group list-group-flush">';
foreach ($months as $ym => $count) {
    echo '<li class="list-group-item d-flex justify-content-between align-items-center py-1">'.date('m/Y', strtotime($ym.'-01')).' <span>'.$count.'</span></li>';
}
echo '</ul>';
echo '</div>';

echo '</div>'; // col-md-3

echo '</div>'; // row
