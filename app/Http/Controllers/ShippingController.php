<?php
namespace App\Http\Controllers;
use App\Models\Shipping;
use App\Services\ShippingService;
use Illuminate\Http\Request;
class ShippingController extends Controller
{
    protected ShippingService $shippingService;
    public function __construct(ShippingService $shippingService)
    {
        $this->shippingService = $shippingService;
    }
    public function index()
    {
        $shippings = Shipping::where("delete_status", "0")->orderByDesc("is_default")->orderBy("name")->get();
        return view("shipping.index", compact("shippings"));
    }
    public function create()
    {
        return view("shipping.new");
    }
    public function store(Request $request)
    {
        $request->validate(["name"=>"required|string|max:255","type"=>"required|string|max:100","account_number"=>"nullable|string|max:255","api_endpoint"=>"nullable|url|max:500","sandbox_endpoint"=>"nullable|url|max:500","tracking_url"=>"nullable|url|max:500","default_service"=>"nullable|string|max:255","weight_unit"=>"required|in:lbs,kg,oz,g","dimension_unit"=>"required|in:inches,cm","client_id"=>"nullable|string|max:500","client_secret"=>"nullable|string|max:500","shipper_name"=>"nullable|string|max:255","shipper_address"=>"nullable|string|max:255","shipper_city"=>"nullable|string|max:100","shipper_state"=>"nullable|string|max:2","shipper_postal_code"=>"nullable|string|max:20","shipper_country"=>"nullable|string|max:2"]);
        $isDefault = $request->boolean("is_default");
        $isAV = $request->boolean("is_address_validation");
        if ($isDefault) { Shipping::where("delete_status","0")->update(["is_default"=>false]); }
        if ($isAV) { Shipping::where("delete_status","0")->update(["is_address_validation"=>false]); }
        $creds = null;
        if ($request->filled("client_id") || $request->filled("client_secret")) {
            $creds = ["client_id"=>$request->input("client_id",""),"client_secret"=>$request->input("client_secret","")];
        }
        Shipping::create(["name"=>$request->name,"type"=>$request->type,"account_number"=>$request->account_number,"api_endpoint"=>$request->api_endpoint,"sandbox_endpoint"=>$request->sandbox_endpoint,"tracking_url"=>$request->tracking_url,"default_service"=>$request->default_service,"weight_unit"=>$request->weight_unit,"dimension_unit"=>$request->dimension_unit,"shipper_name"=>$request->shipper_name,"shipper_address"=>$request->shipper_address,"shipper_city"=>$request->shipper_city,"shipper_state"=>$request->shipper_state ? strtoupper($request->shipper_state) : null,"shipper_postal_code"=>$request->shipper_postal_code,"shipper_country"=>$request->shipper_country ? strtoupper($request->shipper_country) : 'US',"is_sandbox"=>$request->boolean("is_sandbox"),"is_default"=>$isDefault,"is_address_validation"=>$isAV,"credentials"=>$creds,"status"=>"active","active_status"=>"1","delete_status"=>"0"]);
        return redirect()->route("shipping.index")->with("success","Shipping carrier added successfully.");
    }
    public function show(string $id)
    {
        $shipping = Shipping::where("id",$id)->where("delete_status","0")->firstOrFail();
        return view("shipping.show", compact("shipping"));
    }
    public function edit(string $id)
    {
        $shipping = Shipping::where("id",$id)->where("delete_status","0")->firstOrFail();
        return view("shipping.edit", compact("shipping"));
    }
    public function update(Request $request, string $id)
    {
        $shipping = Shipping::where("id",$id)->where("delete_status","0")->firstOrFail();
        $request->validate(["name"=>"required|string|max:255","type"=>"required|string|max:100","account_number"=>"nullable|string|max:255","api_endpoint"=>"nullable|url|max:500","sandbox_endpoint"=>"nullable|url|max:500","tracking_url"=>"nullable|url|max:500","default_service"=>"nullable|string|max:255","weight_unit"=>"required|in:lbs,kg,oz,g","dimension_unit"=>"required|in:inches,cm","client_id"=>"nullable|string|max:500","client_secret"=>"nullable|string|max:500","shipper_name"=>"nullable|string|max:255","shipper_address"=>"nullable|string|max:255","shipper_city"=>"nullable|string|max:100","shipper_state"=>"nullable|string|max:2","shipper_postal_code"=>"nullable|string|max:20","shipper_country"=>"nullable|string|max:2"]);
        $isDefault = $request->boolean("is_default");
        $isAV = $request->boolean("is_address_validation");
        if ($isDefault) { Shipping::where("id","!=",$id)->where("delete_status","0")->update(["is_default"=>false]); }
        if ($isAV) { Shipping::where("id","!=",$id)->where("delete_status","0")->update(["is_address_validation"=>false]); }
        $ec = $shipping->credentials ?? [];
        if ($request->filled("client_id") || $request->filled("client_secret")) {
            $creds = ["client_id"=>$request->input("client_id",$ec["client_id"]??""),"client_secret"=>$request->input("client_secret",$ec["client_secret"]??"")];
        } else { $creds = $ec ?: null; }
        $shipping->update(["name"=>$request->name,"type"=>$request->type,"account_number"=>$request->account_number,"api_endpoint"=>$request->api_endpoint,"sandbox_endpoint"=>$request->sandbox_endpoint,"tracking_url"=>$request->tracking_url,"default_service"=>$request->default_service,"weight_unit"=>$request->weight_unit,"dimension_unit"=>$request->dimension_unit,"shipper_name"=>$request->shipper_name,"shipper_address"=>$request->shipper_address,"shipper_city"=>$request->shipper_city,"shipper_state"=>$request->shipper_state ? strtoupper($request->shipper_state) : null,"shipper_postal_code"=>$request->shipper_postal_code,"shipper_country"=>$request->shipper_country ? strtoupper($request->shipper_country) : 'US',"is_sandbox"=>$request->boolean("is_sandbox"),"is_default"=>$isDefault,"is_address_validation"=>$isAV,"credentials"=>$creds]);
        return redirect()->route("shipping.index")->with("success","Shipping carrier updated successfully.");
    }
    public function destroy(string $id)
    {
        $shipping = Shipping::where("id",$id)->where("delete_status","0")->firstOrFail();
        $shipping->update(["delete_status"=>"1","active_status"=>"0","is_default"=>false,"is_address_validation"=>false]);
        return redirect()->route("shipping.index")->with("success","Shipping carrier deleted.");
    }
    public function toggleStatus(Request $request, string $id)
    {
        $shipping = Shipping::where("id",$id)->where("delete_status","0")->firstOrFail();
        $ns = $shipping->active_status === "1" ? "0" : "1";
        $shipping->update(["active_status"=>$ns]);
        return response()->json(["success"=>true,"active"=>$ns==="1"]);
    }
    public function validateAddress(Request $request)
    {
        $request->validate(["order_id"=>"required|integer|exists:orders,id"]);
        $order = \App\Models\Order::findOrFail($request->order_id);
        $at = $this->shippingService->validateOrderAddress($order);
        return response()->json(["success"=>true,"address_type"=>$at,"validated_at"=>$order->fresh()->address_validated_at?->format("Y-m-d H:i:s")]);
    }
}
