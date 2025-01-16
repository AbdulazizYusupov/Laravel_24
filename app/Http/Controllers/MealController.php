<?php

namespace App\Http\Controllers;

use App\Http\Requests\MealRequest;
use App\Models\Category;
use App\Models\Meal;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class MealController extends Controller
{
    public function index()
    {
        $models = Meal::all();

        $categories = Category::all();

        return view('meal.index', ['meals' => $models, 'categories' => $categories]);
    }

    public function store(MealRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('meals', 'public');
            $data['image'] = $imagePath;
        }

        Meal::create($data);

        return redirect()->back()->with('success', 'Meal created successfully');
    }

    public function update(MealRequest $request, Meal $meal)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($meal->image && Storage::exists($meal->image)) {
                Storage::delete($meal->image);
            }

            $imagePath = $request->file('image')->store('meals', 'public');
            $data['image'] = $imagePath;
        }

        $meal->update($data);

        return redirect()->back()->with('success', 'Meal updated successfully');
    }

    public function destroy(Meal $meal)
    {
        if ($meal->image && Storage::exists($meal->image)) {
            Storage::delete($meal->image);
        }

        $meal->delete();

        return redirect()->back()->with('success', 'Meal deleted successfully');
    }

    public function add(Request $request)
    {
        $id = $request->id;

        $cart = session()->get('cart', []);

        if (isset($cart[$id])) {
            $cart[$id]['quantity']++;
        } else {
            $cart[$id] = [
                'meal_id' => $id,
                'quantity' => 1,
            ];
        }
        session()->put('cart', $cart);

        return redirect()->back();
    }

    public function getCartCount()
    {
        $cart = session()->get('cart', []);
        $totalQuantity = array_sum(array_column($cart, 'quantity'));
        return $totalQuantity;
    }
}
