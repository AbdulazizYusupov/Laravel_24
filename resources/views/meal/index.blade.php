<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .cart-icon {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            font-size: 1.5rem;
            color: #000;
            cursor: pointer;
        }

        .cart-counter {
            position: absolute;
            top: 0;
            right: 0;
            background: red;
            color: white;
            border-radius: 50%;
            font-size: 0.8rem;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>

<body>
    <div class="cart-icon">
        <a class="btn btn-outline-success" href="{{ route('cart.index') }}">
            Send
        </a>
    </div>

    <div class="container mt-5">
        <h1>Meals</h1>
        <div class="mb-3 mt-3">
            <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createMealModal">Create
                Meal</a>
            <ul class="list-group mt-3">
                <a href="{{ route('category.index') }}">Category</a>
            </ul>
        </div>
        <div class="row g-3">
            @foreach ($meals as $meal)
                <div class="col-3 d-flex align-items-stretch">
                    <div class="card h-85 w-100">
                        <img src="{{ asset('storage/' . $meal->image) }}" alt="Meal Image" class="card-img-top" style="height: 200px; width: 312px;">
                        <div class="card-body">
                            <h3 class="card-title">{{ $meal->name }}</h3>
                            <p class="card-text">{{ $meal->category->name }}</p>
                            <h4 class="card-text">{{ $meal->price }} so'm</h4>
                            <div class="d-flex align-items-center gap-2">
                                <a href="#" class="btn btn-warning" data-bs-toggle="modal"
                                    data-bs-target="#updateMealModal{{ $meal->id }}">Edit</a>
                                <form action="{{ route('meal.destroy', $meal->id) }}" method="POST"
                                    class="d-inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="updateMealModal{{ $meal->id }}" tabindex="-1"
                    aria-labelledby="updateMealModalLabel{{ $meal->id }}" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="updateMealModalLabel{{ $meal->id }}">Edit Meal</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>
                            <form action="{{ route('meal.update', $meal->id) }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Name</label>
                                        <input type="text" name="name" class="form-control"
                                            value="{{ $meal->name }}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Category</label>
                                        <select name="category_id" class="form-select" required>
                                            <option value="{{ $meal->category_id }}">{{ $meal->category->name }}
                                            </option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="price" class="form-label">Price</label>
                                        <input type="number" name="price" class="form-control"
                                            value="{{ $meal->price }}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="image" class="form-label">Image</label>
                                        <input type="file" name="image" class="form-control">
                                        @if ($meal->image)
                                            <img src="{{ Storage::url($meal->image) }}" alt="Current Image"
                                                class="img-thumbnail mt-2" width="150">
                                        @endif
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Update Meal</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="modal fade" id="createMealModal" tabindex="-1" aria-labelledby="createMealModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createMealModalLabel">Create Meal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('meal.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="price" class="form-label">Price</label>
                            <input type="number" name="price" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="image" class="form-label">Image</label>
                            <input type="file" name="image" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Meal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
