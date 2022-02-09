<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Models\Task;
use App\Models\User;
use App\Models\CompanyDetail;

class TasksController extends Controller
{
    public function lists(Request $request)
	{ 
		$patient_id = $request->patient_id ?? null;

        if (auth()->user() != null && isset(auth()->user()->company->company)) {
            auth()->user()->company = auth()->user()->company->company;
        }

        if (auth()->user()->role_id == 5) {
            $tasks = auth()->user()->tasks;
        } elseif (auth()->user()->role_id == 3) {
            $tasks = Task::where('doctor_id', auth()->id())->get();
        } elseif (auth()->user()->role_id == 4) {
            if (count(auth()->user()->companies)) {
                foreach (auth()->user()->companies as $company) {
                    $tasks = Task::where('doctor_id', $company->users->pluck('id'))->get();
                }
            } else {
                $tasks = collect();
            }
        } elseif (auth()->user()->role_id == 6) {
            foreach (auth()->user()->companies as $company) {
                $tasks = Task::where('doctor_id', $company->users->pluck('id'))->get();
            }
        } else {
            $tasks = User::with('tasks')->get()->map->tasks->collapse();
        }
		
		foreach($tasks as $key => $task){
			$tasks[$key]['patientassigned'] = User::find($task->user_id)->firstname.' '.User::find($task->user_id)->lastname;
			$tasks[$key]['staffassigned'] 	= User::find($task->doctor_id)->firstname;
		}
		
		return response()->json(['success' => $tasks], 200);
	}
	
	public function getTasksActionData(Request $request)
	{
		$user = auth()->user();
		
        if ($user != null && isset($user->company->company)) {
            $user->company = $user->company->company;
        }
        $company_ids = $user->companies()->pluck('id')->toArray();
        $user_ids = DB::table('company_detail_user')
            ->whereIn('company_detail_id', $company_ids)
            ->pluck('user_id')
            ->toArray();

        if ($user->role_id == 3) {
            $users = User::query()->select('id', DB::raw("concat(firstname, ' ', lastname) AS text"))
                ->whereIn('id', $user_ids)
                ->whereNotIn('role_id', [1, 2, 5, 6])
                ->where('availability', 1)
                ->get();

            $patients = User::query()->select('id', DB::raw("concat(firstname, ' ', lastname) AS text"))
                ->whereIn('id', $user_ids)
                ->where('role_id', 5)
                ->where('availability', 1)
                ->get();

        } elseif ($user->role_id == 4) {
            $users = User::query()->select('id', DB::raw("concat(firstname, ' ', lastname) AS text"))
                ->whereIn('id', $user_ids)
                ->whereNotIn('role_id', [1, 2, 5, 6])
                ->where('availability', 1)
                ->get();

            $patients = User::query()->select('id', DB::raw("concat(firstname, ' ', lastname) AS text"))
                ->whereIn('id', $user_ids)
                ->where('role_id', 5)
                ->where('availability', 1)
                ->get();

        } elseif ($user->role_id == 6) {
            $users = User::query()->select('id', DB::raw("concat(firstname, ' ', lastname) AS text"))
                ->whereIn('id', $user_ids)
                ->whereNotIn('role_id', [1, 2, 5, 6])
                ->where('availability', 1)
                ->get();

            $patients = User::query()->select('id', DB::raw("concat(firstname, ' ', lastname) AS text"))
                ->whereIn('id', $user_ids)
                ->where('role_id', 5)
                ->where('availability', 1)
                ->get();

        } else {
            $users = User::query()->select('id', DB::raw("concat(firstname, ' ', lastname) AS text"))
                ->whereNotIn('role_id', [1, 2, 5, 6])
                ->where('availability', 1)
                ->get();

            $patients = User::query()->select('id', DB::raw("concat(firstname, ' ', lastname) AS text"))
                ->where('role_id', 5)
                ->where('availability', 1)
                ->get();
        }
		
		return response()->json(['users' => $users, 'patients' => $patients], 200);
	}
	
	public function action(Request $request)
	{ 
		if($request->isMethod('post')){
			DB::beginTransaction();
			
			$requestData 				= $request->all();
			if(isset($requestData['deadline'])) 	$requestData['deadline'] 	= date('Y-m-d', strtotime($requestData['deadline']));
			
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
				$requestData['status'] 		= 'open';
				$requestData['creator_id'] 	= auth()->user()->id;
				$result 					= Task::create($requestData);
				$insertid                   = $result->id;
			}else{
				$result 					= Task::find($requestData['actionid'])->update($requestData);
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
		
		$data = 	Task::with('user.doctor.companies')->where('id', $requestData['id'])->first();
		$data['patientdetail'] 		= User::find($data->user_id);
		$data['staffassigned'] 		= User::find($data->doctor_id)->firstname;
		$data['company'] 			= $this->activeCompany();
		return response()->json(['success' => $data], 200);
	}
	
	public function delete(Request $request)
	{ 
		$requestData = $request->all();
		Task::where('id', $requestData['id'])->delete();
		return response()->json(['success' => []], 200);
	}
	
	public function activeCompany()
    {
        $companies = CompanyDetail::all();

        foreach ($companies as $company) {
            if ($company->status == 1) {
                return $company->where('status', 1)->first();
            }
        }
    }
	
}
