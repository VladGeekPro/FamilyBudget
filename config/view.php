<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most applications only use one view path, but this array allows you to
    | configure multiple locations where Blade templates are stored.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | This path is where compiled Blade templates are stored. On Windows,
    | placing this cache under %LOCALAPPDATA% avoids occasional file lock
    | and antivirus conflicts in project directories.
    |
    */

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        storage_path('framework/views')
    ),

];
