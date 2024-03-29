<?php

namespace WalkerChiu\MallCart\Models\Entities;

trait UserTrait
{
    /**
     * @param String  $channel_id
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function cart(?string $channel_id)
    {
        return $this->hasMany(config('wk-core.class.mall-cart.item'), 'user_id', 'id')
                    ->when($channel_id, function ($query, $channel_id) {
                                return $query->where('channel_id', $channel_id);
                            });
    }
}
