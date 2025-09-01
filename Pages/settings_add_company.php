<?php



// Check if company is selected

$message = "";

// AI fix: Use correct relative path for db.php
require_once __DIR__ . '/../db.php';


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $message = "Company name is required.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO companies (name, description) VALUES (?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("ss", $name, $description);

            if ($stmt->execute()) {
                $message = "Company added successfully!";
                // Clear form on success
                $_POST = array();
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Company - Admin Dashboard</title>
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #e67e22;
            --light-color: #ecf0f1;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f5f5f5;
        }

        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .form-header h2 {
            color: var(--primary-color);
            font-weight: 600;
        }

        .form-label {
            font-weight: 500;
            color: var(--secondary-color);
        }

        .form-control {
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(230, 126, 34, 0.25);
        }

        .btn-primary {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            padding: 10px 25px;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #d35400;
            border-color: #d35400;
            transform: translateY(-2px);
        }

        .alert {
            border-radius: 6px;
            padding: 15px;
        }

        .required-field::after {
            content: " *";
            color: red;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
                margin: 20px;
            }

            .form-header h2 {
                font-size: 24px;
            }
        }

        @media (max-width: 576px) {
            .form-container {
                padding: 15px;
                margin: 15px;
            }

            .btn-primary {
                width: 100%;
            }
        }

        .no-gutter {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="form-container">
                    <div class="form-header">
                        <h2><i class="fas fa-building me-2"></i> Add New Company</h2>
                        <p class="text-muted">Add furniture companies to your database</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= strpos($message, 'successfully') !== false ? 'success' : 'danger' ?>">
                            <i class="fas <?= strpos($message, 'successfully') !== false ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="name" class="form-label required-field">Company Name</label>
                            <input type="text" name="name" id="name" class="form-control" required maxlength="255" placeholder="Enter company name (e.g., Modern Furniture Inc.)" />
                            <div class="form-text">The name of the furniture company or manufacturer</div>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label">Company Description</label>
                            <textarea name="description" id="description" class="form-control" rows="5" placeholder="Enter company description (e.g., Specializes in modern Scandinavian furniture designs...)"></textarea>
                            <div class="form-text">Optional details about the company's products and specialties</div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-5">
                            <!--<a href="dashboard.php" class="btn btn-outline-secondary">-->
                            <!--    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard-->
                            <!--</a>-->
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i> Add Company
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Enhance form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const nameField = document.getElementById('name');
            if (nameField.value.trim() === '') {
                e.preventDefault();
                nameField.classList.add('is-invalid');
                nameField.focus();
            }
        });

        // Remove validation class when user starts typing
        document.getElementById('name').addEventListener('input', function() {
            if (this.value.trim() !== '') {
                this.classList.remove('is-invalid');
            }
        });
    </script>
</body>

</html>