<?php

namespace Santosdave\VerteilWrapper\Security;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

trait EncryptsData
{
    /**
     * Encrypt sensitive data
     * 
     * @param string $value
     * @return string
     */
    protected function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    /**
     * Decrypt sensitive data
     * 
     * @param string $encrypted
     * @return string|null
     */
    protected function decrypt(string $encrypted): ?string
    {
        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException $e) {
            return null;
        }
    }
}
