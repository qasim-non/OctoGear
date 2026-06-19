<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $table = 'users';


    protected $fillable = [
        'full_name',
        'mobile',
        'city_id',
        'status',
    ];


    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function customerCars()
    {
        return $this->hasMany(CustomerCar::class, 'customer_id');
    }

    public function customerConversations()
    {
        return $this->hasMany(Conversation::class, 'customer_id');
    }

    public function providerConversations()
    {
        return $this->hasMany(Conversation::class, 'provider_id');
    }
}
