<?php

namespace AlyIcom\MyPackage;

use Illuminate\Support\ServiceProvider;
use AlyIcom\MyPackage\Services\Videos\GoogleDriveServiceVideo;
use AlyIcom\MyPackage\Services\NotificationService;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Messaging;

class MyPackageServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register Google Drive Service Video as singleton
        $this->app->singleton(GoogleDriveServiceVideo::class, function ($app) {
            return new GoogleDriveServiceVideo();
        });

        // Also register with an alias for easier access
        $this->app->alias(GoogleDriveServiceVideo::class, 'google.drive.video');

        // Register Firebase Messaging (if Firebase credentials are available)
        $this->app->singleton(Messaging::class, function ($app) {
            try {
                $firebaseCredentialsPath = config('my-package.firebase.credentials_path');
                if ($firebaseCredentialsPath && file_exists($firebaseCredentialsPath)) {
                    $factory = (new Factory)->withServiceAccount($firebaseCredentialsPath);
                    return $factory->createMessaging();
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Firebase Messaging not initialized: ' . $e->getMessage());
            }
            return null;
        });

        // Register Notification Service
        $this->app->singleton(NotificationService::class, function ($app) {
            $messaging = $app->make(Messaging::class);
            return new NotificationService($messaging);
        });

        // Also register with an alias for easier access
        $this->app->alias(NotificationService::class, 'notification.service');
    }

    public function boot()
    {
        // Boot package features
        // Load API routes
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        
        // Load views: $this->loadViewsFrom(__DIR__.'/../resources/views', 'my-package');
        // Load migrations: $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // Load translations: $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'my-package');

        // Publish assets (config, views, etc.)
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/my-package.php' => config_path('my-package.php'),
            ], 'config');

            // Add other publishable groups as needed (e.g., 'views', 'migrations')
        }
    }
}