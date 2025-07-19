<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refunds</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"
        integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Refunds</div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Charge ID</th>
                                    <th>Amount</th>
                                    <th>Currency</th>
                                    <th>Status</th>
                                    <th>Reason</th>
                                    <th>Refund Id</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($refunds as $refund)
                                    <tr>
                                        <td>{{ $refund->id }}</td>
                                        <td>{{ $refund->charge_id }}</td>
                                        <td>{{ $refund->amount }}</td>
                                        <td>{{ $refund->currency }}</td>
                                        <td>{{ $refund->status }}</td>
                                        <td>{{ $refund->reason }}</td>
                                        <td>{{ $refund->refund_id }}</td>
                                        <td>{{ $refund->created_at }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>