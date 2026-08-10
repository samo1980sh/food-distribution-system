<?php

namespace App\Services\Imports\Excel;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AreaExcelTemplateService
{
    public function download(): StreamedResponse
    {
        return response()->streamDownload(
            function (): void {
                $writer = new Xlsx($this->makeSpreadsheet());
                $writer->save('php://output');
            },
            'areas-import-template.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ],
        );
    }

    public function makeSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator(config('app.name'))
            ->setTitle('قالب استيراد المناطق')
            ->setSubject('Excel Import Template')
            ->setDescription('قالب معتمد لاستيراد المناطق الجغرافية إلى النظام.');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('المناطق');
        $sheet->setRightToLeft(true);
        $sheet->fromArray(AreaExcelImportService::HEADERS, null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:E1000');
        $sheet->getSheetView()->setZoomScale(95);

        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F766E'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        foreach ([
            'A' => 22,
            'B' => 30,
            'C' => 28,
            'D' => 18,
            'E' => 42,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $validation = $sheet->getDataValidation('D2:D1000');
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('قيمة غير صالحة');
        $validation->setError('اختر active أو inactive فقط.');
        $validation->setPromptTitle('الحالة');
        $validation->setPrompt('اختر active أو inactive. ترك الخلية فارغة يعني active.');
        $validation->setFormula1('"active,inactive"');

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('تعليمات');
        $instructions->setRightToLeft(true);
        $instructions->fromArray([
            ['العمود', 'الوصف', 'إجباري؟', 'القيم / مثال'],
            ['code', 'رمز فريد للمنطقة', 'نعم', 'AREA-001'],
            ['name_ar', 'اسم المنطقة بالعربية', 'نعم', 'دمشق - مركز'],
            ['city', 'المدينة أو المحافظة', 'لا', 'دمشق'],
            ['status', 'حالة المنطقة', 'لا', 'active أو inactive - الافتراضي active'],
            ['notes', 'ملاحظات داخلية', 'لا', 'نص اختياري'],
            ['', '', '', ''],
            ['مهم', 'لا تغيّر أسماء الأعمدة أو ترتيبها في ورقة المناطق.', '', ''],
            ['مهم', 'لا تضع صفوفًا تجريبية في ورقة المناطق إلا إذا كنت تريد استيرادها فعلًا.', '', ''],
            ['سياسة الاستيراد', 'All-or-Nothing: أي خطأ في أي صف يمنع استيراد الملف كاملًا.', '', ''],
        ], null, 'A1');
        $instructions->freezePane('A2');
        $instructions->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $instructions->getStyle('A8:D10')->getFont()->setBold(true);
        $instructions->getColumnDimension('A')->setWidth(24);
        $instructions->getColumnDimension('B')->setWidth(58);
        $instructions->getColumnDimension('C')->setWidth(16);
        $instructions->getColumnDimension('D')->setWidth(48);
        $instructions->getStyle('A1:D10')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
