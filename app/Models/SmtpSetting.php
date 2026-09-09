<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmtpSetting extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'smtp_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'mailer',
        'host',
        'port',
        'username',
        'password',
        'from_email',
        'from_address',
        'from_name',
        'encryption',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'port' => 'integer',
        ];
    }

    /**
     * Accessor & Mutator for from_address alias.
     */
    protected function fromAddress(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => $attributes['from_email'] ?? $value ?? null,
            set: fn ($value) => ['from_email' => $value],
        );
    }

    /**
     * Check whether the SMTP setting is active.
     */
    public function isActive(): bool
    {
        return in_array(strtolower((string) $this->status), ['active', '1', 'true'], true);
    }
}
