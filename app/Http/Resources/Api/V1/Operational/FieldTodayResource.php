<?php

namespace App\Http\Resources\Api\V1\Operational;

use App\Models\DailyClosing;
use App\Models\DistributionRoute;
use App\Models\User;
use Illuminate\Http\Request;

class FieldTodayResource extends OperationalResource
{
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'date' => $data['date'],
            'server_time' => $data['server_time'],
            'timezone' => $data['timezone'],
            'available_roles' => $data['available_roles'],
            'contexts' => [
                'sales_representative' => $this->context(
                    $data['contexts']['sales_representative'] ?? null,
                    $request,
                ),
            ],
        ];
    }

    /** @param array<string, mixed>|null $context */
    private function context(?array $context, Request $request): ?array
    {
        if ($context === null) {
            return null;
        }

        $candidateRoutes = RouteResource::collection($context['candidates'])->resolve($request);
        $route = $context['route'] instanceof DistributionRoute
            ? RouteResource::make($context['route'])->resolve($request)
            : null;

        return [
            'role' => $context['role'],
            'status' => $context['status'],
            'schedule_status' => $context['schedule_status'],
            'scheduled_today' => $context['scheduled_today'],
            'operational_today' => $context['operational_today'],
            'exceptional_operation' => $context['exceptional_operation'],
            'available_routes_count' => $context['available_routes_count'],
            'scheduled_routes_count' => $context['scheduled_routes_count'],
            'candidate_routes' => array_map(
                fn (array $candidate): array => $this->routeWithAssignmentRoles($candidate),
                $candidateRoutes,
            ),
            'readiness' => $context['readiness'],
            'route' => $route !== null
                ? $this->routeWithAssignmentRoles($route)
                : null,
            'vehicle' => $context['vehicle'] !== null
                ? VehicleResource::make($context['vehicle'])->resolve($request)
                : null,
            'warehouse' => $context['warehouse'] !== null
                ? WarehouseResource::make($context['warehouse'])->resolve($request)
                : null,
            'summary' => $context['summary'],
            'daily_closing' => $this->dailyClosing($context['daily_closing']),
        ];
    }

    /** @param array<string, mixed> $route */
    private function routeWithAssignmentRoles(array $route): array
    {
        if (
            isset($route['sales_representative'])
            && is_array($route['sales_representative'])
        ) {
            $route['sales_representative']['assignment_role'] = User::ROLE_SALES_REPRESENTATIVE;
        }

        return $route;
    }

    /** @return array<string, mixed>|null */
    private function dailyClosing(?DailyClosing $closing): ?array
    {
        if ($closing === null) {
            return null;
        }

        return [
            'id' => (int) $closing->getKey(),
            'closing_number' => $closing->closing_number,
            'closing_date' => $this->date($closing->closing_date),
            'status' => $closing->status,
            'workflow_status' => $closing->workflowStatus(),
            'requires_admin_review' => $closing->requiresAdministrativeReview(),
            'field_workflow' => (bool) $closing->field_workflow,
            'inventory_submitted' => $closing->inventorySubmitted(),
            'cash_submitted' => $closing->cashSubmitted(),
            'field_handover_complete' => $closing->fieldHandoverComplete(),
        ];
    }
}
