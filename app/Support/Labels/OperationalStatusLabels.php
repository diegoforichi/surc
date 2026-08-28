<?php

namespace App\Support\Labels;

class OperationalStatusLabels
{
    public static function payment(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmado',
            default => $status,
        };
    }

    public static function paymentType(string $type): string
    {
        return match ($type) {
            'deposit' => 'Seña',
            'charge' => 'Cargo',
            'full' => 'Pago',
            default => $type,
        };
    }

    public static function import(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'completed' => 'Completada',
            'completed_with_errors' => 'Completada con errores',
            default => $status,
        };
    }

    public static function importTarget(string $target): string
    {
        return match ($target) {
            'subjects' => terminology('subject', 'Sujetos'),
            'parties' => 'Actores',
            'cases' => terminology('case', 'Casos'),
            default => $target,
        };
    }

    public static function publicContentType(string $type): string
    {
        return match ($type) {
            'carousel' => 'Carrusel',
            'blog' => 'Blog',
            'page' => 'Página',
            default => $type,
        };
    }

    public static function entityType(string $type): string
    {
        return match ($type) {
            'party' => 'Actor',
            'subject' => terminology('subject', 'Sujeto'),
            'case' => terminology('case', 'Caso'),
            default => $type,
        };
    }
}
