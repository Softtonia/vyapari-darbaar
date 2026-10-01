<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'business_profiles';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'company_name',
        'contact_person',
        'business_type',
        'country_id',
        'state_id',
        'city_id',
        'address',
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
     * User associated with this business profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bankDetails()
    {
        return $this->hasMany(CompanyBankDetail::class, 'business_profile_id');
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

    public function businessCategories()
    {
        return $this->belongsToMany(BusinessCategory::class, 'company_business_categories', 'business_profile_id', 'business_category_id');
    }
}