<?php

namespace App\Services\Imports\Excel;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductCategoryExcelTemplateService
{
    public function download(): StreamedResponse
    {
        return response()->streamDownload(
            function (): void {
                $writer = new Xlsx($this->makeSpreadsheet());
                $writer->save('php://output');
            },
            'product-categories-import-template.xlsx',
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
            ->setTitle('قالب استيراد تصنيفات المنتجات')
            ->setSubject('Excel Import Template')
            ->setDescription('قالب معتمد لاستيراد شجرة تصنيفات المنتجات إلى النظام.');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('التصنيفات');
        $sheet->setRightToLeft(true);
        $sheet->fromArray(ProductCategoryExcelImportService::HEADERS, null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:F1000');
        $sheet->getSheetView()->setZoomScale(95);

        $sheet->getStyle('A1:F1')->applyFromArray([
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
            'A' => 24,
            'B' => 32,
            'C' => 24,
            'D' => 18,
            'E' => 18,
            'F' => 42,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sortValidation = new DataValidation();
        $sortValidation->setType(DataValidation::TYPE_WHOLE);
        $sortValidation->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL);
        $sortValidation->setAllowBlank(true);
        $sortValidation->setShowErrorMessage(true);
        $sortValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $sortValidation->setErrorTitle('قيمة غير صالحة');
        $sortValidation->setError('ترتيب العرض يجب أن يكون رقمًا صحيحًا صفر أو أكبر.');
        $sortValidation->setFormula1('0');
        $sheet->setDataValidation('D2:D1000', $sortValidation);

        $statusValidation = new DataValidation();
        $statusValidation->setType(DataValidation::TYPE_LIST);
        $statusValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $statusValidation->setAllowBlank(true);
        $statusValidation->setShowInputMessage(true);
        $statusValidation->setShowErrorMessage(true);
        $statusValidation->setShowDropDown(true);
        $statusValidation->setErrorTitle('قيمة غير صالحة');
        $statusValidation->setError('اختر active أو inactive فقط.');
        $statusValidation->setPromptTitle('الحالة');
        $statusValidation->setPrompt('اختر active أو inactive. ترك الخلية فارغة يعني active.');
        $statusValidation->setFormula1('"active,inactive"');
        $sheet->setDataValidation('E2:E1000', $statusValidation);

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('تعليمات');
        $instructions->setRightToLeft(true);
        $instructions->fromArray([
            ['العمود', 'الوصف', 'إجباري؟', 'القيم / مثال'],
            ['code', 'رمز فريد للتصنيف', 'نعم', 'BEVERAGES'],
            ['name_ar', 'اسم التصنيف بالعربية', 'نعم', 'المشروبات'],
            ['parent_code', 'رمز التصنيف الأب بدل ID', 'لا', 'FOOD - اتركه فارغًا للتصنيف الرئيسي'],
            ['sort_order', 'ترتيب العرض', 'لا', '0 أو رقم صحيح موجب - الافتراضي 0'],
            ['status', 'حالة التصنيف', 'لا', 'active أو inactive - الافتراضي active'],
            ['notes', 'ملاحظات داخلية', 'لا', 'نص اختياري'],
            ['', '', '', ''],
            ['مهم', 'يمكن أن يشير parent_code إلى تصنيف فعال موجود مسبقًا أو إلى تصنيف فعال موجود في الملف نفسه.', '', ''],
            ['مهم', 'ترتيب الصفوف لا يهم؛ يمكن أن يأتي التصنيف الابن قبل الأب داخل الملف.', '', ''],
            ['مهم', 'لا تغيّر أسماء الأعمدة أو ترتيبها في ورقة التصنيفات.', '', ''],
            ['سياسة الاستيراد', 'All-or-Nothing: أي خطأ في أي صف يمنع استيراد الملف كاملًا.', '', ''],
        ], null, 'A1');
        $instructions->freezePane('A2');
        $instructions->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $instructions->getStyle('A9:D12')->getFont()->setBold(true);
        $instructions->getColumnDimension('A')->setWidth(24);
        $instructions->getColumnDimension('B')->setWidth(72);
        $instructions->getColumnDimension('C')->setWidth(16);
        $instructions->getColumnDimension('D')->setWidth(52);
        $instructions->getStyle('A1:D12')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
