<?php

namespace App\Services\Imports\Excel;

use App\Models\Area;
use App\Models\DistributionRoute;
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

class CustomerExcelTemplateService
{
    public function download(): StreamedResponse
    {
        return response()->streamDownload(
            function (): void {
                $writer = new Xlsx($this->makeSpreadsheet());
                $writer->save('php://output');
            },
            'customers-import-template.xlsx',
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
            ->setTitle('قالب استيراد العملاء')
            ->setSubject('Excel Import Template')
            ->setDescription('قالب معتمد لاستيراد العملاء وربط المنطقة وخط التوزيع بأكواد الأعمال مع السياسة الائتمانية.');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('العملاء');
        $sheet->setRightToLeft(true);
        $sheet->fromArray(CustomerExcelImportService::HEADERS, null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:P1000');
        $sheet->getSheetView()->setZoomScale(78);

        $sheet->getStyle('A1:P1')->applyFromArray([
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
            'C' => 30,
            'D' => 20,
            'E' => 21,
            'F' => 21,
            'G' => 24,
            'H' => 28,
            'I' => 42,
            'J' => 18,
            'K' => 18,
            'L' => 20,
            'M' => 18,
            'N' => 20,
            'O' => 16,
            'P' => 44,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        foreach (['A', 'E', 'F', 'G', 'H'] as $column) {
            $sheet->getStyle($column.'2:'.$column.'1000')
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }
        $sheet->getStyle('J2:K1000')->getNumberFormat()->setFormatCode('0.00000000');
        $sheet->getStyle('L2:L1000')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('M2:M1000')->getNumberFormat()->setFormatCode('0');

        $areas = Area::query()
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'code', 'name_ar']);

        $routes = DistributionRoute::query()
            ->where('status', 'active')
            ->whereHas('area', fn ($query) => $query->where('status', 'active'))
            ->with('area:id,code,name_ar,status')
            ->orderBy('code')
            ->get(['id', 'area_id', 'code', 'name', 'status']);

        $references = $spreadsheet->createSheet();
        $references->setTitle('القوائم المرجعية');
        $references->setRightToLeft(true);
        $references->freezePane('A2');

        $references->fromArray([
            ['area_code', 'اسم المنطقة'],
        ], null, 'A1');
        $references->fromArray([
            ['route_code', 'اسم خط التوزيع', 'area_code', 'اسم المنطقة'],
        ], null, 'D1');
        $references->fromArray([
            ['customer_type', 'الوصف'],
        ], null, 'J1');
        $references->fromArray([
            ['payment_type', 'الوصف'],
        ], null, 'M1');
        $references->fromArray([
            ['status', 'الوصف'],
        ], null, 'P1');

        foreach (['A1:B1', 'D1:G1', 'J1:K1', 'M1:N1', 'P1:Q1'] as $range) {
            $references->getStyle($range)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        foreach ([
            'A' => 26, 'B' => 36,
            'D' => 28, 'E' => 38, 'F' => 26, 'G' => 36,
            'J' => 24, 'K' => 28,
            'M' => 22, 'N' => 28,
            'P' => 18, 'Q' => 24,
        ] as $column => $width) {
            $references->getColumnDimension($column)->setWidth($width);
        }

        foreach (['A', 'D', 'F', 'J', 'M', 'P'] as $column) {
            $references->getStyle($column.':'.$column)
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        foreach ($areas as $index => $area) {
            $row = $index + 2;
            $references->setCellValueExplicit('A'.$row, (string) $area->code, DataType::TYPE_STRING);
            $references->setCellValue('B'.$row, $area->name_ar);
        }

        foreach ($routes as $index => $route) {
            $row = $index + 2;
            $references->setCellValueExplicit('D'.$row, (string) $route->code, DataType::TYPE_STRING);
            $references->setCellValue('E'.$row, $route->name);
            $references->setCellValueExplicit('F'.$row, (string) $route->area->code, DataType::TYPE_STRING);
            $references->setCellValue('G'.$row, $route->area->name_ar);
        }

        $customerTypeLabels = [
            'grocery' => 'بقالية',
            'supermarket' => 'سوبر ماركت',
            'restaurant' => 'مطعم',
            'wholesaler' => 'موزع / جملة',
            'mini_market' => 'ميني ماركت',
            'other' => 'أخرى',
        ];
        foreach (CustomerExcelImportService::CUSTOMER_TYPES as $index => $type) {
            $row = $index + 2;
            $references->setCellValueExplicit('J'.$row, $type, DataType::TYPE_STRING);
            $references->setCellValue('K'.$row, $customerTypeLabels[$type]);
        }

        $paymentTypeLabels = [
            'cash' => 'نقدي',
            'credit' => 'آجل',
            'weekly' => 'أسبوعي',
            'monthly' => 'شهري',
        ];
        foreach (CustomerExcelImportService::PAYMENT_TYPES as $index => $type) {
            $row = $index + 2;
            $references->setCellValueExplicit('M'.$row, $type, DataType::TYPE_STRING);
            $references->setCellValue('N'.$row, $paymentTypeLabels[$type]);
        }

        foreach (CustomerExcelImportService::STATUSES as $index => $status) {
            $row = $index + 2;
            $references->setCellValueExplicit('P'.$row, $status, DataType::TYPE_STRING);
            $references->setCellValue('Q'.$row, $status === 'active' ? 'فعال' : 'غير فعال');
        }

        $areaLastRow = max(2, $areas->count() + 1);
        $routeLastRow = max(2, $routes->count() + 1);

        if ($areas->isEmpty()) {
            $references->setCellValueExplicit('A2', '', DataType::TYPE_STRING);
        }
        if ($routes->isEmpty()) {
            $references->setCellValueExplicit('D2', '', DataType::TYPE_STRING);
        }

        $spreadsheet->addNamedRange(new NamedRange('ACTIVE_CUSTOMER_AREA_CODES', $references, '=$A$2:$A$'.$areaLastRow));
        $spreadsheet->addNamedRange(new NamedRange('ACTIVE_CUSTOMER_ROUTE_CODES', $references, '=$D$2:$D$'.$routeLastRow));

        $customerTypeValidation = $this->listValidation(
            '"grocery,supermarket,restaurant,wholesaler,mini_market,other"',
            true,
            'نوع عميل غير صالح',
            'اختر نوع العميل من القائمة.',
            'نوع العميل',
            'ترك الخلية فارغة يعني grocery.',
        );
        $sheet->setDataValidation('D2:D1000', $customerTypeValidation);

        $areaValidation = $this->listValidation(
            '=ACTIVE_CUSTOMER_AREA_CODES',
            true,
            'منطقة غير صالحة',
            'اختر area_code من قائمة المناطق الفعالة أو اتركه فارغًا.',
            'المنطقة',
            'اختياري. إذا اخترت route_code وتركت المنطقة فارغة، يستنتج النظام المنطقة من خط التوزيع.',
        );
        $sheet->setDataValidation('G2:G1000', $areaValidation);

        $routeValidation = $this->listValidation(
            '=ACTIVE_CUSTOMER_ROUTE_CODES',
            true,
            'خط توزيع غير صالح',
            'اختر route_code من قائمة الخطوط الفعالة أو اتركه فارغًا.',
            'خط التوزيع',
            'اختياري. إذا أدخلت area_code أيضًا، يجب أن يتبع الخط نفس المنطقة.',
        );
        $sheet->setDataValidation('H2:H1000', $routeValidation);

        $paymentValidation = $this->listValidation(
            '"cash,credit,weekly,monthly"',
            true,
            'طريقة دفع غير صالحة',
            'اختر طريقة الدفع من القائمة.',
            'طريقة الدفع',
            'ترك الخلية فارغة يعني cash.',
        );
        $sheet->setDataValidation('N2:N1000', $paymentValidation);

        $statusValidation = $this->listValidation(
            '"active,inactive"',
            true,
            'حالة غير صالحة',
            'اختر active أو inactive فقط.',
            'الحالة',
            'ترك الخلية فارغة يعني active.',
        );
        $sheet->setDataValidation('O2:O1000', $statusValidation);

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('تعليمات');
        $instructions->setRightToLeft(true);
        $instructions->fromArray([
            ['العمود', 'الوصف', 'إجباري؟', 'القيم / مثال'],
            ['code', 'رمز فريد للعميل', 'نعم', 'CUS-001'],
            ['name', 'اسم العميل / المحل', 'نعم', 'سوبر ماركت النور'],
            ['owner_name', 'اسم صاحب المحل', 'لا', 'أحمد محمد'],
            ['customer_type', 'نوع العميل', 'لا', 'grocery / supermarket / restaurant / wholesaler / mini_market / other - الافتراضي grocery'],
            ['phone', 'رقم الهاتف كنص للحفاظ على الأصفار في البداية', 'لا', '0110000000'],
            ['mobile', 'رقم الموبايل كنص للحفاظ على الأصفار في البداية', 'لا', '0999000000'],
            ['area_code', 'رمز المنطقة الفعالة', 'لا', 'اختر من القائمة المرجعية'],
            ['route_code', 'رمز خط التوزيع الفعال', 'لا', 'اختر من القائمة المرجعية'],
            ['address', 'العنوان التفصيلي', 'لا', 'دمشق - المزة'],
            ['latitude', 'خط العرض', 'لا', '33.51234567'],
            ['longitude', 'خط الطول', 'لا', '36.29876543'],
            ['credit_limit', 'حد الائتمان، صفر يعني عدم فرض حد آلي', 'لا', '0 - الافتراضي 0'],
            ['credit_days', 'مدة الائتمان بالأيام', 'لا', '1 إلى 365 - الافتراضي 30'],
            ['payment_type', 'طريقة الدفع المعتادة', 'لا', 'cash / credit / weekly / monthly - الافتراضي cash'],
            ['status', 'حالة العميل', 'لا', 'active أو inactive - الافتراضي active'],
            ['notes', 'ملاحظات داخلية', 'لا', 'نص اختياري'],
            ['', '', '', ''],
            ['مهم', 'إذا تم تحديد route_code وترك area_code فارغًا، يستنتج النظام منطقة العميل من خط التوزيع مثل شاشة الإدارة.', '', ''],
            ['مهم', 'إذا تم تحديد area_code و route_code معًا فيجب أن يتبع خط التوزيع المنطقة نفسها، وإلا يرفض الملف.', '', ''],
            ['مهم', 'القوائم المرجعية تعرض المناطق وخطوط التوزيع الفعالة فقط، وخطوط التوزيع المعروضة تتبع مناطق فعالة.', '', ''],
            ['مهم', 'created_by و client_reference و client_payload_hash و operation_source حقول داخلية وغير موجودة في القالب.', '', ''],
            ['مهم', 'لا تغيّر أسماء الأعمدة أو ترتيبها في ورقة العملاء.', '', ''],
            ['سياسة الاستيراد', 'All-or-Nothing: أي خطأ في أي صف يمنع استيراد الملف كاملًا.', '', ''],
        ], null, 'A1');
        $instructions->freezePane('A2');
        $instructions->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $instructions->getStyle('A19:D24')->getFont()->setBold(true);
        $instructions->getColumnDimension('A')->setWidth(32);
        $instructions->getColumnDimension('B')->setWidth(98);
        $instructions->getColumnDimension('C')->setWidth(18);
        $instructions->getColumnDimension('D')->setWidth(74);
        $instructions->getStyle('A1:D24')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

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
        $validation = new DataValidation();
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
