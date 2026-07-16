<?php
namespace App\Http\Resources\Api\V1\Operational;
use App\Enums\PermissionName;
use Illuminate\Http\Request;
class ProductResource extends OperationalResource { public function toArray(Request $request): array { $canSeeCost=$request->user()?->can(PermissionName::REPORT_PROFIT->value)===true; return ['id'=>(int)$this->id,'sku'=>$this->sku,'barcode'=>$this->barcode,'name'=>$this->name_ar,'category'=>$this->whenLoaded('category',fn()=> $this->category ? ['id'=>(int)$this->category->id,'name'=>$this->category->name_ar] : null),'unit'=>$this->whenLoaded('unit',fn()=> $this->unit ? ['id'=>(int)$this->unit->id,'name'=>$this->unit->name_ar,'symbol'=>$this->unit->symbol] : null),'sale_price'=>$this->decimal($this->sale_price),'wholesale_price'=>$this->decimal($this->wholesale_price),'purchase_price'=>$canSeeCost ? $this->decimal($this->purchase_price) : null,'min_stock'=>$this->decimal($this->min_stock,3),'has_expiry'=>(bool)$this->has_expiry,'status'=>$this->status]; } }
