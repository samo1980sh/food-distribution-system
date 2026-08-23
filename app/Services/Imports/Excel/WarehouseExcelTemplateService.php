<?php

namespace App\Services\Imports\Excel;

use App\Models\Vehicle;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarehouseExcelTemplateService
{
    public function download(): StreamedResponse
    {
        return response()->streamDownload(
            function (): void {
                $writer = new Xlsx($this->makeSpreadsheet());
                $writer->save('php://output');
            },
            'warehouses-import-template.xlsx',
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
            ->setTitle('قالب استيراد المستودعات')
            ->setSubject('Excel Import Template')
            ->setDescription('قالب معتمد لاستيراد هيكل المستودعات وربط مخازن السيارات بالسيارات الفعالة المتاحة.');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('المستودعات');
        $sheet->setRightToLeft(true);
        $sheet->fromArray(WarehouseExcelImportService::HEADERS, null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:G1000');
        $sheet->getSheetView()->setZoomScale(90);

        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
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
            'B' => 36,
            'C' => 18,
            'D' => 24,
            'E' => 44,
            'F' => 16,
            'G' => 46,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle('A2:A1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('D2:D1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        $vehicles = Vehicle::query()
            ->where('status', 'active')
            ->whereDoesntHave('warehouse')
            ->orderBy('code')
            ->get(['code', 'plate_number', 'name']);

        $references = $spreadsheet->createSheet();
        $references->setTitle('القوائم المرجعية');
        $references->setRightToLeft(true);
        $references->fromArray([
            ['vehicle_code', 'اسم / وصف السيارة', 'رقم اللوحة'],
        ], null, 'A1');
        $references->freezePane('A2');
        $references->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $references->getColumnDimension('A')->setWidth(26);
        $references->getColumnDimension('B')->setWidth(24);
        $references->getColumnDimension('C')->setWidth(42);
        $references->getStyle('A:A')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $references->getStyle('B:B')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        foreach ($vehicles as $index => $vehicle) {
            $row = $index + 2;
            $references->setCellValueExplicit('A'.$row, (string) $vehicle->code, DataType::TYPE_STRING);
            $references->setCellValueExplicit('C'.$row, (string) $vehicle->plate_number, DataType::TYPE_STRING);
            $references->setCellValue('B'.$row, $vehicle->name);
        }

        $vehicleLastRow = max(2, $vehicles->count() + 1);
        if ($vehicles->isEmpty()) {
            $references->setCellValueExplicit('A2', '', DataType::TYPE_STRING);
        }

        $spreadsheet->addNamedRange(new NamedRange(
            'AVAILABLE_VEHICLE_CODES',
            $references,
            '=$A$2:$A$'.$vehicleLastRow,
        ));

        $typeValidation = new DataValidation();
        $typeValidation->setType(DataValidation::TYPE_LIST);
        $typeValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $typeValidation->setAllowBlank(true);
        $typeValidation->setShowInputMessage(true);
        $typeValidation->setShowErrorMessage(true);
        $typeValidation->setShowDropDown(true);
        $typeValidation->setErrorTitle('نوع غير صالح');
        $typeValidation->setError('اختر main أو branch أو vehicle فقط.');
        $typeValidation->setPromptTitle('نوع المستودع');
        $typeValidation->setPrompt('ترك الخلية فارغة يعني main.');
        $typeValidation->setFormula1('"main,branch,vehicle"');
        $sheet->setDataValidation('C2:C1000', $typeValidation);

        $vehicleValidation = new DataValidation();
        $vehicleValidation->setType(DataValidation::TYPE_LIST);
        $vehicleValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $vehicleValidation->setAllowBlank(true);
        $vehicleValidation->setShowInputMessage(true);
        $vehicleValidation->setShowErrorMessage(true);
        $vehicleValidation->setShowDropDown(true);
        $vehicleValidation->setErrorTitle('سيارة غير صالحة');
        $vehicleValidation->setError('اختر vehicle_code من قائمة السيارات الفعالة غير المرتبطة بمستودع.');
        $vehicleValidation->setPromptTitle('السيارة المرتبطة');
        $vehicleValidation->setPrompt('مطلوب فقط عندما يكون type = vehicle، ويجب تركه فارغًا مع main أو branch.');
        $vehicleValidation->setFormula1('=AVAILABLE_VEHICLE_CODES');
        $sheet->setDataValidation('D2:D1000', $vehicleValidation);

        $statusValidation = new DataValidation();
        $statusValidation->setType(DataValidation::TYPE_LIST);
        $statusValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $statusValidation->setAllowBlank(true);
        $statusValidation->setShowInputMessage(true);
        $statusValidation->setShowErrorMessage(true);
        $statusValidation->setShowDropDown(true);
        $statusValidation->setErrorTitle('حالة غير صالحة');
        $statusValidation->setError('اختر active أو inactive فقط.');
        $statusValidation->setPromptTitle('الحالة');
        $statusValidation->setPrompt('ترك الخلية فارغة يعني active.');
        $statusValidation->setFormula1('"active,inactive"');
        $sheet->setDataValidation('F2:F1000', $statusValidation);

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('تعليمات');
        $instructions->setRightToLeft(true);
        $instructions->fromArray([
            ['العمود', 'الوصف', 'إجباري؟', 'القيم / مثال'],
            ['code', 'رمز فريد للمستودع', 'نعم', 'WH-MAIN-01'],
            ['name', 'اسم المستودع', 'نعم', 'المستودع الرئيسي'],
            ['type', 'نوع المستودع', 'لا', 'main أو branch أو vehicle - الافتراضي main'],
            ['vehicle_code', 'رمز السيارة المرتبطة بمستودع متنقل', 'حسب النوع', 'مطلوب فقط مع vehicle، واختره من القائمة المنسدلة'],
            ['address', 'عنوان المستودع', 'لا', 'نص حتى 255 محرفًا'],
            ['status', 'حالة المستودع', 'لا', 'active أو inactive - الافتراضي active'],
            ['notes', 'ملاحظات داخلية', 'لا', 'نص اختياري'],
            ['', '', '', ''],
            ['مهم', 'vehicle_code يجب أن يكون فارغًا مع main أو branch، ومطلوبًا مع vehicle.', '', ''],
            ['مهم', 'ورقة القوائم المرجعية تعرض فقط السيارات الفعالة غير المرتبطة بمستودع لحظة تحميل القالب.', '', ''],
            ['مهم', 'الاستيراد يعيد التحقق من السيارة والربط عند التنفيذ ولا يعتمد على القائمة المنسدلة وحدها.', '', ''],
            ['مهم', 'هذا الاستيراد ينشئ هيكل المستودعات فقط ولا ينشئ أو يعدّل أرصدة أو حركات مخزون.', '', ''],
            ['مهم', 'لا تغيّر أسماء الأعمدة أو ترتيبها في ورقة المستودعات.', '', ''],
            ['سياسة الاستيراد', 'All-or-Nothing: أي خطأ في أي صف يمنع استيراد الملف كاملًا.', '', ''],
        ], null, 'A1');
        $instructions->freezePane('A2');
        $instructions->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $instructions->getStyle('A10:D15')->getFont()->setBold(true);
        $instructions->getColumnDimension('A')->setWidth(24);
        $instructions->getColumnDimension('B')->setWidth(80);
        $instructions->getColumnDimension('C')->setWidth(18);
        $instructions->getColumnDimension('D')->setWidth(58);
        $instructions->getStyle('A1:D15')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
