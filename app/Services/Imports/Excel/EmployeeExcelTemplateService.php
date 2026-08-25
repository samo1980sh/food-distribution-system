<?php

namespace App\Services\Imports\Excel;

use App\Models\Employee;
use App\Models\User;
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

class EmployeeExcelTemplateService
{
    public function download(): StreamedResponse
    {
        return response()->streamDownload(
            function (): void {
                $writer = new Xlsx($this->makeSpreadsheet());
                $writer->save('php://output');
            },
            'employees-import-template.xlsx',
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
            ->setTitle('قالب استيراد الموظفين')
            ->setSubject('Excel Import Template')
            ->setDescription('قالب معتمد لاستيراد الموظفين وربط حسابات المستخدمين المتاحة بالأدوار المطابقة.');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('الموظفون');
        $sheet->setRightToLeft(true);
        $sheet->fromArray(EmployeeExcelImportService::HEADERS, null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:H1000');
        $sheet->getSheetView()->setZoomScale(90);

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
            'A' => 24,
            'B' => 34,
            'C' => 22,
            'D' => 28,
            'E' => 26,
            'F' => 38,
            'G' => 16,
            'H' => 46,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle('A2:A1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('C2:C1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('F2:F1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        $usedUserIds = Employee::query()
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $users = User::query()
            ->when($usedUserIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $usedUserIds))
            ->whereHas('roles', fn ($query) => $query->whereIn('name', EmployeeExcelImportService::TYPES))
            ->with('roles:id,name')
            ->orderBy('email')
            ->get(['id', 'name', 'email']);

        $references = $spreadsheet->createSheet();
        $references->setTitle('القوائم المرجعية');
        $references->setRightToLeft(true);
        $references->fromArray([
            ['user_email', 'اسم المستخدم', 'الأدوار المؤهلة'],
        ], null, 'A1');
        $references->freezePane('A2');
        $references->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $references->getColumnDimension('A')->setWidth(40);
        $references->getColumnDimension('B')->setWidth(34);
        $references->getColumnDimension('C')->setWidth(52);
        $references->getStyle('A:A')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        foreach ($users as $index => $user) {
            $row = $index + 2;
            $eligibleRoles = $user->getRoleNames()
                ->filter(fn (string $role): bool => in_array($role, EmployeeExcelImportService::TYPES, true))
                ->values()
                ->implode(', ');

            $references->setCellValueExplicit('A'.$row, (string) $user->email, DataType::TYPE_STRING);
            $references->setCellValue('B'.$row, $user->name);
            $references->setCellValue('C'.$row, $eligibleRoles);
        }

        $userLastRow = max(2, $users->count() + 1);
        if ($users->isEmpty()) {
            $references->setCellValueExplicit('A2', '', DataType::TYPE_STRING);
        }

        $spreadsheet->addNamedRange(new NamedRange(
            'AVAILABLE_EMPLOYEE_USER_EMAILS',
            $references,
            '=$A$2:$A$'.$userLastRow,
        ));

        $typeValidation = new DataValidation;
        $typeValidation->setType(DataValidation::TYPE_LIST);
        $typeValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $typeValidation->setAllowBlank(true);
        $typeValidation->setShowInputMessage(true);
        $typeValidation->setShowErrorMessage(true);
        $typeValidation->setShowDropDown(true);
        $typeValidation->setErrorTitle('نوع غير صالح');
        $typeValidation->setError('اختر نوع الموظف من القائمة فقط.');
        $typeValidation->setPromptTitle('نوع الموظف');
        $typeValidation->setPrompt('ترك الخلية فارغة يعني sales_representative.');
        $typeValidation->setFormula1('"sales_representative,warehouse_keeper,accountant,supervisor"');
        $sheet->setDataValidation('E2:E1000', $typeValidation);

        $userValidation = new DataValidation;
        $userValidation->setType(DataValidation::TYPE_LIST);
        $userValidation->setErrorStyle(DataValidation::STYLE_STOP);
        $userValidation->setAllowBlank(true);
        $userValidation->setShowInputMessage(true);
        $userValidation->setShowErrorMessage(true);
        $userValidation->setShowDropDown(true);
        $userValidation->setErrorTitle('حساب غير صالح');
        $userValidation->setError('اختر user_email من قائمة الحسابات المتاحة أو اتركه فارغًا.');
        $userValidation->setPromptTitle('حساب المستخدم');
        $userValidation->setPrompt('اختياري. يجب أن يحمل الحساب الدور المطابق لقيمة type في نفس الصف.');
        $userValidation->setFormula1('=AVAILABLE_EMPLOYEE_USER_EMAILS');
        $sheet->setDataValidation('F2:F1000', $userValidation);

        $statusValidation = new DataValidation;
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
        $sheet->setDataValidation('G2:G1000', $statusValidation);

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('تعليمات');
        $instructions->setRightToLeft(true);
        $instructions->fromArray([
            ['العمود', 'الوصف', 'إجباري؟', 'القيم / مثال'],
            ['employee_code', 'رمز فريد للموظف', 'نعم', 'EMP-001'],
            ['name', 'اسم الموظف', 'نعم', 'أحمد محمد'],
            ['phone', 'رقم الهاتف', 'لا', 'يُحفظ كنص للمحافظة على الأصفار في البداية'],
            ['job_title', 'المسمى الوظيفي', 'لا', 'مندوب مبيعات'],
            ['type', 'النوع التشغيلي للموظف', 'لا', 'sales_representative / warehouse_keeper / accountant / supervisor - الافتراضي sales_representative'],
            ['user_email', 'البريد الإلكتروني لحساب المستخدم المرتبط', 'لا', 'اختره من القائمة المرجعية أو اتركه فارغًا'],
            ['status', 'حالة الموظف', 'لا', 'active أو inactive - الافتراضي active'],
            ['notes', 'ملاحظات داخلية', 'لا', 'نص اختياري'],
            ['', '', '', ''],
            ['مهم', 'كل حساب مستخدم يمكن ربطه بموظف واحد فقط لأن user_id فريد في employees.', '', ''],
            ['مهم', 'الحساب المرتبط يجب أن يحمل الدور المطابق لنوع الموظف في نفس الصف. الحساب ثنائي الدور يمكن استخدامه مع النوع المطابق.', '', ''],
            ['مهم', 'ورقة القوائم المرجعية تعرض الحسابات غير المرتبطة حاليًا والتي تحمل واحدًا على الأقل من أدوار الموظفين المدعومة.', '', ''],
            ['مهم', 'ربط الحساب اختياري، ولا يضيف الاستيراد أي دور جديد إلى الحساب ولا يغير حالة المستخدم.', '', ''],
            ['مهم', 'لا تغيّر أسماء الأعمدة أو ترتيبها في ورقة الموظفين.', '', ''],
            ['سياسة الاستيراد', 'All-or-Nothing: أي خطأ في أي صف يمنع استيراد الملف كاملًا.', '', ''],
        ], null, 'A1');
        $instructions->freezePane('A2');
        $instructions->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $instructions->getStyle('A11:D16')->getFont()->setBold(true);
        $instructions->getColumnDimension('A')->setWidth(24);
        $instructions->getColumnDimension('B')->setWidth(86);
        $instructions->getColumnDimension('C')->setWidth(18);
        $instructions->getColumnDimension('D')->setWidth(64);
        $instructions->getStyle('A1:D16')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
