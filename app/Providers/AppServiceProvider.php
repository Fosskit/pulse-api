<?php

namespace App\Providers;

use App\Helpers\Image;
use App\Models\PersonalAccessToken;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Jenssegers\Agent\Agent;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Passport::enablePasswordGrant();

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });

        Blueprint::macro('commonFields', function () {
            $this->timestamp('created_at')->useCurrent()->index();
            $this->foreignId('created_by')->nullable()->index();
            $this->timestamp('updated_at')->nullable()->useCurrentOnUpdate()->index();
            $this->foreignId('updated_by')->nullable()->index();
            $this->softDeletes($column = 'deleted_at', $precision = 0)->index();
            $this->foreignId('deleted_by')->nullable()->index();
        });

        RateLimiter::for('api', static function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('verification-notification', static function (Request $request) {
            return Limit::perMinute(1)->by($request->user()?->email ?: $request->ip());
        });

        RateLimiter::for('uploads', static function (Request $request) {
            return $request->user()?->hasRole('admin')
                ? Limit::none()
                : Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('login', static function (Request $request) {
            return Limit::perMinute(5)
                ->by(Str::transliterate(implode('|', [
                    strtolower($request->input('email')),
                    $request->ip()
                ])))
                ->response(static function (Request $request, array $headers): void {
                    event(new Lockout($request));

                    throw ValidationException::withMessages([
                        'email' => trans('auth.throttle', [
                            'seconds' => $headers['Retry-After'],
                            'minutes' => ceil($headers['Retry-After'] / 60),
                        ]),
                    ]);
                });
        });

        ResetPassword::createUrlUsing(static function (object $notifiable, string $token) {
            return config('app.frontend_url') . '/auth/reset/' . $token . '?email=' . $notifiable->getEmailForPasswordReset();
        });

        VerifyEmail::createUrlUsing(static function (object $notifiable) {
            $url = url()->temporarySignedRoute(
                'api.v1.auth.verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'ulid' => $notifiable->ulid,
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            return config('app.frontend_url') . '/auth/verify?verify_url=' . urlencode($url);
        });

        /**
         * Convert uploaded image to webp, jpeg or png format and resize it
         */
        UploadedFile::macro('convert', function (?int $width = null, ?int $height = null, string $extension = 'webp', int $quality = 90) {
            return tap($this, static function (UploadedFile $file) use ($width, $height, $extension, $quality) {
                Image::convert($file->path(), $file->path(), $width, $height, $extension, $quality);
            });
        });

        /**
         * Remove all special characters from a string
         */
        Str::macro('onlyWords', static function (string $text): string {
            // \p{L} matches any kind of letter from any language
            // \d matches a digit in any script
            return Str::replaceMatches('/[^\p{L}\d ]/u', '', $text);
        });

        //TODO: Associate device with user token
        Request::macro('deviceName', function (): string {
            return 'null';
        });
    }
}
