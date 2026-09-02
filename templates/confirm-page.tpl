<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{title}</title>
<style>
body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:#f4f5f7; color:#1c1e21; margin:0; padding:48px 16px; display:flex; justify-content:center; }
.fmr-confirm-card { max-width:480px; width:100%; background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); padding:32px; text-align:center; box-sizing:border-box; }
.fmr-confirm-card h1 { font-size:1.25rem; margin:0 0 12px; }
.fmr-confirm-card p { line-height:1.5; color:#444; margin:0; }
.fmr-confirm-card form { margin:0; }
.fmr-confirm-card button { display:inline-block; margin-top:20px; padding:10px 28px; background:#2563eb; color:#fff; border:none; border-radius:6px; font-size:1rem; cursor:pointer; }
.fmr-confirm-card button:hover { background:#1d4ed8; }
</style>
</head>
<body>
<div class="fmr-confirm-card">
<h1>{heading}</h1>
<p>{message}</p>
{action_html}
</div>
{tracking_script}
</body>
</html>
