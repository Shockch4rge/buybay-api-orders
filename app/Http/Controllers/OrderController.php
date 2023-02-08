<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class OrderController extends Controller
{
    public function userOrders(Request $request, string $id)
    {
        $orders = Order::query()->where("customer_id", $id)->with(["items"])->get();

        foreach ($orders as $order) {
            $productIds = $order->items->map(fn ($item) => $item->product_id)->values();
            $order->products = GetOrderProducts::dispatchSync($productIds);
        }

        return response([
            "message" => "Success",
            "orders" => $orders,
        ]);
    }

    public function userSales(Request $request, string $id)
    {
        $orderItems = OrderItem::query()->where("seller_id", $id)->with(["order"])->get();
        $orders = $orderItems->map(fn ($item) => $item->order)->unique("id");

        foreach ($orders as $order) {
            $productIds = $order->items->map(fn ($item) => $item->product_id)->values();
            $response = Http::post(env("PRODUCTS_API_URL") . "/products/ids", [
                "ids" => $productIds,
            ]);
            $order->products = $response->json();
            echo json_encode($response->json());
        }

        return response([
            "message" => "Success",
            "orders" => $orders,
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
            "customer_id" => "required|string",
            "products" => "required|array",
            "products.*.purchased_quantity" => "required|numeric",
        ]);

        if ($validation->fails()) {
            return response([
                "message" => "Invalid request",
                "errors" => $validation->errors(),
            ], 400);
        }

        $products = collect($request->products)->map(fn ($product) => [
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
        ]);

        $checkoutSession = Session::create([
            "line_items" => $products,
            "mode" => "payment",
            "success_url" => "http://localhost:5173/",
            "cancel_url" => "http://localhost:5173/",
        ]);

        $order = Order::query()->create([
            "customer_id" => $request->customer_id,
        ]);

        foreach ($request->products as $product) {
            OrderItem::query()->create([
                "order_id" => $order->id,
                "seller_id" => $product["seller_id"],
                "product_id" => $product["id"],
            ]);
            ProductPurchased::dispatchSync([
                "productId" => $product["id"],
                "quantity" => $product["purchased_quantity"]
            ]);
        }

        return response([
            "message" => "Returning checkout session url and order",
            "order" => $order,
            "url" => $checkoutSession->url,
        ]);
    }
}
