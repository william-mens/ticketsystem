<?php

return [

    'debug'       => env('APP_DEBUG_PDF', true),
    'binpath'     => '/opt/bitnami/projects/ticketsystem/vendor/nitmedia/wkhtml2pdf/src/Nitmedia/Wkhtml2pdf/lib/',
    'binfile'     => env('WKHTML2PDF_BIN_FILE', 'wkhtmltopdf-amd64'),
    'output_mode' => 'F',
];
