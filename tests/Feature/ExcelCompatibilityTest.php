<?php

namespace Tests\Feature;

use App\Imports\LanguageImport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class ExcelCompatibilityTest extends TestCase
{
    public function test_export_can_be_stored_and_read_back_through_language_import(): void
    {
        Storage::fake('local');

        $export = $this->languageExport();

        $this->assertTrue(Excel::store($export, 'language.xlsx', 'local'));

        $file = Storage::disk('local')->path('language.xlsx');
        $this->assertFileExists($file);

        $parsed = (new LanguageImport)->toArray($file)->columnData()->keyValue();

        $this->assertSame(['EN' => ['greeting' => 'Hello']], $parsed);
    }

    public function test_export_can_be_downloaded(): void
    {
        $response = Excel::download($this->languageExport(), 'language.xlsx');

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertFileExists($response->getFile()->getPathname());
    }

    private function languageExport(): FromArray
    {
        return new class implements FromArray, WithHeadings
        {
            public function array(): array
            {
                return [['greeting', 'Hello']];
            }

            public function headings(): array
            {
                return ['Key', 'EN'];
            }
        };
    }
}
