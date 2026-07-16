<?php
namespace App\Http\Resources\Api\V1\Operational;
use Illuminate\Http\Request;
class SalesInvoiceItemResource extends OperationalResource { public function toArray(Request $request): array { return ['id'=>(int)$this->id,'product'=>$this->whenLoaded('product',fn()=> $this->product ? ProductResource::make($this->product)->resolve($request) : null),'batch_number'=>$this->batch_number,'expiry_date'=>$this->date($this->expiry_date),'quantity'=>$this->decimal($this->quantity,3),'unit_price'=>$this->decimal($this->unit_price),'discount_amount'=>$this->decimal($this->discount_amount),'line_total'=>$this->decimal($this->line_total)]; } }
