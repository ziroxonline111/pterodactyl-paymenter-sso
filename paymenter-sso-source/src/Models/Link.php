<?php

declare(strict_types=1);

namespace KareliaLabs\PaymenterSso\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pterodactyl\Models\User;

/**
 * The Paymenter account that signs in to a panel account (one per panel account).
 *
 * @property int $user_id
 * @property string $paymenter_url
 * @property int $paymenter_user_id
 * @property \Carbon\CarbonImmutable|null $last_login_at
 * @property-read User $user
 */
final class Link extends Model
{
    protected $table = 'paymenter_sso_links';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = ['user_id', 'paymenter_url', 'paymenter_user_id', 'last_login_at'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'paymenter_user_id' => 'integer',
            'last_login_at' => 'immutable_datetime',
        ];
    }
}
