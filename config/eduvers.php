<?php

/*
| EduVers application settings.
*/

return [

    // Minutes without activity before a signed-in user is signed out (0 = never).
    'idle_minutes' => (int) env('IDLE_TIMEOUT_MINUTES', 30),

    'notifications' => [
        // Also email students (in addition to the in-app bell) when a lesson,
        // quiz or announcement is posted. Set NOTIFICATIONS_MAIL=false to turn
        // email alerts off without touching the in-app notifications.
        'mail' => (bool) env('NOTIFICATIONS_MAIL', true),

        // Students notified per batch when fanning out (keeps memory flat).
        'chunk' => 200,

        // Whether docker/entrypoint.sh starts a queue worker (shown on System check).
        'queue_worker' => (bool) env('RUN_QUEUE_WORKER', true),
    ],

];
