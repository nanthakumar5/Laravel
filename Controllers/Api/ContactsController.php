<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Hash;

use App\Models\User;
use App\Models\Contact;

class ContactsController extends Controller
{
	public function lists(Request $request)
	{ 
		$requestData = $request->all();
		
		$data 		= 	$this->contacts('all', $requestData);
		$datacount	= 	$this->contacts('count', $requestData);
		return response()->json(["draw" => $requestData['draw'],"recordsTotal" => $datacount,"recordsFiltered" => $datacount,"data" => $data], 200);
	}
	
	public function contacts($type, $input)
	{ 
		$query 			= Contact::where('user_id', $input['id']);

		if(isset($input['search']['value']) && $input['search']['value']!=''){
			$searchvalue = $input['search']['value'];
			
			$query = $query->where(function($q) use($searchvalue){
				$q->where('relative_name', 'like', '%'.$searchvalue.'%');
				$q->orWhere('relationship_type', 'like', '%'.$searchvalue.'%');
				$q->orWhere('created_at', 'like', '%'.$searchvalue.'%');
			});			
		}
		if($type!=='count' && isset($input['start']) && isset($input['length'])){
			$query = $query->offset($input['start'])->limit($input['length']);
		}
		if(isset($input['order']['0']['column']) && isset($input['order']['0']['dir'])){
			$column = ['relative_name', 'relationship_type', 'created_at'];
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
	
	public function action(Request $request)
	{ 
		if($request->isMethod('post')){
			DB::beginTransaction();
			
			
			$requestData = $request->all();
			if(isset($requestData['phone'])) 			$requestData['phone'] 			= "+44" . str_replace("+44", "", $requestData['phone']);
			if(isset($requestData['date_of_birth'])) 	$requestData['date_of_birth'] 	= date('Y-m-d', strtotime($requestData['date_of_birth']));
			$requestData['creator_id'] 		= auth()->user() ? auth()->user()->id : 2;
			
			if($requestData['actionid']==''){
				$result 					= Contact::create($requestData);
				$insertid                   = $result->id;
			}else{
				$result 					= Contact::find($requestData['actionid'])->update($requestData);
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
	
	public function getData(Request $request)
	{
		$requestData 				= $request->all();
		
		$users = 	Contact::where('id', $requestData['id'])->first();
		return response()->json(['success' => $users], 200);
	}
	
	public function fileupload(Request $request)
	{ 
		$file = $this->filesupload($request, 'profile_photo', 'assets/patient_profile_photos/');
		return response()->json(['success' => $file], 200);
	}
}
