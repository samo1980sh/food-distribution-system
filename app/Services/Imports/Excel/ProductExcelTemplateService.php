<?php

namespace App\Services\Imports\Excel;

use App\Models\ProductCategory;
use App\Models\Unit;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductExcelTemplateService
{
    public function download(): StreamedResponse
    {
        return response()->streamDownload(
            function (): void {
                $writer = new Xlsx($this->makeSpreadsheet());
                $writer->save('php://output');
            },
            'products-import-template.xlsx',
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
            ->setTitle('قالب استيراد المنتجات')
            ->setSubject('Excel Import Template')
            ->setDescription('قالب معتمد لاستيراد دليل المنتجات والأسعار المرجعية إلى النظام.');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('المنتجات');
        $sheet->setRightToLeft(true);
        $sheet->fromArray(ProductExcelImportService::HEADERS, null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:L1000');
        $sheet->getSheetView()->setZoomScale(90);

        $sheet->getStyle('A1:L1')->applyFromArray([
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
            'B' => 22,
            'C' => 34,
            'D' => 24,
            'E' => 22,
            'F' => 18,
            'G' => 18,
            'H' => 18,
            'I' => 18,
            'J' => 16,
            'K' => 16,
            'L' => 42,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle('A2:B1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('F2:H1000')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('I2:I1000')->getNumberFormat()->setFormatCode('#,##0.000');

        $categories = ProductCategory::query()
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['code', 'name_ar']);

        $units = Unit::query()
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['code', 'name_ar']);

        $references = $spreadsheet->createSheet();
        $references->setTitle('القوائم المرجعية');
        $references->setRightToLeft(true);
        $references->fromArray([
            ['category_code', 'اسم التصنيف', '', 'unit_code', 'اسم الوحدة'],
        ], null, 'A1');
        $references->freezePane('A2');

        $references->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $references->getColumnDimension('A')->setWidth(26);
        $references->getColumnDimension('B')->setWidth(42);
        $references->getColumnDimension('C')->setWidth(4);
        $references->getColumnDimension('D')->setWidth(24);
        $references->getColumnDimension('E')->setWidth(36);
        $references->getStyle('A:A')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $references->getStyle('D:D')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        foreach ($categories as $index => $category) {
            $row = $index + 2;
            $references->setCellValueExplicit('A'.$row, (string) $category->code, DataType::TYPE_STRING);
            $references->setCellValue('B'.$row, $category->name_ar);
        }

        foreach ($units as $index => $unit) {
            $row = $index + 2;
            $references->setCellValueExplicit('D'.$row, (string) $unit->code, DataType::TYPE_STRING);
            $references->setCellValue('E'.$row, $unit->name_ar);
        }

        $categoryLastRow = max(2, $categories->count() + 1);
        $unitLastRow = max(2, $units->count() + 1);

        if ($categories->isEmpty()) {
            $references->setCellValueExplicit('A2', '', DataType::TYPE_STRING);
        }

        if ($units->isEmpty()) {
            $references->setCellValueExplicit('D2', '', DataType::TYPE_STRING);
        }

        $spreadsheet->addNamedRange(new NamedRange(
            'ACTIVE_CATEGORY_CODES',
            $references,
            '=$A$2:$A$'.$categoryLastRow,
        ));
        $spreadsheet->addNamedRange(new NamedRange(
            'ACTIVE_UNIT_CODES',
            $references,
            '=$D$2:$D$'.$unitLastRow,
        ));

        $categoryValidation = new DataValidation();
        $categoryValidation->setType(DataValidation::TYPE_LIST);
        $categoryValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $categoryValidation->setAllowBlank(true);
        $categoryValidation->setShowInputMessage(true);
        $categoryValidation->setShowErrorMessage(true);
        $categoryValidation->setShowDropDown(true);
        $categoryValidation->setErrorTitle('تصنيف غير صالح');
        $categoryValidation->setError('اختر رمز تصنيف من القائمة المرجعية الفعالة.');
        $categoryValidation->setPromptTitle('التصنيف');
        $categoryValidation->setPrompt('اختر category_code من القائمة. يمكن تركه فارغًا.');
        $categoryValidation->setFormula1('=ACTIVE_CATEGORY_CODES');
        $sheet->setDataValidation('D2:D1000', $categoryValidation);

        $unitValidation = new DataValidation();
        $unitValidation->setType(DataValidation::TYPE_LIST);
        $unitValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $unitValidation->setAllowBlank(true);
        $unitValidation->setShowInputMessage(true);
        $unitValidation->setShowErrorMessage(true);
        $unitValidation->setShowDropDown(true);
        $unitValidation->setErrorTitle('وحدة غير صالحة');
        $unitValidation->setError('اختر رمز وحدة من القائمة المرجعية الفعالة.');
        $unitValidation->setPromptTitle('وحدة القياس');
        $unitValidation->setPrompt('اختر unit_code من القائمة. يمكن تركه فارغًا.');
        $unitValidation->setFormula1('=ACTIVE_UNIT_CODES');
        $sheet->setDataValidation('E2:E1000', $unitValidation);

        $numericValidation = new DataValidation();
        $numericValidation->setType(DataValidation::TYPE_DECIMAL);
        $numericValidation->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL);
        $numericValidation->setAllowBlank(true);
        $numericValidation->setShowErrorMessage(true);
        $numericValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $numericValidation->setErrorTitle('قيمة غير صالحة');
        $numericValidation->setError('القيمة يجب أن تكون رقمًا صفر أو أكبر.');
        $numericValidation->setFormula1('0');
        $sheet->setDataValidation('F2:I1000', $numericValidation);

        $expiryValidation = new DataValidation();
        $expiryValidation->setType(DataValidation::TYPE_LIST);
        $expiryValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $expiryValidation->setAllowBlank(true);
        $expiryValidation->setShowInputMessage(true);
        $expiryValidation->setShowErrorMessage(true);
        $expiryValidation->setShowDropDown(true);
        $expiryValidation->setErrorTitle('قيمة غير صالحة');
        $expiryValidation->setError('اختر 1 أو 0 فقط.');
        $expiryValidation->setPromptTitle('تتبع الصلاحية');
        $expiryValidation->setPrompt('1 = نعم، 0 = لا. ترك الخلية فارغة يعني 1.');
        $expiryValidation->setFormula1('"1,0"');
        $sheet->setDataValidation('J2:J1000', $expiryValidation);

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
        $sheet->setDataValidation('K2:K1000', $statusValidation);

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('تعليمات');
        $instructions->setRightToLeft(true);
        $instructions->fromArray([
            ['العمود', 'الوصف', 'إجباري؟', 'القيم / مثال'],
            ['sku', 'SKU / رمز فريد للمنتج', 'نعم', 'PRD-001'],
            ['barcode', 'باركود فريد - العمود مهيأ كنص للمحافظة على الأصفار في البداية', 'لا', '0123456789012'],
            ['name_ar', 'اسم المنتج بالعربية', 'نعم', 'عصير برتقال 1 لتر'],
            ['category_code', 'رمز تصنيف فعال؛ اختره من القائمة المنسدلة في ورقة المنتجات', 'لا', 'القائمة تُحدّث وقت تحميل القالب'],
            ['unit_code', 'رمز وحدة قياس فعالة؛ اختره من القائمة المنسدلة في ورقة المنتجات', 'لا', 'القائمة تُحدّث وقت تحميل القالب'],
            ['purchase_price', 'سعر الشراء المرجعي', 'لا', '0 أو أكبر - الافتراضي 0'],
            ['sale_price', 'سعر البيع', 'لا', '0 أو أكبر - الافتراضي 0'],
            ['wholesale_price', 'سعر الجملة', 'لا', '0 أو أكبر - الافتراضي 0'],
            ['min_stock', 'حد التنبيه للمخزون', 'لا', '0 أو أكبر - يدعم 3 منازل عشرية - الافتراضي 0'],
            ['has_expiry', 'هل يتطلب المنتج تتبع تاريخ صلاحية؟', 'لا', '1 = نعم، 0 = لا - الافتراضي 1'],
            ['status', 'حالة المنتج', 'لا', 'active أو inactive - الافتراضي active'],
            ['notes', 'ملاحظات داخلية', 'لا', 'نص اختياري'],
            ['', '', '', ''],
            ['مهم', 'ورقة القوائم المرجعية تحتوي التصنيفات والوحدات الفعالة الموجودة لحظة تحميل القالب، ويمكن اختيار رموزها من القوائم المنسدلة.', '', ''],
            ['مهم', 'هذا الاستيراد ينشئ دليل المنتجات فقط ولا يضيف أرصدة أو حركات مخزون.', '', ''],
            ['مهم', 'لا تغيّر أسماء الأعمدة أو ترتيبها في ورقة المنتجات.', '', ''],
            ['سياسة الاستيراد', 'All-or-Nothing: أي خطأ في أي صف يمنع استيراد الملف كاملًا.', '', ''],
        ], null, 'A1');
        $instructions->freezePane('A2');
        $instructions->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $instructions->getStyle('A15:D18')->getFont()->setBold(true);
        $instructions->getColumnDimension('A')->setWidth(24);
        $instructions->getColumnDimension('B')->setWidth(78);
        $instructions->getColumnDimension('C')->setWidth(16);
        $instructions->getColumnDimension('D')->setWidth(56);
        $instructions->getStyle('A1:D18')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
