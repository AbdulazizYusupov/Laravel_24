<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>

<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <table class="table table-striped table-bordered table-hover text-center">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Total</th>
                            <th>Remove</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($models as $meal)
                            <tr>
                                <td>{{ $meal->name }}</td>
                                <td>{{ number_format($meal->price) }}</td>
                                <td>
                                    {{ number_format($meal->price) }}
                                </td>
                                <td>
                                    <form action="{{ route('cart.remove', $meal->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <hr>
                <form action="{{ route('cart.confirm') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="userSelect" class="form-label">Select Company</label>
                        <select class="form-select" id="userSelect" name="company_id" required>
                            <option value="">Choose a company</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="dateTime" class="form-label">Date</label>
                        <input type="date" class="form-control" id="dateTime" name="date" required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Name</label>
                        <input type="text" class="form-control" id="address" name="name" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Confirm Order</button>
                    <a href="{{ route('meal.index') }}" class="btn btn-outline-info">Back</a>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
</body>

</html>
