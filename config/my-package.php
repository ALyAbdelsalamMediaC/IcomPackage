<?php
return [
    'google_drive' => [
        'redirect_uri' => env('GOOGLE_DRIVE_REDIRECT_URI'),
        'videos_folder_id' => env('GOOGLE_DRIVE_FOLDER_V_VIDEOS'),
    ],
    'firebase' => [
        'credentials_path' => env('FIREBASE_CREDENTIALS_PATH', storage_path('app/firebase-credentials.json')),
    ],
    'models' => [
        'notification' => env('NOTIFICATION_MODEL', 'App\Models\Notification'),
        'user' => env('USER_MODEL', 'App\Models\User'),
        'check_update' => env('CHECK_UPDATE_MODEL', 'App\Models\CheckUpdate'),
    ],
    'api' => [
        'prefix' => env('MY_PACKAGE_API_PREFIX', 'api/v1'),
        'guard' => env('MY_PACKAGE_API_GUARD', 'api'),
        'middleware' => env('MY_PACKAGE_API_MIDDLEWARE', 'api') ? explode(',', env('MY_PACKAGE_API_MIDDLEWARE', 'api')) : ['api'],
    ],
    'translations' => [
        'namespace' => env('MY_PACKAGE_TRANSLATION_NAMESPACE', 'user_validation'),
    ],
];