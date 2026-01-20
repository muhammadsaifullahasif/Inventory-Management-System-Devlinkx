<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Category;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('view products'), ['only' => ['index']]);
        $this->middleware(PermissionMiddleware::using('add products'), ['only' => ['create', 'store']]);
        $this->middleware(PermissionMiddleware::using('edit products'), ['only' => ['edit', 'update']]);
        $this->middleware(PermissionMiddleware::using('delete products'), ['only' => ['destroy']]);
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::with('sales_channels')->orderBy('created_at', 'DESC')->paginate(25);
        // return $products;
        return view('products.index', compact('products'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = Category::all();
        $brands = Brand::all();
        return view('products.new', compact('categories', 'brands'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // dd($request->all());

        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'regular_price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'product_image' => 'nullable|image|max:2048',
            'is_featured' => 'sometimes|boolean',
            'active_status' => 'sometimes|boolean',
        ]);

        try {
            // Product creation logic here
            $product = new Product();
            $product->name = $request->name;
            $product->sku = $request->sku;
            $product->barcode = $request->barcode;
            $product->category_id = $request->category_id;
            $product->brand_id = $request->brand_id;
            if (empty($request->sale_price)) {
                $product->price = $request->regular_price;
            } else {
                $product->price = $request->sale_price;
            }
            $product->stock_quantity = $request->stock_quantity;

            if($request->has('product_image') != '') {
                $image = $request->product_image;
                $ext = $image->getClientOriginalExtension();
                $imageName = time() . '.' . $ext;

                $image->move(public_path('uploads'), $imageName);
                $product->product_image = $imageName;
            }
            $product->save();

            $product->product_meta()->createMany([
                [
                    'meta_key' => 'weight',
                    'meta_value' => $request->weight,
                ],
                [
                    'meta_key' => 'length',
                    'meta_value' => $request->length,
                ],
                [
                    'meta_key' => 'width',
                    'meta_value' => $request->width,
                ],
                [
                    'meta_key' => 'height',
                    'meta_value' => $request->height,
                ],
                [
                    'meta_key' => 'regular_price',
                    'meta_value' => $request->regular_price,
                ],
                [
                    'meta_key' => 'sale_price',
                    'meta_value' => $request->sale_price,
                ],
                [
                    'meta_key' => 'alert_quantity',
                    'meta_value' => $request->alert_quantity ?? 0,
                ]
            ]);

            return redirect()->route('products.index')->with('success', 'Product created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while creating the product: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::findOrFail($id);
        return view('products.show', compact('product'));
    }

    /**
     * Search the specified resource.
     */
    public function search(string $query)
    {
        $products = Product::where('name', 'LIKE', "%$query%")
            ->orWhere('sku', 'LIKE', "%$query%")
            ->orWhere('barcode', 'LIKE', "%$query%")
            ->orWhereHas('category', function ($categoryQuery) use ($query) {
                $categoryQuery->where('name', 'LIKE', "%$query%");
            })
            ->orWhereHas('brand', function ($brandQuery) use ($query) {
                $brandQuery->where('name', 'LIKE', "%$query%");
            })
            ->get();

        return response()->json($products);

        // return view('products.index', compact('products'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $product = Product::findOrFail($id);
        $categories = Category::all();
        $brands = Brand::all();
        return view('products.edit', compact('product', 'categories', 'brands'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku,' . $id,
            'barcode' => 'nullable|string|max:100|unique:products,barcode,' . $id,
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'regular_price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'product_image' => 'nullable|image|max:2048',
            'is_featured' => 'sometimes|boolean',
            'active_status' => 'sometimes|boolean',
        ]);

        try {
            $product = Product::findOrFail($id);
            $product->name = $request->name;
            $product->sku = $request->sku;
            $product->barcode = $request->barcode;
            $product->category_id = $request->category_id;
            $product->brand_id = $request->brand_id;
            if (empty($request->sale_price)) {
                $product->price = $request->regular_price;
            } else {
                $product->price = $request->sale_price;
            }
            $product->stock_quantity = $request->stock_quantity;

            if($request->has('product_image') != '') {
                $image = $request->product_image;
                $ext = $image->getClientOriginalExtension();
                $imageName = time() . '.' . $ext;

                $image->move(public_path('uploads'), $imageName);
                $product->product_image = $imageName;
            }
            $product->save();

            // Update product meta
            foreach (['weight', 'length', 'width', 'height', 'regular_price', 'sale_price'] as $metaKey) {
                $metaValue = $request->$metaKey;
                $product->product_meta()->updateOrCreate(
                    ['meta_key' => $metaKey],
                    ['meta_value' => $metaValue]
                );
            }

            return redirect()->route('products.index')->with('success', 'Product updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while updating the product: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();

            return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while deleting the product: ' . $e->getMessage());
        }
    }
}
