<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Customer;
use Validator;
use Illuminate\Support\Facades\Hash;
class CustomerController extends Controller
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
            'customer_name' => 'required',
            'phone_number' => 'required|numeric|digits_between:9,20|unique:customers,phone_number',
            'email' => 'required|email|regex:/^[a-zA-Z]{1}/|unique:customers,email',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $options = [
            'cost' => 12,
        ];
        $input['password'] = password_hash($input["password"], PASSWORD_DEFAULT, $options);
        $input['status'] = 1;

        $customer = Customer::create($input);

        if (is_object($customer)) {
            return response()->json([
                "result" => $customer,
                "message" => 'Registered Successfully',
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
        $input['id'] = $id;
        $validator = Validator::make($input, [
            'id' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $result = Customer::select('id', 'customer_name','phone_number','email','profile_picture','status')->where('id',$id)->first();

        if (is_object($result)) {
            return response()->json([
                "result" => $result,
                "message" => 'Success',
                "status" => 1
            ]);
        } else {
            return response()->json([
                "message" => 'Sorry, something went wrong...',
                "status" => 0
            ]);
        }
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
        $input = $request->all();
        $validator = Validator::make($input, [
            'customer_name' => 'required',
            'phone_number' => 'required|numeric|unique:customers,id,'.$id,
            'email' => 'required|email|unique:customers,id,'.$id
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }
        if($request->password){
            $options = [
                'cost' => 12,
            ];
            $input['password'] = password_hash($input["password"], PASSWORD_DEFAULT, $options);
            $input['status'] = 1;
        }else{
            unset($input['password']);
        }

        if (Customer::where('id',$id)->update($input)) {
            return response()->json([
                "result" => Customer::select('id', 'customer_name','phone_number','email','profile_picture','status')->where('id',$id)->first(),
                "message" => 'Success',
                "status" => 1
            ]);
        } else {
            return response()->json([
                "message" => 'Sorry, something went wrong...',
                "status" => 0
            ]);
        }

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

    public function login(Request $request){

        $input = $request->all();
        $validator = Validator::make($input, [
            'email' => 'required',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $credentials = request(['email', 'password']);
        $customer = Customer::where('email',$credentials['email'])->first();

        if (!($customer)) {
            return response()->json([
                "message" => 'Invalid email or password',
                "status" => 0
            ]);
        }
        
        if (Hash::check($credentials['password'], $customer->password)) {
            if($customer->status == 1){
                return response()->json([
                    "result" => $customer,
                    "message" => 'Success',
                    "status" => 1
                ]);   
            }else{
                return response()->json([
                    "message" => 'Your account has been blocked',
                    "status" => 0
                ]);
            }
        }else{
            return response()->json([
                "message" => 'Invalid email or password',
                "status" => 0
            ]);
        }

    }

    public function profile_picture(Request $request){

        $input = $request->all();
        $validator = Validator::make($input, [
            'customer_id' => 'required',
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif,svg'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        if ($request->hasFile('profile_picture')) {
            $image = $request->file('profile_picture');
            $name = time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/uploads/images');
            $image->move($destinationPath, $name);
            if(Customer::where('id',$input['customer_id'])->update([ 'profile_picture' => 'images/'.$name ])){
                return response()->json([
                    "result" => Customer::select('id', 'customer_name','phone_number','email','profile_picture','status')->where('id',$input['customer_id'])->first(),
                    "message" => 'Success',
                    "status" => 1
                ]);
            }else{
                return response()->json([
                    "message" => 'Sorry something went wrong...',
                    "status" => 0
                ]);
            }
        }

    }

    public function forgot_password(Request $request){

        $input = $request->all();
        $validator = Validator::make($input, [
            'email' => 'required|email|regex:/^[a-zA-Z]{1}/',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $customer = Customer::where('email',$input['email'])->first();
        if(is_object($customer)){
            $otp = rand(1000,9999);
            Customer::where('id',$customer->id)->update(['otp'=> $otp ]);
            $mail_header = array("otp" => $otp);
            $this->send_mail($mail_header,'Reset Password',$input['email']);
            return response()->json([
                "result" => Customer::select('id', 'otp')->where('id',$customer->id)->first(),
                "message" => 'Success',
                "status" => 1
            ]);
        }else{
            return response()->json([
                "message" => 'Invalid email address',
                "status" => 0
            ]);
        }
        
    }

    public function reset_password(Request $request){

        $input = $request->all();
        $validator = Validator::make($input, [
            'id' => 'required',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        $options = [
            'cost' => 12,
        ];
        $input['password'] = password_hash($input["password"], PASSWORD_DEFAULT, $options);

        if(Customer::where('id',$input['id'])->update($input)){
            return response()->json([
                "message" => 'Success',
                "status" => 1
            ]);
        }else{
            return response()->json([
                "message" => 'Invalid email address',
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

}
