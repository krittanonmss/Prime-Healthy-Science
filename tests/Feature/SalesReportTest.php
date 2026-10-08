<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesReportTest extends TestCase
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

    public function test_reports_filter_totals_and_channel_summaries_over_all_pages(): void
    {
        $template = (array) DB::table('orders')->find(1);
        unset($template['id']);
        for ($i = 0; $i < 15; $i++) {
            DB::table('orders')->insert($template);
        }
        $total = DB::table('orders')->sum('total');
        $paid = DB::table('orders')->sum('paid');
        foreach (['/reports', '/reports?page=2'] as $url) {
            $this->get($url)->assertOk()->assertViewHas('summary', fn ($summary) => $summary['count'] === 19 && $summary['sales'] === $total && $summary['paid'] === $paid && $summary['outstanding'] === $total - $paid);
        }
        $this->get('/reports')->assertViewHas('orders', fn ($rows) => $rows->count() === 10)->assertDontSee('action="/export"', false);
        $this->get('/reports?from=2026-10-02&to=2026-10-02&channel=retail&payment=paid')->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['count'] === 1 && $s['sales'] === 72000 && $s['paid'] === 72000 && $s['outstanding'] === 0)
            ->assertViewHas('channels', fn ($channels) => $channels->get('retail')->order_count === 1);
        $this->get('/reports?q=Inno01')->assertOk()->assertViewHas('orders', fn ($rows) => $rows->pluck('id')->all() === [2]);
        DB::table('orders')->where('id', 2)->update(['status' => 'cancelled']);
        $this->get('/reports?q=Inno01')->assertOk()->assertViewHas('summary', fn ($s) => $s['sales'] === 0 && $s['count'] === 0);
    }

    private function workbookRows(string $url): \DOMXPath
    {
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'report-export');
        file_put_contents($path, $response->getContent());
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $doc = new \DOMDocument;
        $this->assertTrue($doc->loadXML($zip->getFromName('xl/worksheets/sheet1.xml')));
        $zip->close();
        unlink($path);
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return $xp;
    }

    public function test_export_form_and_download_share_report_filters_and_ignore_page_limits(): void
    {
        $this->get('/reports/export?q=Inno01&payment=paid')->assertOk()->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [2]);
        $xp = $this->workbookRows('/export?q=Inno01&payment=paid');
        $this->assertCount(2, $xp->query('//m:row'));
        $this->assertStringContainsString('PH-00002', $xp->document->textContent);
        $xp = $this->workbookRows('/export?channel=retail&per_page=10&page=2');
        $this->assertCount(3, $xp->query('//m:row')); // Both matching orders, even when page 2 has no records.
        $xp = $this->workbookRows('/export?q=Inno01&ids[]=1');
        $this->assertCount(1, $xp->query('//m:row')); // Selected IDs cannot bypass filters.
        $this->get('/reports?from=2026-10-04&to=2026-10-01')->assertSessionHasErrors('to');
    }

    public function test_export_can_explicitly_include_cancelled_orders_but_defaults_exclude_them(): void
    {
        DB::table('orders')->where('id', 1)->update(['status' => 'cancelled']);
        $this->get('/reports/export')->assertOk()->assertViewHas('orders', fn ($rows) => ! $rows->contains('id', 1));
        $this->get('/reports/export?status=cancelled')->assertOk()->assertViewHas('orders', fn ($rows) => $rows->pluck('id')->all() === [1]);
        $xp = $this->workbookRows('/export?status=cancelled');
        $this->assertCount(2, $xp->query('//m:row'));
        $this->assertStringContainsString('PH-00001',$xp->document->textContent);
    }
}
