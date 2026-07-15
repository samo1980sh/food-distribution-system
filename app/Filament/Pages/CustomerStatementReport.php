<?php

namespace App\Filament\Pages;

use App\Enums\PermissionName;

use App\Models\Customer;
use App\Services\Reports\CustomerStatementService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

class CustomerStatementReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'كشف حساب العميل';

    protected static ?string $title = 'كشف حساب العميل';

    protected static ?string $slug = 'customer-statement';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.customer-statement-report';

    public ?array $data = [];

    public ?array $customer = null;

    public array $transactions = [];

    public array $totals = [];

    public bool $generated = false;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'التقارير';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can(PermissionName::REPORT_CUSTOMER_STATEMENT->value) === true;
    }

    public function getHeading(): string|Htmlable
    {
        return 'كشف حساب العميل';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'عرض حركة فواتير البيع والتحصيلات والمرتجعات والرصيد المتحرك خلال فترة محددة.';
    }

    public function mount(): void
    {
        $this->form->fill([
            'customer_id' => null,
            'from' => now()->startOfMonth()->toDateString(),
            'until' => now()->toDateString(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('طباعة كشف الحساب')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(
                    fn (): string => route(
                        'reports.customer-statement.print',
                        [
                            'customer_id' => $this->data['customer_id'],
                            'from' => $this->data['from'],
                            'until' => $this->data['until'],
                        ],
                    ),
                    shouldOpenInNewTab: true,
                )
                ->visible(
                    fn (): bool => $this->generated
                        && filled($this->data['customer_id'] ?? null)
                        && filled($this->data['from'] ?? null)
                        && filled($this->data['until'] ?? null)
                ),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Select::make('customer_id')
                        ->label('العميل')
                        ->options(
                            fn (): array => Customer::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),

                    DatePicker::make('from')
                        ->label('من تاريخ')
                        ->required()
                        ->native(false)
                        ->displayFormat('Y-m-d'),

                    DatePicker::make('until')
                        ->label('إلى تاريخ')
                        ->required()
                        ->native(false)
                        ->displayFormat('Y-m-d'),
                ])
                    ->columns(3)
                    ->livewireSubmitHandler('generate')
                    ->footer([
                        Actions::make([
                            Action::make('generate')
                                ->label('عرض كشف الحساب')
                                ->icon('heroicon-o-magnifying-glass')
                                ->color('primary')
                                ->submit('generate'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function generate(): void
    {
        $state = $this->form->getState();

        $from = Carbon::parse((string) $state['from'])
            ->toDateString();

        $until = Carbon::parse((string) $state['until'])
            ->toDateString();

        if ($until < $from) {
            Notification::make()
                ->title('الفترة غير صحيحة')
                ->body('يجب أن يكون تاريخ النهاية مساويًا لتاريخ البداية أو بعده.')
                ->danger()
                ->send();

            return;
        }

        $statement = app(CustomerStatementService::class)
            ->generate(
                customerId: (int) $state['customer_id'],
                from: $from,
                until: $until,
            );

        $this->customer = $statement['customer'];
        $this->transactions = $statement['transactions'];
        $this->totals = $statement['totals'];
        $this->generated = true;
    }
}