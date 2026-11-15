<?php

return [
    // Edición del reglamento que se carga por defecto
    'edition' => env('IPSC_EDITION', 'January 2026'),

    // Preguntas al mes por árbitro (se puede ajustar por usuario) y tope global del mes
    'default_quota' => (int) env('IPSC_DEFAULT_QUOTA', 60),
    'global_monthly_limit' => (int) env('IPSC_GLOBAL_LIMIT', 3000),

    // Exámenes que puede empezar cada árbitro al mes
    'exams_per_month' => (int) env('IPSC_EXAMS_PER_MONTH', 10),

    // Invitaciones
    'invitation_days' => 14,

    // Fragmentos de reglamento que se envían al modelo en cada consulta
    'context_passages' => 12,
];
