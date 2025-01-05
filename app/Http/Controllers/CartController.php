<?php

namespace App\Http\Controllers;
use Auth;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\Cart;
use Illuminate\Support\Str;
use Helper;

class CartController extends Controller
{
    protected $product=null;
    public function __construct(Product $product){
        $this->product=$product;
    }

    public function addToCart(Request $request) {
        // Validasi slug produk
        if (empty($request->slug)) {
            request()->session()->flash('error', 'Invalid Products');
            return back();
        }
    
        // Ambil data produk berdasarkan slug
        $product = Product::where('slug', $request->slug)->first();
        if (empty($product)) {
            request()->session()->flash('error', 'Invalid Products');
            return back();
        }
    
        // Cek ukuran produk di database
        // dd(explode(',', $product->size)[0]);


        // Menghitung harga setelah diskon
        $discountedPrice = $product->price - ($product->price * $product->discount) / 100;
    
        // Cek apakah produk sudah ada di keranjang
        $already_cart = Cart::where('user_id', auth()->user()->id)
                            ->where('order_id', null)
                            ->where('product_id', $product->id)
                            ->first();
    
        if ($already_cart) {
            // Jika produk sudah ada di keranjang, tambahkan kuantitas dan hitung ulang total harga
            $already_cart->quantity = $already_cart->quantity + 1;
            $already_cart->amount = $discountedPrice * $already_cart->quantity; // Menggunakan harga setelah diskon
    
            // Cek apakah stok cukup
            if ($already_cart->product->stock < $already_cart->quantity || $already_cart->product->stock <= 0) {
                return back()->with('error', 'Stock not sufficient!');
            }
    
            // Simpan perubahan ke keranjang
            $already_cart->save();
    
        } else {
            // Jika produk belum ada di keranjang, buat entri baru
            $cart = new Cart;
            $cart->user_id = auth()->user()->id;
            $cart->product_id = $product->id;
            $cart->price = $discountedPrice; // Simpan harga setelah diskon
            $cart->size = explode(',', $product->size)[0]; // Default size
            $cart->quantity = 1; // Menambahkan 1 produk
            $cart->amount = $discountedPrice * $cart->quantity; // Menghitung total harga setelah diskon
    
            // Cek apakah stok cukup
            if ($cart->product->stock < $cart->quantity || $cart->product->stock <= 0) {
                return back()->with('error', 'Stock not sufficient!');
            }
    
            // Simpan entri baru ke keranjang
            $cart->save();
    
            // Update wishlist jika ada produk yang ada di wishlist
            $wishlist = Wishlist::where('user_id', auth()->user()->id)
                                ->where('cart_id', null)
                                ->update(['cart_id' => $cart->id]);
        }
    
        // Kirim pesan sukses
        request()->session()->flash('success', 'Product has been added to cart');
        return back();
    }
    

    public function singleAddToCart(Request $request) {
        $request->validate([
            'slug'  => 'required',
            'quant' => 'required'
        ]);
    
        // Pastikan user sudah login
        if (!auth()->check()) {
            return redirect()->route('login.form')->with('error', 'You must be logged in to add products to your cart.');
        }
    
        // Ambil produk berdasarkan slug
        $product = Product::where('slug', $request->slug)->first();
    
        // Cek size produk sudah di pilh atau belum
        if (empty($request->size)) {
            return back()->with('error', 'Please select a size.');
        }

        // Cek stok produk
        if ($product->stock < $request->quant[1]) {
            return back()->with('error', 'Out of stock, You can add other products.');
        }
    
        // Validasi input kuantitas
        if ($request->quant[1] < 1 || empty($product)) {
            request()->session()->flash('error', 'Invalid Products');
            return back();
        }
    
        // Menghitung harga setelah diskon
        $discountedPrice = $product->price - ($product->price * $product->discount) / 100;
    
        // Cek apakah produk dengan ukuran yang sama sudah ada di keranjang
        $already_cart = Cart::where('user_id', auth()->user()->id)
                            ->where('order_id', null)
                            ->where('product_id', $product->id)
                            ->where('size', $request->size) // Cek berdasarkan size juga
                            ->first();
    
        if ($already_cart) {
            // Update jumlah dan total harga jika produk sudah ada di keranjang dengan ukuran yang sama
            $already_cart->quantity += $request->quant[1];
            $already_cart->amount = $discountedPrice * $already_cart->quantity; // Menggunakan harga setelah diskon
            // Validasi stok setelah update jumlah
            if ($already_cart->product->stock < $already_cart->quantity || $already_cart->product->stock <= 0) {
                return back()->with('error', 'Stock not sufficient.');
            }
            $already_cart->save();
        } else {
            // Jika produk belum ada di keranjang atau dengan ukuran berbeda, buat item baru
            $cart = new Cart;
            $cart->user_id = auth()->user()->id;
            $cart->product_id = $product->id;
            $cart->size = $request->size; // Simpan ukuran yang dipilih
            $cart->price = $discountedPrice;
            $cart->quantity = $request->quant[1];
            $cart->amount = $discountedPrice * $request->quant[1]; // Menggunakan harga setelah diskon
            // Validasi stok
            if ($cart->product->stock < $cart->quantity || $cart->product->stock <= 0) {
                return back()->with('error', 'Stock not sufficient.');
            }
            $cart->save();
        }
    
        request()->session()->flash('success', 'Product has been added to cart.');
        return back();
    }
    
    
    public function cartDelete(Request $request){
        $cart = Cart::find($request->id);
        if ($cart) {
            $cart->delete();
            request()->session()->flash('success','Cart removed successfully');
            return back();  
        }
        request()->session()->flash('error','Error please try again');
        return back();       
    }     

    public function cartUpdate(Request $request){
        // dd($request->all());
        if($request->quant){
            $error = array();
            $success = '';
            // return $request->quant;
            foreach ($request->quant as $k=>$quant) {
                // return $k;
                $id = $request->qty_id[$k];
                // return $id;
                $cart = Cart::find($id);
                // return $cart;
                if($quant > 0 && $cart) {
                    // return $quant;

                    if($cart->product->stock < $quant){
                        request()->session()->flash('error','Out of stock');
                        return back();
                    }
                    $cart->quantity = ($cart->product->stock > $quant) ? $quant  : $cart->product->stock;
                    // return $cart;
                    
                    if ($cart->product->stock <=0) continue;
                    $after_price=($cart->product->price-($cart->product->price*$cart->product->discount)/100);
                    $cart->amount = $after_price * $quant;
                    // return $cart->price;
                    $cart->save();
                    $success = 'Cart updated successfully!';
                }else{
                    $error[] = 'Cart Invalid!';
                }
            }
            return back()->with($error)->with('success', $success);
        }else{
            return back()->with('Cart Invalid!');
        }    
    }

    // public function addToCart(Request $request){
    //     // return $request->all();
    //     if(Auth::check()){
    //         $qty=$request->quantity;
    //         $this->product=$this->product->find($request->pro_id);
    //         if($this->product->stock < $qty){
    //             return response(['status'=>false,'msg'=>'Out of stock','data'=>null]);
    //         }
    //         if(!$this->product){
    //             return response(['status'=>false,'msg'=>'Product not found','data'=>null]);
    //         }
    //         // $session_id=session('cart')['session_id'];
    //         // if(empty($session_id)){
    //         //     $session_id=Str::random(30);
    //         //     // dd($session_id);
    //         //     session()->put('session_id',$session_id);
    //         // }
    //         $current_item=array(
    //             'user_id'=>auth()->user()->id,
    //             'id'=>$this->product->id,
    //             // 'session_id'=>$session_id,
    //             'title'=>$this->product->title,
    //             'summary'=>$this->product->summary,
    //             'link'=>route('product-detail',$this->product->slug),
    //             'price'=>$this->product->price,
    //             'photo'=>$this->product->photo,
    //         );
            
    //         $price=$this->product->price;
    //         if($this->product->discount){
    //             $price=($price-($price*$this->product->discount)/100);
    //         }
    //         $current_item['price']=$price;

    //         $cart=session('cart') ? session('cart') : null;

    //         if($cart){
    //             // if anyone alreay order products
    //             $index=null;
    //             foreach($cart as $key=>$value){
    //                 if($value['id']==$this->product->id){
    //                     $index=$key;
    //                 break;
    //                 }
    //             }
    //             if($index!==null){
    //                 $cart[$index]['quantity']=$qty;
    //                 $cart[$index]['amount']=ceil($qty*$price);
    //                 if($cart[$index]['quantity']<=0){
    //                     unset($cart[$index]);
    //                 }
    //             }
    //             else{
    //                 $current_item['quantity']=$qty;
    //                 $current_item['amount']=ceil($qty*$price);
    //                 $cart[]=$current_item;
    //             }
    //         }
    //         else{
    //             $current_item['quantity']=$qty;
    //             $current_item['amount']=ceil($qty*$price);
    //             $cart[]=$current_item;
    //         }

    //         session()->put('cart',$cart);
    //         return response(['status'=>true,'msg'=>'Cart successfully updated','data'=>$cart]);
    //     }
    //     else{
    //         return response(['status'=>false,'msg'=>'You need to login first','data'=>null]);
    //     }
    // }

    // public function removeCart(Request $request){
    //     $index=$request->index;
    //     // return $index;
    //     $cart=session('cart');
    //     unset($cart[$index]);
    //     session()->put('cart',$cart);
    //     return redirect()->back()->with('success','Successfully remove item');
    // }

    public function checkout(Request $request) {
        $cart = Cart::where('user_id', auth()->user()->id)
                    ->where('order_id', null) 
                    ->get();
    
        // dd($cart);                
        if ($cart->isEmpty()) {
            return redirect()->back()->with('error', 'Your cart is empty. Please add products to your cart before proceeding to checkout.');
        }
        return view('frontend.pages.checkout', compact('cart'));
    }
    
}
