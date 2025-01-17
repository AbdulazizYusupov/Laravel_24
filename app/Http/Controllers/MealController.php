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
}
