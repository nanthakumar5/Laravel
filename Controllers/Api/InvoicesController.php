<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Models\Invoice;
use App\Models\User;
use App\Models\Charge;
use App\Models\Tax;
use App\Models\Service;
use App\Models\Product;
use App\Models\Currency;
use App\Models\CompanyDetail;
use App\Models\Paymentmethod;

class InvoicesController extends Controller
{
    public function lists(Request $request)
	{ 
		$requestData = $request->all();
		
		$data 		= 	$this->invoices('all', $requestData);
		$datacount 	= 	$this->invoices('count', $requestData);
		return response()->json(["draw" => $requestData['draw'],"recordsTotal" => $datacount,"recordsFiltered" => $datacount,"data" => $data], 200);
	}
	
	public function invoices($type, $input)
	{ 
		if (auth()->user() != null && isset(auth()->user()->company->company)) {
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

		$sql = 	Invoice::with('items', 'currency')->select(
					'invoices.*',
					DB::raw("CONCAT(A.firstname, ' ', A.lastname) as patient_name"),
					DB::raw("CONCAT(B.firstname, ' ', B.lastname) as doctor_name")
				)
                ->leftjoin('users AS A', 'A.id', '=', 'invoices.user_id')
                ->leftjoin('users AS B', 'B.id', '=', 'invoices.doctor_id')
                ->where(function ($query) use ($input) {
                    if (auth()->user()->role_id == 3 || auth()->user()->role_id == 6 || auth()->user()->role_id == 4) {
                        if (isset($input['doctors'])) {
                            $query->where(function ($q) use ($input) {
                                $q->whereIn('invoices.doctor_id', $input['doctors']);
                            });
                        } else {
                            $query->where(function ($q) use ($input) {
                                $q->where('invoices.doctor_id', auth()->user()->id);
                            });
                        }
                    }
                    if (auth()->user()->role_id == 5) {
                        $query->where(function ($q) use ($input) {
                            $q->where('invoices.user_id', auth()->user()->id);
                        });
                    }
/*
                    if ($input['from_date'] != "") {
                        $query->where(function ($q) use ($input) {
                            $q->Where('invoices.created_at', '>=', $input['from_date']);
                        });
                    }

                    if ($input['to_date'] != "") {
                        $query->where(function ($q) use ($input) {
                            $q->Where('invoices.created_at', '<=', $input['to_date']);
                        });
                    }

                    if ($input['doctor_id'] != "") {
                        $query->where(function ($q) use ($input) {
                            $q->Where('invoices.user_id', $input['doctor_id']);
                        });
                    }*/
                });
		
		if(isset($input['search']['value']) && $input['search']['value']!=''){
			$searchvalue = $input['search']['value'];
			
			$sql = $sql->where(function($q) use($searchvalue){
				$q->where('invoices.id', 'like', '%'.$searchvalue.'%');
				$q->orWhere('invoices.created_at', 'like', '%'.$searchvalue.'%');
				$q->orWhere(\DB::raw("CONCAT(A.firstname, ' ', A.lastname)"), 'like', '%'.$searchvalue.'%');
				$q->orWhere(\DB::raw("CONCAT(B.firstname, ' ', B.lastname)"), 'like', '%'.$searchvalue.'%');
			});			
		}
		if($type!=='count' && isset($input['start']) && isset($input['length'])){
			$sql = $sql->offset($input['start'])->limit($input['length']);
		}
		if(isset($input['order']['0']['column']) && isset($input['order']['0']['dir'])){
			$column = ['invoices.id', 'invoices.name', 'invoices.stock', 'invoices.buying_price', 'invoices.selling_price', 'invoices.created_at', 'B.firstname'];
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
		
		if (auth()->user() != null && isset(auth()->user()->company->company)) {
            auth()->user()->company = auth()->user()->company->company;
        }

        if (auth()->user()->role_id == 3) {
            $invoices = auth()->user()->invoices;
            $paymentmethods = Paymentmethod::with('items')->latest()->where('user_id', auth()->id())->get();
        } elseif (auth()->user()->role_id == 4) {
            if (count(auth()->user()->companies)) {
                foreach (auth()->user()->companies as $company) {
                    $invoices = Invoice::with('items')->latest()->whereIn('doctor_id', $company->users->pluck('id'))->get();
                    $paymentmethods = Paymentmethod::latest()->whereIn('user_id', $company->users->pluck('id'))->get();
                }
            } else {
                $invoices = collect();
                $paymentmethods = collect();
            }
        } elseif (auth()->user()->role_id == 6) {
            foreach (auth()->user()->companies as $company) {
                $invoices = Invoice::with('items')->latest()->whereIn('doctor_id', $company->users->pluck('id'))->get();
                $paymentmethods = Paymentmethod::latest()->whereIn('user_id', $company->users->pluck('id'))->get();
            }
        } else {
            $invoices = User::with('invoices')->get()->map->invoices->collapse();
            $paymentmethods = Paymentmethod::all();
        }
		
		$invoice = Invoice::with(['items', 'doctor', 'doctor.companies', 'company', 'user', 'currency', 'tax'])->where('id', $requestData['id'])->first();
        $currency = $invoice->currency;

		if($invoice){
			return response()->json(['success' => compact(
				'invoice',
				'invoices',
				'paymentmethods',
				'currency'
			)], 200);
		}else{
			return response()->json(['error' => []], 200);
		}
	}
	
    public function action(Request $request)
	{ 
		if($request->isMethod('post')){
			DB::beginTransaction();
			
			$requestData 				= $request->all();
			
			$user = auth()->user();
            $company = $this->activeCompany();
			
			if($requestData['actionid']==''){
				$invoice = new Invoice;
				$invoice->creator_id = $user->id;
			}else{
				$invoice = Invoice::find($requestData['actionid']);
				
				if ($request->filled('products')) {
					$invoice->items()->delete();
				}
			}
			
            $invoice->description = $request->description;
            $invoice->tax_id = $request->tax_id;
            $invoice->currency_id = $request->currency_id;
            $invoice->due_date = date('Y-m-d', strtotime($request->due_date));
            $invoice->company_id = $company->id;
            $invoice->insurance_name = $request->insurance_name;
            $invoice->user_id = $request->user_id;
            $invoice->doctor_id = $request->doctor_id;
            $invoice->save();

            $invoice_amount = 0;
            $products = collect();
            foreach ($request->products as $index => $productItem) {
                if ($productItem['type'] === "product") {
                    $product = Product::query()->findOrfail($productItem['product_service']);
                    if ($productItem['quantity'] > $product->stock) {
						return response()->json(['error' => 'Requested Quantity Greater than Stock Quantity'], 500);
                    } elseif ($product->stock < 0) {
						return response()->json(['error' => 'Product Stock Out'], 500);
                    }

                    $remainingStock = $product->stock - $productItem['quantity'];
                    $product->stock = $remainingStock;
                    $product->save();

                    $item_total = $product->selling_price * $productItem['quantity'];

                    //if ($product->stock < 5) {
                        //mail::to('no-replay@hospitalnotes.com')->send(new BellowProductStockReminder($productItem['product_service']));
                    //}
                } else {
                    $item_total = $productItem['quantity'] * Charge::query()->find($productItem['charge_id'])->amount;
                }
                $products->push([
                    'product_service' => $productItem['product_service'],
                    'type' => $productItem['type'],
                    'quantity' => $productItem['quantity'],
                    'charge_id' => $productItem['charge_id'],
                    'item_total' => $item_total,
                ]);
                $invoice_amount += $item_total;
            }

            $invoice->items()->createMany($products->toArray());

            $invoice->update(['invoice_total' => $invoice_amount]);

            DB::commit();

			if($requestData['actionid']==''){
				return response()->json(['success' => ['message' => 'Invoice added successfully']], 200);
			}else{
				return response()->json(['success' => ['message' => 'Invoice updated successfully']], 200);
			}
		}
	}
	
	public function delete(Request $request)
	{ 
		$requestData = $request->all();
		Product::where('id', $requestData['id'])->delete();
		return response()->json(['success' => []], 200);
	}
	
	public function getUsersandInvoices(Request $request)
	{ 
		$user = auth()->user();

        if ($user && $user->company->company) {
            $user->company = $user->company->company;
        } else {
            $user->company = $this->activeCompany();
        }

        if ($user->role_id == 5) {
            $invoices = $user->invoices;
            $users = User::query()->latest()->where('role_id', 5)->get();
        } elseif ($user->role_id == 3) {
            $invoices = $user->doctorInvoices;
            foreach ($user->companies as $company) {
                $users = User::where(['role_id' => 5])->whereIn('user_id', $company->users->pluck('id'))->get();
            }
        } elseif ($user->role_id == 4) {
            if (count($user->companies)) {
                foreach ($user->companies as $company) {
                    $invoices = Invoice::query()->latest()->whereIn('doctor_id', $company->users->pluck('id'))->get();
                    $users = User::query()->where(['role_id' => 5])->whereIn('user_id', $company->users->pluck('id'))->get();
                }
            } else {
                $invoices = collect();
                $users = collect();
            }
        } elseif ($user->role_id == 6) {
            foreach ($user->companies as $company) {
                $invoices = Invoice::query()->latest()->whereIn('doctor_id', $company->users->pluck('id'))->get();
                $users = User::query()->where(['role_id' => 5])->whereIn('user_id', $company->users->pluck('id'))->get();
            }
        } else {
            $users = User::query()->latest()->where('role_id', 5)->get();
            $invoices = User::with('invoices')->get()->map->invoices->collapse();
        }
		
		return response()->json(['success' => ['users' => $users, 'invoices' => $invoices]], 200);
	}
	
	public function getInvoicesActionData(Request $request)
	{ 
		if (auth()->user() != null && isset(auth()->user()->company->company)) {
            auth()->user()->company = auth()->user()->company->company;
        }

        if (auth()->user()->role_id == 3) {
            foreach (auth()->user()->companies as $company) {
                $doctors = User::where('role_id', '!=', 5)
                    ->where('role_id', '!=', 1)
                    ->where('role_id', '!=', 2)
                    ->where('role_id', '!=', 6)
                    ->whereIn('id', $company->users->pluck('id'))
                    ->get();
                $users = User::where(['role_id' => 5])->whereIn('user_id', $company->users->pluck('id'))->get();
                $services = Service::whereIn('user_id', $company->users->pluck('id'))
                    ->get();
                $products = Product::whereIn('user_id', $company->users->pluck('id'))
                    ->get();
                $charges = Charge::latest()->whereIn('user_id', $company->users->pluck('id'))->get();
                $currencies = Currency::latest()->whereIn('user_id', $company->users->pluck('id'))->get();
                $taxes = Tax::latest()->whereIn('user_id', $company->users->pluck('id'))->get();
            }
        } elseif (auth()->user()->role_id == 4) {
            if (count(auth()->user()->companies)) {
                foreach (auth()->user()->companies as $company) {
                    $doctors = User::where('role_id', '!=', 5)
                        ->where('role_id', '!=', 1)
                        ->where('role_id', '!=', 2)
                        ->where('role_id', '!=', 6)
                        ->whereIn('id', $company->users->pluck('id'))
                        ->get();
                    $users = User::where(['role_id' => 5])->whereIn('user_id', $company->users->pluck('id'))->get();
                    $services = Service::whereIn('user_id', $company->users->pluck('id'))
                        ->get();
                    $products = Product::whereIn('user_id', $company->users->pluck('id'))
                        ->get();
                    $charges = Charge::latest()->whereIn('user_id', $company->users->pluck('id'))->get();
                    $currencies = Currency::latest()->whereIn('user_id', $company->users->pluck('id'))->get();
                    $taxes = Tax::latest()->whereIn('user_id', $company->users->pluck('id'))->get();
                }
            } else {
                $users = collect();
                $doctors = collect();
                $charges = collect();
                $taxes = collect();
                $services = collect();
                $products = collect();
                $currencies = collect();
            }
        } elseif (auth()->user()->role_id == 6) {
            foreach (auth()->user()->companies as $company) {
                $doctors = User::where('role_id', '!=', 5)
                    ->where('role_id', '!=', 1)
                    ->where('role_id', '!=', 2)
                    ->where('role_id', '!=', 6)
                    ->whereIn('id', $company->users->pluck('id'))
                    ->get();
                $users = User::where(['role_id' => 5])->whereIn('user_id', $company->users->pluck('id'))->get();
                $services = Service::whereIn('user_id', $company->users->pluck('id'))
                    ->get();
                $products = Product::whereIn('user_id', $company->users->pluck('id'))
                    ->get();
                $charges = Charge::latest()->whereIn('user_id', $company->users->pluck('id'))->get();
                $currencies = Currency::latest()->whereIn('user_id', $company->users->pluck('id'))->get();
                $taxes = Tax::latest()->whereIn('user_id', $company->users->pluck('id'))->get();
            }
        } else {
            $users = User::latest()->select('id', 'firstname', 'lastname', 'date_of_birth')->where('role_id', 5)->get();
            $doctors = User::select('id', 'firstname', 'lastname')->where('role_id', '!=', 5)
                ->where('role_id', '!=', 1)
                ->where('role_id', '!=', 2)
                ->where('role_id', '!=', 6)
                ->get();
            $charges = Charge::select('id', 'name', 'amount')->get();
            $taxes = Tax::select('id', 'name', 'rate')->get();
            $services = Service::select('id', 'name')->get();
            $products = Product::select('id', 'name')->get();
            $currencies = Currency::select('id', 'name')->get();
        }
		
		return response()->json(['success' => [
			'users' => $users,
			'doctors'  => $doctors,
			'charges'  => $charges,
			'taxes'  => $taxes,
			'currencies'  => $currencies,
			'services'  => $services,
			'products'  => $products
		]], 200);
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
