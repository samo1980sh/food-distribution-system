<?php
namespace App\Http\Resources\Api\V1\Operational;
use App\Enums\PermissionName;
use Illuminate\Http\Request;
class StockBalanceResource extends OperationalResource { public function toArray(Request $request): array { $canSeeCost=$request->user()?->can(PermissionName::REPORT_PROFIT->value)===true; return ['id'=>(int)$this->id,'warehouse'=>$this->whenLoaded('warehouse',fn()=> $this->warehouse ? WarehouseResource::make($this->warehouse)->resolve($request) : null),'product'=>$this->whenLoaded('product',fn()=> $this->product ? ProductResource::make($this->product)->resolve($request) : null),'batch_number'=>$this->batch_number,'expiry_date'=>$this->date($this->expiry_date),'quantity'=>$this->decimal($this->quantity,3),'average_unit_cost'=>$canSeeCost ? $this->decimal($this->average_unit_cost,6) : null,'is_expired'=>$this->expiry_date?->isPast() ?? false,'expires_within_30_days'=>$this->expiry_date?->between(today(),today()->addDays(30)) ?? false]; } }
