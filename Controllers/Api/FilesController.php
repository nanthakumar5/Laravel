<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Models\User;
use App\Models\File;

class FilesController extends Controller
{
    public function getPatients(Request $request)
	{ 
		if(auth()->user() != null && isset(auth()->user()->company->company)){
            auth()->user()->company = auth()->user()->company->company;
        }

        if(auth()->user()->role_id == 3) {
            $patients = User::select('id', DB::raw("concat('DOB : ', date_of_birth, ' - ', firstname, ' ', lastname) AS text"))->where(['role_id' => 5, 'user_id' => auth()->id()])->get();
        } elseif (auth()->user()->role_id == 4) {
            if (count(auth()->user()->companies)) {
                foreach (auth()->user()->companies as $company) {
                    $patients = User::select('id', DB::raw("concat('DOB : ', date_of_birth, ' - ', firstname, ' ', lastname) AS text"))->where(['role_id' => 5])->whereIn('user_id', $company->users->pluck('id'))->get();
                }
            } else {
                $patients = collect();
            }
        } elseif (auth()->user()->role_id == 6) {
            foreach (auth()->user()->companies as $company) {
                $patients = User::select('id', DB::raw("concat('DOB : ', date_of_birth, ' - ', firstname, ' ', lastname) AS text"))->where(['role_id' => 5])->whereIn('user_id', $company->users->pluck('id'))->get();
            }
        } else {
            $patients = User::select('id', DB::raw("concat('DOB : ', date_of_birth, ' - ', firstname, ' ', lastname) AS text"))->where('role_id', 5)->get();
        }
		
		return response()->json(['success' => $patients], 200);
	}
	
	public function lists(Request $request)
	{ 
		$requestData = $request->all();
		
		$data 		= 	$this->files('all', $requestData);
		$datacount 	= 	$this->files('count', $requestData);
		return response()->json(["draw" => $requestData['draw'],"recordsTotal" => $datacount,"recordsFiltered" => $datacount,"data" => $data], 200);
	}
	
	public function files($type, $input)
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

		$sql = File::select('files.*',
                \DB::raw("CONCAT(A.firstname, ' ', A.lastname) as patient_name"),
                \DB::raw("CONCAT(B.firstname, ' ', B.lastname) as doctor_name"))
                ->leftjoin('users AS A','A.id','=','files.user_id')
                ->leftjoin('users AS B','B.id','=','files.creator_id')
                ->where(function ($query) use ($input) {
                    if (auth()->user()->role_id == 3 || auth()->user()->role_id == 6 || auth()->user()->role_id == 4) {
                        if (isset($input['doctors'])) {
                            $query->where(function ($q) use ($input) {
                                $q->whereIn('files.creator_id', $input['doctors']);
                            });
                        } else {
                            $query->where(function ($q) use ($input) {
                                $q->where('files.creator_id', auth()->user()->id);
                            });
                        }
                    }
                    if (auth()->user()->role_id == 5) {
                        $query->where(function ($q) use ($input) {
                            $q->where('files.user_id', auth()->user()->id);
                        });
                    }
                });
		
		if(isset($input['search']['value']) && $input['search']['value']!=''){
			$searchvalue = $input['search']['value'];
			
			$sql = $sql->where(function($q) use($searchvalue){
				$q->where('files.name', 'like', '%'.$searchvalue.'%');
				$q->orWhere('files.created_at', 'like', '%'.$searchvalue.'%');
				$q->orWhere(\DB::raw("CONCAT(A.firstname, ' ', A.lastname)"), 'like', '%'.$searchvalue.'%');
				$q->orWhere(\DB::raw("CONCAT(B.firstname, ' ', B.lastname)"), 'like', '%'.$searchvalue.'%');
			});			
		}
		if($type!=='count' && isset($input['start']) && isset($input['length'])){
			$sql = $sql->offset($input['start'])->limit($input['length']);
		}
		if(isset($input['order']['0']['column']) && isset($input['order']['0']['dir'])){
			$column = ['files.name', 'A.firstname', 'products.created_at', 'B.firstname'];
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
		
		$product = 	File::select('files.*',
					\DB::raw("CONCAT(A.firstname, ' ', A.lastname) as patient_name"),
					\DB::raw("CONCAT(B.firstname, ' ', B.lastname) as doctor_name"))
					->leftjoin('users AS A','A.id','=','files.user_id')
					->leftjoin('users AS B','B.id','=','files.creator_id')
					->where('files.id', $requestData['id'])
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
			$requestData['creator_id'] 	= auth()->user()->id;
			
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
				$result 					= File::create($requestData);
				$insertid                   = $result->id;
			}else{
				$result 					= File::find($requestData['actionid'])->update($requestData);
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
		File::where('id', $requestData['id'])->delete();
		return response()->json(['success' => []], 200);
	}
	
	public function fileupload(Request $request)
	{ 
		$file = $this->filesupload($request, 'filename', 'assets/patient_files/');
		return response()->json(['success' => $file], 200);
	}
}
