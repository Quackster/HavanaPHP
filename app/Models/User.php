<?php

namespace App\Models;

use App\Support\HousekeepingRank;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'username',
        'password',
        'figure',
        'sex',
        'email',
        'motto',
        'remember_token',
        'sso_ticket',
        'selected_room_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'sso_ticket',
    ];

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getName(): string
    {
        return (string) $this->username;
    }

    public function getUsername(): string
    {
        return (string) $this->username;
    }

    public function getId(): int
    {
        return (int) $this->id;
    }

    public function getEmail(): string
    {
        return (string) $this->email;
    }

    public function getMotto(): string
    {
        return (string) $this->motto;
    }

    public function getFigure(): string
    {
        return (string) $this->figure;
    }

    public function getPoolFigure(): string
    {
        return (string) $this->pool_figure;
    }

    public function hasClubSubscription(): bool
    {
        return (int) $this->club_expiration > time();
    }

    public function isTradeEnabled(): bool
    {
        return (bool) $this->trade_enabled;
    }

    public function isOnlineStatusVisible(): bool
    {
        return (bool) $this->online_status_visible;
    }

    public function doesAllowStalking(): bool
    {
        return (bool) $this->allow_stalking;
    }

    public function isProfileVisible(): bool
    {
        return (bool) $this->profile_visible;
    }

    public function isAllowFriendRequests(): bool
    {
        return (bool) $this->allow_friend_requests;
    }

    public function isWordFilterEnabled(): bool
    {
        return (bool) $this->wordfilter_enabled;
    }

    public function isOnline(): bool
    {
        return (bool) $this->is_online;
    }

    public function getSsoTicket(): string
    {
        return (string) $this->sso_ticket;
    }

    public function getSelectedRoomId(): int
    {
        return (int) $this->selected_room_id;
    }

    public function getRank(): HousekeepingRank
    {
        return new HousekeepingRank((int) $this->rank);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'last_online' => 'datetime',
            'is_online' => 'boolean',
            'allow_stalking' => 'boolean',
            'allow_friend_requests' => 'boolean',
            'online_status_visible' => 'boolean',
            'profile_visible' => 'boolean',
            'wordfilter_enabled' => 'boolean',
            'trade_enabled' => 'boolean',
            'sound_enabled' => 'boolean',
            'tutorial_finished' => 'boolean',
            'daily_coins_enabled' => 'boolean',
            'has_flash_warning' => 'boolean',
        ];
    }
}
