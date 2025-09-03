<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Test</title>
</head>
<body>
    <h1>File Upload Test</h1>

    <form action="<?= base_url('upload-test/upload') ?>" method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <p>
            <label for="testfile">Select a file:</label>
            <input type="file" name="testfile" id="testfile">
        </p>
        <p>
            <button type="submit">Upload</button>
        </p>
    </form>

    <script>
        // Log file selection
        document.getElementById('testfile').addEventListener('change', function() {
            console.log('File selected:', this.files[0]?.name);
        });
    </script>
</body>
</html>
