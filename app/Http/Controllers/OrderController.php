<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Shipping;
use App\User;
use App\Models\Product;
use PDF;
use Notification;
use Helper;
use Illuminate\Support\Str;
use App\Notifications\StatusNotification;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $orders=Order::orderBy('id','DESC')->paginate(10);
        return view('backend.order.index')->with('orders',$orders);
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
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'coupon' => 'nullable|numeric',
            'phone' => ['required', 'regex:/^(?:\+62|0)8[1-9][0-9]{6,10}$/', 'min:10', 'max:13'], // Validasi telepon Indonesia,
            'post_code' => 'nullable|numeric|digits:5',
            'email' => 'required|email'
        ]);
    
        // Ambil semua item dari cart
        $cart = Cart::where('user_id', auth()->user()->id)
                    ->where('order_id', null) // Pastikan cart belum ada order
                    ->get();
    
        if ($cart->isEmpty()) {
            request()->session()->flash('error', 'Cart is Empty!');
            return back();
        }
    
        // Group by title_product to separate different orders for different products
        $groupedCart = $cart->groupBy(function ($item) {
            $product = Product::find($item->product_id);
            return $product ? $product->title : null;
        });
    
        foreach ($groupedCart as $title => $items) {
            // Cek apakah ada order sebelumnya dengan title_product yang sama dan payment_status null
            $existingOrder = Order::where('user_id', auth()->user()->id)
                                ->where('title_product', $title)
                                ->where('payment_status', null)
                                ->first();
    
            if ($existingOrder) {
                request()->session()->flash('error', 'You already have an order in process for this product title.');
                return back();
            }
    
            // Jika tidak ada order sebelumnya, buat order baru untuk title tersebut
            $order = new Order();
            $order_data = $request->all();
            $order_data['order_number'] = 'ORD-' . strtoupper(Str::random(10));
            $order_data['user_id'] = $request->user()->id;
            $order_data['title_product'] = $title; // Title produk yang sedang diproses
            $order_data['name'] = $request->name;
            $order_data['shipping_id'] = $request->shipping;
            $shipping = Shipping::where('id', $order_data['shipping_id'])->pluck('price');
            $order_data['sub_total'] = Helper::totalCartPrice();
            $order_data['quantity'] = Helper::cartCount();
    
            // Menyimpan ukuran produk dari cart untuk order ini
            $sizes = $items->pluck('size')->unique()->implode(',');
            $order_data['size'] = $sizes;
    
            // Handling coupon
            if (session('coupon')) {
                $order_data['coupon'] = session('coupon')['value'];
            }
    
            // Handling total amount berdasarkan coupon dan shipping
            if ($request->shipping) {
                if (session('coupon')) {
                    $order_data['total_amount'] = Helper::totalCartPrice() + $shipping[0] - session('coupon')['value'];
                } else {
                    $order_data['total_amount'] = Helper::totalCartPrice() + $shipping[0];
                }
            } else {
                if (session('coupon')) {
                    $order_data['total_amount'] = Helper::totalCartPrice() - session('coupon')['value'];
                } else {
                    $order_data['total_amount'] = Helper::totalCartPrice();
                }
            }
    
            // Payment method handling
            if (request('payment_method') == 'qris') {
                 // Set your Merchant Server Key
                \Midtrans\Config::$serverKey = config('midtrans.serverKey');
                // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
                \Midtrans\Config::$isProduction = false;
                // Set sanitization on (default)
                \Midtrans\Config::$isSanitized = true;
                // Set 3DS transaction for credit card to true
                \Midtrans\Config::$is3ds = true;

                $order_data['payment_method'] = 'qris';
                $order_data['payment_status'] = 'unpaid';

                $params = array(
                    'transaction_details' => array(
                        'order_id' => $order_data['order_number'],
                        'gross_amount' => $order_data['total_amount'],
                    ),
                );

                $snapToken = \Midtrans\Snap::getSnapToken($params);
                // $order_data['snap_token'] = $snapToken;
            } 
            else {
                $order_data['payment_status'] = 'Unpaid';
            }
    
            // Menyimpan order
            $order->fill($order_data);
            $order->snap_token = $snapToken;
            $status = $order->save();
    
            // Jika order berhasil disimpan, simpan item dari cart ke order dan kurangi stok produk
            if ($status) {
                foreach ($items as $cart_item) {
                    $product = Product::find($cart_item->product_id);
                    if ($product) {
                        // Kurangi stok produk
                        $product->stock -= $cart_item->quantity;
                        $product->save();
                    }
    
                    // Memperbarui order_id pada cart untuk item ini
                    $cart_item->order_id = $order->id;
                    $cart_item->save();
                }
    
                // Kirimkan notifikasi ke admin
                $users = User::where('role', 'admin')->first();
                $details = [
                    'title' => 'New Order Received',
                    'actionURL' => route('order.show', $order->id),
                    'fas' => 'fa-file-alt'
                ];
                Notification::send($users, new StatusNotification($details));
    
                // Mengarahkan ke halaman pembayaran atau mengosongkan cart
                if (request('payment_method') == 'paypal') {
                    return redirect()->route('payment')->with(['id' => $order->id]);
                } else {
                    session()->forget('cart');
                    session()->forget('coupon');
                }
                
                request()->session()->flash('success', 'Your product order has been placed. Thank you for shopping with us.');
                // request()->session()->flash('success', 'Your product order has been placed. Thank you for shopping with us.');
            }
        }
        // dd($order->id);
        if ($order->payment_method === 'qris') {
            // return redirect()->route('payment')->with(['id' => $order->id]);
            return redirect()->route('payment', ['id' => $order->id]);
        }
        return redirect()->route('home');
    }
    

    public function payment($id)
    {   
        $order = Order::find($id);
        // dd($order->title_product);

        // $order = Order::find(request('id'));
        return view('frontend.pages.payment', compact('order'));
    }

    public function paymentSuccess(Request $request)
    {
        // Set your Merchant Server Key
        \Midtrans\Config::$serverKey = config('midtrans.serverKey');
        // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
        \Midtrans\Config::$isProduction = false;
        // Set sanitization on (default)
        \Midtrans\Config::$isSanitized = true;
        // Set 3DS transaction for credit card to true
        \Midtrans\Config::$is3ds = true;

        $order = Order::find($request->order_id);
        // dd($request->order_id);
        $order->payment_status = 'paid';
        $order->save();

        $notification = array(
            'message' => 'Payment Success',
            'alert-type' => 'success'
        );
        request()->session()->flash('success', 'Payment Success');
        return redirect()->route('home')->with($notification);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $order=Order::find($id);
        // return $order;
        return view('backend.order.show')->with('order',$order);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $order=Order::find($id);
        return view('backend.order.edit')->with('order',$order);
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
        $order=Order::find($id);
        $this->validate($request,[
            'status'=>'required|in:new,process,delivered,cancel'
        ]);
        $data=$request->all();
        // return $request->status;
        if($request->status=='delivered'){
            foreach($order->cart as $cart){
                $product=$cart->product;
                // return $product;
                $product->stock -=$cart->quantity;
                $product->save();
            }
        }
        $status=$order->fill($data)->save();
        if($status){
            request()->session()->flash('success','Successfully updated order');
        }
        else{
            request()->session()->flash('error','Error while updating order');
        }
        return redirect()->route('order.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $order = Order::find($id);
        dd($order);
        if ($order) {
            // Ambil semua item dari order yang akan dihapus
            $cart_items = Cart::where('order_id', $order->id)->get();
    
            // Kembalikan stok produk yang telah berkurang
            foreach ($cart_items as $cart_item) {
                $product = Product::find($cart_item->product_id);
                if ($product) {
                    // Tambahkan kembali stok produk
                    $product->stock += $cart_item->quantity;
                    $product->save();
                }
            }
    
            // Hapus order setelah stok dikembalikan
            $status = $order->delete();
            if ($status) {
                request()->session()->flash('success', 'Order Successfully deleted');
            } else {
                request()->session()->flash('error', 'Order cannot be deleted');
            }
            return redirect()->route('order.index');
        } else {
            request()->session()->flash('error', 'Order not found');
            return redirect()->back();
        }
    }
    
    public function orderTrack(){
        return view('frontend.pages.order-track');
    }

    public function productTrackOrder(Request $request){
        // return $request->all();
        $order=Order::where('user_id',auth()->user()->id)->where('order_number',$request->order_number)->first();
        if($order){
            if($order->status=="new"){
            request()->session()->flash('success','Your order has been placed.');
            return redirect()->route('home');

            }
            elseif($order->status=="process"){
                request()->session()->flash('success','Your order is currently processing.');
                return redirect()->route('home');
    
            }
            elseif($order->status=="delivered"){
                request()->session()->flash('success','Your order has been delivered. Thank you for shopping with us.');
                return redirect()->route('home');
    
            }
            else{
                request()->session()->flash('error','Sorry, your order has been canceled.');
                return redirect()->route('home');
    
            }
        }
        else{
            request()->session()->flash('error','Invalid order number. Please try again!');
            return back();
        }
    }

    // PDF generate
    public function pdf(Request $request){
        $order=Order::getAllOrder($request->id);
        // return $order;
        $file_name=$order->order_number.'-'.$order->first_name.'.pdf';
        // return $file_name;
        $pdf=PDF::loadview('backend.order.pdf',compact('order'));
        return $pdf->download($file_name);
    }
    // Income chart
    public function incomeChart(Request $request){
        $year=\Carbon\Carbon::now()->year;
        // dd($year);
        $items=Order::with(['cart_info'])->whereYear('created_at',$year)->where('status','delivered')->get()
            ->groupBy(function($d){
                return \Carbon\Carbon::parse($d->created_at)->format('m');
            });
            // dd($items);
        $result=[];
        foreach($items as $month=>$item_collections){
            foreach($item_collections as $item){
                $amount=$item->cart_info->sum('amount');
                // dd($amount);
                $m=intval($month);
                // return $m;
                isset($result[$m]) ? $result[$m] += $amount :$result[$m]=$amount;
            }
        }
        $data=[];
        for($i=1; $i <=12; $i++){
            $monthName=date('F', mktime(0,0,0,$i,1));
            $data[$monthName] = (!empty($result[$i]))? number_format((float)($result[$i]), 2, '.', '') : 0.0;
        }
        return $data;
    }
}
