<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

use App\Models\User;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
	
	public function filesupload($request, $name, $path)
	{
		if ($request->hasFile($name)){
			$image 		= $request->file($name);	
			$imagename 	= $image->getClientOriginalName();
			
			if($image->move($path, $imagename)){
				return $imagename;
			}else{
				return false;
			}			
		}else{
			return false;
		}
	}
	
	function fetchAvailableDoctorsByRole()
    {
        if (auth()->user() != null && isset(auth()->user()->company->company)) {
            auth()->user()->company = auth()->user()->company->company;
        }

        if (auth()->user()->role_id == 5) {
            if (count(auth()->user()->companies)) {
                $users = User::where(function ($q) {
                    if (auth()->user()->role_type != 3) {
                        $q->whereIn('id', auth()->user()->companies->first()->users->pluck('id'))
                            ->where('role_id', 3)
                            ->where('availability', 1)
                            ->get();
                    } else {
                        $q->where('id', auth()->id());
                    }
                })->get();
            } else {
                $users = User::where('id', auth()->user()->id)->get();
            }

        } elseif (auth()->user()->role_id == 3) {
            if (count(auth()->user()->companies)) {
                $users = User::where(function ($q) {
                    if (auth()->user()->role_type != 3) {
                        $q->whereIn('id', auth()->user()->companies->first()->users->pluck('id'))
                            ->where('role_id', 3)
                            ->where('availability', 1)
                            ->get();
                    } else {
                        $q->where('id', auth()->id());
                    }
                })->get();
            } else {
                $users = User::where('id', auth()->user()->id)->get();
            }
        } elseif (auth()->user()->role_id == 4) {
            if (count(auth()->user()->companies)) {
                $users = auth()->user()->companies->first()->users()
                    ->groupBy('id')
                    ->where('role_id', 3)
                    ->where('availability', 1)
                    ->get();
            } else {
                $users = collect();
            }
        } elseif (auth()->user()->role_id == 6) {
            $users = auth()->user()->company->users()
                ->groupBy('id')
                ->where('role_id', 3)
                ->where('availability', 1)
                ->get();
        } else {
            $users = User::where('role_id', 3)
                ->where('availability', 1)
                ->get();
        }

        return $users;
    }
}
