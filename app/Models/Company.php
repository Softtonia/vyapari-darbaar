<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Company extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'companies';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'contact_person',
        'business_type',
        'gstin',
        'pan_number',
        'year_of_establishment',
        'business_category',
        'no_of_employees',
        'website',
        'country_id',
        'state_id',
        'city_id',
        'address',
        'address_line_2',
        'pin_code',
        'commodities_handled',
        'trade_preference',
        'verification_status',
        'business_description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commodities_handled' => 'array',
        ];
    }

    /**
     * Users associated with this company.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_has_companies')
            ->withPivot('role', 'is_primary')
            ->withTimestamps();
    }

    public function bankDetails()
    {
        return $this->hasMany(CompanyBankDetail::class);
    }

    public function kycDocuments()
    {
        return $this->hasMany(CompanyKycDocument::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
