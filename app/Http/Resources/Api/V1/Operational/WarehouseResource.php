<?php
namespace App\Http\Resources\Api\V1\Operational;
use Illuminate\Http\Request;
class WarehouseResource extends OperationalResource { public function toArray(Request $request): array { return ['id'=>(int)$this->id,'code'=>$this->code,'name'=>$this->name,'type'=>$this->type,'address'=>$this->address,'status'=>$this->status,'vehicle'=>$this->whenLoaded('vehicle',fn()=> $this->vehicle ? ['id'=>(int)$this->vehicle->id,'code'=>$this->vehicle->code,'plate_number'=>$this->vehicle->plate_number] : null)]; } }
