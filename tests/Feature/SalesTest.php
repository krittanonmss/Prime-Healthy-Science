<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        DB::table('products')->update(['stock_initialized' => true, 'stock_on_hand' => 1000]);
    }

    public function test_all_presentation_pages_render(): void
    {
        foreach (['/', '/orders', '/orders/create', '/orders/1', '/products', '/customers', '/reports', '/stock', '/export'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    private function order(string $channel, int $product = 3): array
    {
        return ['customer_id' => 1, 'channel' => $channel, 'salesperson' => 'Sales Demo', 'order_date' => '2026-10-04', 'lines' => [['product_id' => $product, 'quantity' => 2, 'free_quantity' => 3]], 'price' => 1];
    }

    public function test_channel_prices_are_selected_on_server_and_frozen(): void
    {
        $this->post('/orders', $this->order('retail'))->assertSessionHasNoErrors();
        $retail = DB::table('orders')->max('id');
        $this->assertDatabaseHas('order_lines', ['order_id' => $retail, 'price' => 18000, 'total' => 36000]);
        $this->post('/orders', $this->order('hospital'))->assertSessionHasNoErrors();
        $wholesale = DB::table('orders')->max('id');
        $this->assertDatabaseHas('order_lines', ['order_id' => $wholesale, 'price' => 11600, 'total' => 23200]);
        $this->post('/products/3', ['retail' => '190', 'hospital' => '120'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order_lines', ['order_id' => $retail, 'price' => 18000]);
        $this->assertDatabaseHas('order_lines', ['order_id' => $wholesale, 'price' => 11600]);
    }

    public function test_missing_wholesale_and_equal_prices_are_rejected(): void
    {
        $count = DB::table('orders')->count();
        $this->post('/orders', $this->order('hospital', 7))->assertSessionHasErrors('lines');
        $this->assertSame($count, DB::table('orders')->count());
        $this->post('/products/3', ['retail' => 180, 'hospital' => 180])->assertSessionHasErrors('hospital');
    }

    public function test_payments_and_fulfillment_are_independent_and_validated(): void
    {
        $this->post('/orders/1/payment', ['amount' => 100])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => 1, 'paid' => 10000, 'status' => 'confirmed']);
        $this->post('/orders/1/payment', ['amount' => 100000])->assertSessionHasErrors('amount');
        $this->post('/orders/1/status', ['status' => 'delivered'])->assertSessionHasErrors('status');
        foreach (['packing', 'dispatched', 'delivered'] as $status) {
            $this->post('/orders/1/status', compact('status'))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseHas('orders', ['id' => 1, 'status' => 'delivered', 'paid' => 10000]);
    }

    public function test_customer_creation_and_invalid_quantity(): void
    {
        $this->post('/customers', ['name' => 'Client Demo', 'phone' => '0800000000', 'address' => 'Bangkok', 'channel' => 'hospital'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['name' => 'Client Demo']);
        $v = $this->order('retail');
        $v['lines'][0]['quantity'] = -1;
        $this->post('/orders', $v)->assertSessionHasErrors('lines.0.quantity');
    }

    public function test_pharmacy_negotiated_price_and_tax_snapshot(): void
    {
        $v = $this->order('pharmacy');
        $v['tax_id'] = '0105566195210';
        $v['lines'][0]['price'] = '125.50';
        $this->post('/orders', $v)->assertSessionHasErrors('lines');
        $v['lines'][0]['price_reason'] = 'ตกลงราคาหน้างาน';
        $this->post('/orders', $v)->assertSessionHasNoErrors();
        $id = DB::table('orders')->max('id');
        $this->assertDatabaseHas('order_lines', ['order_id' => $id, 'catalog_price' => 13700, 'price' => 12550, 'total' => 25100, 'free' => 3, 'free_box' => 0, 'free_sachet' => 0]);
        DB::table('customers')->where('id', 1)->update(['name' => 'Changed name']);
        $this->get('/orders/'.$id.'/document?type=tax')->assertOk()->assertSee('0105566195210')->assertSee('Siam Wellness Clinic')->assertSee('Payee')->assertSee('Payer');
        $this->get('/orders/'.$id.'/document?type=goods')->assertOk()->assertSee('Receiver')->assertSee('Sender');
    }

    public function test_excel_structure_filters_and_text_safety(): void
    {
        $this->get('/export?from=2026-11-01&to=2026-10-01')->assertSessionHasErrors('to');
        $r = $this->get('/export?channel=retail&ids[]=2')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'test-xlsx');
        file_put_contents($path, $r->getContent());
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $doc = new \DOMDocument;
        $this->assertTrue($doc->loadXML($xml));
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $this->assertCount(2, $xp->query('//m:row'));
        $this->assertCount(18, $xp->query('//m:row[@r="1"]/m:c'));
        $this->assertStringContainsString('FOC Sachet', $xml);
        $this->assertStringContainsString('PH-00002', $xml);
        $this->assertStringNotContainsString('PH-00001', $xml);
        $zip->close();
        unlink($path);
        $toOnly = $this->get('/export?to=2099-12-31')->assertOk();
    }

    public function test_thai_customer_input_is_preserved_in_documents_and_excel(): void
    {
        $this->post('/customers', ['name' => 'ร้านยาสุขใจ', 'phone' => '0800000000', 'address' => 'กรุงเทพมหานคร ประเทศไทย', 'channel' => 'pharmacy'])->assertSessionHasNoErrors();
        $customer = DB::table('customers')->where('name', 'ร้านยาสุขใจ')->first();
        $this->assertNotNull($customer);
        $payload = $this->order('pharmacy');
        $payload['customer_id'] = $customer->id;
        $payload['lines'][0]['price'] = '125';
        $payload['lines'][0]['price_reason'] = 'ราคาต่อรองสำหรับลูกค้าประจำ';
        $this->post('/orders', $payload)->assertSessionHasNoErrors();
        $id = DB::table('orders')->max('id');
        $this->get('/orders/'.$id)->assertOk()->assertSee('ร้านยาสุขใจ')->assertSee('ราคาต่อรองสำหรับลูกค้าประจำ');
        $this->get('/orders/'.$id.'/document?type=receipt')->assertOk()->assertSee('กรุงเทพมหานคร ประเทศไทย');
        $response = $this->get('/export?ids[]='.$id)->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'thai-export');
        file_put_contents($path, $response->getContent());
        $zip = new \ZipArchive;
        $zip->open($path);
        $this->assertStringContainsString('ร้านยาสุขใจ', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        unlink($path);
    }

    public function test_percentage_discount_applies_after_negotiated_price_and_validates_bounds(): void
    {
        $v = $this->order('retail');
        $v['lines'][0]['price'] = '175.55';
        $v['lines'][0]['price_reason'] = 'Negotiated';
        $v['lines'][0]['discount_percent'] = '12.50';
        $this->post('/orders', $v)->assertSessionHasNoErrors();
        $id = DB::table('orders')->max('id');
        $this->assertDatabaseHas('order_lines', ['order_id' => $id, 'price' => 17555, 'discount_basis_points' => 1250, 'discount_amount' => 4389, 'total' => 30721]);
        $this->assertDatabaseHas('orders', ['id' => $id, 'total' => 30721]);
        $this->get('/orders/'.$id)->assertOk()->assertSee('12.50%');
        $this->get('/orders/'.$id.'/document?type=tax')->assertOk()->assertSee('12.50%');
        foreach ([-1, 101, '1.234'] as $invalid) {
            $v['lines'][0]['discount_percent'] = $invalid;
            $this->post('/orders', $v)->assertSessionHasErrors('lines.0.discount_percent');
        }
        $v['lines'][0]['discount_percent'] = 100;
        $this->post('/orders', $v)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => DB::table('orders')->max('id'), 'total' => 0]);
    }
}
