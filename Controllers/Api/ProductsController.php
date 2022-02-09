<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Models\Product;

class ProductsController extends Controller
{
    public function lists(Request $request)
	{ 
		$requestData = $request->all();
		
		$data 		= 	$this->products('all', $requestData);
		$datacount 	= 	$this->products('count', $requestData);
		return response()->json(["draw" => $requestData['draw'],"recordsTotal" => $datacount,"recordsFiltered" => $datacount,"data" => $data], 200);
	}
	
	public function products($type, $input)
	{ 
		if(auth()->user() != null && isset(auth()->user()->company->company)){
			auth()->user()->company = auth()->user()->company->company;
		}

		if (auth()->user()->role_id == 6) {
			$input['doctors'] = auth()->user()->company->users->pluck('id');
		} elseif (auth()->user()->role_id == 4) {
			if (count(auth()->user()->companies)) {
				foreach (auth()->user()->companies as $company) {
					$input['doctors'] = $company->users->pluck('id');
				}
			} else {
				$input['doctors'] = [];
			}
		}

		$sql = Product::select('products.*',
			\DB::raw("CONCAT(B.firstname, ' ', B.lastname) as doctor_name"))
			->leftjoin('users AS B','B.id','=','products.user_id')
			->where(function ($query) use ($input) {
				if (auth()->user()->role_id == 3 || auth()->user()->role_id == 6 || auth()->user()->role_id == 4) {
					if (isset($input['doctors'])) {
						$query->where(function ($q) use ($input) {
							$q->whereIn('products.user_id', $input['doctors']);
						});
					} else {
						$query->where(function ($q) use ($input) {
							$q->where('products.user_id', auth()->user()->id);
						});
					}
				}
				if (auth()->user()->role_id == 5) {
					$query->where(function ($q) use ($input) {
						$q->where('products.user_id', auth()->user()->id);
					});
				}
			});
		
		if(isset($input['search']['value']) && $input['search']['value']!=''){
			$searchvalue = $input['search']['value'];
			
			$sql = $sql->where(function($q) use($searchvalue){
				$q->where('products.name', 'like', '%'.$searchvalue.'%');
				$q->orWhere('products.created_at', 'like', '%'.$searchvalue.'%');
				$q->orWhere('products.code_serial_number', 'like', '%'.$searchvalue.'%');
				$q->orWhere('products.stock', 'like', '%'.$searchvalue.'%');
				$q->orWhere('products.buying_price', 'like', '%'.$searchvalue.'%');
				$q->orWhere('products.selling_price', 'like', '%'.$searchvalue.'%');
				$q->orWhere(\DB::raw("CONCAT(B.firstname, ' ', B.lastname)"), 'like', '%'.$searchvalue.'%');
			});			
		}
		if($type!=='count' && isset($input['start']) && isset($input['length'])){
			$sql = $sql->offset($input['start'])->limit($input['length']);
		}
		if(isset($input['order']['0']['column']) && isset($input['order']['0']['dir'])){
			$column = ['products.code_serial_number', 'products.name', 'products.stock', 'products.buying_price', 'products.selling_price', 'products.created_at', 'B.firstname'];
			$sql = $sql->orderBy($column[$input['order']['0']['column']], $input['order']['0']['dir']);
		}
		
		if($type=='count'){
			$result = $sql->count();
		}else{
			if($type=='all') 		$result = $sql->get();
			elseif($type=='row') 	$result = $sql->first();
		}
		
		return $result;
	}
	
    public function getData(Request $request)
	{ 
		$requestData = $request->all();
		
		$product = 	Product::select('products.*', \DB::raw("CONCAT(B.firstname, ' ', B.lastname) as doctor_name"))
					->leftjoin('users AS B','B.id','=','products.user_id')
					->where('products.id', $requestData['id'])
					->first();
		
		if($product){
			return response()->json(['success' => $product], 200);
		}else{
			return response()->json(['error' => []], 200);
		}
	}
	
    public function action(Request $request)
	{ 
		if($request->isMethod('post')){
			DB::beginTransaction();
			
			$requestData 				= $request->all();
			$requestData['user_id'] 	= auth()->user()->id;
			
			/*
			$validator = Validator::make($request->all(), [
                'name' => 'required',
				'code_serial_number' => 'required',
				'stock' => 'required',
				'buying_price' => 'required',
				'selling_price' => 'required',
            ]);
			
			if($validator->fails()){
                return response()->json(['error' => [$validator->errors()->toJson()]], 500);
            }
			*/
			
			if($requestData['actionid']==''){
				$result 					= Product::create($requestData);
				$insertid                   = $result->id;
			}else{
				$result 					= Product::find($requestData['actionid'])->update($requestData);
				$insertid                   = $requestData['actionid'];
			}
			
			if($result){
				DB::commit();
				return response()->json(['success' => ['id' => $insertid]], 200);
			}else{
				DB::rollBack();
				return response()->json(['error' => []], 500);
			}
		}
	}
	
	public function delete(Request $request)
	{ 
		$requestData = $request->all();
		Product::where('id', $requestData['id'])->delete();
		return response()->json(['success' => []], 200);
	}
	
	public function fileupload(Request $request)
	{ 
		$file = $this->filesupload($request, 'product_image', 'assets/product_image/');
		return response()->json(['success' => $file], 200);
	}
}
