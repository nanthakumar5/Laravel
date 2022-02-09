<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Hash;

use App\Models\User;
use App\Models\Contact;

class PatientsController extends Controller
{
    public function lists(Request $request)
	{ 
		$requestData = $request->all();
		
		$data 		= 	$this->patients('all', $requestData);
		$datacount 	= 	$this->patients('count', $requestData);
		return response()->json(["draw" => $requestData['draw'],"recordsTotal" => $datacount,"recordsFiltered" => $datacount,"data" => $data], 200);
	}
	
	public function patients($type, $input)
	{ 
		if (auth()->user()->role_id == 6) {
			$input['doctors'] = auth()->user()->company->users->pluck('id');
		} elseif ((auth()->user()->role_id == 3 && auth()->user()->role_type != 3) || auth()->user()->role_id == 4) {
			if (count(auth()->user()->companies)) {
				$input['doctors'] = auth()->user()->companies->first()->users->pluck('id');
			} else {
				$input['doctors'] = [];
			}
		}
		
		$query = 	User::with('creator')
					->where('role_id', 5)
					->where(function ($query) use ($input) {
						if (auth()->user()->role_id == 3 || auth()->user()->role_id == 6 || auth()->user()->role_id == 4) {
							if (isset($input['doctors'])) {
								$query->where(function ($q) use ($input) {
									$q->whereIn('user_id', $input['doctors']);
								});
							} else {
								$query->where(function ($q) use ($input) {
									$q->where('user_id', auth()->user()->id);
								});
							}
						}
						if (!empty($input['date_of_birth'])) {
							$query->where(function ($q) use ($input) {
								$q->where('date_of_birth', $input['date_of_birth']);
							});
						}
					});

		
		if(isset($input['search']['value']) && $input['search']['value']!=''){
			$searchvalue = $input['search']['value'];
			
			$query = $query->where(function($q) use($searchvalue){
				$q->where('nhs_number', 'like', '%'.$searchvalue.'%');
				$q->orWhere('firstname', 'like', '%'.$searchvalue.'%');
				$q->orWhere('gender', 'like', '%'.$searchvalue.'%');
				$q->orWhere('date_of_birth', 'like', '%'.$searchvalue.'%');
				$q->orWhere('created_at', 'like', '%'.$searchvalue.'%');
			});			
		}
		if($type!=='count' && isset($input['start']) && isset($input['length'])){
			$query = $query->offset($input['start'])->limit($input['length']);
		}
		if(isset($input['order']['0']['column']) && isset($input['order']['0']['dir'])){
			$column = ['nhs_number', 'firstname', 'gender', 'date_of_birth', 'created_at'];
			$query = $query->orderBy($column[$input['order']['0']['column']], $input['order']['0']['dir']);
		}
		
		if($type=='count'){
			$result = $query->count();
		}else{
			if($type=='all') 		$result = $query->get();
			elseif($type=='row') 	$result = $query->first();
		}
		
		return $result;
	}
	
    public function getData(Request $request)
	{ 
		$requestData 				= $request->all();
		
		$users = 	User::with('creator')->where('id', $requestData['id'])->first();
		return response()->json(['success' => $users], 200);
	}
	
    public function action(Request $request)
	{ 
		if($request->isMethod('post')){
			DB::beginTransaction();
			
			
			$requestData = $request->all();
			if(isset($requestData['username'])) 		$requestData['username'] 		= strtolower($requestData['firstname']);
			if(isset($requestData['date_of_birth'])) 	$requestData['date_of_birth'] 	= date('Y-m-d', strtotime($requestData['date_of_birth']));
			if(isset($requestData['phone'])) 			$requestData['phone'] 			= "+44" . str_replace("+44", "", $requestData['phone']);
			if(isset($requestData['password']) && $requestData['password']!='') $requestData['password'] 		= $requestData['password'] ? Hash::make($requestData['password']) : Hash::make($requestData['firstname']);
			if(isset($requestData['user_id'])) 			$requestData['user_id'] 		= $requestData['user_id'] ? $requestData['user_id'] : auth()->user()->id;
			$requestData['creator_id'] 		= auth()->user() ? auth()->user()->id : 2;
			
			if($requestData['actionid']==''){
				$requestData['password'] 	= $requestData['password'] ? Hash::make($requestData['password']) : Hash::make($requestData['firstname']);
				$result 					= User::create($requestData);
				$insertid                   = $result->id;
			}else{
				$result 					= User::find($requestData['actionid'])->update($requestData);
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
		User::where('id', $requestData['id'])->delete();
		return response()->json(['success' => []], 200);
	}
	
	public function fileupload(Request $request)
	{ 
		$file = $this->filesupload($request, 'profile_photo', 'assets/patient_profile_photos/');
		return response()->json(['success' => $file], 200);
	}
	
	public function getAvailableDoctorsByRole()
	{ 
		return $this->fetchAvailableDoctorsByRole();
	}
}
