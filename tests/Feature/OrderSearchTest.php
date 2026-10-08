<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        foreach ([1, 2, 3, 4] as $id) {
            DB::table('orders')->where('id', $id)->update(['order_date' => '2026-10-0'.$id]);
        }
    }

    private function results(array $filters, array $ids): void
    {
        $this->get('/orders?'.http_build_query($filters))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === $ids);
    }

    public function test_search_matches_full_number_saved_customer_salesperson_and_products(): void
    {
        $this->results(['q' => 'PH-00001'], [1]);
        $this->results(['q' => '1'], [4, 3, 2, 1]); // A numeric term can also match customer phone numbers.
        $this->results(['q' => 'Inno01 Narin'], [2]);
        $this->results(['q' => 'โฟมสีฟัน'], [2]);
        DB::table('orders')->where('id', 1)->update([
            'customer_snapshot' => json_encode(['name' => 'ร้านยาทดสอบ', 'phone' => '0812345678', 'tax_id' => '0123456789012', 'address' => 'ทดสอบกรุงเทพ'], JSON_UNESCAPED_UNICODE),
            'salesperson' => 'Alice', 'sales_code' => 'SA-TEST', 'sales_phone' => '0899999999',
        ]);
        DB::table('customers')->where('id', 1)->update(['name' => 'Changed customer']);
        foreach (['ร้านยาทดสอบ', '0812345678', '0123456789012', 'ทดสอบกรุงเทพ', 'alice', 'SA-TEST', '0899999999'] as $q) {
            $this->results(compact('q'), [1]);
        }
        $this->get('/orders?q=SA-TEST')->assertSee('ร้านยาทดสอบ')->assertDontSee('Changed customer');
        $this->results(['q' => '%'], []);
        $this->results(['q' => '_'], []);
    }

    public function test_filters_combine_and_date_bounds_are_inclusive(): void
    {
        $this->results(['channel' => 'retail'], [4, 2]);
        $this->results(['payment' => 'paid'], [4, 2]);
        $this->results(['payment' => 'partial'], [3]);
        $this->results(['payment' => 'unpaid'], [1]);
        $this->results(['channel' => 'retail', 'status' => 'packing', 'payment' => 'paid', 'from' => '2026-10-02', 'to' => '2026-10-02'], [2]);
        $this->results(['from' => '2026-10-02', 'to' => '2026-10-03'], [3, 2]);
        DB::table('orders')->where('id', 1)->update(['status' => 'cancelled']);
        $this->results(['status' => 'cancelled'], [1]);
        $this->results(['payment' => 'unpaid'], []);
        $this->get('/orders?from=2026-10-04&to=2026-10-01')->assertSessionHasErrors('to');
        $this->get('/orders?per_page=10000')->assertSessionHasErrors('per_page');
        $this->get('/orders?status=unknown')->assertSessionHasErrors('status');
    }

    public function test_pagination_limits_rows_keeps_filters_and_preserves_overview_behavior(): void
    {
        $template = (array) DB::table('orders')->find(1);
        unset($template['id']);
        for ($i = 0; $i < 21; $i++) {
            DB::table('orders')->insert($template);
        }
        $this->get('/orders?q=Demo&per_page=20')->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 25 && $orders->count() === 20 && $orders->first()->id === 25 && $orders->last()->id === 6)
            ->assertSee('q=Demo&amp;per_page=20&amp;page=2', false)
            ->assertDontSee('All orders ↗');
        $this->results(['q' => 'Demo', 'per_page' => 20, 'page' => 2], [5, 4, 3, 2, 1]);
        $this->get('/orders?q=Demo&per_page=20&page=999')->assertRedirect('/orders?q=Demo&per_page=20&page=2');
        $this->get('/orders?per_page=50')->assertOk()->assertViewHas('orders', fn ($orders) => $orders->count() === 25);
        $response = $this->get('/')->assertOk()->assertSee('All orders ↗');
        preg_match_all('/class="link" href="\/orders\/[0-9]+"/', $response->getContent(), $rows);
        $this->assertCount(6, $rows[0]);
    }
}
