<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Order;
use App\OrderItem;
use App\Label;
use Validator;
use Kreait\Firebase;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use Kreait\Firebase\Database;
class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'customer_id' => 'required',
            'address_id' => 'required',
            'expected_delivery_date' => 'required',
            'total' => 'required',
            'discount' => 'required',
            'sub_total' => 'required',
            'promo_id' => 'required',
            'payment_mode' => 'required',
            'items' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }
        $items = json_decode($input['items'], true);
        $date = explode('/',$input['expected_delivery_date']);
        $input['expected_delivery_date'] = $date[2].'-'.$date[1].'-'.$date[0];
        $input['expected_delivery_date'] = date('Y-m-d', strtotime($input['expected_delivery_date']));
        $order = Order::create($input);
        $order_id = str_pad($order->id, 5, "0", STR_PAD_LEFT);
        Order::where('id',$order->id)->update([ 'order_id'=>$order_id]);
        if (is_object($order)) {
            foreach ($items as $key => $value) {
                $value['order_id'] = $order->id;
                OrderItem::create($value);
            }
            return response()->json([
                "message" => 'Order Placed Successfully',
                "status" => 1
            ]);
        } else {
            return response()->json([
                "message" => 'Sorry, something went wrong !',
                "status" => 0
            ]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function getOrders(Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'customer_id' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }
        $orders = DB::table('orders')
            ->join('addresses', 'addresses.id', '=', 'orders.address_id')
            ->join('labels', 'labels.id', '=', 'orders.status')
            ->select('orders.id','orders.order_id','addresses.address','orders.expected_delivery_date','orders.total','orders.discount','orders.sub_total','orders.status','orders.items','labels.label_name','orders.created_at','orders.updated_at')
            ->where('orders.customer_id',$input['customer_id'])
            ->orderBy('orders.created_at', 'desc')
            ->get();
        if ($orders) {
            return response()->json([
                "result" => $orders,
                "count" => count($orders),
                "message" => 'Success',
                "status" => 1
            ]);
        } else {
            return response()->json([
                "message" => 'Sorry, something went wrong !',
                "status" => 0
            ]);
        }
    }

    public function sendError($message) {
        $message = $message->all();
        $response['error'] = "validation_error";
        $response['message'] = implode('',$message);
        $response['status'] = "0";
        return response()->json($response, 200);
    }

    public function order_status_change(Request $request){
        $input = $request->all();
        $validator = Validator::make($input, [
            'order_id' => 'required',
            'status' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $order = Order::where('id',$input['order_id'])->first();
        if(is_object($order)){
            $old_label = Label::where('id',$input['status'])->first();
            Order::where('id',$input['order_id'])->update([ 'status' => $old_label->id ]);
            $serviceAccount = ServiceAccount::fromJsonFile(config_path().'/'.env('FIREBASE_FILE'));
            $firebase = (new Factory)
            ->withServiceAccount($serviceAccount)
            ->withDatabaseUri(env('FIREBASE_DB'))
            ->create();
            $database = $firebase->getDatabase();
            $database->getReference('delivery_partners/'.$order->delivered_by.'/orders/'.$order->id.'/status')
                ->set($old_label->id);
            $database->getReference('delivery_partners/'.$order->delivered_by.'/orders/'.$order->id.'/status_name')
                ->set($old_label->label_name);
            if($input['status'] != 7){
                $new_label = Label::where('id',$input['status']+1)->first();
                $database->getReference('delivery_partners/'.$order->delivered_by.'/orders/'.$order->id.'/new_status')
                ->set($new_label->id);
                $database->getReference('delivery_partners/'.$order->delivered_by.'/orders/'.$order->id.'/new_status_name')
                ->set($new_label->label_for_delivery_boy);
            }
            $response['message'] = "Success";
            $response['status'] = 1;
            return response()->json($response, 200);
        }else{
            $response['message'] = "Invalid order id";
            $response['status'] = 0;
            return response()->json($response, 200);
        }



    }
}
