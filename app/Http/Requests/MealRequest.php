<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|max:255',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|integer',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:4096',
        ];

        if ($this->isMethod('patch')) {
            $rules['image'] = 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:4096';
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'name.required' => 'Name is required',
            'name.max' => 'Name must be less than 255 characters',
            'category_id.required' => 'Category is required',
            'category_id.exists' => 'Category does not exist',
            'price.required' => 'Price is required',
            'price.integer' => 'Price must be an integer',
            'image.required' => 'Image is required',
            'image.image' => 'Image must be an image',
            'image.mimes' => 'Image must be a jpeg, png, jpg, gif, or svg',
            'image.max' => 'Image size must be less than 4MB',
        ];
    }
}
