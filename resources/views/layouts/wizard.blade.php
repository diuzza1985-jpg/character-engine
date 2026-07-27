<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title ?? 'Character Engine — crea il tuo personaggio' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
{{-- Nessun @vite qui: pagina pubblica autonoma, CSS del prototipo portato 1:1 (partial condiviso
     con layouts.public), coerente con "il markup/CSS del prototipo va portato così com'è" (spec
     tecnica §1). Livewire inietta da solo Alpine.js e i propri asset in qualunque pagina che
     renderizza un suo componente (SupportAutoInjectedAssets) — non serve altro caricato a mano. --}}
@include('layouts.partials.wizard-styles')
</head>
<body>

<div class="page-bg">
  <div class="blob blob1"></div>
  <div class="blob blob2"></div>
</div>

{{ $slot }}

</body>
</html>
