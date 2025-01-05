@extends('frontend.layouts.master')

@section('title', 'Checkout page')

@section('main-content')

    <!-- Breadcrumbs -->
    <div class="breadcrumbs">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="bread-inner">
                        <ul class="bread-list">
                            <li><a href="{{ route('home') }}">Home<i class="ti-arrow-right"></i></a></li>
                            <li class="active"><a href="javascript:void(0)">Checkout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Breadcrumbs -->

    <!-- Start Checkout -->
    <section class="shop checkout section">
        <div class="container">
            <form class="form" method="POST" action="{{ route('cart.order') }}">
                @csrf
                <div class="row">
                    <div class="col-lg-8 col-12">
                        <div class="checkout-form">
                            <h2>Selesaikan Pembayaran</h2>
                            <p>Tinggal beberapa langkah lagi</p>
                            <!-- Form -->
                            <div class="col">
                                <div class="">
                                    <div class="form-group">
                                        <label>Nama<span>*</span></label>
                                        <input type="text" name="name" placeholder="Masukkan Nama anda...."
                                            value="{{ old('name') }}" required>
                                        @error('name')
                                            <span class='text-danger'>{{ $message }}</span>
                                        @enderror
                                    </div>

                                </div>
                                <div class="">
                                    <div class="form-group">
                                        <label>Email<span>*</span></label>
                                        <input type="email" name="email" placeholder="Masukkan Email anda...."
                                            value="{{ old('email') }}" required>
                                        @error('email')
                                            <span class='text-danger'>{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="">
                                    <div class="form-group">
                                        <label>Nomor Tlpn <span>*</span></label>
                                        <input type="number" name="phone" placeholder="08... or +62...." required
                                            value="{{ old('phone') }}">
                                        @error('phone')
                                            <span class='text-danger'>{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="">
                                    <div class="form-group">
                                        <label>Alamat<span>*</span></label>
                                        <input type="text" name="address" placeholder="Masukkan Alamat Lengkap...."
                                            value="{{ old('address') }}">
                                        @error('address')
                                            <span class='text-danger'>{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="">
                                    <div class="form-group">
                                        <label>Kode Pos</label>
                                        <input type="text" name="post_code" placeholder="Masukkan Kode Pos...."
                                            value="{{ old('post_code') }}">
                                        @error('post_code')
                                            <span class='text-danger'>{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <!--/ End Form -->
                        </div>
                    </div>
                    <div class="col-lg-4 col-12">
                        <div class="order-details">
                            <!-- Order Widget -->
                            <div class="single-widget">
                                <h2>CART TOTAL</h2>
                                <div class="content">
                                    <ul>
                                        <li class="order_subtotal" data-price="{{ Helper::totalCartPrice() }}">Cart
                                            Subtotal<span>Rp.
                                                {{ number_format(Helper::totalCartPrice(), 0, ',', '.') }}</span></li>
                                        <li class="shipping">
                                            Shipping Cost
                                            @if (count(Helper::shipping()) > 0 && Helper::cartCount() > 0)
                                                <select name="shipping" class="nice-select" required>
                                                    @foreach (Helper::shipping() as $shipping)
                                                        <option value="{{ $shipping->id }}" class="shippingOption"
                                                            data-price="{{ $shipping->price }}">{{ $shipping->type }}:
                                                            Rp. {{ number_format($shipping->price, 2, ',', '.') }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <span>Free</span>
                                            @endif
                                        </li>

                                        @if (session('coupon'))
                                            <li class="coupon_price" data-price="{{ session('coupon')['value'] }}">You
                                                Save<span>Rp. { number_format(session('coupon')['value'], 2) }}</span></li>
                                        @endif
                                        @php
                                            $total_amount = Helper::totalCartPrice();
                                            if (session('coupon')) {
                                                $total_amount = $total_amount - session('coupon')['value'];
                                            }
                                        @endphp
                                        @if (session('coupon'))
                                            <li class="last" id="order_total_price">Total<span>Rp.
                                                    {{ number_format($total_amount, 2, ',', '.') }}</span></li>
                                        @else
                                            <li class="last" id="order_total_price">Total<span>Rp.
                                                    {{ number_format($total_amount, 2, ',', '.') }}</span></li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                            <!--/ End Order Widget -->
                            <!-- Order Widget -->
                            <div class="single-widget">
                                <h2>Payment Methods</h2>
                                <div class="content">
                                    <div class="checkbox">
                                        <form-group>
                                            <input name="payment_method" type="radio" value="cod" required> <label>
                                                Cash On Delivery</label><br>
                                            <input name="payment_method" type="radio" value="qris" required> <label>
                                                Qris</label><br>
                                        </form-group>
                                    </div>
                                </div>
                            </div>
                            <!--/ End Order Widget -->
                            <!-- Payment Method Widget -->
                            {{-- <div class="single-widget payement">
                                    <div class="content">
                                        <img src="{{('backend/img/payment-method.png')}}" alt="#">
                                    </div>
                                </div> --}}
                            <!--/ End Payment Method Widget -->
                            <!-- Button Widget -->
                            <div class="single-widget get-button">
                                <div class="content">
                                    <div class="button">
                                        <button type="submit" class="btn" id="pay-button">proceed to checkout</button>
                                    </div>
                                </div>
                            </div>
                            <!--/ End Button Widget -->
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
    <!--/ End Checkout -->
@endsection
@push('styles')
    <style>
        li.shipping {
            display: inline-flex;
            width: 100%;
            font-size: 14px;
        }

        li.shipping .input-group-icon {
            width: 100%;
            margin-left: 10px;
        }

        .input-group-icon .icon {
            position: absolute;
            left: 20px;
            top: 0;
            line-height: 40px;
            z-index: 3;
        }

        .form-select {
            height: 30px;
            width: 100%;
        }

        .form-select .nice-select {
            border: none;
            border-radius: 0px;
            height: 40px;
            background: #f6f6f6 !important;
            padding-left: 45px;
            padding-right: 40px;
            width: 100%;
        }

        .list li {
            margin-bottom: 0 !important;
        }

        .list li:hover {
            background: #F7941D !important;
            color: white !important;
        }

        .form-select .nice-select::after {
            top: 14px;
        }
    </style>
@endpush
@push('scripts')
    <script src="{{ asset('frontend/js/nice-select/js/jquery.nice-select.min.js') }}"></script>
    <script src="{{ asset('frontend/js/select2/js/select2.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $("select.select2").select2();
        });
        $('select.nice-select').niceSelect();
    </script>
    <script>
        function showMe(box) {
            var checkbox = document.getElementById('shipping').style.display;
            // alert(checkbox);
            var vis = 'none';
            if (checkbox == "none") {
                vis = 'block';
            }
            if (checkbox == "block") {
                vis = "none";
            }
            document.getElementById(box).style.display = vis;
        }
    </script>
    <script>
        $(document).ready(function() {
            $('.shipping select[name=shipping]').change(function() {
                let cost = parseFloat($(this).find('option:selected').data('price')) || 0;
                let subtotal = parseFloat($('.order_subtotal').data('price'));
                let coupon = parseFloat($('.coupon_price').data('price')) || 0;

                // Menghitung total harga
                let totalPrice = subtotal + cost - coupon;

                // Fungsi untuk format angka ke Rupiah
                function formatRupiah(angka) {
                    return 'RP. ' + angka.toLocaleString('id-ID', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }

                // Menampilkan total harga dengan format Rupiah
                $('#order_total_price span').text(formatRupiah(totalPrice));
            });
        });
    </script>


    <script>
        $(document).ready(function() {
            $('input[name="payment_method"]').change(function() {
                if ($(this).val() === 'cardpay') {
                    $('#creditCardDetails').show();
                } else {
                    $('#creditCardDetails').hide();
                }
            });
        });
    </script>
@endpush
