<?php

namespace App\Support;

use App\Mail\TemplatedMail;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class OrderCreator
{
    /**
     * @param  array<int, array{product_id: int, product_name: string, quantity: int, mrp: float, sale_price: float, subtotal: float}>  $itemsData
     * @param  array{totalMrp: float, total: float, savings: float, shippingCharge: ?float, couponCode?: ?string, couponDiscount?: float, grandTotal: float}  $totals
     * @param  array<string, mixed>  $extra
     */
    public static function create(
        User $user,
        array $itemsData,
        Address $shippingAddress,
        Address $billingAddress,
        array $totals,
        string $paymentMethod,
        string $paymentStatus,
        array $extra = []
    ): Order {
        return DB::transaction(function () use ($user, $itemsData, $shippingAddress, $billingAddress, $totals, $paymentMethod, $paymentStatus, $extra) {
            $stockBeforeByProductId = [];

            foreach ($itemsData as $item) {
                $product = Product::whereKey($item['product_id'])->lockForUpdate()->first();

                if (! $product || $product->stock < $item['quantity']) {
                    throw new RuntimeException("Sorry, \"{$item['product_name']}\" doesn't have enough stock available.");
                }

                $stockBeforeByProductId[$item['product_id']] = $product->stock;
            }

            $order = Order::create(array_merge([
                'user_id' => $user->id,
                'order_number' => Order::generateOrderNumber(),
                'status' => 'pending',
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_phone' => $user->phone,
                'shipping_name' => $shippingAddress->name,
                'shipping_phone' => $shippingAddress->phone,
                'shipping_address_line1' => $shippingAddress->address_line1,
                'shipping_address_line2' => $shippingAddress->address_line2,
                'shipping_city' => $shippingAddress->city,
                'shipping_state' => $shippingAddress->state,
                'shipping_postal_code' => $shippingAddress->postal_code,
                'shipping_country' => $shippingAddress->country,
                'billing_name' => $billingAddress->name,
                'billing_phone' => $billingAddress->phone,
                'billing_address_line1' => $billingAddress->address_line1,
                'billing_address_line2' => $billingAddress->address_line2,
                'billing_city' => $billingAddress->city,
                'billing_state' => $billingAddress->state,
                'billing_postal_code' => $billingAddress->postal_code,
                'billing_country' => $billingAddress->country,
                'total_mrp' => $totals['totalMrp'],
                'total_discount' => $totals['savings'],
                'subtotal' => $totals['total'],
                'shipping_charge' => $totals['shippingCharge'] ?? 0,
                'coupon_code' => $totals['couponCode'] ?? null,
                'coupon_discount' => $totals['couponDiscount'] ?? 0,
                'grand_total' => $totals['grandTotal'],
            ], $extra));

            $order->statusHistories()->create([
                'status' => $order->status,
                'note' => 'Order placed.',
            ]);

            foreach ($itemsData as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'mrp' => $item['mrp'],
                    'sale_price' => $item['sale_price'],
                    'subtotal' => $item['subtotal'],
                ]);

                Product::whereKey($item['product_id'])->decrement('stock', $item['quantity']);

                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'order_id' => $order->id,
                    'quantity_change' => -$item['quantity'],
                    'stock_after' => $stockBeforeByProductId[$item['product_id']] - $item['quantity'],
                    'reason' => 'Order placed',
                ]);
            }

            return $order;
        });
    }

    /**
     * @return array<int, array{product_id: int, product_name: string, quantity: int, mrp: float, sale_price: float, subtotal: float}>
     */
    public static function snapshotItems(Collection $items): array
    {
        return $items->map(fn ($item) => [
            'product_id' => $item['product']->id,
            'product_name' => $item['product']->name,
            'quantity' => $item['quantity'],
            'mrp' => (float) $item['product']->mrp,
            'sale_price' => (float) $item['product']->sale_price,
            'subtotal' => (float) $item['subtotal'],
        ])->values()->all();
    }

    /**
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $pairs
     * @return array<int, array{product_id: int, product_name: string, quantity: int, mrp: float, sale_price: float, subtotal: float}>
     */
    public static function snapshotItemsFromInput(array $pairs): array
    {
        $productIds = collect($pairs)->pluck('product_id')->unique()->all();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        return collect($pairs)->map(function ($pair) use ($products) {
            $product = $products->get((int) $pair['product_id']);

            if (! $product) {
                throw new RuntimeException('One of the selected products could not be found.');
            }

            $quantity = (int) $pair['quantity'];

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'mrp' => (float) $product->mrp,
                'sale_price' => (float) $product->sale_price,
                'subtotal' => (float) $product->sale_price * $quantity,
            ];
        })->values()->all();
    }

    public static function sendConfirmationEmail(User $user, Order $order): void
    {
        Mail::to($user->email)->send(new TemplatedMail('order-confirmation', [
            'name' => $user->name,
            'order_number' => $order->order_number,
            'grand_total' => number_format((float) $order->grand_total, 2),
            'order_url' => route('checkout.confirmation', $order),
        ]));
    }

    public static function sendStatusChangedEmail(Order $order): void
    {
        Mail::to($order->customer_email)->send(new TemplatedMail('order-status-changed', [
            'name' => $order->customer_name,
            'order_number' => $order->order_number,
            'status' => ucfirst($order->status),
            'order_url' => route('orders.show', $order),
        ]));
    }
}
