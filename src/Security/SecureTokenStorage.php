<?php

namespace Santosdave\VerteilWrapper\Security;

use Illuminate\Support\Facades\Cache;

class SecureTokenStorage
{
    use EncryptsData;

    protected int $tokenExpiry;

    public function __construct(int $tokenExpiry = 55)
    {
        $this->tokenExpiry = $tokenExpiry;
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
        Cache::put('verteil_token', $encryptedToken, now()->addMinutes($this->tokenExpiry));
    }

    /**
     * Retrieve stored token
     * 
     * @return string|null
     */
    public function retrieveToken(): ?string
    {
        $encryptedToken = Cache::get('verteil_token');
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
        return Cache::has('verteil_token') && $this->retrieveToken() !== null;
    }

    /**
     * Clear stored token
     * 
     * @return void
     */
    public function clearToken(): void
    {
        Cache::forget('verteil_token');
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
