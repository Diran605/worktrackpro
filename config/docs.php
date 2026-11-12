<?php

return [
    // Slug of the organisation whose library is shown at "/" on the main site.
    // Empty = the first active organisation.
    'default_organisation' => env('DOCS_DEFAULT_ORGANISATION'),

    // How long an entered access code keeps working in a browser (days).
    'code_session_days' => (int) env('DOCS_CODE_SESSION_DAYS', 30),
];
