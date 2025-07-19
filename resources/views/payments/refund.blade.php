<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Process Refund</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons (optional) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
        }

        .card {
            border-radius: 1rem;
        }
    </style>
</head>

<body>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8">

                <div class="card shadow">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">Process Refund</h5>
                    </div>

                    <form method="POST" action="{{ route('admin.payments.process_refund', $payment->charge_id) }}">
                        @csrf
                        <div class="card-body">
                            <!-- Amount -->
                            <div class="mb-3">
                                <label class="form-label">Amount</label>
                                <input type="number" class="form-control" step="0.01" name="amount" required
                                    max="{{ $payment->amount }}" placeholder="Enter refund amount">
                                <small class="text-muted">Max: {{ number_format($payment->amount, 2) }}</small>
                            </div>

                            <!-- Reason -->
                            <div class="mb-3">
                                <label class="form-label">Reason</label>
                                <input type="text" class="form-control" name="reason"
                                    placeholder="Optional reason for refund">
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <input type="text" class="form-control" name="description"
                                    placeholder="Short description (optional)">
                            </div>
                        </div>

                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Refund
                            </button>
                        </div>
                    </form>

                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS (Popper + Bootstrap) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>