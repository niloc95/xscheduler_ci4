<!DOCTYPE html>
<html>
<head>
    <title>Simple Settings Test</title>
</head>
<body>
    <h1>Simple Settings Form Test</h1>
    
    <?php if (session()->getFlashdata('error')): ?>
        <div style="background: red; color: white; padding: 10px;">
            ERROR: <?= esc(session()->getFlashdata('error')) ?>
        </div>
    <?php endif; ?>
    
    <?php if (session()->getFlashdata('success')): ?>
        <div style="background: green; color: white; padding: 10px;">
            SUCCESS: <?= esc(session()->getFlashdata('success')) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= base_url('settings') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        
        <h3>Basic Fields Test</h3>
        <p>
            <label>Company Name:</label><br>
            <input type="text" name="company_name" value="Test Company">
        </p>
        <p>
            <label>Company Email:</label><br>
            <input type="email" name="company_email" value="test@example.com">
        </p>
        
        <h3>Logo Upload Test</h3>
        <p>
            <label>Company Logo:</label><br>
            <input type="file" name="company_logo" accept="image/*">
        </p>
        
        <p>
            <button type="submit">Submit Test</button>
        </p>
    </form>

    <hr>
    <h3>Current Settings</h3>
    <pre><?php
        try {
            $model = new \App\Models\SettingModel();
            $data = $model->getByPrefix('general.');
            print_r($data);
        } catch (Exception $e) {
            echo "Error loading settings: " . $e->getMessage();
        }
    ?></pre>
</body>
</html>
