<?php

namespace App\Enums;

enum PermissionName: string
{
    case ADMIN_ACCESS = 'admin.access';
    case API_ACCESS = 'api.access';
    case DASHBOARD_VIEW = 'dashboard.view';
    case DASHBOARD_FINANCIAL = 'dashboard.financial';
    case DASHBOARD_OPERATIONAL = 'dashboard.operational';

    case USERS_VIEW = 'users.view';
    case USERS_CREATE = 'users.create';
    case USERS_UPDATE = 'users.update';
    case ROLES_ASSIGN = 'roles.assign';

    case AREAS_VIEW = 'areas.view';
    case AREAS_CREATE = 'areas.create';
    case AREAS_UPDATE = 'areas.update';
    case AREAS_DELETE = 'areas.delete';

    case EMPLOYEES_VIEW = 'employees.view';
    case EMPLOYEES_CREATE = 'employees.create';
    case EMPLOYEES_UPDATE = 'employees.update';
    case EMPLOYEES_DELETE = 'employees.delete';

    case CUSTOMERS_VIEW = 'customers.view';
    case CUSTOMERS_CREATE = 'customers.create';
    case CUSTOMERS_UPDATE = 'customers.update';
    case CUSTOMERS_DELETE = 'customers.delete';

    case UNITS_VIEW = 'units.view';
    case UNITS_CREATE = 'units.create';
    case UNITS_UPDATE = 'units.update';
    case UNITS_DELETE = 'units.delete';

    case PRODUCT_CATEGORIES_VIEW = 'product_categories.view';
    case PRODUCT_CATEGORIES_CREATE = 'product_categories.create';
    case PRODUCT_CATEGORIES_UPDATE = 'product_categories.update';
    case PRODUCT_CATEGORIES_DELETE = 'product_categories.delete';

    case PRODUCTS_VIEW = 'products.view';
    case PRODUCTS_CREATE = 'products.create';
    case PRODUCTS_UPDATE = 'products.update';
    case PRODUCTS_DELETE = 'products.delete';

    case DISTRIBUTION_ROUTES_VIEW = 'distribution_routes.view';
    case DISTRIBUTION_ROUTES_CREATE = 'distribution_routes.create';
    case DISTRIBUTION_ROUTES_UPDATE = 'distribution_routes.update';
    case DISTRIBUTION_ROUTES_DELETE = 'distribution_routes.delete';

    case VEHICLES_VIEW = 'vehicles.view';
    case VEHICLES_CREATE = 'vehicles.create';
    case VEHICLES_UPDATE = 'vehicles.update';
    case VEHICLES_DELETE = 'vehicles.delete';

    case WAREHOUSES_VIEW = 'warehouses.view';
    case WAREHOUSES_CREATE = 'warehouses.create';
    case WAREHOUSES_UPDATE = 'warehouses.update';
    case WAREHOUSES_DELETE = 'warehouses.delete';

    case STOCK_BALANCES_VIEW = 'stock_balances.view';

    case STOCK_MOVEMENTS_VIEW = 'stock_movements.view';
    case STOCK_MOVEMENTS_CREATE = 'stock_movements.create';
    case STOCK_MOVEMENTS_UPDATE = 'stock_movements.update';
    case STOCK_MOVEMENTS_DELETE = 'stock_movements.delete';

    case VEHICLE_LOADS_VIEW = 'vehicle_loads.view';
    case VEHICLE_LOADS_CREATE = 'vehicle_loads.create';
    case VEHICLE_LOADS_UPDATE = 'vehicle_loads.update';
    case VEHICLE_LOADS_DELETE = 'vehicle_loads.delete';
    case VEHICLE_LOADS_APPROVE = 'vehicle_loads.approve';
    case VEHICLE_LOADS_CANCEL = 'vehicle_loads.cancel';
    case VEHICLE_LOADS_PRINT = 'vehicle_loads.print';

    case SALES_INVOICES_VIEW = 'sales_invoices.view';
    case SALES_INVOICES_CREATE = 'sales_invoices.create';
    case SALES_INVOICES_UPDATE = 'sales_invoices.update';
    case SALES_INVOICES_DELETE = 'sales_invoices.delete';
    case SALES_INVOICES_CONFIRM = 'sales_invoices.confirm';
    case SALES_INVOICES_CANCEL = 'sales_invoices.cancel';
    case SALES_INVOICES_PRINT = 'sales_invoices.print';

    case SALES_RETURNS_VIEW = 'sales_returns.view';
    case SALES_RETURNS_CREATE = 'sales_returns.create';
    case SALES_RETURNS_UPDATE = 'sales_returns.update';
    case SALES_RETURNS_DELETE = 'sales_returns.delete';
    case SALES_RETURNS_CONFIRM = 'sales_returns.confirm';
    case SALES_RETURNS_CANCEL = 'sales_returns.cancel';
    case SALES_RETURNS_PRINT = 'sales_returns.print';

    case CUSTOMER_PAYMENTS_VIEW = 'customer_payments.view';
    case CUSTOMER_PAYMENTS_CREATE = 'customer_payments.create';
    case CUSTOMER_PAYMENTS_UPDATE = 'customer_payments.update';
    case CUSTOMER_PAYMENTS_DELETE = 'customer_payments.delete';
    case CUSTOMER_PAYMENTS_CONFIRM = 'customer_payments.confirm';
    case CUSTOMER_PAYMENTS_CANCEL = 'customer_payments.cancel';
    case CUSTOMER_PAYMENTS_PRINT = 'customer_payments.print';

    case VEHICLE_EXPENSES_VIEW = 'vehicle_expenses.view';
    case VEHICLE_EXPENSES_CREATE = 'vehicle_expenses.create';
    case VEHICLE_EXPENSES_UPDATE = 'vehicle_expenses.update';
    case VEHICLE_EXPENSES_DELETE = 'vehicle_expenses.delete';
    case VEHICLE_EXPENSES_APPROVE = 'vehicle_expenses.approve';
    case VEHICLE_EXPENSES_REJECT = 'vehicle_expenses.reject';
    case VEHICLE_EXPENSES_PRINT = 'vehicle_expenses.print';

    case DAILY_CLOSINGS_VIEW = 'daily_closings.view';
    case DAILY_CLOSINGS_CREATE = 'daily_closings.create';
    case DAILY_CLOSINGS_UPDATE = 'daily_closings.update';
    case DAILY_CLOSINGS_DELETE = 'daily_closings.delete';
    case DAILY_CLOSINGS_REFRESH_TOTALS = 'daily_closings.refresh_totals';
    case DAILY_CLOSINGS_CONFIRM = 'daily_closings.confirm';
    case DAILY_CLOSINGS_CANCEL = 'daily_closings.cancel';
    case DAILY_CLOSINGS_PRINT = 'daily_closings.print';

    case REPORT_SALES = 'reports.sales';
    case REPORT_CUSTOMER_PAYMENTS = 'reports.customer_payments';
    case REPORT_CUSTOMER_STATEMENT = 'reports.customer_statement';
    case REPORT_DAILY_CLOSINGS = 'reports.daily_closings';
    case REPORT_VEHICLE_LOADS = 'reports.vehicle_loads';
    case REPORT_VEHICLE_STOCK = 'reports.vehicle_stock';
    case REPORT_SALES_RETURNS = 'reports.sales_returns';
    case REPORT_PROFIT = 'reports.profit';
    case REPORT_VEHICLE_EXPENSES = 'reports.vehicle_expenses';
    case REPORT_EXPIRY_RISK = 'reports.expiry_risk';
    case REPORT_OVERDUE_CUSTOMERS = 'reports.overdue_customers';
    case REPORT_TOP_CUSTOMERS = 'reports.top_customers';
    case REPORT_ROUTE_PERFORMANCE = 'reports.route_performance';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(
            fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }
}
