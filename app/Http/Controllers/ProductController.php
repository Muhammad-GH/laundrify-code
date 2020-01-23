<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Validator;
use App\Category;
use App\Product;
use App\FareManagement;
use Illuminate\Support\Facades\DB;
class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

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
        $validator =  Validator::make($request->all(),[
            'service_id' => 'required'
        ]);

        if($validator->fails()){
            return response()->json([
                "error" => 'validation_error',
                "message" => 'service_id field is required',
                "status" => "0",
            ], 422);
        }
    
        $all_categories = Category::select('id','service_id')->where('status',1)->get();
        $find_ids = array();
        foreach ($all_categories as $key => $value) {
            if(is_array($value->service_id) && in_array($request->service_id, $value->service_id)){
                array_push($find_ids, $value->id);
            }
        }
        $categories = Category::select('id','category_name')->where('status',1)->whereIn('id',$find_ids)->get();
        
        foreach ($categories as $key => $value) {
            $categories[$key]['product'] = Product::where('status',1)->where('category_id',$value->id)->get();
            foreach ( $categories[$key]['product'] as $key1 => $value1) {
                $categories[$key]['product'][$key1]['price'] = FareManagement::where('service_id',$request->service_id)->where('category_id',$value->id)->where('product_id',$value1->id)->value('amount');
            }
        }

        return response()->json([
            "result" => $categories,
            "count" => count($categories),
            "message" => 'Success',
            "status" => 1
        ]);

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
}
