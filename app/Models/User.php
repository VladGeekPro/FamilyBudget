<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        // if ($panel->getId() === 'admin') {
        //     // return str_ends_with($this->email, 'vladret0@gmail.com') && $this->hasVerifiedEmail();
        //     return str_ends_with($this->email, 'vladret0@gmail.com');
        // }

        return true;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'image',
        'name',
        'email',
        'email_verified_at',
        'password',
        'widget_preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'widget_preferences' => 'array',
        ];
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public static function getIcon(string $email): string
    {
        $icons = [
            'vladret0@gmail.com' => '😎',
            'criclivaia.olga@gmail.com' => '😇',
        ];

        return $icons[$email] ?? '👤';
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function getWidgetPreferences(string $widgetClass): array
    {
        $preferences = $this->widget_preferences ?? [];
        return $preferences[$widgetClass] ?? [];
    }

    public function getWidgetPreference(string $widgetClass, string $section): bool
    {
        $preferences = $this->widget_preferences ?? [];
        return $preferences[$widgetClass][$section] ?? true;
    }

    public function setWidgetPreferences(string $widgetClass, array $sections): void
    {
        $preferences = $this->widget_preferences ?? [];
        $preferences[$widgetClass] = $sections;
        $this->update(['widget_preferences' => $preferences]);
    }
}
