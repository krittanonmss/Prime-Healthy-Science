<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaginationNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_pager_is_inside_a_refreshable_region_and_ajax_responses_contain_that_region(): void
    {
        $this->seed();
        $order = (array) DB::table('orders')->find(1);
        unset($order['id']);
        for ($i = 1; $i <= 25; $i++) {
            DB::table('orders')->insert($order);
            DB::table('products')->insert(['sku' => 'PAGER'.$i, 'name' => 'Pagination fixture '.$i, 'unit' => 'piece', 'retail' => 100]);
            DB::table('customers')->insert(['name' => 'Pagination fixture '.$i, 'phone' => '0000000000', 'address' => 'Test', 'channel' => 'retail']);
            DB::table('stock_movements')->insert(['product_id' => 3, 'type' => 'receive', 'quantity' => 1, 'balance' => $i, 'movement_date' => now()->toDateString(), 'reason' => 'Test', 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([
            '/orders' => 'orders-results',
            '/products' => 'products-results',
            '/customers' => 'customers-results',
            '/reports' => 'reports-results',
            '/stock?tab=balances' => 'stock-balance-results',
            '/stock?tab=history' => 'stock-history-results',
        ] as $url => $regionId) {
            $response = $this->get($url)->assertOk()->assertSee('src="/list-navigation.js"', false);
            $document = new \DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $links = $xpath->query('//nav[contains(@class,"orders-pagination")]//a');
            $this->assertGreaterThan(0, $links->length, $url);
            foreach ($links as $link) {
                $this->assertSame(1, $xpath->query('ancestor::*[@data-ajax-region]', $link)->length, $url);
            }
            $next = $xpath->query('//*[@id="'.$regionId.'"]//a[@rel="next"]')->item(0);
            $this->assertNotNull($next, $url);
            $nextUrl = $next->getAttribute('href');
            $ajax = $this->get($nextUrl, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
            @$document->loadHTML($ajax->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame(1, $xpath->query('//*[@id="'.$regionId.'" and @data-ajax-region]')->length, $nextUrl);
            $this->assertSame('2', trim($xpath->query('//*[@id="'.$regionId.'"]//a[@aria-current="page"]')->item(0)->textContent), $nextUrl);
        }
    }
}
