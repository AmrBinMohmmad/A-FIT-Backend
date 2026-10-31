<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Photos;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /** Alphabet without look-alikes (0/O, 1/I/L) so a code read aloud survives. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'name',
    ];

    protected function casts(): array
    {
        return [
            'max_members' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn (Group $group) => app(Photos::class)->remove($group->photo_path));
    }

    public function photoUrl(): ?string
    {
        return Photos::urlFor($this->photo_path);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->using(GroupMember::class)
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class);
    }

    /** Generates a fresh, collision-free 8-character invite code. */
    public static function freshInviteCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (static::query()->where('invite_code', $code)->exists());

        return $code;
    }

    /** Normalises user input ("abcd-1234", lower-case) to the stored form. */
    public static function normaliseCode(string $raw): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
    }
}
