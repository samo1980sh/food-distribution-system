<?php

namespace App\Services\Imports\Excel;

use App\Models\Product;
use App\Models\Warehouse;
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

class OpeningInventoryExcelTemplateService
{
    public function download(): StreamedResponse
    {
        return response()->streamDownload(
            function (): void {
                $writer = new Xlsx($this->makeSpreadsheet());
                $writer->save('php://output');
            },
            'opening-inventory-import-template.xlsx',
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
            ->setTitle('قالب استيراد الرصيد الافتتاحي')
            ->setSubject('Opening Inventory Excel Import Template')
            ->setDescription('قالب معتمد لإدخال الرصيد الافتتاحي عبر محرك حركات المخزون الحالي.');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('الرصيد الافتتاحي');
        $sheet->setRightToLeft(true);
        $sheet->fromArray(OpeningInventoryExcelImportService::HEADERS, null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:H1000');
        $sheet->getSheetView()->setZoomScale(90);

        $sheet->getStyle('A1:H1')->applyFromArray([
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
            'B' => 22,
            'C' => 16,
            'D' => 24,
            'E' => 20,
            'F' => 18,
            'G' => 20,
            'H' => 48,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle('A2:B1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('D2:D1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('C2:C1000')->getNumberFormat()->setFormatCode('#,##0.000');
        $sheet->getStyle('F2:F1000')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('E2:E1000')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle('G2:G1000')->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        $warehouses = Warehouse::query()
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['code', 'name', 'type']);

        $products = Product::query()
            ->where('status', 'active')
            ->orderBy('sku')
            ->get(['sku', 'name_ar', 'has_expiry', 'purchase_price']);

        $references = $spreadsheet->createSheet();
        $references->setTitle('القوائم المرجعية');
        $references->setRightToLeft(true);
        $references->fromArray([
            ['warehouse_code', 'اسم المستودع', 'نوع المستودع', '', 'sku', 'اسم المنتج', 'has_expiry', 'purchase_price'],
        ], null, 'A1');
        $references->freezePane('A2');

        $references->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach ([
            'A' => 26,
            'B' => 42,
            'C' => 22,
            'D' => 4,
            'E' => 24,
            'F' => 44,
            'G' => 16,
            'H' => 20,
        ] as $column => $width) {
            $references->getColumnDimension($column)->setWidth($width);
        }

        $references->getStyle('A:A')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $references->getStyle('E:E')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        foreach ($warehouses as $index => $warehouse) {
            $row = $index + 2;
            $references->setCellValueExplicit('A'.$row, (string) $warehouse->code, DataType::TYPE_STRING);
            $references->setCellValue('B'.$row, $warehouse->name);
            $references->setCellValue('C'.$row, $warehouse->type);
        }

        foreach ($products as $index => $product) {
            $row = $index + 2;
            $references->setCellValueExplicit('E'.$row, (string) $product->sku, DataType::TYPE_STRING);
            $references->setCellValue('F'.$row, $product->name_ar);
            $references->setCellValue('G'.$row, $product->has_expiry ? 1 : 0);
            $references->setCellValue('H'.$row, (float) $product->purchase_price);
        }

        $warehouseLastRow = max(2, $warehouses->count() + 1);
        $productLastRow = max(2, $products->count() + 1);

        if ($warehouses->isEmpty()) {
            $references->setCellValueExplicit('A2', '', DataType::TYPE_STRING);
        }

        if ($products->isEmpty()) {
            $references->setCellValueExplicit('E2', '', DataType::TYPE_STRING);
        }

        $spreadsheet->addNamedRange(new NamedRange(
            'ACTIVE_WAREHOUSE_CODES',
            $references,
            '=$A$2:$A$'.$warehouseLastRow,
        ));
        $spreadsheet->addNamedRange(new NamedRange(
            'ACTIVE_PRODUCT_SKUS',
            $references,
            '=$E$2:$E$'.$productLastRow,
        ));

        $warehouseValidation = new DataValidation();
        $warehouseValidation->setType(DataValidation::TYPE_LIST);
        $warehouseValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $warehouseValidation->setAllowBlank(false);
        $warehouseValidation->setShowInputMessage(true);
        $warehouseValidation->setShowErrorMessage(true);
        $warehouseValidation->setShowDropDown(true);
        $warehouseValidation->setErrorTitle('مستودع غير صالح');
        $warehouseValidation->setError('اختر warehouse_code من القائمة المرجعية الفعالة.');
        $warehouseValidation->setPromptTitle('المستودع');
        $warehouseValidation->setPrompt('اختر warehouse_code من القائمة.');
        $warehouseValidation->setFormula1('=ACTIVE_WAREHOUSE_CODES');
        $sheet->setDataValidation('A2:A1000', $warehouseValidation);

        $productValidation = new DataValidation();
        $productValidation->setType(DataValidation::TYPE_LIST);
        $productValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $productValidation->setAllowBlank(false);
        $productValidation->setShowInputMessage(true);
        $productValidation->setShowErrorMessage(true);
        $productValidation->setShowDropDown(true);
        $productValidation->setErrorTitle('منتج غير صالح');
        $productValidation->setError('اختر sku من القائمة المرجعية الفعالة.');
        $productValidation->setPromptTitle('المنتج');
        $productValidation->setPrompt('اختر sku من القائمة.');
        $productValidation->setFormula1('=ACTIVE_PRODUCT_SKUS');
        $sheet->setDataValidation('B2:B1000', $productValidation);

        $quantityValidation = new DataValidation();
        $quantityValidation->setType(DataValidation::TYPE_DECIMAL);
        $quantityValidation->setOperator(DataValidation::OPERATOR_GREATERTHAN);
        $quantityValidation->setAllowBlank(false);
        $quantityValidation->setShowErrorMessage(true);
        $quantityValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $quantityValidation->setErrorTitle('كمية غير صالحة');
        $quantityValidation->setError('الكمية يجب أن تكون أكبر من الصفر.');
        $quantityValidation->setFormula1('0');
        $sheet->setDataValidation('C2:C1000', $quantityValidation);

        $costValidation = new DataValidation();
        $costValidation->setType(DataValidation::TYPE_DECIMAL);
        $costValidation->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL);
        $costValidation->setAllowBlank(false);
        $costValidation->setShowErrorMessage(true);
        $costValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $costValidation->setErrorTitle('تكلفة غير صالحة');
        $costValidation->setError('تكلفة الوحدة يجب أن تكون صفرًا أو أكبر.');
        $costValidation->setFormula1('0');
        $sheet->setDataValidation('F2:F1000', $costValidation);

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('تعليمات');
        $instructions->setRightToLeft(true);
        $instructions->fromArray([
            ['العمود', 'الوصف', 'إجباري؟', 'القيم / مثال'],
            ['warehouse_code', 'رمز المستودع الفعال الذي يوجد فيه الرصيد عند بدء استخدام النظام', 'نعم', 'اختر من القائمة المرجعية'],
            ['sku', 'SKU / رمز المنتج الفعال', 'نعم', 'اختر من القائمة المرجعية'],
            ['quantity', 'الكمية الافتتاحية الفعلية', 'نعم', 'أكبر من 0 - يدعم 3 منازل عشرية'],
            ['batch_number', 'رقم التشغيلة / الدفعة إن وجد', 'لا', 'OPEN-PRD001-A'],
            ['expiry_date', 'تاريخ الصلاحية؛ مطلوب للمنتج الذي has_expiry = 1 ويترك فارغًا للمنتج الذي has_expiry = 0', 'بحسب المنتج', 'YYYY-MM-DD'],
            ['unit_cost', 'تكلفة الوحدة الفعلية للرصيد الافتتاحي', 'نعم', '0 أو أكبر؛ سعر الشراء المرجعي ظاهر في القوائم المرجعية'],
            ['movement_date', 'تاريخ اعتماد الرصيد الافتتاحي في دفتر المخزون', 'نعم', 'YYYY-MM-DD'],
            ['notes', 'سبب موثق للحركة الإدارية', 'نعم', 'رصيد افتتاحي عند بدء تشغيل النظام'],
            ['', '', '', ''],
            ['مهم', 'كل صف ينشئ حركة opening_balance فعلية عبر InventoryMovementService ولا يكتب مباشرة في جدول الأرصدة.', '', ''],
            ['مهم', 'يمكن تكرار نفس sku في عدة صفوف عند وجود تشغيلات أو تواريخ صلاحية مختلفة.', '', ''],
            ['مهم', 'القالب يعرض المستودعات والمنتجات الفعالة المتاحة للمستخدم وقت التحميل.', '', ''],
            ['مهم', 'لا تغيّر أسماء الأعمدة أو ترتيبها في ورقة الرصيد الافتتاحي.', '', ''],
            ['سياسة الاستيراد', 'All-or-Nothing: أي خطأ في أي صف يمنع استيراد الملف كاملًا.', '', ''],
        ], null, 'A1');
        $instructions->freezePane('A2');
        $instructions->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $instructions->getStyle('A11:D15')->getFont()->setBold(true);
        $instructions->getColumnDimension('A')->setWidth(24);
        $instructions->getColumnDimension('B')->setWidth(88);
        $instructions->getColumnDimension('C')->setWidth(18);
        $instructions->getColumnDimension('D')->setWidth(58);
        $instructions->getStyle('A1:D15')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
