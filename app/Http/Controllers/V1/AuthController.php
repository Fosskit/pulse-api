<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Models\UserProvider;
use App\StandardResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    use StandardResponse;
    /**
     * @unauthenticated
     * Register
     *
     * Register new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->ulid = Str::ulid()->toBase32();
        $user->save();
        $user->assignRole('user');
        event(new Registered($user));

        // Issue Passport token using Password Grant
        $response = Http::post(config('services.passport.login_endpoint', url('/oauth/token')), [
            'grant_type' => 'password',
            'client_id' => config('passport.password_client_id'),
            'client_secret' => config('passport.password_client_secret'),
            'username' => $request->email,
            'password' => $request->password,
            'scope' => '',
        ]);

        $tokenData = $response->json();

        return response()->json([
            'user' => $user,
            'access_token' => $tokenData['access_token'] ?? null,
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'access_expires_at' => isset($tokenData['expires_in']) ? now()->addSeconds($tokenData['expires_in'])->toDateTimeString() : null,
            'message' => __('Successfully registered'),
            'ok' => true,
        ]);
    }

    /**
     * @unauthenticated
     * Provider Redirect
     *
     * Redirect to provider for authentication
     */
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->stateless()->redirect();
    }

    /**
     * @unauthenticated
     * Provider Callback
     *
     * Handle callback from provider
     * @throws \Exception
     */
    public function callback(Request $request, string $provider): View
    {
        $oAuthUser = Socialite::driver($provider)->stateless()->user();

        if (!$oAuthUser?->token) {
            return view('oauth', [
                'message' => [
                    'ok' => false,
                    'message' => __('Unable to authenticate with :provider', ['provider' => $provider]),
                ],
            ]);
        }

        $userProvider = UserProvider::select('id', 'user_id')
            ->where('name', $provider)
            ->where('provider_id', $oAuthUser->id)
            ->first();

        if (!$userProvider) {
            if (User::where('email', $oAuthUser->email)->exists()) {
                return view('oauth', [
                    'message' => [
                        'ok' => false,
                        'message' => __('Unable to authenticate with :provider. User with email :email already exists. To connect a new service to your account, you can go to your account settings and go through the process of linking your account.', [
                            'provider' => $provider,
                            'email' => $oAuthUser->email,
                        ]),
                    ],
                ]);
            }

            $user = new User();
            $user->ulid = Str::ulid()->toBase32();
            $user->avatar = $oAuthUser->picture ?? $oAuthUser->avatar_original ?? $oAuthUser->avatar;
            $user->name = $oAuthUser->name;
            $user->email = $oAuthUser->email;
            $user->password = null;
            $user->email_verified_at = now();
            $user->save();

            $user->assignRole('user');

            $user->userProviders()->create([
                'provider_id' => $oAuthUser->id,
                'name' => $provider,
            ]);
        } else {
            $user = $userProvider->user;
        }

        // Issue Passport token for social login
        $tokenResult = $user->createToken($request->deviceName() ?? 'oauth-device');
        $token = $tokenResult->accessToken;

        return view('oauth', [
            'message' => [
                'ok' => true,
                'provider' => $provider,
                'token' => $token,
            ],
        ]);
    }

    /**
     * @unauthenticated
     * Login
     *
     * Generating Passport token and return user and the token
     * @throws ValidationException
     * @response array{user: User, access_token: string, access_expires_at: string}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Use Laravel's HTTP client to request Passport token
        $response = \Illuminate\Support\Facades\Http::post(config('services.passport.login_endpoint', url('/oauth/token')), [
            'grant_type' => 'password',
            'client_id' => config('passport.password_client_id'),
            'client_secret' => config('passport.password_client_secret'),
            'username' => $request->email,
            'password' => $request->password,
            'scope' => '*',
        ]);
        if ($response->failed()) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }
        $tokenData = $response->json();
        return response()->json([
            'user' => $user,
            'access_token' => $tokenData['access_token'] ?? null,
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'access_expires_at' => isset($tokenData['expires_in']) ? now()->addSeconds($tokenData['expires_in'])->toDateTimeString() : null,
        ]);
    }

    /**
     * Logout
     *
     * Revoke Passport token
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();

        return $this->success(__('Successfully logged out'));
    }

    /**
     * User
     *
     * Get authenticated user details
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return
            $this->success(
            __('Successfully get user info'),
            [
                ...$user->toArray(),
                'must_verify_email' => $user->mustVerifyEmail(),
                'has_password' => (bool) $user->password,
                'roles' => $user->roles()->select('name')->pluck('name'),
                'providers' => $user->userProviders()->select('name')->pluck('name'),
            ]);
    }

    /**
     * Request Reset Password
     *
     * Handle an incoming password reset link request.
     * @throws ValidationException
     */
    public function sendResetPasswordLink(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => __($status),
        ]);
    }

    /**
     *
     * Reset Password
     *
     * Handle an incoming new password request.
     * @throws ValidationException
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            static function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return $this->success($status);

    }

    /**
     * Verify Email
     *
     * Mark the authenticated user's email address as verified.
     */
    public function verifyEmail(Request $request, string $ulid, string $hash): JsonResponse
    {
        $user = User::where('ulid', $ulid)->first();

        abort_if(!$user, 404);
        abort_if(!hash_equals(sha1($user->getEmailForVerification()), $hash), 403, __('Invalid verification link'));

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        return $this->success();
    }

    /**
     *
     * Verification Notification
     *
     * Send a new email verification notification.
     */
    public function verificationNotification(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = $request->user()?: User::where('email', $request->email)->whereNull('email_verified_at')->first();

        abort_if(!$user, 400);

        $user->sendEmailVerificationNotification();

        return $this->success(__('Verification link sent!'));
    }

    /**
     * Get Devices
     *
     * Get authenticated user devices (Passport tokens)
     */
    public function devices(Request $request): JsonResponse
    {
        $user = $request->user();

        $devices = $user->tokens()
            ->select('id', 'name', 'revoked', 'created_at', 'expires_at')
            ->orderBy('created_at', 'DESC')
            ->get();

        $currentToken = $user->token();

        foreach ($devices as $device) {
            $device->hash = Crypt::encryptString($device->id);
            $device->is_current = ($currentToken && $currentToken->id === $device->id);
            unset($device->id);
        }

        return $this->success(__('Devices available'), $devices);
    }

    /**
     * Device Disconnect
     *
     * Revoke Passport token by id
     */
    public function deviceDisconnect(Request $request): JsonResponse
    {
        $request->validate([
            'hash' => 'required',
        ]);

        $user = $request->user();
        $id = (int) Crypt::decryptString($request->hash);

        if (!empty($id)) {
            $user->tokens()->where('id', $id)->update(['revoked' => true]);
        }

        return $this->success(__('Device disconnected'));
    }

    /**
     * @unauthenticated
     * Refresh Token
     *
     * Refresh access token using refresh token
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $response = Http::post(url('/oauth/token'), [
            'grant_type' => 'refresh_token',
            'refresh_token' => $request->refresh_token,
            'client_id' => config('passport.password_client_id'),
            'client_secret' => config('passport.password_client_secret'),
            'scope' => '',
        ]);

        $tokenData = $response->json();

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'refresh_token' => [__('Invalid refresh token')],
            ]);
        }

        return response()->json([
            'access_token' => $tokenData['access_token'] ?? null,
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'access_expires_at' => isset($tokenData['expires_in']) ? now()->addSeconds($tokenData['expires_in'])->toDateTimeString() : null,
        ]);
    }
}
