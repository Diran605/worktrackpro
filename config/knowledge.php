<?php

return [
    // Private disk for screenshots, uploaded PDFs and imported Word files.
    'disk' => env('KNOWLEDGE_DISK', 'local'),

    // Upload limits (kilobytes).
    'max_image_kb' => (int) env('KNOWLEDGE_MAX_IMAGE_KB', 5120),
    'max_pdf_kb' => (int) env('KNOWLEDGE_MAX_PDF_KB', 51200),
    'max_docx_kb' => (int) env('KNOWLEDGE_MAX_DOCX_KB', 20480),

    // qpdf binary (https://qpdf.sourceforge.io). Optional in Phase 2 (used to count PDF pages and
    // validate page ranges); required in Phase 3 to merge uploaded PDFs into a generated manual.
    'qpdf_path' => env('QPDF_PATH', 'qpdf'),

    // Owners are reminded to re-verify sections older than this.
    'stale_after_days' => (int) env('KNOWLEDGE_STALE_AFTER_DAYS', 90),
];
