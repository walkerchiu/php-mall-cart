<?php

namespace WalkerChiu\MallCart\Models\Services;

use Illuminate\Support\Facades\App;
use WalkerChiu\Core\Models\Exceptions\NotExpectedEntityException;
use WalkerChiu\Core\Models\Exceptions\NotFoundEntityException;
use WalkerChiu\Core\Models\Services\CheckExistTrait;

class CartService
{
    use CheckExistTrait;

    protected $repository;
    protected $repository_item;

    public function __construct()
    {
        $this->repository = App::make(config('wk-core.class.mall-cart.channelRepository'));
        $this->repository_item = App::make(config('wk-core.class.mall-cart.itemRepository'));
    }

    /*
    |--------------------------------------------------------------------------
    | Get Channel
    |--------------------------------------------------------------------------
    */

    /**
     * @param Int $channel_id
     * @return Channel
     */
    public function find(Int $channel_id)
    {
        $entity = $this->repository->find($channel_id);

        if (empty($entity))
            throw new NotFoundEntityException($entity);

        return $entity;
    }

    /**
     * @param Channel|Int $source
     * @return Channel
     */
    public function findBySource($source)
    {
        if (is_string($source))
            $source = (int) $source;

        if (is_integer($source))
            $entity = $this->find($source);
        elseif (is_a($source, config('wk-core.class.mall-cart.channel')))
            $entity = $source;
        else
            throw new NotExpectedEntityException($source);

        return $entity;
    }



    /*
    |--------------------------------------------------------------------------
    | Operation
    |--------------------------------------------------------------------------
    */

    /**
     * @param String $type
     * @param Int    $channel_id
     * @param Int    $user_id
     * @param Int    $stock_id
     * @param Int    $nums
     * @return Boolean
     */
    public function checkOverflowWithMember(String $type, Int $channel_id, Int $user_id, Int $stock_id, Int $nums)
    {
        if ( config('wk-mall-cart.onoff.mall-shelf') && !empty(config('wk-core.class.mall-shelf.stock')) ) {
            $service = new \WalkerChiu\MallShelf\Models\Services\StockService();
            $stock = $service->find($stock_id);
            if (empty($stock))
                throw new NotFoundEntityException($source);

            if (is_null($stock->quantity))
                return false;

            $quantity = $stock->quantity;
            $nums_now = $this->repository_item->countNums($channel_id, $user_id, $stock_id);

            switch ($type) {
                case "push":
                    return ($quantity < $nums_now+$nums) ? true : false;

                case "pop":
                    return ($nums_now+$nums < 0) ? true : false;

                case "update":
                    return ($quantity < $nums) ? true : false;

                default:
                    throw new NotExpectedEntityException($type);
            }
        }
    }

    /**
     * @param Int $channel_id
     * @param Int $user_id
     * @return Boolean
     */
    public function checkOverflowForCheckoutWithMember(Int $channel_id, Int $user_id)
    {
        $service = new \WalkerChiu\MallShelf\Models\Services\StockService();

        $channel = $this->findBySource($channel_id);
        $items = $channel->items($user_id)->get();
        foreach ($items as $item) {
            $stock = $service->find($item->stock_id);
            if (empty($stock))
                throw new NotFoundEntityException($source);

            if (is_null($stock->quantity))
                continue;

            $nums_now = $this->repository_item->countNums($channel_id, $user_id, $item->stock_id);
            if ($stock->quantity < $nums_now)
                return true;

            if (is_iterable($item->binding)) {
                foreach ($item->binding as $binding) {
                    if (is_string($binding))
                        $binding = json_decode($binding);

                    $stock = $service->find($binding->stock_id);
                    if (empty($stock))
                        throw new NotFoundEntityException($source);

                    if ($stock->quantity < $binding->nums)
                        return true;
                }
            }
        }

        return false;
    }

    /**
     * @param Int   $channel_id
     * @param Array $items
     * @return Boolean
     */
    public function checkOverflowForCheckoutWithGuest(Int $channel_id, Array $items)
    {
        $service = new \WalkerChiu\MallShelf\Models\Services\StockService();

        foreach ($items as $item) {
            $stock = $service->find($item->stock->id);
            if (empty($stock))
                throw new NotFoundEntityException($source);

            if (is_null($stock->quantity))
                continue;

            if ($stock->quantity < $item->nums)
                return true;

            if (is_iterable($item->binding)) {
                foreach ($item->binding as $binding) {
                    if (is_string($binding))
                        $binding = json_decode($binding);

                    $stock = $service->find($binding->stock_id);
                    if (empty($stock))
                        throw new NotFoundEntityException($source);

                    if ($stock->quantity < $binding->nums)
                        return true;
                }
            }
        }

        return false;
    }

    /**
     * @param Channel|Int $source
     * @param Int $user_id
     * @return Boolean
     */
    public function clearCart($source, $user_id)
    {
        $channel = $this->findBySource($source);

        return $channel->items($user_id)->delete();
    }

    /**
     * @param Channel|Int $source
     * @param Array $items
     * @return Array
     */
    public function getTotalWithGuest($source, Array $items)
    {
        $channel = $this->findBySource($source);

        $data = [
            'subtotal_original'   => 0,
            'subtotal_discount'   => 0,
            'subtotal_difference' => 0,
            'fee' => 0, 'fee_original' => 0,
            'tax' => 0, 'tax_original' => 0,
            'tip' => 0, 'tip_original' => 0,
            'grandtotal' => 0
        ];

        foreach ($items as $item) {
            $data['subtotal_original'] += ( $item['price_original'] * $item['nums'] );
            $data['subtotal_discount'] += ( $item['price_discount'] * $item['nums'] );
            if ($item['tip'])
                $data['tip_original'] += ( $item['tip'] * $item['nums'] );
            if ($item['fee'])
                $data['fee_original'] += ( $item['fee'] * $item['nums'] );
            if ($item['tax'])
                $data['tax_original'] += ( $item['tax'] * $item['nums'] );
        }
        $data['fee'] = $data['fee_original'];
        $data['tax'] = $data['tax_original'];
        $data['tip'] = $data['tip_original'];
        $data['grandtotal'] = $data['subtotal_discount'] + $data['fee'] + $data['tax'] + $data['tip'];
        $data['subtotal_difference'] = $data['subtotal_original'] - $data['subtotal_discount'];

        return $data;
    }

    /**
     * @param Channel|Int $source
     * @param Int $user_id
     * @return Array
     */
    public function getTotalWithMember($source, Int $user_id)
    {
        $channel = $this->findBySource($source);

        $data = [
            'subtotal_original'   => 0,
            'subtotal_discount'   => 0,
            'subtotal_difference' => 0,
            'fee' => 0, 'fee_original' => 0,
            'tax' => 0, 'tax_original' => 0,
            'tip' => 0, 'tip_original' => 0,
            'grandtotal' => 0
        ];

        $items = $channel->items($user_id)->get();
        foreach ($items as $item) {
            $data['subtotal_original'] += ( $item->stock->price_original * $item->nums );
            $data['subtotal_discount'] += ( $item->stock->price_discount * $item->nums );
            if ($item->stock->fee)
                $data['fee_original'] += ( $item->stock->fee * $item->nums );
            if ($item->stock->tax)
                $data['tax_original'] += ( $item->stock->tax * $item->nums );
            if ($item->stock->tip)
                $data['tip_original'] += ( $item->stock->tip * $item->nums );
        }
        $data['fee'] = $data['fee_original'];
        $data['tax'] = $data['tax_original'];
        $data['tip'] = $data['tip_original'];
        $data['grandtotal'] = $data['subtotal_discount'] + $data['fee'] + $data['tax'] + $data['tip'];
        $data['subtotal_difference'] = $data['subtotal_original'] - $data['subtotal_discount'];

        return $data;
    }

    /**
     * @param Array $data
     * @param Int $discount_coupon
     * @param Int $discount_point
     * @param Int $discount_shipment
     * @param Int $discount_custom
     * @return Array
     */
    public function discount(Array $data, $discount_coupon = 0, $discount_point = 0, $discount_shipment = 0, $discount_custom = 0)
    {
        $data = array_merge($data, [
            'discount_coupon'   => $discount_coupon,
            'discount_point'    => $discount_point,
            'discount_shipment' => $discount_shipment,
            'discount_custom'   => $discount_custom,
            'discount_total'    => $discount_coupon + $discount_point + $discount_shipment + $discount_custom
        ]);
        $data['grandtotal'] -= $data['discount_total'];

        return $data;
    }

    /**
     * @param Channel|Int $source
     * @param Int $user_id
     * @param Int $discount_coupon
     * @param Int $discount_point
     * @param Int $discount_shipment
     * @param Int $discount_custom
     * @return Array
     */
    public function calculate($source, $user_id, $discount_coupon = 0, $discount_point = 0, $discount_shipment = 0, $discount_custom = 0, $items = [])
    {
        $data1 = (is_null($user_id)) ? $this->getTotalWithGuest($source, $items)
                                     : $this->getTotalWithMember($source, $user_id);
        $data2 = $this->discount($data1, $discount_coupon, $discount_point, $discount_shipment, $discount_custom);

        return $data2;
    }

    /**
     * @param Array $source
     * @return Array
     */
    public function pack(Array $source)
    {
        $obj = json_encode($source);

        return [
            'source'        => $source,
            'data'          => $obj,
            'security_code' => bcrypt($obj)
        ];
    }
}
