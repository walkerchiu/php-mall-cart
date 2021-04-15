<?php

namespace WalkerChiu\MallCart\Models\Entities;

use WalkerChiu\Core\Models\Entities\Lang;

class ChannelLang extends Lang
{
    /**
     * @param array $attributes
     */
    public function __construct(array $attributes = array())
    {
        $this->table = config('wk-core.table.mall-cart.channels_lang');

        parent::__construct($attributes);
    }
}
