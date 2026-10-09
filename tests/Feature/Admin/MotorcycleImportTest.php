<?php

namespace Tests\Feature\Admin;

use App\Jobs\ProcessMotorcycleImportChunkJob;
use App\Models\MotorcycleImport;
use App\Models\User;
use App\Services\MotorcycleImport\MotorcycleImportMapper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Admin motorcycle catalog import: existing motorcycles (Make+Model+Year) are
 * updated in place (ids kept, they are referenced by listings), new ones are
 * inserted, empty cells never wipe stored data.
 */
class MotorcycleImportTest extends TestCase
{
    use DatabaseTransactions;

    private string $token;
    private int $typeId;
    private string $make;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $admin = User::factory()->create(['role_id' => 1]);
        $this->token = JWTAuth::fromUser($admin);

        // Leftover running imports in the dev DB would trigger the 409 guard.
        MotorcycleImport::whereIn('status', ['queued', 'preparing', 'processing'])->update(['status' => 'failed']);

        $this->typeId = DB::table('motorcycle_types')->insertGetId(['name' => 'TestType ' . Str::random(6), 'created_at' => now(), 'updated_at' => now()]);
        $this->make = 'TestMake ' . Str::random(8);
    }

    /** Builds an .xlsx shaped like the scraper sheet: group row, then header row, then data. */
    private function xlsx(array $rows): UploadedFile
    {
        $headers = ['Source URL', 'Title', 'Make', 'Model', 'Year', 'Capacity', 'Power', 'Wheelbase', 'Comments', 'ABS', 'Rake (fork angle)', 'Rake', 'Summary', 'Some new column'];

        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->fromArray(['URL', 'Naming'], null, 'A1');
        $sheet->fromArray($headers, null, 'A2');
        $sheet->fromArray($rows, null, 'A3', true);

        $path = tempnam(sys_get_temp_dir(), 'moto') . '.xlsx';
        (new Xlsx($sheet->getParent()))->save($path);

        return new UploadedFile($path, 'bikes.xlsx', null, null, true);
    }

    private function upload(UploadedFile $file)
    {
        return $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->post('/api/admin/motorcycle-imports', ['file' => $file, 'default_type_id' => $this->typeId], ['Accept' => 'application/json']);
    }

    private function seedExisting(): array
    {
        $brandId = DB::table('motorcycle_brands')->insertGetId(['name' => $this->make, 'created_at' => now(), 'updated_at' => now()]);
        $modelId = DB::table('motorcycle_models')->insertGetId(['brand_id' => $brandId, 'name' => 'Alpha', 'type_id' => $this->typeId, 'created_at' => now(), 'updated_at' => now()]);
        $yearId = DB::table('motorcycle_years')->insertGetId(['model_id' => $modelId, 'year' => 2020, 'created_at' => now(), 'updated_at' => now()]);
        $detailId = DB::table('motorcycle_details')->insertGetId(['year_id' => $yearId, 'power' => 50, 'comments' => 'keep me', 'created_at' => now(), 'updated_at' => now()]);

        return compact('brandId', 'modelId', 'yearId', 'detailId');
    }

    public function test_upserts_rows_keeps_ids_and_reports_counters(): void
    {
        $ids = $this->seedExisting();
        $newMake = $this->make . ' New';

        $rows = [
            // existing (different case + extra spaces) -> update power, keep comments
            ['https://s/1', 'T1', strtoupper($this->make), '  alpha ', '2020', '649.0 ccm (39.60 cubic inches)', '70.0 HP (51.1 kW) @ 9000 RPM', '1,410 mm (55.5 inches)', '', 'Standard', '25.0°', '', 'Great bike', 'x'],
            // existing model, new year -> insert
            ['https://s/2', 'T2', $this->make, 'Alpha', '2021', '650 ccm', '72 HP', '', 'c2', '', '', '26°', '', ''],
            // brand-new brand + model -> insert with default type
            ['https://s/3', 'T3', $newMake, 'Beta', '2022', '', '', '', '', '', '', '', '', ''],
            // invalid year -> failed
            ['https://s/4', 'T4', $this->make, 'Alpha', 'abc', '', '', '', '', '', '', '', '', ''],
            // no make -> skipped
            ['https://s/5', 'T5', '', 'Gamma', '2020', '', '', '', '', '', '', '', '', ''],
        ];

        $this->upload($this->xlsx($rows))->assertStatus(202);

        $import = MotorcycleImport::latest('id')->first();
        $this->assertSame('completed', $import->status, (string) $import->error_message);
        $this->assertSame(5, $import->total_rows);
        $this->assertSame(2, $import->inserted_count);
        $this->assertSame(1, $import->updated_count);
        $this->assertSame(1, $import->failed_count);
        $this->assertSame(1, $import->skipped_count);
        $this->assertSame(1, $import->new_brands_count);
        $this->assertSame(1, $import->new_models_count);
        $this->assertSame(['Some new column'], $import->unmapped_headers);
        $this->assertSame(4, $import->row_errors[0]['row'] - 2); // excel row 6 = 4th data row

        // Existing ids untouched, detail updated in place, empty cell did not wipe comments
        $detail = DB::table('motorcycle_details')->where('id', $ids['detailId'])->first();
        $this->assertSame($ids['yearId'], $detail->year_id);
        $this->assertEquals(70, $detail->power);
        $this->assertEquals(649, $detail->displacement);
        $this->assertEquals(1410, $detail->wheelbase);
        $this->assertSame('keep me', $detail->comments);
        $this->assertSame('Standard', $detail->abs_system);
        $this->assertSame('25.0°', $detail->rake);
        $this->assertSame('Great bike', $detail->summary);
        $this->assertSame(1, DB::table('motorcycle_models')->where('brand_id', $ids['brandId'])->count());
        $this->assertSame(1, DB::table('motorcycle_details')->where('year_id', $ids['yearId'])->count());

        // "Rake" is used when "Rake (fork angle)" is empty
        $year2021 = DB::table('motorcycle_years')->where('model_id', $ids['modelId'])->where('year', 2021)->value('id');
        $this->assertSame('26°', DB::table('motorcycle_details')->where('year_id', $year2021)->value('rake'));

        // New model got the default type
        $newBrandId = DB::table('motorcycle_brands')->where('name', $newMake)->value('id');
        $this->assertSame($this->typeId, DB::table('motorcycle_models')->where('brand_id', $newBrandId)->value('type_id'));

        // Same file again: nothing changes
        $this->upload($this->xlsx($rows))->assertStatus(202);
        $again = MotorcycleImport::latest('id')->first();
        $this->assertSame('completed', $again->status);
        $this->assertSame(0, $again->inserted_count);
        $this->assertSame(0, $again->updated_count);
        $this->assertSame(3, $again->unchanged_count);
    }

    public function test_model_with_make_prefix_matches_existing_and_stat_rows_are_ignored(): void
    {
        $ids = $this->seedExisting();

        $rows = [
            // statistic rows the real sheet has under the header
            ['43311', '43311', '43311', '43308', '43311', '41585', '42689', '', '', '', '', '', '', ''],
            ['100.00%', '100.00%', '100.00%', '99.99%', '100.00%', '96.01%', '98.56%', '', '', '', '', '', '', ''],
            // "TestMake Alpha" must hit existing model "Alpha"
            ['https://s/1', 'T', $this->make, $this->make . ' Alpha', '2020', '', '88 HP', '', '', '', '', '', '', ''],
        ];

        $this->upload($this->xlsx($rows))->assertStatus(202);

        $import = MotorcycleImport::latest('id')->first();
        $this->assertSame('completed', $import->status, (string) $import->error_message);
        $this->assertSame(1, $import->total_rows);
        $this->assertSame(0, $import->failed_count);
        $this->assertSame(0, $import->new_models_count);
        $this->assertSame(1, $import->updated_count);
        $this->assertEquals(88, DB::table('motorcycle_details')->where('id', $ids['detailId'])->value('power'));
        $this->assertSame('RS 457', MotorcycleImportMapper::stripMakePrefix('Aprilia', 'Aprilia RS 457'));
        $this->assertSame('Aprilia', MotorcycleImportMapper::stripMakePrefix('Aprilia', 'Aprilia'));
    }

    public function test_csv_semicolon_and_header_detection(): void
    {
        $this->seedExisting();
        $csv = "Group;Group;Group\nMake;Model;Year;Power\n{$this->make};Alpha;2020;90 HP\n";
        $path = tempnam(sys_get_temp_dir(), 'moto') . '.csv';
        file_put_contents($path, "\xEF\xBB\xBF" . $csv);

        $this->upload(new UploadedFile($path, 'bikes.csv', 'text/csv', null, true))->assertStatus(202);

        $import = MotorcycleImport::latest('id')->first();
        $this->assertSame('completed', $import->status, (string) $import->error_message);
        $this->assertSame(1, $import->updated_count);
    }

    public function test_missing_header_fails_cleanly(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'moto') . '.csv';
        file_put_contents($path, "foo,bar\n1,2\n");

        $this->upload(new UploadedFile($path, 'bad.csv', 'text/csv', null, true))->assertStatus(202);

        $import = MotorcycleImport::latest('id')->first();
        $this->assertSame('failed', $import->status);
        $this->assertStringContainsString('Header row not found', $import->error_message);
    }

    public function test_rejects_second_import_while_one_is_running(): void
    {
        MotorcycleImport::create(['original_name' => 'x', 'file_path' => 'x', 'status' => 'processing']);

        $this->upload($this->xlsx([[ '', '', $this->make, 'A', '2020', '', '', '', '', '', '', '', '', '' ]]))
            ->assertStatus(409);
    }

    public function test_stale_chunk_job_is_a_no_op_and_cancel_resume_flow(): void
    {
        $import = MotorcycleImport::create(['original_name' => 'x', 'file_path' => 'x', 'status' => 'processing', 'processed_rows' => 500]);

        // A duplicate job dispatched for an older checkpoint must not touch the import.
        (new ProcessMotorcycleImportChunkJob($import->id, 250))->handle();
        $this->assertSame(500, $import->fresh()->processed_rows);

        $auth = ['Authorization' => "Bearer {$this->token}"];
        $this->withHeaders($auth)->postJson("/api/admin/motorcycle-imports/{$import->id}/cancel")->assertOk();
        $this->assertSame('cancelled', $import->fresh()->status);
        $this->withHeaders($auth)->postJson("/api/admin/motorcycle-imports/{$import->id}/cancel")->assertStatus(422);

        // Source file is gone -> resume refuses
        $this->withHeaders($auth)->postJson("/api/admin/motorcycle-imports/{$import->id}/resume")->assertStatus(422);
    }

    public function test_first_number_parsing(): void
    {
        $this->assertSame(649.0, MotorcycleImportMapper::firstNumber('649.0 ccm (39.60 cubic inches)'));
        $this->assertSame(1410.0, MotorcycleImportMapper::firstNumber('1,410 mm (55.5 inches)'));
        $this->assertSame(11.5, MotorcycleImportMapper::firstNumber('11,5:1'));
        $this->assertSame(11.3, MotorcycleImportMapper::firstNumber('11.3:1'));
        $this->assertNull(MotorcycleImportMapper::firstNumber('n/a'));
    }
}
