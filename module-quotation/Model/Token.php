<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

/**
 * Checkout-link tokens: 32 random bytes, URL-safe; only the SHA-256 hash is stored on the quotation.
 */
class Token
{
    /** @return array{0: string, 1: string} [plain token for the link, hash to store] */
    public function generate(): array
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        return [$token, $this->hash($token)];
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function verify(string $token, ?string $storedHash): bool
    {
        return $token !== '' && $storedHash !== null && $storedHash !== ''
            && hash_equals($storedHash, $this->hash($token));
    }
}
