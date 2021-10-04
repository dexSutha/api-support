<?php

namespace App\Http\Controllers\Payment;

use App\Models\CareerSupportModelsNormalStudentParticipants;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\CareerSupportModelsOrdersWebinar;
use App\Models\CareerSupportModelsWebinarBiasa;
use App\Models\NotificationWebinarModel;
use App\Models\StudentModel;
use App\Models\UserPersonal;
use App\Traits\MicroBridge;
use CareerSupportModelsOrders as GlobalCareerSupportModelsOrders;
use Illuminate\Support\Facades\DB;
use Veritrans_Config;
use Veritrans_Snap;
use App\Traits\ResponseHelper;
use Carbon\Carbon;
use Exception;
use Veritrans_Notification;

class WebinarPaymentController extends Controller
{
    use ResponseHelper;

    private $tbOrder;
    private $tbStudent;
    private $tbWebinar;
    private $tbNotif;
    private $tbParticipant;
    private $tbUser;

    public function __construct()
    {
        Veritrans_Config::$serverKey = '';
        Veritrans_Config::$isProduction = false; //false -> sandbox, true -> production
        Veritrans_Config::$isSanitized = true;
        Veritrans_Config::$is3ds = true;

        $this->tbOrder = CareerSupportModelsOrdersWebinar::tableName();
        $this->tbStudent = StudentModel::tableName();
        $this->tbWebinar = CareerSupportModelsWebinarBiasa::tableName();
        $this->tbNotif = NotificationWebinarModel::tableName();
        $this->tbParticipant = CareerSupportModelsNormalStudentParticipants::tableName();
        $this->tbUser = UserPersonal::tableName();
    }

    //function for handle transaction checkout
    public function charge(Request $request)
    {
        //param -> order_id
        $validation = Validator::make($request->all(), [
            'order_id' => 'required|numeric|exists:' . $this->tbOrder . ',id'
        ]);

        if ($validation->fails()) {
            return response(["message" => $validation->errors()->first()], 400);
        } else {
            $status = DB::transaction(function () use ($request) {
                //get the detail of order & webinar by order_id
                $orderWebinar = DB::table($this->tbOrder, 'order')
                    ->leftJoin($this->tbWebinar . ' as webinar', 'order.webinar_id', '=', 'webinar.id')
                    ->leftJoin($this->tbParticipant . ' as participant', 'participant.id', '=', 'order.participant_id')
                    ->where('order.id', '=', $request->order_id)
                    ->get();

                $token = "";

                //check the status of order
                if ($orderWebinar[0]->status == "order" || $orderWebinar[0]->status == "expire") {
                    $student = DB::connection('pgsql2')
                        ->table($this->tbStudent, 'student')
                        ->leftJoin($this->tbUser . ' as user', 'student.user_id', '=', 'user.id')
                        ->where('student.id', '=', $orderWebinar[0]->student_id)
                        ->get();

                    //generate order_id
                    $order_id = "WB007" . $student[0]->id . $request->order_id;

                    //initialization the detail of transaction_detail
                    $transaction_details = array(
                        'order_id' => $order_id,
                        'gross_amount' => $orderWebinar[0]->price,
                    );

                    $item_details = array([
                        'id' => $request->order_id,
                        'price' => $orderWebinar[0]->price,
                        'name' => $orderWebinar[0]->event_name,
                        'quantity' => 1
                    ]);

                    $customer_detail = array(
                        'first_name'    => $student[0]->first_name,
                        'last_name'     => $student[0]->last_name,
                        'email'         => $student[0]->email,
                        'phone'         => $student[0]->phone
                    );

                    $params = array(
                        'transaction_details' => $transaction_details,
                        'item_details' => $item_details,
                        'customer_details' => $customer_detail
                    );

                    //send the tracsaction_details to midtrans and get the midtrans token
                    
                    $token = MicroBridge::gateway()->payment->Create($params);
                    // $token = Veritrans_Snap::getSnapToken($params);

                    //update the token, order_id, modified from order
                    DB::table($this->tbOrder)
                        ->where('id', $request->order_id)
                        ->update([
                            'token' => $token,
                            'order_id' => $order_id,
                            'modified' => Carbon::now()
                        ]);
                } else {
                    $token = $orderWebinar[0]->token;
                }

                return $token;
            });

            return $status ? $this->makeJSONResponse(['token' => $status], 201) : $this->makeJSONResponse(['message' => 'failed'], 400);
        }
    }

    //function for change the order status and triggered by midtrans
    public function updateStatus()
    {
        try {
            //get midtrans notification data
            $notif = new Veritrans_Notification();
            $transaction = $notif->transaction_status;
            $fraud = $notif->fraud_status;
            $order_id = $notif->order_id;
            $status = "failure";

            //get the detail of order & webinar by order_id
            $orderDetail = DB::table($this->tbOrder, 'order')
                ->leftJoin($this->tbParticipant . ' as participant', 'participant.id', '=', 'order.participant_id')
                ->leftJoin($this->tbWebinar . ' as webinar', 'participant.webinar_id', '=', 'webinar.id')
                ->where('order.order_id', '=', $order_id)
                ->get();

            $message_id = "Transaksi pembayaran anda untuk webinar " . $orderDetail[0]->event_name . " gagal";
            $message_en = "Your payment transaction for the " . $orderDetail[0]->event_name . " webinar failed";

            //handle the midtrans status changes
            if ($fraud == "accept") {
                switch ($transaction) {
                    case "capture":
                        $message_en = "Your payment transaction for the " . $orderDetail[0]->event_name . " webinar has been paid off and we have received it";
                        $message_id = "Transaksi pembayaran anda untuk webinar " . $orderDetail[0]->event_name . " telah lunas dan telah kami terima";
                        $status = "success";
                        break;
                    case "pending":
                        $message_en = "Please complete your payment for the " . $orderDetail[0]->event_name . " webinar";
                        $message_id = "Tolong selesaikan pembayaran anda untuk webinar " . $orderDetail[0]->event_name;
                        $status = "pending";
                        break;
                    case "expire":
                        $message_en = "Your payment transaction for the " . $orderDetail[0]->event_name . " webinar has been expired";
                        $message_id = "Transaksi pembayaran anda untuk webinar " . $orderDetail[0]->event_name . " telah kadarluarsa";
                        $status = "expire";
                        break;
                    case "settlement":
                        $message_en = "Your payment transaction for the " . $orderDetail[0]->event_name . " webinar has been paid off and we have received it";
                        $message_id = "Transaksi pembayaran anda untuk webinar " . $orderDetail[0]->event_name . " telah lunas dan telah kami terima";
                        $status = "success";
                        break;
                }
            }

            DB::transaction(function () use ($orderDetail, $message_id, $message_en, $status) {
                //send the transaction notification to participant
                DB::table($this->tbNotif)->insert(array(
                    'student_id'    => $orderDetail[0]->student_id,
                    'message_id'    => $message_id,
                    'message_en'    => $message_en,
                    'webinar_normal_id' => $orderDetail[0]->webinar_id
                ));

                //update the token, order_id, modified from order
                DB::table($this->tbOrder)
                    ->where('order_id', $orderDetail[0]->order_id)
                    ->update([
                        'status' => $status,
                        'modified' => Carbon::now()
                    ]);
            });
        } catch (\Throwable $th) {
            return response($th, 500);
        }
    }
}
