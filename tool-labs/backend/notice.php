<?php
declare(strict_types=1);

if (true) {
    /* configure */
    $type = 'warn';
    $message = 'This tool is currently receiving extreme bot traffic. It may be intermittently unavailable while mitigations are being worked on.';

    /* render */
    echo '<div class="global-notice is-', $type, '">', $message, '</div>';
}
