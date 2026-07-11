<?php

namespace App\Services\Reports;

use App\Models\ProfitReportEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class ProfitReportQuery
{
    public function build(): Builder
    {
        $model = new ProfitReportEntry();
        $entries = $this->invoiceEntries()
            ->unionAll($this->returnEntries());

        return $model->newQuery()
            ->fromSub($entries, $model->getTable())
            ->select($model->getTable().'.*');
    }

    private function invoiceEntries(): QueryBuilder
    {
        $itemTotals = DB::table('sales_invoice_items')
            ->select('sales_invoice_id')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw(
                'SUM(CASE WHEN total_cost > 0 THEN total_cost ELSE quantity * unit_cost END) as total_cost'
            )
            ->groupBy('sales_invoice_id');

        return DB::table('sales_invoices')
            ->leftJoinSub(
                $itemTotals,
                'invoice_item_totals',
                'invoice_item_totals.sales_invoice_id',
                '=',
                'sales_invoices.id',
            )
            ->where('sales_invoices.status', 'confirmed')
            ->select([
                DB::raw('CAST(sales_invoices.id AS SIGNED) as id'),
                'sales_invoices.id as source_id',
                'sales_invoices.invoice_number as document_number',
                'sales_invoices.invoice_date as entry_date',
                'sales_invoices.customer_id',
                'sales_invoices.warehouse_id',
                'sales_invoices.vehicle_id',
                'sales_invoices.route_id',
                'sales_invoices.sales_representative_id',
            ])
            ->selectRaw("'invoice' as entry_type")
            ->selectRaw('COALESCE(invoice_item_totals.total_quantity, 0) as quantity')
            ->selectRaw('sales_invoices.total_amount as sales_amount')
            ->selectRaw('COALESCE(invoice_item_totals.total_cost, 0) as cost_amount')
            ->selectRaw(
                '(sales_invoices.total_amount - COALESCE(invoice_item_totals.total_cost, 0)) as profit_amount'
            )
            ->selectRaw(
                'CASE
                    WHEN ABS(sales_invoices.total_amount) < 0.0001 THEN 0
                    ELSE ((sales_invoices.total_amount - COALESCE(invoice_item_totals.total_cost, 0)) / sales_invoices.total_amount) * 100
                END as margin_percent'
            );
    }

    private function returnEntries(): QueryBuilder
    {
        $itemTotals = DB::table('sales_return_items')
            ->select('sales_return_id')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw(
                'SUM(CASE WHEN total_cost > 0 THEN total_cost ELSE quantity * unit_cost END) as total_cost'
            )
            ->groupBy('sales_return_id');

        return DB::table('sales_returns')
            ->leftJoinSub(
                $itemTotals,
                'return_item_totals',
                'return_item_totals.sales_return_id',
                '=',
                'sales_returns.id',
            )
            ->where('sales_returns.status', 'confirmed')
            ->select([
                DB::raw('-CAST(sales_returns.id AS SIGNED) as id'),
                'sales_returns.id as source_id',
                'sales_returns.return_number as document_number',
                'sales_returns.return_date as entry_date',
                'sales_returns.customer_id',
                'sales_returns.warehouse_id',
                'sales_returns.vehicle_id',
                'sales_returns.route_id',
                'sales_returns.sales_representative_id',
            ])
            ->selectRaw("'return' as entry_type")
            ->selectRaw('-COALESCE(return_item_totals.total_quantity, 0) as quantity')
            ->selectRaw('-sales_returns.total_amount as sales_amount')
            ->selectRaw('-COALESCE(return_item_totals.total_cost, 0) as cost_amount')
            ->selectRaw(
                '(COALESCE(return_item_totals.total_cost, 0) - sales_returns.total_amount) as profit_amount'
            )
            ->selectRaw(
                'CASE
                    WHEN ABS(sales_returns.total_amount) < 0.0001 THEN 0
                    ELSE ((sales_returns.total_amount - COALESCE(return_item_totals.total_cost, 0)) / sales_returns.total_amount) * 100
                END as margin_percent'
            );
    }
}
