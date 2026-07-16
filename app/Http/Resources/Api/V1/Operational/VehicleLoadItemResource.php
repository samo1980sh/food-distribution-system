<?php
namespace App\Http\Resources\Api\V1\Operational;
use App\Enums\PermissionName;
use Illuminate\Http\Request;
class VehicleLoadItemResource extends OperationalResource { public function toArray(Request $request): array { $canSeeCost=$request->user()?->can(PermissionName::REPORT_PROFIT->value)===true; return ['id'=>(int)$this->id,'product'=>$this->whenLoaded('product',fn()=> $this->product ? ProductResource::make($this->product)->resolve($request) : null),'batch_number'=>$this->batch_number,'expiry_date'=>$this->date($this->expiry_date),'quantity'=>$this->decimal($this->quantity,3),'unit_cost'=>$canSeeCost ? $this->decimal($this->unit_cost,6) : null,'total_cost'=>$canSeeCost ? $this->decimal($this->total_cost) : null]; } }
