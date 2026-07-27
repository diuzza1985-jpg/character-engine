<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'Character Engine')</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
@include('layouts.partials.wizard-styles')
</head>
<body>

<div class="page-bg">
  <div class="blob blob1"></div>
  <div class="blob blob2"></div>
</div>

<main style="padding:54px 8vw 90px; display:flex; justify-content:center;">
  <div style="width:100%; max-width:600px;">
    @yield('content')
  </div>
</main>

</body>
</html>
