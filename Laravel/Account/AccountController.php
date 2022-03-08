<?php

namespace App\Http\Controllers;

use App\Account;
use App\CostCenter;
use App\AccountTransaction;
use App\AccountType;
use App\FinGlobalAccount;
use App\TransactionPayment;
use App\Utils\Util;
use App\Utils\ProductUtil;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Yajra\DataTables\Facades\DataTables;

class AccountController extends Controller
{
    protected $commonUtil;
    protected $productUtil;

    /**
     * Constructor
     *
     * @param Util $commonUtil
     * @return void
     */
    public function __construct(Util $commonUtil,ProductUtil $productUtil)
    {
        $this->commonUtil = $commonUtil;
       $this->productUtil = $productUtil;

    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }


        $business_id = session()->get('user.business_id');
        if (request()->ajax()) {
            $accounts = Account::leftjoin('account_transactions as AT', function ($join) {
                $join->on('AT.account_id', '=', 'accounts.id');
                $join->whereNull('AT.deleted_at');
            })
            ->leftjoin(
                'account_types as ats',
                'accounts.account_type_id',
                '=',
                'ats.id'
            )
            ->leftjoin(
                'account_types as pat',
                'ats.parent_account_type_id',
                '=',
                'pat.id'
            )
             ->leftjoin(
                'accounts as superCheck',
                'accounts.parent_id',
                '=',
                'superCheck.id'
            )
            ->leftJoin('users AS u', 'accounts.created_by', '=', 'u.id')
                                ->where('accounts.business_id', $business_id)
                                  ->where('accounts.type', 'Account')
                                ->select(['accounts.name', 'accounts.account_code', 'accounts.note', 'accounts.id', 'accounts.account_type_id',
                                    'ats.name as account_type_name',
                                    'pat.name as parent_account_type_name',
                                   'superCheck.name as parent_id',
                                    'accounts.is_closed', DB::raw("SUM( IF(AT.type='credit', amount, -1*amount) ) as balance"),
                                    DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as added_by")
                                    ])
                                ->groupBy('accounts.id');

            return DataTables::of($accounts)
                            ->addColumn(
                                'action',
                                '<button data-href="{{action(\'AccountController@edit\',[$id])}}" data-container=".account_model" class="btn btn-xs btn-primary btn-modal"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                                <a href="{{action(\'AccountController@show\',[$id])}}" class="btn btn-warning btn-xs"><i class="fa fa-book"></i> @lang("account.account_book")</a>&nbsp;
                                @if($is_closed == 0)
                                <button data-href="{{action(\'AccountController@getFundTransfer\',[$id])}}" class="btn btn-xs btn-info btn-modal" data-container=".view_modal"><i class="fa fa-exchange"></i> @lang("account.fund_transfer")</button>

                                <button data-href="{{action(\'AccountController@getDeposit\',[$id])}}" class="btn btn-xs btn-success btn-modal" data-container=".view_modal"><i class="fas fa-money-bill-alt"></i> @lang("account.deposit")</button>

                                <button data-url="{{action(\'AccountController@close\',[$id])}}" class="btn btn-xs btn-danger close_account"><i class="fa fa-close"></i> @lang("messages.close")</button>
                                @endif'
                            )
                            ->editColumn('name', function ($row) {
                                if ($row->is_closed == 1) {
                                    return $row->name . ' <small class="label pull-right bg-red no-print">' . __("account.closed") . '</small><span class="print_section">(' . __("account.closed") . ')</span>';
                                } else {
                                    return $row->name;
                                }
                            })
                            ->editColumn('balance', function ($row) {
                                return '<span class="display_currency" data-currency_symbol="true">' . $row->balance . '</span>';
                            })
                            ->editColumn('account_type', function ($row) {
                                $account_type = '';
                                if (!empty($row->account_type->parent_account)) {
                                    $account_type .= $row->account_type->parent_account->name . ' - ';
                                }
                                if (!empty($row->account_type)) {
                                    $account_type .= $row->account_type->name;
                                }
                                return $account_type;
                            })
                            ->editColumn('parent_account_type_name', function ($row) {
                                $parent_account_type_name = empty($row->parent_account_type_name) ? $row->account_type_name : $row->parent_account_type_name;
                                return $parent_account_type_name;
                            })
                            ->editColumn('account_type_name', function ($row) {
                                $account_type_name = empty($row->parent_account_type_name) ? '' : $row->account_type_name;
                                return $account_type_name;
                            })
                            ->removeColumn('id')
                            ->removeColumn('is_closed')
                            ->rawColumns(['action', 'balance', 'name'])
                            ->make(true);
        }

        $not_linked_payments = TransactionPayment::leftjoin(
            'transactions as T',
            'transaction_payments.transaction_id',
            '=',
            'T.id'
        )
                                    ->whereNull('transaction_payments.parent_id')
                                    ->where('transaction_payments.business_id', $business_id)
                                    ->whereNull('account_id')
                                    ->count();

        $account_types = AccountType::where('business_id', $business_id)
                                     ->whereNull('parent_account_type_id')
                                     ->with(['sub_types'])
                                     ->get();

        return view('account.index')
                ->with(compact('not_linked_payments', 'account_types'));
    }

//gloabl account
    public function globalSettings(request $request){

         if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }
 if(!empty($request->all()) && $request->branch){
    $business_id  = $request->branch;
 }else{
     $business_id = session()->get('user.business_id');
 }
      $group = Account::where('type','Account')->get();
      $business = FinGlobalAccount::where('BranchID',$business_id)->first();
      $branch = DB::select('select * from business_locations');
//echo'<pre>';print_r($branch);die('a');


      return view('account.global_account')
                ->with(compact('business_id','branch','group','business')); 
  }

//  global store 
 public function globalStore(Request $request){

    if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->post()) {
            try {
                $input = $request->only(['id','created_by','BranchID','freeze_date','TradeDiscount', 'CostAccount', 'StockNotBilled', 'InventoryAccount', 'SalaryPayable','SalesAccount','BankAccount','CashAccount','ARAccount','APAccount']);

                 if($request->freeze_date){
                       $input['freeze_date'] = $this->productUtil->uf_date($request->freeze_date, true);
                  }  

      $business = FinGlobalAccount::where('BranchID',$request->BranchID)->first();

                if(!empty($business)){

                $account = FinGlobalAccount::where('BranchID',$request->BranchID)->update($input);
                $output = ['success' => true,
                            'msg' => __("Account updated Successfully.")
                        ];

                }else{
                $user_id = $request->session()->get('user.id');
                $input['created_by'] = $user_id;
                         

                $account = FinGlobalAccount::create($input);

                 $output = ['success' => true,
                            'msg' => __("Account Created Successfully.")
                        ];
            }

               
            } catch (\Exception $e) {
                \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
                    
                $output = ['success' => false,
              // 'msg' => $e->getMessage()
               'msg' => __("messages.something_went_wrong")
                            ];
            }

         return redirect('account/global_accounts?branch='.$request->BranchID)->with('status', $output);

        }

 }

/*********cost center**********/

    // add group
       public function addGroup_costCenter()
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = session()->get('user.business_id');
        $group = CostCenter::where('business_id', $business_id)->where('type','Group')
                                     ->get();
                          
          $res = CostCenter::max('number');
          if($res){
           $total = $res+1;
            $max = 'New Group '.$total;
        } else{ 
            $max = 'New Group 1';
       }
        return view('account.center_group')
                ->with(compact('group','max'));
    }
//save group
       public function save_cost_group(Request $request)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['created_by','name','parent_id','main_group', 'number', 'business_id', 'type', 'account_code','belong','account_belong']);

                   $number = CostCenter::max('number');
                   if($number)
                        $number = $number+1 ;
                    else
                        $number =1;
                 $input['number'] = $number;

                if(empty($input['parent_id'])){

                   $max = CostCenter::max('main_group');
                       if($max)
                        $max = $max+1 ;
                    else
                        $max =1;

                  $input['main_group'] = $max;
                  $input['account_code'] = $max;
                  $input['belong'] = $max;
                  
                }else{
              
                $check = CostCenter::where('id',$input['parent_id'])->first();

                $main = CostCenter::where('belong',$check['belong'])->max('account_code');
                if($check['account_code'] < 100){
                    $maths = $check['account_code']*1000+1 ;

                }else{
                   $maths = $check['account_code'].'01' ;
                 
                }
             if($maths){
                if($maths > $main){

                 $input['account_code'] = $this->checkAccount($maths);

                }else{

                 $input['account_code'] = $this->checkAccount($main+1);
             
               }
          }else{

               $input['account_code'] = $this->checkAccount($main+1);
          
          }
                 $input['belong'] = $check['belong'];
             }
                  //  echo '<pre>';print_r($input);die('a');
           

                
                $business_id = $request->session()->get('user.business_id');
                $user_id = $request->session()->get('user.id');
                $input['business_id'] = $business_id;
                $input['created_by'] = $user_id;
                $input['type'] = 'Group';
                    // echo '<pre>';print_r($input);die('a');

                $account = CostCenter::create($input);

                $output = ['success' => true,
                            'msg' => __("Group Successfully Created.")
                        ];
            } catch (\Exception $e) {
                \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
                    
                $output = ['success' => false,
                            'msg' => __("messages.something_went_wrong")
                            ];
            }

            return $output;
        }
    }

  public function create_costCenter()
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = session()->get('user.business_id');
        $group = CostCenter::where('business_id', $business_id)->where('type','Group')
                                     ->get();

        return view('account.create_cost')
                ->with(compact('group'));
    }

//save cost center
        public function save_cost(Request $request)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['created_by','name','parent_id','main_group', 'number', 'business_id', 'type', 'account_code','belong','account_belong']);
                $business_id = $request->session()->get('user.business_id');
                $user_id = $request->session()->get('user.id');
                $input['business_id'] = $business_id;
                $input['created_by'] = $user_id;
                $input['type'] = 'Account';
                $input['account_belong'] = $input['parent_id'];
                 $number = CostCenter::max('number');
                   if($number)
                        $number = $number+1 ;
                    else
                        $number =1;
                 $input['number'] = $number;


 $check = CostCenter::where('id',$input['parent_id'])->first();

 $main = CostCenter::where('account_belong',$input['parent_id'])->max('account_code');
if($main){
 $maths = $main+1;
}else{
 $maths = $check['account_code'].'001' ;
}

 $input['account_code'] = $this->checkAccount($maths);
 $input['belong'] = $check['belong'];
             
                $account = CostCenter::create($input);
                
                $output = ['success' => true,
                            'msg' => __("account.account_created_success")
                        ];
            } catch (\Exception $e) {
                \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
                    
                $output = ['success' => false,
                            'msg' => __("messages.something_went_wrong")
                            ];
            }

            return $output;
        }
    }
    // Cost              
   public function delete_cost_center_group(Request $request){
      if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }
         if (request()->ajax()) {
            $res = CostCenter::where('id',$request['id'])->delete();
            return $output = ['success' => true,
                            'msg' => __("Successfully deleted")
                        ];
            }
   }


    public function edit_cost_center_group(Request $request)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
           // echo '<pre>';print_r($request->all());die('a');
           $business_id = request()->session()->get('user.business_id');

             $group = CostCenter::where('business_id', $business_id)->where('type','Group')
                                     ->get();
            $account = CostCenter::where('business_id', $business_id)
                                ->find($request->id);
           
            return view('account.cost_group_edit')
                ->with(compact('group','account'));
        }
    }


   public function save_cost_edit_group(Request $request)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }
        if (request()->ajax()) {
            try {
                $input = $request->only(['created_by','name','parent_id','main_group', 'number', 'business_id', 'type', 'account_code','belong','account_belong']);
           $business_id = request()->session()->get('user.business_id');

   $account = CostCenter::where('business_id', $business_id)
                                                    ->findOrFail($request['id']);

      if(!empty($input['parent_id'])){

                $check = CostCenter::where('id',$input['parent_id'])->first();

               
              $account->belong = $check['belong'];

            }else{

               $account->belong = '';

            }
            if($request['account_code']){

                  $account->account_code = $this->checkAccount($request['account_code'],$request['id']);

                }else{
                    $account->account_code ='';
                }
                $account->name = $input['name'];
                 $account->parent_id = $input['parent_id'];
                $account->save();

           

                $output = ['success' => true,
                            'msg' => __("Group Successfully updated.")
                        ];
            } catch (\Exception $e) {
                \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
                    
                $output = ['success' => false,
                            'msg' => __("messages.something_went_wrong")
                            ];
            }

            return $output;
        }
    }


/*******************/
 //cost account
 public function cost_center(){

   $groups = CostCenter::leftjoin('cost_center as AT', function ($join) {
                $join->on('AT.id', '=', 'cost_center.parent_id');
            })->select('cost_center.*', 'AT.name as superParent')->groupBy('cost_center.id')->get(); 

   return view('account.cost_center')
                ->with(compact('groups'));

 }
// Account New
  public function chart_of_accounts()
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = session()->get('user.business_id');

          $groups = Account::leftjoin('accounts as AT', function ($join) {
                $join->on('AT.id', '=', 'accounts.parent_id');
            })
            ->leftjoin(
                'account_types as ats',
                'accounts.account_type_id',
                '=',
                'ats.id'
            )->select('accounts.*', 'AT.name as superParent','ats.name as account_type')->groupBy('accounts.id')->get(); 

        // echo'<pre>';print_r($groups);die('a');
         /// $groups = DB::table('groups')->where('name', 'John')->value('email');

             if (request()->ajax()) {

            $accounts = Account::leftjoin('account_transactions as AT', function ($join) {
                $join->on('AT.account_id', '=', 'accounts.id');
                $join->whereNull('AT.deleted_at');
            })
            ->leftjoin(
                'account_types as ats',
                'accounts.account_type_id',
                '=',
                'ats.id'
            )
            ->leftjoin(
                'account_types as pat',
                'ats.parent_account_type_id',
                '=',
                'pat.id'
            )
            ->leftJoin('users AS u', 'accounts.created_by', '=', 'u.id')
                                ->where('accounts.business_id', $business_id)
                                ->select(['accounts.name', 'accounts.account_code', 'accounts.note', 'accounts.id', 'accounts.account_type_id',
                                    'ats.name as account_type_name',
                                    'pat.name as parent_account_type_name',
                                    'is_closed', DB::raw("SUM( IF(AT.type='credit', amount, -1*amount) ) as balance"),
                                    DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as added_by")
                                    ])
                                ->groupBy('accounts.id');

            return DataTables::of($accounts)
                            ->addColumn(
                                'action',
                                '<button data-href="{{action(\'AccountController@edit\',[$id])}}" data-container=".account_model" class="btn btn-xs btn-primary btn-modal"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                                <a href="{{action(\'AccountController@show\',[$id])}}" class="btn btn-warning btn-xs"><i class="fa fa-book"></i> @lang("account.account_book")</a>&nbsp;
                                @if($is_closed == 0)
                                <button data-href="{{action(\'AccountController@getFundTransfer\',[$id])}}" class="btn btn-xs btn-info btn-modal" data-container=".view_modal"><i class="fa fa-exchange"></i> @lang("account.fund_transfer")</button>

                                <button data-href="{{action(\'AccountController@getDeposit\',[$id])}}" class="btn btn-xs btn-success btn-modal" data-container=".view_modal"><i class="fas fa-money-bill-alt"></i> @lang("account.deposit")</button>

                                <button data-url="{{action(\'AccountController@close\',[$id])}}" class="btn btn-xs btn-danger close_account"><i class="fa fa-close"></i> @lang("messages.close")</button>
                                @endif'
                            )
                            ->editColumn('name', function ($row) {
                                if ($row->is_closed == 1) {
                                    return $row->name . ' <small class="label pull-right bg-red no-print">' . __("account.closed") . '</small><span class="print_section">(' . __("account.closed") . ')</span>';
                                } else {
                                    return $row->name;
                                }
                            })
                            ->editColumn('balance', function ($row) {
                                return '<span class="display_currency" data-currency_symbol="true">' . $row->balance . '</span>';
                            })
                            ->editColumn('account_type', function ($row) {
                                $account_type = '';
                                if (!empty($row->account_type->parent_account)) {
                                    $account_type .= $row->account_type->parent_account->name . ' - ';
                                }
                                if (!empty($row->account_type)) {
                                    $account_type .= $row->account_type->name;
                                }
                                return $account_type;
                            })
                            ->editColumn('parent_account_type_name', function ($row) {
                                $parent_account_type_name = empty($row->parent_account_type_name) ? $row->account_type_name : $row->parent_account_type_name;
                                return $parent_account_type_name;
                            })
                            ->editColumn('account_type_name', function ($row) {
                                $account_type_name = empty($row->parent_account_type_name) ? '' : $row->account_type_name;
                                return $account_type_name;
                            })
                            ->removeColumn('id')
                            ->removeColumn('is_closed')
                            ->rawColumns(['action', 'balance', 'name'])
                            ->make(true);
        }

        $not_linked_payments = TransactionPayment::leftjoin(
            'transactions as T',
            'transaction_payments.transaction_id',
            '=',
            'T.id'
        )
                                    ->whereNull('transaction_payments.parent_id')
                                    ->where('transaction_payments.business_id', $business_id)
                                    ->whereNull('account_id')
                                    ->count();

        // $capital_account_count = Account::where('business_id', $business_id)
        //                             ->NotClosed()
        //                             ->where('account_type', 'capital')
        //                             ->count();

        $account_types = AccountType::where('business_id', $business_id)
                                     ->whereNull('parent_account_type_id')
                                     ->with(['sub_types'])
                                     ->get();

        return view('account.chart')
                ->with(compact('groups','not_linked_payments', 'account_types'));
    }


    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = session()->get('user.business_id');
        $group = Account::where('business_id', $business_id)->where('type','Group')
                                     ->get();
        $account_types = AccountType::where('business_id', $business_id)
                                     ->whereNull('parent_account_type_id')
                                     ->with(['sub_types'])
                                     ->get();

        return view('account.create')
                ->with(compact('group','account_types'));
    }

    /**
     * Store a newly created resource in storage.
     * @param  Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['name','account_code', 'note', 'account_type_id', 'parent_id', 'number','business_id','account_type_id']);
                $business_id = $request->session()->get('user.business_id');
                $user_id = $request->session()->get('user.id');
                $input['business_id'] = $business_id;
                $input['created_by'] = $user_id;
                $input['type'] = 'Account';
                $input['account_belong'] = $input['parent_id'];
                 $number = Account::max('number');
                   if($number)
                        $number = $number+1 ;
                    else
                        $number =1;
                 $input['number'] = $number;


 $check = Account::where('id',$input['parent_id'])->first();

 $main = Account::where('account_belong',$input['parent_id'])->max('account_code');
if($main){
 $maths = $main+1;
}else{
 $maths = $check['account_code'].'001' ;
}

 $input['account_code'] = $this->checkAccount($maths);
 $input['belong'] = $check['belong'];
             

                $account = Account::create($input);

                //Opening Balance
                $opening_bal = $request->input('opening_balance');

                if (!empty($opening_bal)) {
                    $ob_transaction_data = [
                        'amount' =>$this->commonUtil->num_uf($opening_bal),
                        'account_id' => $account->id,
                        'type' => 'credit',
                        'sub_type' => 'opening_balance',
                        'operation_date' => \Carbon::now(),
                        'created_by' => $user_id
                    ];

                    AccountTransaction::createAccountTransaction($ob_transaction_data);
                }
                
                $output = ['success' => true,
                            'msg' => __("account.account_created_success")
                        ];
            } catch (\Exception $e) {
                \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
                    
                $output = ['success' => false,
                            'msg' => __("messages.something_went_wrong")
                            ];
            }

            return $output;
        }
    }


    public function addGroup()
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = session()->get('user.business_id');
        $group = Account::where('business_id', $business_id)->where('type','Group')
                                     ->get();

           $account_types = AccountType::where('business_id', $business_id)
                                     ->whereNull('parent_account_type_id')
                                     ->with(['sub_types'])
                                     ->get();                             
          $res = Account::max('number');
          if($res){
           $total = $res+1;
            $max = 'New Group '.$total;
        } else{ 
            $max = 'New Group 1';
       }
        return view('account.group')
                ->with(compact('group','account_types','max'));
    }

    
    /**
     * Save the specified group response.
     * @return Response
     */

    public function checkAccount($code,$id=false){
           if($id)
              $quit = Account::where('account_code',$code)->where('id','!=',$id)->first();
              else
              $quit = Account::where('account_code',$code)->first();

               if(!empty($quit)){
                $code = $quit->account_code+1;
                return $this->checkAccount($code,$id);
               }else{
                return $code;
               }
            }
     

    public function save_group(Request $request)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'parent_id', 'main_group', 'number','business_id','created_by','account_type_id','note']);

                   $number = Account::max('number');
                   if($number)
                        $number = $number+1 ;
                    else
                        $number =1;
                 $input['number'] = $number;

                if(empty($input['parent_id'])){

                   $max = Account::max('main_group');
                       if($max)
                        $max = $max+1 ;
                    else
                        $max =1;

                  $input['main_group'] = $max;
                  $input['account_code'] = $max;
                  $input['belong'] = $max;
                  
                }else{
              
                $check = Account::where('id',$input['parent_id'])->first();

                $main = Account::where('belong',$check['belong'])->max('account_code');
                if($check['account_code'] < 100){
                    $maths = $check['account_code']*1000+1 ;

                }else{
                   $maths = $check['account_code'].'01' ;
                 
                }
             if($maths){
                if($maths > $main){

                 $input['account_code'] = $this->checkAccount($maths);

                }else{

                 $input['account_code'] = $this->checkAccount($main+1);
             
               }
          }else{

               $input['account_code'] = $this->checkAccount($main+1);
          
          }
                 $input['belong'] = $check['belong'];
             }
                  //  echo '<pre>';print_r($input);die('a');
           

                
                $business_id = $request->session()->get('user.business_id');
                $user_id = $request->session()->get('user.id');
                $input['business_id'] = $business_id;
                $input['created_by'] = $user_id;
                $input['type'] = 'Group';
                    // echo '<pre>';print_r($input);die('a');

                $account = Account::create($input);

                $output = ['success' => true,
                            'msg' => __("Group Successfully Created.")
                        ];
            } catch (\Exception $e) {
                \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
                    
                $output = ['success' => false,
                            'msg' => __("messages.something_went_wrong")
                            ];
            }

            return $output;
        }
    }

   public function delete_group(Request $request){
      if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }
         if (request()->ajax()) {
            $res = Account::where('id',$request['id'])->delete();
            return $output = ['success' => true,
                            'msg' => __("Successfully deleted")
                        ];
            }
   }


    public function edit_group(Request $request)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
           // echo '<pre>';print_r($request->all());die('a');
           $business_id = request()->session()->get('user.business_id');

             $group = Account::where('business_id', $business_id)->where('type','Group')
                                     ->get();
            $account = Account::where('business_id', $business_id)
                                ->find($request->id);
            $account_types = AccountType::where('business_id', $business_id)
                                     ->whereNull('parent_account_type_id')
                                     ->with(['sub_types'])
                                     ->get();
           
            return view('account.group_edit')
                ->with(compact('group','account', 'account_types'));
        }
    }


   public function save_edit_group(Request $request)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }
        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'parent_id', 'main_group', 'number','business_id','created_by','account_type_id','account_code']);
           $business_id = request()->session()->get('user.business_id');

   $account = Account::where('business_id', $business_id)
                                                    ->findOrFail($request['id']);

      if(!empty($input['parent_id'])){

                $check = Account::where('id',$input['parent_id'])->first();

               
              $account->belong = $check['belong'];

            }else{

               $account->belong = '';

            }
            if($request['account_code']){

                  $account->account_code = $this->checkAccount($request['account_code'],$request['id']);

                }else{
                    $account->account_code ='';
                }
                $account->name = $input['name'];
                $account->account_type_id = $input['account_type_id'];
                 $account->parent_id = $input['parent_id'];
                $account->save();

           

                $output = ['success' => true,
                            'msg' => __("Group Successfully updated.")
                        ];
            } catch (\Exception $e) {
                \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
                    
                $output = ['success' => false,
                            'msg' => __("messages.something_went_wrong")
                            ];
            }

            return $output;
        }
    }

// Edit cost center account
       public function edit_cost_center(Request $request)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

             $group = CostCenter::where('business_id', $business_id)->where('type','Group')
                                     ->get();

            $account = CostCenter::where('business_id', $business_id)
                                ->find($request->id);
           
            return view('account.edit_cost_center')
                ->with(compact('group','account'));
        }
    }

    /**
     * Update the specified resource in storage.
     * @param  Request $request
     * @return Response
     */
    public function save_cost_center(Request $request)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['id','name', 'parent_id', 'main_group', 'number','business_id','created_by','account_code']);

                $business_id = request()->session()->get('user.business_id');
                $account = CostCenter::where('business_id', $business_id)
                                                    ->findOrFail($input['id']);
                $account->name = $input['name'];
                $account->account_code = $input['account_code'];
                $account->save();

                $output = ['success' => true,
                                'msg' => __("Cost Center updated Successfully")
                                ];
            } catch (\Exception $e) {
                \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
            
                $output = ['success' => false,
                            'msg' => __("messages.something_went_wrong")
                        ];
            }
            
            return $output;
        }
    }
    

    /**
     * Show the specified resource.
     * @return Response
     */
    public function show($id)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $accounts = AccountTransaction::join(
                'accounts as A',
                'account_transactions.account_id',
                '=',
                'A.id'
            )
            ->leftJoin('users AS u', 'account_transactions.created_by', '=', 'u.id')
                            ->where('A.business_id', $business_id)
                            ->where('A.id', $id)
                            ->with(['transaction', 'transaction.contact', 'transfer_transaction'])
                            ->select(['type', 'amount', 'operation_date',
                                'sub_type', 'transfer_transaction_id',
                                DB::raw('(SELECT SUM(IF(AT.type="credit", AT.amount, -1 * AT.amount)) from account_transactions as AT WHERE AT.operation_date <= account_transactions.operation_date AND AT.account_id  =account_transactions.account_id AND AT.deleted_at IS NULL AND AT.id <= account_transactions.id) as balance'),
                                'transaction_id',
                                'account_transactions.id',
                                DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as added_by")
                                ])
                             ->groupBy('account_transactions.id')
                             ->orderBy('account_transactions.id', 'asc')
                             ->orderBy('account_transactions.operation_date', 'asc');
            if (!empty(request()->input('type'))) {
                $accounts->where('type', request()->input('type'));
            }

            $start_date = request()->input('start_date');
            $end_date = request()->input('end_date');
            
            if (!empty($start_date) && !empty($end_date)) {
                $accounts->whereBetween(DB::raw('date(operation_date)'), [$start_date, $end_date]);
            }

            return DataTables::of($accounts)
                            ->addColumn('debit', function ($row) {
                                if ($row->type == 'debit') {
                                    return '<span class="display_currency" data-currency_symbol="true">' . $row->amount . '</span>';
                                }
                                return '';
                            })
                            ->addColumn('credit', function ($row) {
                                if ($row->type == 'credit') {
                                    return '<span class="display_currency" data-currency_symbol="true">' . $row->amount . '</span>';
                                }
                                return '';
                            })
                            ->editColumn('balance', function ($row) {
                                return '<span class="display_currency" data-currency_symbol="true">' . $row->balance . '</span>';
                            })
                            ->editColumn('operation_date', function ($row) {
                                return $this->commonUtil->format_date($row->operation_date, true);
                            })
                            ->editColumn('sub_type', function ($row) {
                                $details = '';
                                if (!empty($row->sub_type)) {
                                    $details = __('account.' . $row->sub_type);
                                    if (in_array($row->sub_type, ['fund_transfer', 'deposit']) && !empty($row->transfer_transaction)) {
                                        if ($row->type == 'credit') {
                                            $details .= ' ( ' . __('account.from') .': ' . $row->transfer_transaction->account->name . ')';
                                        } else {
                                            $details .= ' ( ' . __('account.to') .': ' . $row->transfer_transaction->account->name . ')';
                                        }
                                    }
                                } else {
                                    if (!empty($row->transaction->type)) {
                                        if ($row->transaction->type == 'purchase') {
                                            $details = '<b>' . __('purchase.supplier') . ':</b> ' . $row->transaction->contact->name . '<br><b>'.
                                            __('purchase.ref_no') . ':</b> ' . $row->transaction->ref_no;
                                        } elseif ($row->transaction->type == 'sell') {
                                            $details = '<b>' . __('contact.customer') . ':</b> ' . $row->transaction->contact->name . '<br><b>'.
                                            __('sale.invoice_no') . ':</b> ' . $row->transaction->invoice_no;
                                        }
                                    }
                                }

                                return $details;
                            })
                            ->editColumn('action', function ($row) {
                                $action = '';
                                if ($row->sub_type == 'fund_transfer' || $row->sub_type == 'deposit') {
                                    $action = '<button type="button" class="btn btn-danger btn-xs delete_account_transaction" data-href="' . action('AccountController@destroyAccountTransaction', [$row->id]) . '"><i class="fa fa-trash"></i> ' . __('messages.delete') . '</button>';
                                }
                                return $action;
                            })
                            ->removeColumn('id')
                            ->removeColumn('is_closed')
                            ->rawColumns(['credit', 'debit', 'balance', 'sub_type', 'action'])
                            ->make(true);
        }
        $account = Account::where('business_id', $business_id)
                        ->with(['account_type', 'account_type.parent_account'])
                        ->find($id);

        return view('account.show')
                ->with(compact('account'));
    }

    /**
     * Show the form for editing the specified resource.
     * @return Response
     */

    public function edit($id)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $account = Account::where('business_id', $business_id)
                                ->find($id);

            $account_types = AccountType::where('business_id', $business_id)
                                     ->whereNull('parent_account_type_id')
                                     ->with(['sub_types'])
                                     ->get();
           
            return view('account.edit')
                ->with(compact('account', 'account_types'));
        }
    }

    /**
     * Update the specified resource in storage.
     * @param  Request $request
     * @return Response
     */
    public function update(Request $request, $id)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'account_code', 'note', 'account_type_id']);

                $business_id = request()->session()->get('user.business_id');
                $account = Account::where('business_id', $business_id)
                                                    ->findOrFail($id);
                $account->name = $input['name'];
                $account->account_code = $input['account_code'];
                $account->note = $input['note'];
                $account->account_type_id = $input['account_type_id'];
                $account->save();

                $output = ['success' => true,
                                'msg' => __("account.account_updated_success")
                                ];
            } catch (\Exception $e) {
                \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
            
                $output = ['success' => false,
                            'msg' => __("messages.something_went_wrong")
                        ];
            }
            
            return $output;
        }
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroyAccountTransaction($id)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                $account_transaction = AccountTransaction::findOrFail($id);
                
                if (in_array($account_transaction->sub_type, ['fund_transfer', 'deposit'])) {
                    //Delete transfer transaction for fund transfer
                    if (!empty($account_transaction->transfer_transaction_id)) {
                        $transfer_transaction = AccountTransaction::findOrFail($account_transaction->transfer_transaction_id);
                        $transfer_transaction->delete();
                    }
                    $account_transaction->delete();
                }

                $output = ['success' => true,
                            'msg' => __("lang_v1.deleted_success")
                            ];
            } catch (\Exception $e) {
                \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
            
                $output = ['success' => false,
                            'msg' => __("messages.something_went_wrong")
                        ];
            }

            return $output;
        }
    }

    /**
     * Closes the specified account.
     * @return Response
     */
    public function close($id)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }
        
        if (request()->ajax()) {
            try {
                $business_id = session()->get('user.business_id');
            
                $account = Account::where('business_id', $business_id)
                                                    ->findOrFail($id);
                $account->is_closed = 1;
                $account->save();

                $output = ['success' => true,
                                    'msg' => __("account.account_closed_success")
                                    ];
            } catch (\Exception $e) {
                \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
            
                $output = ['success' => false,
                            'msg' => __("messages.something_went_wrong")
                        ];
            }
            
            return $output;
        }
    }

    /**
     * Shows form to transfer fund.
     * @param  int $id
     * @return Response
     */
    public function getFundTransfer($id)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }
        
        if (request()->ajax()) {
            $business_id = session()->get('user.business_id');
            
            $from_account = Account::where('business_id', $business_id)
                            ->NotClosed()
                            ->find($id);

            $to_accounts = Account::where('business_id', $business_id)
                            ->where('id', '!=', $id)
                            ->NotClosed()
                            ->pluck('name', 'id');

            return view('account.transfer')
                ->with(compact('from_account', 'to_accounts'));
        }
    }

    /**
     * Transfers fund from one account to another.
     * @return Response
     */
    public function postFundTransfer(Request $request)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }
        
        if (request()->ajax()) {
            try {
                $business_id = session()->get('user.business_id');

                $amount = $this->commonUtil->num_uf($request->input('amount'));
                $from = $request->input('from_account');
                $to = $request->input('to_account');
                $note = $request->input('note');
                if (!empty($amount)) {
                    $debit_data = [
                        'amount' => $amount,
                        'account_id' => $from,
                        'type' => 'debit',
                        'sub_type' => 'fund_transfer',
                        'created_by' => session()->get('user.id'),
                        'note' => $note,
                        'transfer_account_id' => $to,
                        'operation_date' => $this->commonUtil->uf_date($request->input('operation_date'), true),
                    ];

                    DB::beginTransaction();
                    $debit = AccountTransaction::createAccountTransaction($debit_data);

                    $credit_data = [
                            'amount' => $amount,
                            'account_id' => $to,
                            'type' => 'credit',
                            'sub_type' => 'fund_transfer',
                            'created_by' => session()->get('user.id'),
                            'note' => $note,
                            'transfer_account_id' => $from,
                            'transfer_transaction_id' => $debit->id,
                            'operation_date' => $this->commonUtil->uf_date($request->input('operation_date'), true),
                        ];

                    $credit = AccountTransaction::createAccountTransaction($credit_data);

                    $debit->transfer_transaction_id = $credit->id;
                    $debit->save();
                    DB::commit();
                }
                
                $output = ['success' => true,
                                    'msg' => __("account.fund_transfered_success")
                                    ];
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
            
                $output = ['success' => false,
                            'msg' => __("messages.something_went_wrong")
                        ];
            }

            return $output;
        }
    }

    /**
     * Shows deposit form.
     * @param  int $id
     * @return Response
     */
    public function getDeposit($id)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }
        
        if (request()->ajax()) {
            $business_id = session()->get('user.business_id');
            
            $account = Account::where('business_id', $business_id)
                            ->NotClosed()
                            ->find($id);

            $from_accounts = Account::where('business_id', $business_id)
                            ->where('id', '!=', $id)
                            // ->where('account_type', 'capital')
                            ->NotClosed()
                            ->pluck('name', 'id');

            return view('account.deposit')
                ->with(compact('account', 'account', 'from_accounts'));
        }
    }

    /**
     * Deposits amount.
     * @param  Request $request
     * @return json
     */
    public function postDeposit(Request $request)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = session()->get('user.business_id');

            $amount = $this->commonUtil->num_uf($request->input('amount'));
            $account_id = $request->input('account_id');
            $note = $request->input('note');

            $account = Account::where('business_id', $business_id)
                            ->findOrFail($account_id);

            if (!empty($amount)) {
                $credit_data = [
                    'amount' => $amount,
                    'account_id' => $account_id,
                    'type' => 'credit',
                    'sub_type' => 'deposit',
                    'operation_date' => $this->commonUtil->uf_date($request->input('operation_date'), true),
                    'created_by' => session()->get('user.id'),
                    'note' => $note
                ];
                $credit = AccountTransaction::createAccountTransaction($credit_data);

                $from_account = $request->input('from_account');
                if (!empty($from_account)) {
                    $debit_data = $credit_data;
                    $debit_data['type'] = 'debit';
                    $debit_data['account_id'] = $from_account;
                    $debit_data['transfer_transaction_id'] = $credit->id;

                    $debit = AccountTransaction::createAccountTransaction($debit_data);

                    $credit->transfer_transaction_id = $debit->id;

                    $credit->save();
                }
            }
            
            $output = ['success' => true,
                                'msg' => __("account.deposited_successfully")
                                ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
        
            $output = ['success' => false,
                        'msg' => __("messages.something_went_wrong")
                    ];
        }

        return $output;
    }

    /**
     * Calculates account current balance.
     * @param  int $id
     * @return json
     */
    public function getAccountBalance($id)
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = session()->get('user.business_id');
        $account = Account::leftjoin(
            'account_transactions as AT',
            'AT.account_id',
            '=',
            'accounts.id'
        )
            ->whereNull('AT.deleted_at')
            ->where('accounts.business_id', $business_id)
            ->where('accounts.id', $id)
            ->select('accounts.*', DB::raw("SUM( IF(AT.type='credit', amount, -1 * amount) ) as balance"))
            ->first();

        return $account;
    }

    /**
     * Show the specified resource.
     * @return Response
     */
    public function cashFlow()
    {
        if (!auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $accounts = AccountTransaction::join(
                'accounts as A',
                'account_transactions.account_id',
                '=',
                'A.id'
                )
                ->leftjoin(
                    'transaction_payments as TP',
                    'account_transactions.transaction_payment_id',
                    '=',
                    'TP.id'
                )
                ->where('A.business_id', $business_id)
                ->with(['transaction', 'transaction.contact', 'transfer_transaction'])
                ->select(['type', 'account_transactions.amount', 'operation_date',
                    'sub_type', 'transfer_transaction_id',
                    DB::raw("(SELECT SUM(IF(AT.type='credit', AT.amount, -1 * AT.amount)) from account_transactions as AT JOIN accounts as ac ON ac.id=AT.account_id WHERE ac.business_id= $business_id AND AT.operation_date <= account_transactions.operation_date AND AT.deleted_at IS NULL) as balance"),
                    'account_transactions.transaction_id',
                    'account_transactions.id',
                    'A.name as account_name',
                    'TP.payment_ref_no as payment_ref_no'
                    ])
                 ->groupBy('account_transactions.id')
                 ->orderBy('account_transactions.operation_date', 'desc');
            if (!empty(request()->input('type'))) {
                $accounts->where('type', request()->input('type'));
            }

            if (!empty(request()->input('account_id'))) {
                $accounts->where('A.id', request()->input('account_id'));
            }

            $start_date = request()->input('start_date');
            $end_date = request()->input('end_date');
            
            if (!empty($start_date) && !empty($end_date)) {
                $accounts->whereBetween(DB::raw('date(operation_date)'), [$start_date, $end_date]);
            }

            return DataTables::of($accounts)
                ->addColumn('debit', function ($row) {
                    if ($row->type == 'debit') {
                        return '<span class="display_currency" data-currency_symbol="true">' . $row->amount . '</span>';
                    }
                    return '';
                })
                ->addColumn('credit', function ($row) {
                    if ($row->type == 'credit') {
                        return '<span class="display_currency" data-currency_symbol="true">' . $row->amount . '</span>';
                    }
                    return '';
                })
                ->editColumn('balance', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true">' . $row->balance . '</span>';
                })
                ->editColumn('operation_date', function ($row) {
                    return $this->commonUtil->format_date($row->operation_date, true);
                })
                ->editColumn('sub_type', function ($row) {
                    $details = '';
                    if (!empty($row->sub_type)) {
                        $details = __('account.' . $row->sub_type);
                        if (in_array($row->sub_type, ['fund_transfer', 'deposit']) && !empty($row->transfer_transaction)) {
                            if ($row->type == 'credit') {
                                $details .= ' ( ' . __('account.from') .': ' . $row->transfer_transaction->account->name . ')';
                            } else {
                                $details .= ' ( ' . __('account.to') .': ' . $row->transfer_transaction->account->name . ')';
                            }
                        }
                    } else {
                        if (!empty($row->transaction->type)) {
                            if ($row->transaction->type == 'purchase') {
                                $details = '<b>' . __('purchase.supplier') . ':</b> ' . $row->transaction->contact->name . '<br><b>'.
                                __('purchase.ref_no') . ':</b> ' . $row->transaction->ref_no;
                            } elseif ($row->transaction->type == 'sell') {
                                $details = '<b>' . __('contact.customer') . ':</b> ' . $row->transaction->contact->name . '<br><b>'.
                                __('sale.invoice_no') . ':</b> ' . $row->transaction->invoice_no;
                            }
                        }
                    }

                    if (!empty($row->payment_ref_no)) {
                        if (!empty($details)) {
                            $details .= '<br/>';
                        }

                        $details .= '<b>' . __('lang_v1.pay_reference_no') . ':</b> ' . $row->payment_ref_no;
                    }

                    return $details;
                })
                ->removeColumn('id')
                ->rawColumns(['credit', 'debit', 'balance', 'sub_type'])
                ->make(true);
        }
        $accounts = Account::forDropdown($business_id, false);

        $accounts->prepend(__('messages.all'), '');
                            
        return view('account.cash_flow')
                 ->with(compact('accounts'));
    }
}