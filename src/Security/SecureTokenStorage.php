<?php

namespace Santosdave\VerteilWrapper\Security;

use Illuminate\Support\Facades\Cache;

class SecureTokenStorage
{
    use EncryptsData;

    protected int $tokenExpiry;

    protected string $key;

    /**
     * @param  string  $scope  the account the token belongs to, so several accounts in one
     *                         application never share or overwrite a token; empty for the
     *                         old single, app-wide token
     */
    public function __construct(int $tokenExpiry = 55, string $scope = '')
    {
        $this->tokenExpiry = $tokenExpiry;
        $this->key = 'verteil_token' . ($scope !== '' ? '_' . $scope : '');
    }

    /**
     * Store token securely
     * 
     * @param string $token
     * @return void
     */
    public function storeToken(string $token): void
    {
        $encryptedToken = $this->encrypt($token);
        Cache::put($this->key, $encryptedToken, now()->addMinutes($this->tokenExpiry));
    }

    /**
     * Retrieve stored token
     * 
     * @return string|null
     */
    public function retrieveToken(): ?string
    {
        $encryptedToken = Cache::get($this->key);
        if (!$encryptedToken) {
            return null;
        }

        return $this->decrypt($encryptedToken);
    }

    /**
     * Check if token exists and is valid
     * 
     * @return bool
     */
    public function hasValidToken(): bool
    {
        return Cache::has($this->key) && $this->retrieveToken() !== null;
    }

    /**
     * Clear stored token
     * 
     * @return void
     */
    public function clearToken(): void
    {
        Cache::forget($this->key);
    }

    /**
     * Get token expiry time in minutes
     * 
     * @return int
     */
    public function getTokenExpiry(): int
    {
        return $this->tokenExpiry;
    }

    /**
     * Set token expiry time in minutes
     * 
     * @param int $minutes
     * @return void
     */
    public function setTokenExpiry(int $minutes): void
    {
        $this->tokenExpiry = $minutes;
    }
}
