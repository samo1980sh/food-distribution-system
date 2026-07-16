<?php
namespace App\Http\Resources\Api\V1\Operational;
use Illuminate\Http\Request;
class VehicleResource extends OperationalResource { public function toArray(Request $request): array { return ['id'=>(int)$this->id,'code'=>$this->code,'plate_number'=>$this->plate_number,'name'=>$this->name,'vehicle_type'=>$this->vehicle_type,'capacity'=>$this->capacity===null?null:$this->decimal($this->capacity,3),'status'=>$this->status,'current_odometer'=>$this->current_odometer===null?null:(int)$this->current_odometer,'insurance_expiry_date'=>$this->date($this->insurance_expiry_date),'license_expiry_date'=>$this->date($this->license_expiry_date),'warehouse'=>$this->whenLoaded('warehouse',fn()=> $this->warehouse ? WarehouseResource::make($this->warehouse)->resolve($request) : null)]; } }
