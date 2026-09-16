<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Types a row in `zaak_relaties`. Every type fixes its own reading
 * direction as a sentence: `zaak_id` (subject) performs the type (verb)
 * on `gerelateerde_zaak_id` (object). A new type must document that
 * direction (or explicit symmetry), its write location and its read
 * location in the UI before it is introduced — otherwise this generic
 * table grows the exact wild growth it is meant to prevent.
 */
enum ZaakRelatieType: string
{
    /**
     * The definitive aanvraag (`zaak_id`) replaces the vooraankondiging
     * (`gerelateerde_zaak_id`). Written on submit of an aanvraag that
     * was linked to a vooraankondiging; read on both zaak screens and
     * by the calendar filter that hides the replaced vooraankondiging.
     */
    case VervangtVooraankondiging = 'vervangt_vooraankondiging';
}
