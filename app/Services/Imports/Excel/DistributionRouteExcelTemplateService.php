<?php

namespace App\Services\Imports\Excel;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Employee;
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

class DistributionRouteExcelTemplateService
{
    public function download(): StreamedResponse
    {
        return response()->streamDownload(
            function (): void {
                $writer = new Xlsx($this->makeSpreadsheet());
                $writer->save('php://output');
            },
            'distribution-routes-import-template.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ],
        );
    }

    public function makeSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator(config('app.name'))
            ->setTitle('قالب استيراد خطوط التوزيع')
            ->setSubject('Excel Import Template')
            ->setDescription('قالب معتمد لاستيراد خطوط التوزيع وربط المناطق والسيارات والفرق الميدانية بأكواد الأعمال.');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('خطوط التوزيع');
        $sheet->setRightToLeft(true);
        $sheet->fromArray(DistributionRouteExcelImportService::HEADERS, null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:H1000');
        $sheet->getSheetView()->setZoomScale(85);

        $sheet->getStyle('A1:H1')->applyFromArray([
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
            'A' => 22,
            'B' => 34,
            'C' => 24,
            'D' => 24,
            'E' => 30,
            'F' => 44,
            'G' => 16,
            'H' => 46,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        foreach (['A', 'C', 'D', 'E'] as $column) {
            $sheet->getStyle($column.'2:'.$column.'1000')
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        $areas = Area::query()
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'code', 'name_ar']);

        $vehicles = Vehicle::query()
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'code', 'plate_number', 'name']);

        $representatives = Employee::query()
            ->where('status', 'active')
            ->forOperationalRole(UserRole::SALES_REPRESENTATIVE)
            ->orderBy('employee_code')
            ->get(['id', 'employee_code', 'name', 'type']);

        $references = $spreadsheet->createSheet();
        $references->setTitle('القوائم المرجعية');
        $references->setRightToLeft(true);
        $references->freezePane('A2');

        $references->fromArray([
            ['area_code', 'اسم المنطقة'],
        ], null, 'A1');
        $references->fromArray([
            ['vehicle_code', 'اسم / وصف السيارة', 'رقم اللوحة'],
        ], null, 'D1');
        $references->fromArray([
            ['sales_representative_code', 'اسم المندوب', 'نوع سجل الموظف'],
        ], null, 'H1');
        $references->fromArray([
            ['visit_day', 'الوصف'],
        ], null, 'L1');

        foreach (['A1:B1', 'D1:F1', 'H1:J1', 'L1:M1'] as $range) {
            $references->getStyle($range)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        foreach ([
            'A' => 26, 'B' => 34,
            'D' => 26, 'E' => 26, 'F' => 34,
            'H' => 32, 'I' => 34, 'J' => 24,
            'L' => 22, 'M' => 24,
        ] as $column => $width) {
            $references->getColumnDimension($column)->setWidth($width);
        }

        foreach (['A', 'D', 'H', 'L'] as $column) {
            $references->getStyle($column.':'.$column)
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        foreach ($areas as $index => $area) {
            $row = $index + 2;
            $references->setCellValueExplicit('A'.$row, (string) $area->code, DataType::TYPE_STRING);
            $references->setCellValue('B'.$row, $area->name_ar);
        }

        foreach ($vehicles as $index => $vehicle) {
            $row = $index + 2;
            $references->setCellValueExplicit('D'.$row, (string) $vehicle->code, DataType::TYPE_STRING);
            $references->setCellValueExplicit('F'.$row, (string) $vehicle->plate_number, DataType::TYPE_STRING);
            $references->setCellValue('E'.$row, $vehicle->name);
        }

        foreach ($representatives as $index => $representative) {
            $row = $index + 2;
            $references->setCellValueExplicit('H'.$row, (string) $representative->employee_code, DataType::TYPE_STRING);
            $references->setCellValue('I'.$row, $representative->name);
            $references->setCellValue('J'.$row, $representative->type);
        }

        $dayLabels = [
            'saturday' => 'السبت',
            'sunday' => 'الأحد',
            'monday' => 'الإثنين',
            'tuesday' => 'الثلاثاء',
            'wednesday' => 'الأربعاء',
            'thursday' => 'الخميس',
            'friday' => 'الجمعة',
        ];

        foreach (DistributionRouteExcelImportService::VISIT_DAYS as $index => $day) {
            $row = $index + 2;
            $references->setCellValueExplicit('L'.$row, $day, DataType::TYPE_STRING);
            $references->setCellValue('M'.$row, $dayLabels[$day]);
        }

        $areaLastRow = max(2, $areas->count() + 1);
        $vehicleLastRow = max(2, $vehicles->count() + 1);
        $representativeLastRow = max(2, $representatives->count() + 1);

        if ($areas->isEmpty()) {
            $references->setCellValueExplicit('A2', '', DataType::TYPE_STRING);
        }
        if ($vehicles->isEmpty()) {
            $references->setCellValueExplicit('D2', '', DataType::TYPE_STRING);
        }
        if ($representatives->isEmpty()) {
            $references->setCellValueExplicit('H2', '', DataType::TYPE_STRING);
        }

        $spreadsheet->addNamedRange(new NamedRange('ACTIVE_AREA_CODES', $references, '=$A$2:$A$'.$areaLastRow));
        $spreadsheet->addNamedRange(new NamedRange('ACTIVE_VEHICLE_CODES', $references, '=$D$2:$D$'.$vehicleLastRow));
        $spreadsheet->addNamedRange(new NamedRange('ACTIVE_SALES_REP_CODES', $references, '=$H$2:$H$'.$representativeLastRow));

        $areaValidation = $this->listValidation(
            '=ACTIVE_AREA_CODES',
            false,
            'منطقة غير صالحة',
            'اختر area_code من قائمة المناطق الفعالة.',
            'المنطقة',
            'هذا الحقل إجباري ويستخدم رمز المنطقة، وليس رقم قاعدة البيانات.',
        );
        $sheet->setDataValidation('C2:C1000', $areaValidation);

        $vehicleValidation = $this->listValidation(
            '=ACTIVE_VEHICLE_CODES',
            true,
            'سيارة غير صالحة',
            'اختر vehicle_code من قائمة السيارات الفعالة أو اتركه فارغًا.',
            'السيارة',
            'اختياري. يتم حفظ الربط داخليًا باستخدام السيارة المطابقة لهذا الكود.',
        );
        $sheet->setDataValidation('D2:D1000', $vehicleValidation);

        $representativeValidation = $this->listValidation(
            '=ACTIVE_SALES_REP_CODES',
            true,
            'مندوب غير صالح',
            'اختر sales_representative_code من قائمة الموظفين الفعالين المؤهلين كمندوبي مبيعات أو اتركه فارغًا.',
            'مندوب المبيعات',
            'اختياري. القائمة تراعي الأهلية التشغيلية الحالية، بما فيها الحسابات ثنائية الدور.',
        );
        $sheet->setDataValidation('E2:E1000', $representativeValidation);

        $statusValidation = $this->listValidation(
            '"active,inactive"',
            true,
            'حالة غير صالحة',
            'اختر active أو inactive فقط.',
            'الحالة',
            'ترك الخلية فارغة يعني active.',
        );
        $sheet->setDataValidation('G2:G1000', $statusValidation);

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('تعليمات');
        $instructions->setRightToLeft(true);
        $instructions->fromArray([
            ['العمود', 'الوصف', 'إجباري؟', 'القيم / مثال'],
            ['code', 'رمز فريد لخط التوزيع', 'نعم', 'ROUTE-001'],
            ['name', 'اسم خط التوزيع', 'نعم', 'خط وسط دمشق'],
            ['area_code', 'رمز المنطقة الفعالة', 'نعم', 'اختر من القائمة المرجعية'],
            ['vehicle_code', 'رمز السيارة الفعالة المرتبطة بالخط', 'لا', 'اختر من القائمة أو اتركه فارغًا'],
            ['sales_representative_code', 'رمز الموظف الفعال المؤهل كمندوب مبيعات', 'لا', 'اختر من القائمة أو اتركه فارغًا'],
            ['visit_days', 'أيام الزيارة مفصولة بفاصلة إنجليزية', 'لا', 'saturday,monday,wednesday'],
            ['status', 'حالة خط التوزيع', 'لا', 'active أو inactive - الافتراضي active'],
            ['notes', 'ملاحظات تشغيلية', 'لا', 'نص اختياري'],
            ['', '', '', ''],
            ['مهم', 'visit_days يدعم الأيام الإنجليزية فقط كما تظهر في القوائم المرجعية، ويمكن تركه فارغًا.', '', ''],
            ['مهم', 'sales_representative_code يطبق أهلية مندوب المبيعات المستخدمة في شاشة خطوط التوزيع.', '', ''],
            ['مهم', 'كل المراجع تعتمد أكواد الأعمال فقط؛ لا تستخدم area_id أو vehicle_id أو employee IDs.', '', ''],
            ['مهم', 'لا تغيّر أسماء الأعمدة أو ترتيبها في ورقة خطوط التوزيع.', '', ''],
            ['سياسة الاستيراد', 'All-or-Nothing: أي خطأ في أي صف يمنع استيراد الملف كاملًا.', '', ''],
        ], null, 'A1');
        $instructions->freezePane('A2');
        $instructions->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $instructions->getStyle('A11:D15')->getFont()->setBold(true);
        $instructions->getColumnDimension('A')->setWidth(32);
        $instructions->getColumnDimension('B')->setWidth(92);
        $instructions->getColumnDimension('C')->setWidth(18);
        $instructions->getColumnDimension('D')->setWidth(66);
        $instructions->getStyle('A1:D15')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function listValidation(
        string $formula,
        bool $allowBlank,
        string $errorTitle,
        string $error,
        string $promptTitle,
        string $prompt,
    ): DataValidation {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank($allowBlank);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle($errorTitle);
        $validation->setError($error);
        $validation->setPromptTitle($promptTitle);
        $validation->setPrompt($prompt);
        $validation->setFormula1($formula);

        return $validation;
    }
}
