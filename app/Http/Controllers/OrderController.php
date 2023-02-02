<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class OrderController extends Controller
{
    public function userOrders(Request $request, string $id)
    {
        $orders = Order::query()->where("buyer_id", $id)->get();

        return response([
            "message" => "Success",
            "orders" => $orders,
        ]);
    }

    public function userSales(Request $request, string $id)
    {
        $orders = Order::query()->where("seller_id", $id)->get();

        return response([
            "message" => "Success",
            "orders" => $orders,
        ]);
    }

    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            "buyer_id" => "required|string",
            "seller_id" => "required|string",
            "product_id" => "required|string",
            "created_at" => "required|date",
            "product_quantity" => "required|numeric",
        ]);

        if ($validation->fails()) {
            return response([
                "message" => "Invalid request",
                "errors" => $validation->errors(),
            ], 400);
        }

        $order = Order::query()->create([
            "buyer_id" => $request->buyer_id,
            "seller_id" => $request->seller_id,
            "product_id" => $request->product_id,
            "created_at" => $request->purchased_at,
            "quantity" => $request->quantity,
        ]);

        return response([
            "message" => "Order successfully recorded",
            "order" => $order,
        ]);
    }

    public function show($id)
    {
        $order = Order::query()->find($id);

        if (!$order) {
            return response([
                "message" => "Order not found",
            ], 404);
        }

        return response([
            "message" => "Success",
            "order" => $order,
        ]);
    }

    public function checkout(Request $request)
    {
        $validation = Validator::make($request->all(), [
            "buyer_id" => "required|string",
            "products" => "required|array",
            "products.*.purchased_quantity" => "required|numeric",
        ]);

        if ($validation->fails()) {
            return response([
                "message" => "Invalid request",
                "errors" => $validation->errors(),
            ], 400);
        }

        $products = array_map(function ($product) {
            return [
                "quantity" => $product["purchased_quantity"],
                "price_data" => [
                    "currency" => "sgd",
                    "unit_amount_decimal" => $product["price"] * 100,
                    "product_data" => [
                        "name" => $product["name"],
                        "description" => $product["description"],
                        "images" => array_map(fn ($image) => $image["url"], $product["images"]),
                        "metadata" => [
                            "id" => $product["id"],
                        ],
                    ],
                ],
            ];
        }, $request->products);

        $checkout_session = Session::create([
            "line_items" => $products,
            "mode" => "payment",
            "success_url" => "http://localhost:5173/",
            "cancel_url" => "http://localhost:5173/",
            
        ]);

        $order = Order::query()->create([
            "buyer_id" => $request->buyer_id,
        ]);

        // redirect client to check out session
        return response([
            "message" => "Success",
            "order" => $order,
            "url" => $checkout_session->url,
        ]);
    }
}
