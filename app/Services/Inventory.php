<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Inventory
{
    public static function movement($p, string $type, int $delta, string $reason, ?int $order = null, ?string $date = null): void
    {
        $balance = $p->stock_on_hand + $delta;
        if ($balance < $p->stock_reserved) {
            throw ValidationException::withMessages(['stock' => 'ยอดคงเหลือต้องไม่น้อยกว่ายอดจอง: '.$p->sku]);
        }
        DB::table('products')->where('id', $p->id)->update(['stock_on_hand' => $balance]);
        DB::table('stock_movements')->insert(['product_id' => $p->id, 'order_id' => $order, 'type' => $type, 'quantity' => $delta, 'balance' => $balance, 'movement_date' => $date ?? now()->toDateString(), 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function quantities(int $order): array
    {
        $result = [];
        foreach (DB::table('order_lines')->where('order_id', $order)->get() as $l) {
            if (! $l->unit) {
                throw ValidationException::withMessages(['stock' => 'ออเดอร์เก่ามีหน่วยของแถมที่ยังไม่ชัดเจน กรุณายกเลิกและสร้างใหม่หากต้องการจองสต็อก']);
            }
            $result[$l->product_id] = ($result[$l->product_id] ?? 0) + $l->quantity + $l->free;
        }
        ksort($result);

        return $result;
    }

    public static function reserve(int $order): void
    {
        foreach (self::quantities($order) as $id => $qty) {
            $p = DB::table('products')->where('id', $id)->lockForUpdate()->first();
            if (! $p->stock_initialized || $p->stock_on_hand - $p->stock_reserved < $qty) {
                throw ValidationException::withMessages(['stock' => 'สต็อกพร้อมขายไม่พอ หรือยังไม่ได้ตั้งยอดเริ่มต้น: '.$p->sku]);
            }
            DB::table('products')->where('id', $id)->update(['stock_reserved' => $p->stock_reserved + $qty]);
            self::movement($p, 'reserve', 0, 'จองสินค้า '.$qty.' '.$p->unit, $order);
        }
        DB::table('orders')->where('id', $order)->update(['stock_managed' => true]);
    }

    public static function release(int $order, bool $dispatch = false): void
    {
        foreach (self::quantities($order) as $id => $qty) {
            $p = DB::table('products')->where('id', $id)->lockForUpdate()->first();
            if ($p->stock_reserved < $qty) {
                throw ValidationException::withMessages(['stock' => 'ยอดจองไม่ตรงกับออเดอร์']);
            }
            $p->stock_reserved -= $qty;
            DB::table('products')->where('id', $id)->update(['stock_reserved' => $p->stock_reserved]);
            self::movement($p, $dispatch ? 'dispatch' : 'release', $dispatch ? -$qty : 0, ($dispatch ? 'ส่งสินค้า ' : 'คืนยอดจอง ').$qty.' '.$p->unit, $order);
        }
    }
}
