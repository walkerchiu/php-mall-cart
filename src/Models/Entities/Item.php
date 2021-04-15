<?php

namespace WalkerChiu\MallCart\Models\Entities;

use WalkerChiu\Core\Models\Entities\UuidModel;

class Item extends UuidModel
{
    protected $fillable = [
        'channel_id',
        'user_id',
        'stock_id', 'nums',
        'binding',
        'options'
    ];

    protected $dates = [
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'binding' => 'json',
        'options' => 'json'
    ];

    /**
     * @param array $attributes
     */
    public function __construct(array $attributes = array())
    {
        $this->table = config('wk-core.table.mall-cart.items');

        parent::__construct($attributes);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(config('wk-core.class.user'), 'user_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function channel()
    {
        return $this->belongsTo(config('wk-core.class.mall-cart.channel'), 'channel_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function stock()
    {
        return $this->belongsTo(config('wk-core.class.mall-shelf.stock'), 'stock_id', 'id');
    }
}
