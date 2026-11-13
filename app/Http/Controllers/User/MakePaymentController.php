<?php

namespace App\Http\Controllers\User;

use App\Constants\EmailTemplateConst;
use App\Constants\NotificationConst;
use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\NotificationHelper;
use App\Models\Admin\BasicSettings;
use App\Models\Admin\Currency;
use App\Models\Admin\TransactionSetting;
use App\Models\Merchants\Merchant;
use App\Models\Merchants\MerchantNotification;
use App\Models\Merchants\MerchantWallet;
use App\Models\Transaction;
use App\Models\UserNotification;
use App\Models\UserWallet;
use App\Notifications\User\MakePayment\ReceiverMail;
use App\Notifications\User\MakePayment\SenderMail;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\PushNotificationHelper;
use App\Http\Helpers\TransactionLimit;
use App\Notifications\Admin\ActivityNotification;
use App\Providers\Admin\BasicSettingsProvider;
use Illuminate\Support\Facades\Validator;

class MakePaymentController extends Controller
{
    protected  $trx_id;
    protected $basic_settings;
    public function __construct()
    {
        $this->trx_id = 'MP'.getTrxNum();
        $this->basic_settings = BasicSettingsProvider::get();
    }
    public function index() {
        $page_title = __("Make Payment");
        $makePaymentCharge = TransactionSetting::where('slug','make-payment')->where('status',1)->first();
        $transactions = Transaction::auth()->makePayment()->latest()->take(10)->get();
        return view('user.sections.make-payment.index',compact("page_title",'makePaymentCharge','transactions'));
    }
    public function checkUser(Request $request){
        $credentials = $request->credentials;
        $exist['data'] = Merchant::where(function($query) use ($credentials) {
            $query->where('email', $credentials)
                  ->orWhere('mobile',(int)$credentials)
                  ->orWhere('full_mobile', $credentials);
        })->active()->first();

        $user = userGuard()['user'];
        if(@$exist['data'] && @$user->email == @$exist['data']->email || @$user->full_mobile == @$exist['data']->full_mobile){
            return response()->json(['own'=>__("Can't payment to your own")]);
        }
        return response($exist);
    }
    public function confirmed(Request $request){
        $request->validate([

        ]);
        $validated = Validator::make($request->all(),[
            'amount'        => 'required|numeric|gt:0',
            'credentials'   => 'required',
            'pin'           =>  $this->basic_settings->user_pin_verification == true ? 'required|digits:4' : 'nullable'
        ])->validate();

        $basic_setting = BasicSettings::first();
        $user = userGuard()['user'];
        //check user pin
        if( $this->basic_settings->user_pin_verification == true){
            $pin_status = pin_verification($user,$validated['pin']);
            if( $pin_status['status'] == false) return back()->with(['error' => [$pin_status['message']]]);
        }
        $credentials = $request->credentials;
        $amount = $request->amount;
        $makePaymentCharge = TransactionSetting::where('slug','make-payment')->where('status',1)->first();
        $userWallet = UserWallet::where('user_id',$user->id)->first();
        if(!$userWallet){
            return back()->with(['error' => [__('User wallet not found')]]);
        }
        $baseCurrency = Currency::default();
        if(!$baseCurrency){
            return back()->with(['error' => [__('Default currency not found')]]);
        }
        $rate = $baseCurrency->rate;
        $receiver = Merchant::where(function($query) use ($credentials) {
            $query->where('email', $credentials)
                  ->orWhere('mobile',(int)$credentials)
                  ->orWhere('full_mobile', $credentials);
        })->active()->first();
        if(!$receiver){
            return back()->with(['error' => [__('Receiver not exist')]]);
        }
        $receiverWallet = MerchantWallet::where('merchant_id',$receiver->id)->first();
        if(!$receiverWallet){
            return back()->with(['error' => [__('Receiver wallet not found')]]);
        }
        if( $user->email == $receiver->email || $user->full_mobile == $receiver->full_mobile ){
            return back()->with(['error' => [__("Can't transfer/request to your own")]]);
        }

        $minLimit =  $makePaymentCharge->min_limit *  $rate;
        $maxLimit =  $makePaymentCharge->max_limit *  $rate;
        if($amount < $minLimit || $amount > $maxLimit) {
            return back()->with(['error' => [__("Please follow the transaction limit")]]);
        }
        //daily and monthly
        try{
            (new TransactionLimit())->trxLimit('user_id',$userWallet->user->id,PaymentGatewayConst::TYPEMAKEPAYMENT,$userWallet->currency,$amount,$makePaymentCharge,PaymentGatewayConst::SEND);
        }catch(Exception $e){
           $errorData = json_decode($e->getMessage(), true);
            return back()->with(['error' => [__($errorData['message'] ?? __("Something went wrong! Please try again."))]]);
        }
        //charge calculations
        $fixedCharge = $makePaymentCharge->fixed_charge *  $rate;
        $percent_charge = ($request->amount / 100) * $makePaymentCharge->percent_charge;
        $total_charge = $fixedCharge + $percent_charge;
        $payable = $total_charge + $amount;
        $recipient = $amount;
        if($payable > $userWallet->balance ){
            return back()->with(['error' => [__('Sorry, insufficient balance')]]);
        }

        try{
            $trx_id = $this->trx_id;
            $sender = $this->insertSender( $trx_id,$user,$userWallet,$amount,$recipient,$payable,$receiver);
            if($sender){
                 $this->insertSenderCharges( $fixedCharge,$percent_charge, $total_charge, $amount,$user,$sender,$receiver);
            }

            $receiverTrans = $this->insertReceiver( $trx_id,$user,$userWallet,$amount,$recipient,$payable,$receiver,$receiverWallet);
            if($receiverTrans){
                 $this->insertReceiverCharges( $fixedCharge,$percent_charge, $total_charge, $amount,$user,$receiverTrans,$receiver);
            }

            // setup sender sms
            if( $basic_setting->sms_notification == true){
                try{
                    sendSms($user,'MAKE_PAYMENT',[
                        'to_user'   => $receiver->fullname.'('.$receiver->email.')',
                        'amount'    => get_amount($amount,get_default_currency_code()),
                        'charge'    => get_amount( $total_charge,get_default_currency_code()),
                        'trx'       => $trx_id,
                        'time'      => now()->format('Y-m-d h:i:s A'),
                    ]);
                }catch(Exception $e){}
            }
            //Sender notifications email
            try{
                if($basic_setting->email_notification == true){
                    $notifyDataSender = [
                        'trx_id'            => $trx_id,
                        'title'             => __("Make Payment to")." @" . @$receiver->username." (".@$receiver->fullname.")",
                        'request_amount'    => getAmount($amount,4).' '.get_default_currency_code(),
                        'payable'           => getAmount($payable,4).' ' .get_default_currency_code(),
                        'charges'           => getAmount( $total_charge, 2).' ' .get_default_currency_code(),
                        'received_amount'   => getAmount( $recipient, 2).' ' .get_default_currency_code(),
                        'status'            => __("success"),
                    ];
                    $user->notify(new SenderMail($user,(object)$notifyDataSender));
                }
            }catch(Exception $e){}

            //setup receiver notifications email
            try{
                if( $basic_setting->merchant_email_notification == true){
                    //Receiver notifications
                    $notifyDataReceiver = [
                        'trx_id'            => $trx_id,
                        'title'             => __("Make Payment From")." @" .@$user->username." (".@$user->fullname.")",
                        'received_amount'   => getAmount( $recipient, 2).' ' .get_default_currency_code(),
                        'status'            => __("success"),
                    ];
                    //send notifications
                    $receiver->notify(new ReceiverMail($receiver,(object)$notifyDataReceiver));
                }
            }catch(Exception $e){}
            // setup receiver notifications sms
            if( $basic_setting->merchant_sms_notification == true){
                try{
                    sendSms($receiver,'MAKE_PAYMENT_MERCHANT',[
                        'from_user'     => $user->fullname.'('.$user->fullname.' )',
                        'amount'        => get_amount($amount,get_default_currency_code()),
                        'trx'           => $trx_id,
                        'time'          => now()->format('Y-m-d h:i:s A'),
                    ]);
                }catch(Exception $e){}
            }
            //admin notifications
            $this->adminNotification($trx_id,$total_charge,$amount,$payable,$user,$receiver);

            return redirect()->route("user.make.payment.index")->with(['success' => [__('Make Payment successful to').' '.$receiver->fullname]]);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }

    }

     //sender transaction
    public function insertSender($trx_id,$user,$userWallet,$amount,$recipient,$payable,$receiver) {
        $trx_id = $trx_id;
        $authWallet = $userWallet;
        $afterCharge = ($authWallet->balance - $payable);
        $details =[
            'recipient_amount' => $recipient,
            'receiver' => $receiver,
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $user->id,
                'user_wallet_id'                => $authWallet->id,
                'payment_gateway_currency_id'   => null,
                'type'                          => PaymentGatewayConst::TYPEMAKEPAYMENT,
                'trx_id'                        => $trx_id,
                'request_amount'                => $amount,
                'payable'                       => $payable,
                'available_balance'             => $afterCharge,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::TYPEMAKEPAYMENT," ")) . " To " .$receiver->fullname,
                'details'                       => json_encode($details),
                'attribute'                      =>PaymentGatewayConst::SEND,
                'status'                        => true,
                'created_at'                    => now(),
            ]);
            $this->updateSenderWalletBalance($authWallet,$afterCharge);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again."));
        }
        return $id;
    }
    public function updateSenderWalletBalance($authWallet,$afterCharge) {
        $authWallet->update([
            'balance'   => $afterCharge,
        ]);
    }
    public function insertSenderCharges($fixedCharge,$percent_charge, $total_charge, $amount,$user,$id,$receiver) {
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $percent_charge,
                'fixed_charge'      =>$fixedCharge,
                'total_charge'      =>$total_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         =>__("Make Payment"),
                'message'       => __("Payment To ")." ".$receiver->fullname.' ' .$amount.' '.get_default_currency_code()." ".__("Successful"),
                'image'         => get_image($user->image,'user-profile'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::MAKE_PAYMENT,
                'user_id'  => $user->id,
                'message'   => $notification_content,
            ]);
            //Push Notifications
            if( $this->basic_settings->push_notification == true){
                try{
                    (new PushNotificationHelper())->prepare([$user->id],[
                        'title' => $notification_content['title'],
                        'desc'  => $notification_content['message'],
                        'user_type' => 'user',
                    ])->send();
                }catch(Exception $e){}
            }

        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again."));
        }
    }
    //Receiver Transaction
    public function insertReceiver($trx_id,$user,$userWallet,$amount,$recipient,$payable,$receiver,$receiverWallet) {
        $trx_id = $trx_id;
        $receiverWallet = $receiverWallet;
        $recipient_amount = ($receiverWallet->balance + $recipient);
        $details =[
            'sender_amount' => $amount,
            'sender' => $user,
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'merchant_id'                       => $receiver->id,
                'merchant_wallet_id'                => $receiverWallet->id,
                'payment_gateway_currency_id'   => null,
                'type'                          => PaymentGatewayConst::TYPEMAKEPAYMENT,
                'trx_id'                        => $trx_id,
                'request_amount'                => $amount,
                'payable'                       => $payable,
                'available_balance'             => $recipient_amount,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::TYPEMAKEPAYMENT," ")) . " From " .$user->fullname,
                'details'                       => json_encode($details),
                'attribute'                      =>PaymentGatewayConst::RECEIVED,
                'status'                        => true,
                'created_at'                    => now(),
            ]);
            $this->updateReceiverWalletBalance($receiverWallet,$recipient_amount);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again."));
        }
        return $id;
    }
    public function updateReceiverWalletBalance($receiverWallet,$recipient_amount) {
        $receiverWallet->update([
            'balance'   => $recipient_amount,
        ]);
    }
    public function insertReceiverCharges($fixedCharge,$percent_charge, $total_charge, $amount,$user,$id,$receiver) {
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $percent_charge,
                'fixed_charge'      =>$fixedCharge,
                'total_charge'      =>0,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         =>__("Make Payment"),
                'message'       => __("Payment From")." ".$user->fullname.' ' .$amount.' '.get_default_currency_code()." ".__("Successful"),
                'image'         => get_image($receiver->image,'merchant-profile'),
            ];

            MerchantNotification::create([
                'type'      => NotificationConst::MAKE_PAYMENT,
                'merchant_id'  => $receiver->id,
                'message'   => $notification_content,
            ]);

            //Push Notifications
            if( $this->basic_settings->merchant_push_notification == true){
                try{
                    (new PushNotificationHelper())->prepare([$receiver->id],[
                        'title' => $notification_content['title'],
                        'desc'  => $notification_content['message'],
                        'user_type' => 'merchant',
                    ])->send();
                }catch(Exception $e){}
            }

        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again."));
        }
    }
    //admin notification
    public function adminNotification($trx_id,$total_charge,$amount,$payable,$user,$receiver){
        $notification_content = [
            //email notification
            'subject' => __("Make Payment to")." @" . @$receiver->username." (".@$receiver->email.")",
            'greeting' =>__("Make Payment Information"),
            'email_content' =>__("web_trx_id")." : ".$trx_id."<br>".__("sender").": @".$user->email."<br>".__("Receiver").": @".$receiver->email."<br>".__("Sender Amount")." : ".get_amount($amount,get_default_currency_code())."<br>".__("Fees & Charges")." : ".get_amount($total_charge,get_default_currency_code())."<br>".__("Total Payable Amount")." : ".get_amount($payable,get_default_currency_code())."<br>".__("Recipient Received")." : ".get_amount($amount,get_default_currency_code())."<br>".__("Status")." : ".__("success"),

            //push notification
            'push_title' => __("Make Payment")." (".userGuard()['type'].")",
            'push_content' => __('web_trx_id')." ".$trx_id." ".__("sender").": @".$user->email." ".__("Receiver").": @".$receiver->email." ".__("Sender Amount")." : ".get_amount($amount,get_default_currency_code())." ".__("Receiver Amount")." : ".get_amount($amount,get_default_currency_code()),

            //admin db notification
            'notification_type' =>  NotificationConst::MAKE_PAYMENT,
            'admin_db_title' => "Make Payment"." (".userGuard()['type'].")",
            'admin_db_message' =>"Sender".": @".$user->email.","."Receiver".": @".$receiver->email.","."Sender Amount"." : ".get_amount($amount,get_default_currency_code()).","."Receiver Amount"." : ".get_amount($amount,get_default_currency_code())
        ];

        try{
            //notification
            (new NotificationHelper())->admin(['admin.make.payment.index','admin.make.payment.export.data'])
                                    ->mail(ActivityNotification::class, [
                                        'subject'   => $notification_content['subject'],
                                        'greeting'  => $notification_content['greeting'],
                                        'content'   => $notification_content['email_content'],
                                        'template'  => EmailTemplateConst::MAKE_PAYMENT,
                                    ])
                                    ->push([
                                        'user_type' => "admin",
                                        'title' => $notification_content['push_title'],
                                        'desc'  => $notification_content['push_content'],
                                    ])
                                    ->adminDbContent([
                                        'type' => $notification_content['notification_type'],
                                        'title' => $notification_content['admin_db_title'],
                                        'message'  => $notification_content['admin_db_message'],
                                    ])
                                    ->send();


        }catch(Exception $e) {}

    }
}
