<style>
  :root{
    --p1:#8B5CF6;
    --p2:#6C63F5;
    --p3:#4F7FFA;
    --ink:#FFFFFF;
    --ink-soft:#C3C7E0;
    --ink-faint:#8A8FB5;
    --bg:#0A0D1A;
    --bg2:#0D1120;
    --card-solid:#161A30;
    --surface:#1E2340;
    --surface-hover:#262B4C;
    --border:#33395C;
    --success:#3FCF8E;
    --warn:#F5B84E;
    --radius-lg:24px;
    --radius-md:16px;
    --shadow:0 20px 55px -22px rgba(90,60,240,0.5);
    --font-display:'Outfit',sans-serif;
    --font-body:'Plus Jakarta Sans',sans-serif;
  }
  *{box-sizing:border-box;}
  body{
    margin:0;
    font-family:var(--font-body);
    background:var(--bg);
    color:var(--ink);
    min-height:100vh;
  }
  .page-bg{ position:fixed; inset:0; z-index:-2; overflow:hidden; background:var(--bg); }
  .blob{ position:absolute; border-radius:50%; filter:blur(110px); opacity:.28; }
  .blob1{ width:520px; height:520px; background:var(--p1); top:-200px; left:-180px; }
  .blob2{ width:460px; height:460px; background:var(--p3); bottom:-180px; right:-140px; }

  .layout{ display:grid; grid-template-columns:340px 1fr; min-height:100vh; }
  @media (max-width:880px){
    .layout{ grid-template-columns:1fr; }
    .sidebar{ position:relative !important; height:auto !important; padding-bottom:26px !important; }
  }

  .sidebar{
    position:sticky; top:0; height:100vh;
    background:linear-gradient(165deg,#0B0E1C 0%, #171045 55%, #101B4A 100%);
    color:#fff; padding:36px 30px; display:flex; flex-direction:column; overflow:hidden;
    border-right:1px solid var(--border);
  }
  .sidebar::before{
    content:''; position:absolute; width:280px; height:280px; border-radius:50%;
    background:radial-gradient(circle, rgba(139,92,246,.35), transparent 70%); top:-100px; right:-100px;
  }
  .brand{ position:relative; margin-bottom:44px; }
  .brand-name{
    font-family:var(--font-display); font-weight:700; font-size:16px; letter-spacing:.02em;
    display:flex; gap:5px;
  }
  .brand-name .e{ color:#B39BFF; }
  .brand-tag{ font-size:10px; letter-spacing:.08em; text-transform:uppercase; color:var(--ink-faint); margin-top:4px; }

  .avatar-wrap{ display:flex; justify-content:center; margin-bottom:26px; position:relative; }
  .avatar-blob{ width:150px; height:150px; position:relative; filter:drop-shadow(0 0 26px rgba(139,92,246,.55)); }

  .sidebar h2{ font-family:var(--font-display); font-size:20px; font-weight:700; line-height:1.35; margin:0 0 10px; text-align:center; }
  .sidebar p.flavor{ font-size:13.5px; line-height:1.6; color:#C9CCE8; text-align:center; margin:0; }

  .progress-list{ margin-top:auto; display:flex; flex-direction:column; gap:12px; padding-top:30px; }
  .progress-item{ display:flex; align-items:center; gap:11px; font-size:13px; font-weight:600; color:#7A7FA6; }
  .progress-item.active{ color:#fff; }
  .progress-item.done{ color:#C9CCE8; }
  .progress-dot{
    width:22px;height:22px;border-radius:50%; border:2px solid #3B3F63;
    display:flex;align-items:center;justify-content:center; font-size:11px; flex:0 0 auto;
  }
  .progress-item.active .progress-dot{ background:linear-gradient(135deg,var(--p1),var(--p3)); color:#fff; border-color:transparent; }
  .progress-item.done .progress-dot{ background:rgba(255,255,255,.12); color:#C9CCE8; border-color:#3B3F63; }

  main{ padding:54px 8vw 90px; display:flex; justify-content:center; }
  .content{ width:100%; max-width:600px; }

  .card{
    background:var(--card-solid); border-radius:var(--radius-lg); box-shadow:var(--shadow);
    padding:40px 38px; border:1px solid var(--border);
  }
  .eyebrow{ font-family:var(--font-display); font-size:12.5px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#B39BFF; margin:0 0 10px; }
  h1{ font-family:var(--font-display); font-size:29px; line-height:1.25; margin:0 0 10px; font-weight:700; color:var(--ink); }
  .subtitle{ color:var(--ink-soft); font-size:15px; line-height:1.6; margin:0 0 30px; }

  .field-label{ font-family:var(--font-display); font-size:15px; font-weight:600; margin:28px 0 4px; color:var(--ink); display:flex; align-items:center; justify-content:space-between; }
  .field-hint{ font-size:13px; color:var(--ink-soft); margin:0 0 16px; }
  .counter{ font-size:12px; font-weight:700; color:var(--ink-soft); background:rgba(255,255,255,.06); padding:4px 11px; border-radius:999px; }
  .counter.ok{ color:#08331F; background:var(--success); }

  .tile-grid{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .tile{
    border:1.5px solid var(--border); border-radius:18px; padding:16px 15px; cursor:pointer;
    display:flex; align-items:center; gap:12px; font-size:14px; font-weight:600; color:var(--ink);
    background:var(--surface);
    transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease, background .15s ease;
  }
  .tile:hover{ transform:translateY(-2px); border-color:var(--p2); background:var(--surface-hover); }
  .badge{ width:38px; height:38px; border-radius:12px; flex:0 0 auto; display:flex; align-items:center; justify-content:center; font-size:18px; }
  .tile.selected{
    border-color:var(--p1); background:linear-gradient(135deg,#3D2E70,#2A3564);
    box-shadow:0 12px 26px -16px rgba(139,92,246,.7);
  }
  .tile.wide{ grid-column:1 / -1; }

  .chip-row{ display:flex; flex-wrap:wrap; gap:9px; }
  .chip{
    border:1.5px solid var(--border); border-radius:999px; padding:9px 16px; font-size:13.5px; font-weight:600;
    cursor:pointer; background:var(--surface); color:var(--ink);
    transition:transform .12s ease, background .15s ease, border-color .15s ease, color .15s ease;
  }
  .chip:hover{ border-color:var(--p2); background:var(--surface-hover); }
  .chip.selected{ background:linear-gradient(135deg,var(--p1),var(--p3)); border-color:transparent; color:#fff; }
  .chip.checked-default{ background:var(--surface); border-color:var(--border); color:var(--ink-soft); }
  .chip.checked-default.selected{ background:#fff; border-color:#fff; color:#0B0E1C; }

  .helper-suggest{ margin-top:13px; font-size:13px; color:#B39BFF; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }

  .actions{ display:flex; justify-content:space-between; align-items:center; margin-top:36px; }
  button{ font-family:var(--font-display); border:none; cursor:pointer; font-weight:700; font-size:14.5px; border-radius:999px; padding:14px 28px; transition:.15s ease; }
  .btn-primary{ background:linear-gradient(135deg,var(--p1),var(--p3)); color:#fff; box-shadow:0 12px 26px -12px rgba(139,92,246,.65); }
  .btn-primary:disabled{ background:#2A2E45; color:#6E7396; box-shadow:none; cursor:not-allowed; }
  .btn-primary:not(:disabled):hover{ filter:brightness(1.1); transform:translateY(-1px); }
  .btn-ghost{ background:transparent; color:var(--ink-soft); padding:14px 8px; }
  .btn-ghost:hover{ color:var(--ink); }
  .btn-outline{ background:transparent; color:var(--ink); border:1.5px solid var(--border); }
  .btn-outline:hover{ border-color:var(--p2); }
  .btn-warn{ background:linear-gradient(135deg,#F5B84E,#F58E4E); color:#241300; }

  .intro-illustration{
    height:170px; border-radius:20px;
    background:radial-gradient(circle at 25% 30%, rgba(139,92,246,.55), transparent 60%),
               radial-gradient(circle at 80% 70%, rgba(79,127,250,.5), transparent 55%),
               #0D1128;
    border:1px solid var(--border);
    margin-bottom:28px; position:relative; overflow:hidden;
  }
  .intro-illustration span{ position:absolute; bottom:14px; left:18px; color:#C9CCE8; font-size:12.5px; font-weight:700; }

  .branch-reveal{ max-height:0; overflow:hidden; transition:max-height .4s ease; }
  .branch-reveal.open{ max-height:1200px; }
  .serious-note{
    margin-top:20px; background:rgba(139,92,246,.08); border:1px dashed var(--p2); border-radius:var(--radius-md);
    padding:17px 19px; font-size:13.5px; color:var(--ink-soft); display:flex; gap:11px; align-items:flex-start;
  }

  .swatch{ width:34px; height:34px; border-radius:50%; cursor:pointer; border:3px solid var(--card-solid); box-shadow:0 0 0 1.5px var(--border); transition:transform .12s ease, box-shadow .15s ease; }
  .swatch:hover{ transform:translateY(-2px); }
  .swatch.selected{ box-shadow:0 0 0 2.5px var(--p1); transform:translateY(-2px); }

  .summary-list{ display:flex; flex-direction:column; gap:0; border:1px solid var(--border); border-radius:var(--radius-md); overflow:hidden; margin-bottom:22px; }
  .summary-row{ display:flex; justify-content:space-between; gap:14px; padding:14px 18px; border-bottom:1px solid var(--border); font-size:13.5px; }
  .summary-row:last-child{ border-bottom:none; }
  .summary-row .k{ color:var(--ink-soft); font-weight:600; }
  .summary-row .v{ color:var(--ink); font-weight:700; text-align:right; }

  .ghost-avatar{
    height:130px; border-radius:var(--radius-md); border:1.5px dashed var(--border);
    display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px;
    color:var(--ink-faint); font-size:12.5px; font-weight:600; margin-bottom:24px;
    background:repeating-linear-gradient(135deg, rgba(255,255,255,.02) 0 10px, transparent 10px 20px);
  }

  .hype-box{
    margin-top:8px; padding:26px; border-radius:var(--radius-md);
    background:linear-gradient(135deg, rgba(139,92,246,.16), rgba(79,127,250,.1));
    border:1px solid var(--p2); text-align:center;
  }
  .hype-box h3{ font-family:var(--font-display); font-size:19px; margin:0 0 8px; }
  .hype-box p{ font-size:13.5px; color:var(--ink-soft); margin:0 0 20px; }
  .hype-actions{ display:flex; gap:10px; justify-content:center; flex-wrap:wrap; }

  .note{ text-align:center; font-size:12.5px; color:var(--ink-faint); margin-top:20px; }

  .tabbar{ display:flex; gap:8px; flex-wrap:wrap; margin-bottom:24px; }
  .tab{
    font-size:12.5px; font-weight:700; padding:9px 14px; border-radius:10px; cursor:pointer;
    border:1px solid var(--border); color:var(--ink-soft); background:var(--surface);
  }
  .tab.active{ background:linear-gradient(135deg,var(--p1),var(--p3)); color:#fff; border-color:transparent; }
  .tab.locked{ display:flex; align-items:center; gap:5px; }

  .ig-banner{
    display:flex; align-items:center; gap:12px; padding:14px 16px; border-radius:14px;
    background:rgba(63,207,142,.08); border:1px solid rgba(63,207,142,.35); margin-bottom:24px; font-size:13px; color:var(--ink-soft);
  }
  .ig-banner strong{ color:var(--ink); }

  .pane{ display:none; }
  .pane.active{ display:block; }
  .generic-pane{ padding:34px 20px; text-align:center; color:var(--ink-soft); font-size:13.5px; border:1px dashed var(--border); border-radius:var(--radius-md); }

  .locked-pane{
    padding:32px 26px; text-align:center; border-radius:var(--radius-md);
    background:rgba(245,184,78,.07); border:1px solid rgba(245,184,78,.3);
  }
  .coin-icon{ font-size:34px; margin-bottom:10px; }
  .locked-pane h3{ font-family:var(--font-display); font-size:17px; margin:0 0 8px; }
  .locked-pane p{ font-size:13.5px; color:var(--ink-soft); margin:0 0 20px; line-height:1.6; }

  /* Nuovi componenti — nessun riferimento nel prototipo (screen "components" mancante dal
     file consegnato), disegnati nello stesso linguaggio visivo: bordi/superfici della palette,
     stessi radius, stessa transizione .15s delle chip/tile già presenti. */
  .text-input{
    width:100%; border:1.5px solid var(--border); border-radius:var(--radius-md);
    background:var(--surface); color:var(--ink); font-family:var(--font-body); font-size:14.5px;
    padding:13px 16px; transition:border-color .15s ease, background .15s ease;
  }
  .text-input::placeholder{ color:var(--ink-faint); }
  .text-input:focus{ outline:none; border-color:var(--p2); background:var(--surface-hover); }

  .slider-group{ margin-bottom:22px; }
  .slider-labels{ display:flex; justify-content:space-between; font-size:12.5px; color:var(--ink-soft); font-weight:600; margin-bottom:8px; }
  .slider-group input[type="range"]{
    width:100%; appearance:none; -webkit-appearance:none; height:6px; border-radius:999px;
    background:var(--border); cursor:pointer; margin:0;
  }
  .slider-group input[type="range"]::-webkit-slider-thumb{
    appearance:none; -webkit-appearance:none; width:20px; height:20px; border-radius:50%;
    background:#fff; box-shadow:0 0 0 4px rgba(139,92,246,.45), 0 2px 8px rgba(0,0,0,.35); cursor:pointer;
  }
  .slider-group input[type="range"]::-moz-range-thumb{
    width:20px; height:20px; border-radius:50%; border:none;
    background:#fff; box-shadow:0 0 0 4px rgba(139,92,246,.45), 0 2px 8px rgba(0,0,0,.35); cursor:pointer;
  }

  .screen{ display:none; animation:fade .3s ease; }
  .screen.visible{ display:block; }
  @keyframes fade{ from{opacity:0; transform:translateY(10px);} to{opacity:1; transform:translateY(0);} }
</style>
