<?php
namespace App\Http\Resources\Api\V1\Operational;
use Illuminate\Http\Request;
class AreaResource extends OperationalResource { public function toArray(Request $request): array { return ['id'=>(int)$this->id,'code'=>$this->code,'name'=>$this->name_ar,'city'=>$this->city,'status'=>$this->status]; } }
