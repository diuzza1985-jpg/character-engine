<?php
namespace App\Events;
use App\Models\TimelineEntry;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Punto di estensione per 11.4: emesso dal tick quando un life_event viene estratto e registrato.
 * Nessun listener registrato per ora — la descrizione in linguaggio naturale dell'evento resta un
 * TODO, da coprire in futuro con una singola chiamata GPT batch notturna su tutti gli eventi nati
 * (tutti i personaggi/tenant), non qui, non per singolo evento.
 */
class LifeEventOccurred
{
    use Dispatchable;

    public function __construct(public TimelineEntry $timelineEntry) {}
}
