<?php
namespace App\Http\Resources\Api\V1\Operational;
use Illuminate\Http\Request;
class EmployeeSummaryResource extends OperationalResource { public function toArray(Request $request): array { return ['id'=>(int)$this->id,'employee_code'=>$this->employee_code,'name'=>$this->name,'phone'=>$this->phone,'job_title'=>$this->job_title,'type'=>$this->type,'status'=>$this->status]; } }
