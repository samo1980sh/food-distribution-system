<?php

namespace App\Services\Imports\Excel;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleExcelTemplateService
{
    public function download(): StreamedResponse
    {
        return response()->streamDownload(
            function (): void {
                $writer = new Xlsx($this->makeSpreadsheet());
                $writer->save('php://output');
            },
            'vehicles-import-template.xlsx',
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
            ->setTitle('قالب استيراد السيارات')
            ->setSubject('Excel Import Template')
            ->setDescription('قالب معتمد لاستيراد بيانات أسطول السيارات إلى النظام.');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('السيارات');
        $sheet->setRightToLeft(true);
        $sheet->fromArray(VehicleExcelImportService::HEADERS, null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:J1000');
        $sheet->getSheetView()->setZoomScale(90);

        $sheet->getStyle('A1:J1')->applyFromArray([
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
            'B' => 24,
            'C' => 34,
            'D' => 24,
            'E' => 18,
            'F' => 20,
            'G' => 24,
            'H' => 24,
            'I' => 18,
            'J' => 42,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle('A2:B1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('E2:E1000')->getNumberFormat()->setFormatCode('#,##0.000');
        $sheet->getStyle('F2:F1000')->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle('G2:H1000')->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        $capacityValidation = new DataValidation();
        $capacityValidation->setType(DataValidation::TYPE_DECIMAL);
        $capacityValidation->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL);
        $capacityValidation->setAllowBlank(true);
        $capacityValidation->setShowErrorMessage(true);
        $capacityValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $capacityValidation->setErrorTitle('سعة غير صالحة');
        $capacityValidation->setError('سعة التحميل يجب أن تكون رقمًا صفر أو أكبر.');
        $capacityValidation->setFormula1('0');
        $sheet->setDataValidation('E2:E1000', $capacityValidation);

        $odometerValidation = new DataValidation();
        $odometerValidation->setType(DataValidation::TYPE_WHOLE);
        $odometerValidation->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL);
        $odometerValidation->setAllowBlank(true);
        $odometerValidation->setShowErrorMessage(true);
        $odometerValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $odometerValidation->setErrorTitle('عداد غير صالح');
        $odometerValidation->setError('عداد الكيلومترات يجب أن يكون عددًا صحيحًا صفر أو أكبر.');
        $odometerValidation->setFormula1('0');
        $sheet->setDataValidation('F2:F1000', $odometerValidation);

        $statusValidation = new DataValidation();
        $statusValidation->setType(DataValidation::TYPE_LIST);
        $statusValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $statusValidation->setAllowBlank(true);
        $statusValidation->setShowInputMessage(true);
        $statusValidation->setShowErrorMessage(true);
        $statusValidation->setShowDropDown(true);
        $statusValidation->setErrorTitle('حالة غير صالحة');
        $statusValidation->setError('اختر active أو maintenance أو inactive فقط.');
        $statusValidation->setPromptTitle('الحالة التشغيلية');
        $statusValidation->setPrompt('active = فعالة، maintenance = صيانة، inactive = خارج الخدمة. تركها فارغة يعني active.');
        $statusValidation->setFormula1('"active,maintenance,inactive"');
        $sheet->setDataValidation('I2:I1000', $statusValidation);

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('تعليمات');
        $instructions->setRightToLeft(true);
        $instructions->fromArray([
            ['العمود', 'الوصف', 'إجباري؟', 'القيم / مثال'],
            ['code', 'رمز فريد للسيارة', 'نعم', 'VEH-001'],
            ['plate_number', 'رقم لوحة فريد؛ العمود مهيأ كنص للمحافظة على التنسيق والأصفار', 'نعم', '001234'],
            ['name', 'اسم أو وصف السيارة', 'لا', 'شاحنة توزيع 1'],
            ['vehicle_type', 'نوع السيارة كنص حر حسب دليل الشركة', 'لا', 'شاحنة مبردة'],
            ['capacity', 'سعة التحميل', 'لا', '0 أو أكبر - يدعم 3 منازل عشرية'],
            ['current_odometer', 'عداد الكيلومترات الحالي', 'لا', 'عدد صحيح 0 أو أكبر'],
            ['insurance_expiry_date', 'تاريخ انتهاء التأمين', 'لا', 'YYYY-MM-DD مثل 2027-12-31'],
            ['license_expiry_date', 'تاريخ انتهاء الترخيص', 'لا', 'YYYY-MM-DD مثل 2027-10-15'],
            ['status', 'الحالة التشغيلية', 'لا', 'active أو maintenance أو inactive - الافتراضي active'],
            ['notes', 'ملاحظات داخلية', 'لا', 'نص اختياري'],
            ['', '', '', ''],
            ['مهم', 'code و plate_number يجب أن يكونا فريدين داخل الملف وغير مستخدمين مسبقًا في النظام.', '', ''],
            ['مهم', 'vehicle_type حقل نصي حر لأن شاشة النظام الحالية لا تفرض قائمة أنواع ثابتة.', '', ''],
            ['مهم', 'يمكن إدخال التاريخ كقيمة تاريخ حقيقية في Excel أو كنص بالصيغة YYYY-MM-DD.', '', ''],
            ['مهم', 'لا تغيّر أسماء الأعمدة أو ترتيبها في ورقة السيارات.', '', ''],
            ['سياسة الاستيراد', 'All-or-Nothing: أي خطأ في أي صف يمنع استيراد الملف كاملًا.', '', ''],
        ], null, 'A1');
        $instructions->freezePane('A2');
        $instructions->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $instructions->getStyle('A13:D17')->getFont()->setBold(true);
        $instructions->getColumnDimension('A')->setWidth(28);
        $instructions->getColumnDimension('B')->setWidth(80);
        $instructions->getColumnDimension('C')->setWidth(16);
        $instructions->getColumnDimension('D')->setWidth(58);
        $instructions->getStyle('A1:D17')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
