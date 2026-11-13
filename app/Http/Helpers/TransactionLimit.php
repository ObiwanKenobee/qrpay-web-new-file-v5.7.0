<?php

namespace App\Http\Helpers;

use App\Constants\PaymentGatewayConst;
use App\Models\Transaction;
use Carbon\Carbon;
use Exception;

class TransactionLimit{
    public function getExchangeRate($fromCurrency)
    {
        $base_currency_rate     = get_default_currency_rate();
        $from_currency_rate     = $fromCurrency->rate ?? 1;
        $exchange_rate          = $from_currency_rate / $base_currency_rate;

        return $exchange_rate;
    }
    function trxLimit($user_field,$userId,$transactionType,$currency,$amount,$limits,$attribute,$json = null,$extra_rate = 1){

        $transactionDate = now();
        //  Get the limits for the specified transaction type
        if($transactionType == PaymentGatewayConst::TYPEMONEYOUT || $transactionType == PaymentGatewayConst::TYPEADDMONEY){
            $exchange_rate =  $limits->rate/$currency->rate;

            $dailyLimitBaseCurrency     = $limits->daily_limit / $exchange_rate ;  // in Base Currency
            $monthlyLimitBaseCurrency   = $limits->monthly_limit / $exchange_rate ; // in Base Currency

            //make convert to base curr
            $reverse_exchange_rate_base = ( get_default_currency_rate() / $currency->rate);

            $dailyLimitBaseCurrency     = ( $dailyLimitBaseCurrency *  $reverse_exchange_rate_base);   // in Selected Currency
            $monthlyLimitBaseCurrency   = ($monthlyLimitBaseCurrency *  $reverse_exchange_rate_base);  // in Selected Currency

            $dailyLimitSelectedCurrency     = ( $limits->daily_limit / $exchange_rate);  // in Selected Currency
            $monthlyLimitSelectedCurrency   = ($limits->monthly_limit / $exchange_rate); // in Selected Currency


        }elseif($transactionType == PaymentGatewayConst::BILLPAY || $transactionType == PaymentGatewayConst::MOBILETOPUP || $transactionType == PaymentGatewayConst::VIRTUALCARD){
            if (!is_numeric($extra_rate) || is_nan((float) $extra_rate)) {
                $extra_rate = 1;
            }
            $dailyLimitBaseCurrency     = $limits->daily_limit / $extra_rate;  // in Base Currency
            $monthlyLimitBaseCurrency   = $limits->monthly_limit / $extra_rate; // in Base Currency


            $dailyLimitSelectedCurrency     = ( $dailyLimitBaseCurrency * $currency->rate);  // in Selected Currency
            $monthlyLimitSelectedCurrency   = ($monthlyLimitBaseCurrency * $currency->rate); // in Selected Currency
        }else{
            $dailyLimitBaseCurrency     = $limits->daily_limit;  // in Base Currency
            $monthlyLimitBaseCurrency   = $limits->monthly_limit ; // in Base Currency

            $dailyLimitSelectedCurrency     = ( $dailyLimitBaseCurrency * $currency->rate);  // in Selected Currency
            $monthlyLimitSelectedCurrency   = ($monthlyLimitBaseCurrency * $currency->rate); // in Selected Currency
        }


        $exchangeRate = $this->getExchangeRate($currency); // This function should return the conversion rate for currency to Base Currency

        if (!$exchangeRate) {
            throw new Exception(json_encode([
                'status' => false,
                'message' => 'Exchange rate not found',
                'user_field' => $user_field,
                'user_id' => $userId,
                'transaction_type' => $transactionType
            ]));
        }


        // Convert the transaction amount to the base currency (Base Currency)
        $amountInBasedCurrency = $amount / $exchangeRate;


        $dailyTotals = Transaction::where($user_field, $userId)
                            ->where('type', $transactionType)
                            ->where('attribute',$attribute)
                            ->where('status',PaymentGatewayConst::STATUSSUCCESS)
                            ->whereDate('created_at', Carbon::parse($transactionDate)->toDateString())
                            ->get();


        $monthlyTotals= Transaction::where($user_field, $userId)
                            ->where('type', $transactionType)
                            ->where('attribute',$attribute)
                            ->where('status',PaymentGatewayConst::STATUSSUCCESS)
                            ->whereYear('created_at', Carbon::parse($transactionDate)->year)
                            ->whereMonth('created_at', Carbon::parse($transactionDate)->month)
                            ->get();

        // Calculate the daily & monthly total for the transaction type in the base currency (Base Currency)
        $dailyTotalInBasedCurrency      = get_amount($this->getTransactionOnBaseCurrency($dailyTotals),null,get_wallet_precision($currency));
        $monthlyTotalInBasedCurrency    = get_amount($this->getTransactionOnBaseCurrency($monthlyTotals),null,get_wallet_precision($currency));

         // Calculate the daily & monthly total for the transaction type in the selected currency (Selected Currency)
        $totalInCurrencyDaily = get_amount(($dailyTotalInBasedCurrency * $currency->rate), null, get_wallet_precision($currency));
        $totalInCurrencyMonthly = get_amount(($monthlyTotalInBasedCurrency * $currency->rate), null, get_wallet_precision($currency));

         // Calculate the remaining  daily & monthly total for the transaction type in the selected currency (Selected Currency)
        $totalRemainingDaily = get_amount(($dailyLimitSelectedCurrency - $totalInCurrencyDaily), null, get_wallet_precision($currency));
        $totalRemainingMonthly = get_amount(($monthlyLimitSelectedCurrency - $totalInCurrencyMonthly), null, get_wallet_precision($currency));

        $totalRemainingDaily  = $totalRemainingDaily <= 0 ? 0 :$totalRemainingDaily;
        $totalRemainingMonthly  = $totalRemainingMonthly <= 0 ? 0 :$totalRemainingMonthly;

        $data =[
            'totalDailyTxnBase'             => $dailyTotalInBasedCurrency ?? 0,
            'totalMonthlyTxnBase'           => $monthlyTotalInBasedCurrency ?? 0,

            'totalDailyTxnSelected'         => $totalInCurrencyDaily ?? 0,
            'totalMonthlyTxnSelected'       => $totalInCurrencyMonthly ?? 0,

            'remainingDailyTxnSelected'     => $totalRemainingDaily ?? 0,
            'remainingMonthlyTxnSelected'   => $totalRemainingMonthly ?? 0,

            // limit totals in the selected currency (0 = unlimited), used by the limit meter UI
            'dailyLimitSelected'            => get_amount($dailyLimitSelectedCurrency ?? 0, null, get_wallet_precision($currency)),
            'monthlyLimitSelected'          => get_amount($monthlyLimitSelectedCurrency ?? 0, null, get_wallet_precision($currency)),
            'currency'                      => $currency->code ?? null,
            'precision'                     => get_wallet_precision($currency),


        ];

        // Validate daily and monthly limits
        if ($dailyLimitBaseCurrency > 0 && ($dailyTotalInBasedCurrency + $amountInBasedCurrency) > $dailyLimitBaseCurrency) {

            if($json != null){
                return[
                    'status'            => false,
                    'message'           => __('Daily transaction limit exceeded.'),
                    'user_field'        => $user_field,
                    'user_id'           => $userId,
                    'transaction_type'  => $transactionType,
                    'data'              => $data,
                ];

            }else{

                throw new Exception(json_encode([
                    'status'            => false,
                    'message'           => __('Daily transaction limit exceeded.'),
                    'user_field'        => $user_field,
                    'user_id'           => $userId,
                    'transaction_type'  => $transactionType,
                    'data'              =>  $data,
                ]));
            }

        }

        if ($monthlyLimitBaseCurrency > 0 && ($monthlyTotalInBasedCurrency + $amountInBasedCurrency) > $monthlyLimitBaseCurrency) {
            if($json != null){
                return[
                    'status'            => false,
                    'message'           => __('Monthly transaction limit exceeded.'),
                    'user_field'        => $user_field,
                    'user_id'           => $userId,
                    'transaction_type'  => $transactionType,
                    'data'              => $data,
                ];

            }else{
                throw new Exception(json_encode([
                    'status'                => false,
                    'message'               => __('Monthly transaction limit exceeded.'),
                    'user_field'            => $user_field,
                    'user_id'               => $userId,
                    'transaction_type'      => $transactionType,
                    'data'                  => $data,
                ]));
            }

        }


        return [
            'status'                => true,
            'message'               => __('Your current total transaction amount'),
            'user_field'            => $user_field,
            'user_id'               => $userId,
            'transaction_type'      => $transactionType,
            'data'                  => $data,

        ];
    }

    public function getTransactionOnBaseCurrency($transactions){
        $totalAmount = 0;
        foreach ($transactions as $transaction) {
            $requestAmount = $transaction->request_amount;
            if($transaction->type == PaymentGatewayConst::VIRTUALCARD){
                $exchange_rate = $transaction->details->charges->card_currency_rate??get_default_currency_rate();
            }else{
                $exchange_rate = $transaction->creator_wallet->currency->rate??get_default_currency_rate();
            }
            $result = $requestAmount / $exchange_rate;
            $totalAmount += $result;
        }
        return $totalAmount??0 ;

    }
}
