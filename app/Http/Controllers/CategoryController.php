<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategory;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $models = Category::where('status', 1)->get();
        return view('category.index', ['models' => $models]);
    }

    public function store(StoreCategory $request)
    {
        $data = $request->validated();

        Category::create($data);

        return redirect()->back()->with('success', 'Category created successfully');
    }

    public function update(StoreCategory $request, Category $category)
    {
        $data = $request->validated();

        $category->update($data);

        return redirect()->back()->with('success', 'Category updated successfully');
    }

    public function destroy(Category $category)
    {
        $category->update(['status' => 0]);
        return redirect()->back()->with('success', 'Category deleted successfully');
    }
}
