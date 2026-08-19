<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Enums\UserType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'full_name',
        'mobile',
        'type',
        'city_id',
        'status',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            'status' => UserStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function customerCars(): HasMany
    {
        return $this->hasMany(CustomerCar::class, 'customer_id');
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function customerConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'customer_id');
    }

    public function providerConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'provider_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function serviceProviderRequests(): HasMany
    {
        return $this->hasMany(ServiceProviderRequest::class);
    }

    public function storeRequests(): HasMany
    {
        return $this->hasMany(StoreRequest::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class, 'customer_id');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function isCustomer(): bool
    {
        return $this->type === UserType::Customer;
    }

    public function isProvider(): bool
    {
        return $this->type === UserType::ServiceProvider;
    }

    public function isBlocked(): bool
    {
        return $this->status === UserStatus::Blocked;
    }
}
