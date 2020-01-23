<?php

namespace App\Admin\Controllers;

use App\Order;
use App\Customer;
use App\DeliveryBoy;
use App\Address;
use App\Promo;
use App\Label;
use App\OrderHistory;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Facades\Admin;
use Kreait\Firebase;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use Kreait\Firebase\Database;
use Illuminate\Support\MessageBag;
class OrderController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Orders';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Order);

        $grid->column('id', __('Id'));
        $grid->column('order_id', __('Order id'));
        $grid->column('customer_id', __('Customer id'))->display(function($customer_id){
            return Customer::where('id',$customer_id)->value('customer_name');
        });
        $grid->column('expected_delivery_date', __('Expected delivery date'))->display(function($expected_delivery_date){
            return date('d M-Y',strtotime($expected_delivery_date));
        });
        $grid->column('delivered_by', __('Delivered by'))->display(function($delivered_by){
            if($delivered_by){
                return DeliveryBoy::where('id',$delivered_by)->value('delivery_boy_name');
            }else{
                return '---';
            }
            
        });
        $grid->column('status', __('Status'))->display(function($status){
            $label_name = Label::where('id',$status)->value('label_name');
            if ($status == 7) {
                return "<span class='label label-success'>$label_name</span>";
            } else {
                return "<span class='label label-warning'>$label_name</span>";
            }
        });
        $grid->column('View Orders')->display(function () {
            return "<a href='/admin/view_orders/".$this->id."'><span class='label label-info'>View Orders</span></a>";
        });
        $grid->disableExport();
        $grid->disableCreateButton();
        $grid->actions(function ($actions) {
            $actions->disableView();
            $actions->disableDelete();
        });
        $grid->filter(function ($filter) {
            //Get All status
            $labels = Label::pluck('label_name', 'id');
            $customers = Customer::pluck('customer_name', 'id');
            $delivery_boys = DeliveryBoy::pluck('delivery_boy_name', 'id');
            $filter->equal('customer_id', 'Customer')->select($customers);
            $filter->equal('collected_by', 'Collected By')->select($delivery_boys);
            $filter->equal('delivered_by', 'Delivered By')->select($delivery_boys);
            $filter->equal('status', 'Status')->select($labels);
            $filter->date('expected_delivery_date', 'Expected Delivery Date');
        });
        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Order::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('order_id', __('Order id'));
        $show->field('customer_id', __('Customer id'));
        $show->field('address_id', __('Address id'));
        $show->field('expected_delivery_date', __('Expected delivery date'));
        $show->field('actual_delivery_date', __('Actual delivery date'));
        $show->field('total', __('Total'));
        $show->field('discount', __('Discount'));
        $show->field('sub_total', __('Sub total'));
        $show->field('promo_id', __('Promo id'));
        $show->field('collected_by', __('Collected by'));
        $show->field('delivered_by', __('Delivered by'));
        $show->field('payment_mode', __('Payment mode'));
        $show->field('items', __('Items'));
        $show->field('status', __('Status'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Order);
        $statuses = Label::pluck('label_name', 'id');
        $delivery_boys = DeliveryBoy::where('status',1)->pluck('delivery_boy_name', 'id');
        $form->text('order_id', __('Order id'))->readonly();
        $form->select('delivered_by', __('Delivered by'))->options($delivery_boys)->rules(function ($form) {
            return 'required';
        });
        $form->select('status', __('Status'))->options($statuses)->default(1)->rules(function ($form) {
            return 'required';
        });
        $form->saving(function (Form $form) {
           if($form->delivered_by > 0 && $form->status == 1){
                $error = new MessageBag([
                    'title'   => 'Warning',
                    'message' => 'Please change order status...',
                ]);

                return back()->with(compact('error'));
           }
        });
        $form->saved(function (Form $form) {
            $this->update_history($form->model()->id);
        });
        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete(); 
            $tools->disableView();
        });
        $form->footer(function ($footer) {
            $footer->disableViewCheck();
            $footer->disableEditingCheck();
            $footer->disableCreatingCheck();
        });
        return $form;
    }

    public function update_history($id){
        $order_count = OrderHistory::where('order_id',$id)->count();
        $new_order = Order::where('id',$id)->first();
        if($order_count == 0){
            OrderHistory::create([ 'order_id'=>$id, 'delivery_boy_id'=>$new_order->delivered_by,'status'=>$new_order->status ]);
            if($new_order->delivered_by){
                $this->update_firebase(0,$new_order->delivered_by,$id);
            }
            
        }else{
            $last_order_history = OrderHistory::where('order_id',$id)->orderBy('id', 'DESC')->first();
            OrderHistory::create([ 'order_id'=>$id, 'delivery_boy_id'=>$new_order->delivered_by,'status'=>$new_order->status ]);
            if($last_order_history->delivery_boy_id == null && $new_order->delivered_by > 0){
                $this->update_firebase(0,$new_order->delivered_by,$id);
            }else if($last_order_history->delivery_boy_id > 0 && $new_order->delivered_by > 0){
                $this->update_firebase($last_order_history->delivery_boy_id,$new_order->delivered_by,$id);
            }else if($last_order_history->delivery_boy_id > 0 && $new_order->delivered_by == null){
                $this->update_firebase($last_order_history->delivery_boy_id,0,$id);
            }
        }
    }
    public function update_firebase($old_del_boy,$new_del_boy,$id){
        $order = Order::where('id',$id)->first();
        $address = Address::where('id',$order->address_id)->first();
        $customer = Customer::where('id',$order->customer_id)->first();
        $data = array();
        $serviceAccount = ServiceAccount::fromJsonFile(config_path().'/'.env('FIREBASE_FILE'));
            $firebase = (new Factory)
            ->withServiceAccount($serviceAccount)
            ->withDatabaseUri(env('FIREBASE_DB'))
            ->create();
        $database = $firebase->getDatabase();

        if($new_del_boy > 0){

            $data['cus_address'] = $address->address;
            $data['cus_address_lat'] = $address->latitude;
            $data['cus_address_lng'] = $address->longitude;
            $data['cus_name'] = $customer->customer_name;
            $data['cus_phone'] = $customer->phone_number;
            $data['discount'] = $order->discount;
            $data['ex_del_date'] = date('d M-Y', strtotime($order->expected_delivery_date));
            $data['id'] = $order->id;
            $data['items'] = json_decode($order->items, true);
            $data['order_id'] = $order->order_id;
            if($order->payment_mode == 1){
                $data['payment_mode'] = 'Cash';
            }
            $data['status'] = $order->status;
            $data['status_name'] = Label::where('id',$order->status)->value('label_name');
            $data['sub_total'] = $order->sub_total;
            $data['total'] = $order->total;
            if($order->status != 7){
                $new_label = Label::where('id',$order->status+1)->first();
                $data['new_status'] = $new_label->id;
                $data['new_status_name'] = $new_label->label_for_delivery_boy;
            }
            $newPost = $database
            ->getReference('delivery_partners/'.$new_del_boy.'/orders/'.$id)
            ->update($data);
        }

        if($old_del_boy > 0 && $old_del_boy != $new_del_boy){
            $database->getReference('delivery_partners/'.$old_del_boy.'/orders/'.$id)->remove();
        }
        
    }
} 
