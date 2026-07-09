<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class PlatformCredential extends Model
{
    use HasFactory;

    protected $table = 'platform_credentials';

    protected $fillable = [
        'user_id',
        'platform',
        'api_key',
        'api_secret',
        'access_token',
        'access_token_secret',
        'bearer_token',
        'page_access_token',
        'page_id',
        'li_access_token',
        'li_person_urn',
        'is_verified',
        'connected_username',
        'last_verified_at',
    ];

    protected $casts = [
        'is_verified'      => 'boolean',
        'last_verified_at' => 'datetime',
    ];

    // Fields to auto-encrypt/decrypt
    private static array $encryptedFields = [
        'api_key', 'api_secret', 'access_token', 'access_token_secret',
        'bearer_token', 'page_access_token', 'li_access_token',
    ];

    // Dynamic getters - decrypt on read
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        if (in_array($key, self::$encryptedFields) && !empty($value)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return $value; // Return as-is if not encrypted
            }
        }
        return $value;
    }

    // Dynamic setters - encrypt on write
    public function setAttribute($key, $value)
    {
        if (in_array($key, self::$encryptedFields) && !empty($value)) {
            $value = Crypt::encryptString($value);
        }
        return parent::setAttribute($key, $value);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
