<?php

// Only the messages that differ from Laravel's own (the rest come from the framework).
return [
    // PHP refused the file before the app saw it: almost always the server's upload size limit.
    'uploaded' => \App\Support\UploadLimits::tooLargeMessage(),
];
